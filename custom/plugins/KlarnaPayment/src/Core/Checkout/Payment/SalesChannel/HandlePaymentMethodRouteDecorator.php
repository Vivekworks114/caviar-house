<?php declare(strict_types=1);

namespace KlarnaPayment\Core\Checkout\Payment\SalesChannel;

use KlarnaPayment\Installer\Modules\CustomFieldInstaller;
use KlarnaPayment\Components\Client\Hydrator\Request\CreateHppSession\CreateHppSessionRequestHydratorInterface;
use KlarnaPayment\Components\Client\ClientInterface;
use KlarnaPayment\Components\Client\Hydrator\Request\UpdateSession\UpdateExtendedSessionRequestHydratorInterface;
use KlarnaPayment\Components\Client\Hydrator\Request\UpdateSession\UpdateSessionRequestHydratorInterface;
use KlarnaPayment\Components\Client\Hydrator\Request\CreateSession\CreateExtendedSessionRequestHydratorInterface;
use KlarnaPayment\Components\Client\Hydrator\Struct\Address\AddressStructHydratorInterface;
use KlarnaPayment\Components\Helper\PaymentHelper\PaymentHelperInterface;
use KlarnaPayment\Components\Client\Response\GenericResponse;
use KlarnaPayment\Components\Converter\CustomOrderConverter;
use KlarnaPayment\Components\Helper\OrderFetcherInterface;

use Monolog\Logger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRouteResponse;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class HandlePaymentMethodRouteDecorator extends HandlePaymentMethodRoute
{
    /** @var HandlePaymentMethodRoute */
    protected $decorator;

    /** @var CreateHppSessionRequestHydratorInterface */
    protected $requestHydrator;

    /** @var UpdateExtendedSessionRequestHydratorInterface */
    protected $updateSessionHydrator;

    /** @var CreateExtendedSessionRequestHydratorInterface */
    protected $createSessionHydrator;

    /** @var AddressStructHydratorInterface */
    protected $addressHydrator;

    /** @var ClientInterface */
    protected $client;

    /** @var PaymentHelperInterface */
    protected $paymentHelper;

    /** @var SalesChannelContextPersister */
    protected $salesChannelContextPersister;

    /** @var EntityRepository */
    protected $orderRepository;

    /** @var CustomOrderConverter */
    protected $orderConverter;

    /** @var OrderFetcherInterface */
    protected $orderFetcher;

    /** @var Logger */
    protected $logger;

    /** @var string */
    protected $appSecret;

    public function __construct(
        HandlePaymentMethodRoute $decorator,
        CreateHppSessionRequestHydratorInterface $requestHydrator,
        UpdateExtendedSessionRequestHydratorInterface $updateSessionHydrator,
        CreateExtendedSessionRequestHydratorInterface $createSessionHydrator,
        AddressStructHydratorInterface $addressHydrator,
        ClientInterface $client,
        PaymentHelperInterface $paymentHelper,
        SalesChannelContextPersister $salesChannelContextPersister,
        EntityRepository $orderRepository,
        CustomOrderConverter $orderConverter,
        OrderFetcherInterface $orderFetcher,
        Logger $logger,
        string $appSecret
    ) {
        $this->decorator = $decorator;
        $this->requestHydrator = $requestHydrator;
        $this->updateSessionHydrator = $updateSessionHydrator;
        $this->createSessionHydrator = $createSessionHydrator;
        $this->addressHydrator = $addressHydrator;
        $this->client = $client;
        $this->paymentHelper = $paymentHelper;
        $this->salesChannelContextPersister = $salesChannelContextPersister;
        $this->orderRepository = $orderRepository;
        $this->orderConverter = $orderConverter;
        $this->orderFetcher = $orderFetcher;
        $this->logger = $logger;
        $this->appSecret = $appSecret;
    }

    public function getDecorated(): HandlePaymentMethodRoute
    {
        return $this->decorator;
    }

    #[Route(path: '/store-api/handle-payment', name: 'store-api.payment.handle', methods: ['GET', 'POST'])]
    public function load(Request $request, SalesChannelContext $salesChannelContext): HandlePaymentMethodRouteResponse
    {
        $routeScope = is_array($request->attributes->get('_routeScope')) ? $request->attributes->get('_routeScope')[0] : $request->attributes->get('_routeScope');

        if($routeScope !== "store-api" || !$this->paymentHelper->isKlarnaPaymentsEnabled($salesChannelContext)){
            return $this->getDecorated()->load($request, $salesChannelContext);
        }

        $this->logger->info('klarna-composable-frontend handle-payment', [
            'routeScope' => $request->attributes->get('_routeScope'),
            'request' => $request
        ]);

        $token = $salesChannelContext->getToken();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $customerId = $salesChannelContext->getCustomer()?->getId();

        $data = $this->salesChannelContextPersister->load($token, $salesChannelId, $customerId);

        if(!isset($data[CustomFieldInstaller::KLARNA_SESSION_KEY])){
            return $this->getDecorated()->load($request, $salesChannelContext);
        }

        $orderId = $request->get('orderId');

        $order = $this->orderFetcher->getOrderFromOrder($orderId, $salesChannelContext->getContext());

        $cart = $this->orderConverter->convertOrderToCart($order, $salesChannelContext->getContext());
        $sessionId = $data[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID];

        $klarnaSessionUpdateRequest = $this->updateSessionHydrator->hydrate($sessionId, $cart, $salesChannelContext);

        /** @var GenericResponse $updateSessionResponse */
        $updateSessionResponse = $this->client->request($klarnaSessionUpdateRequest, $salesChannelContext->getContext());
        if($updateSessionResponse->getHttpStatus() === Response::HTTP_NOT_FOUND) {
            $this->logger->warning('klarna-composable-frontend handle-payment: Session ID not found, creating new one', [
                'oldSessionId' => $sessionId
            ]);
            $createSessionResponse = $this->createKlarnaSession($cart, $salesChannelContext);
            if ($this->isValidResponseStatus($createSessionResponse)) {
                $this->addKlarnaSessionToShopware($createSessionResponse->getResponse(), $salesChannelContext);
                $data = $this->salesChannelContextPersister->load($token, $salesChannelId, $customerId);
            } else {
                $this->logger->warning('klarna-composable-frontend handle-payment: AAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', [
                    'oldSessionId' => $createSessionResponse->getHttpStatus()
                ]);
            }
        }

        $hppSessionRequest = $this->requestHydrator->hydrate($data[CustomFieldInstaller::KLARNA_SESSION_KEY], $request, $salesChannelContext);

        $response = $this->client->request($hppSessionRequest, $salesChannelContext->getContext());

        if(!$this->isValidResponseStatus($response)){
            return $this->getDecorated()->load($request, $salesChannelContext);
        }

        $customFields = $order->getCustomFields();
        $customFields[CustomFieldInstaller::KLARNA_HPP_SESSION_ID] = $response->getResponse()['session_id'];
        $customFields[CustomFieldInstaller::KLARNA_HPP_REDIRECT_SUCCESS] = $request->get('finishUrl');
        $customFields[CustomFieldInstaller::KLARNA_HPP_REDIRECT_ERROR] = $request->get('errorUrl');
        $customFields[CustomFieldInstaller::KLARNA_HPP_SESSION_TOKEN] = $salesChannelContext->getToken();

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'customFields' => $customFields
            ]
        ], $salesChannelContext->getContext());

        $response = new RedirectResponse($response->getResponse()['redirect_url']);

        return new HandlePaymentMethodRouteResponse($response);
    }

    private function isValidResponseStatus(GenericResponse $response): bool
    {
        return in_array($response->getHttpStatus(), [Response::HTTP_OK, Response::HTTP_CREATED, Response::HTTP_NO_CONTENT], true);
    }

    private function createKlarnaSession(Cart $cart, SalesChannelContext $salesChannelContext): GenericResponse
    {
        $request = $this->createSessionHydrator->hydrate($cart, $salesChannelContext);

        return $this->client->request($request, $salesChannelContext->getContext());
    }

    /**
     * @param array<string,mixed> $klarnaSession
     */
    private function addKlarnaSessionToShopware(array $klarnaSession, SalesChannelContext $salesChannelContext): void
    {
        $token = $salesChannelContext->getToken();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $customerId = $salesChannelContext->getCustomer()?->getId();

        $payload = $this->loadPayloadFromSalesChannelApiContext($salesChannelContext);

        $payload[CustomFieldInstaller::KLARNA_SESSION_KEY] = [
            UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID => $klarnaSession['session_id'],
            UpdateSessionRequestHydratorInterface::KLARNA_CLIENT_TOKEN => $klarnaSession['client_token'],
            UpdateSessionRequestHydratorInterface::KLARNA_PAYMENT_METHOD_CATEGORIES => $klarnaSession['payment_method_categories'],
            UpdateSessionRequestHydratorInterface::KLARNA_ADDRESS_HASH => $this->getAddressHash($salesChannelContext)
        ];

        $this->salesChannelContextPersister->save($token, $payload, $salesChannelId, $customerId);
    }

    private function loadPayloadFromSalesChannelApiContext(SalesChannelContext $salesChannelContext): array
    {
        $token = $salesChannelContext->getToken();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $customerId = $salesChannelContext->getCustomer()?->getId();

        return $this->salesChannelContextPersister->load($token, $salesChannelId, $customerId);
    }

    private function getAddressHash(SalesChannelContext $salesChannelContext): ?string
    {
        $customer = $this->addressHydrator->hydrateFromContext($salesChannelContext);

        if ($customer === null) {
            return null;
        }

        $json = json_encode($customer, JSON_PRESERVE_ZERO_FRACTION);

        if (empty($json)) {
            throw new \LogicException('could not generate hash');
        }

        if (empty($this->appSecret)) {
            throw new \LogicException('empty app secret');
        }

        return hash_hmac('sha256', $json, $this->appSecret);
    }
}