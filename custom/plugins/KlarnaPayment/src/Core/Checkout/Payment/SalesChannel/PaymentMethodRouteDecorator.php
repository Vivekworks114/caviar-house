<?php declare(strict_types=1);

namespace KlarnaPayment\Core\Checkout\Payment\SalesChannel;

use KlarnaPayment\Installer\Modules\PaymentMethodInstaller;
use KlarnaPayment\Components\Helper\PaymentHelper\PaymentHelperInterface;
use KlarnaPayment\Components\Helper\MediaHelper\MediaHelperInterface;
use KlarnaPayment\Components\Client\Response\GenericResponse;
use KlarnaPayment\Components\Controller\Storefront\KlarnaExpressCheckoutController;
use KlarnaPayment\Components\Client\Hydrator\Request\CreateSession\CreateExtendedSessionRequestHydratorInterface;
use KlarnaPayment\Components\Client\Hydrator\Request\UpdateSession\UpdateSessionRequestHydratorInterface;
use KlarnaPayment\Components\Client\Hydrator\Request\UpdateSession\UpdateExtendedSessionRequestHydratorInterface;
use KlarnaPayment\Components\Client\ClientInterface;
use KlarnaPayment\Components\Client\Hydrator\Struct\Address\AddressStructHydratorInterface;
use KlarnaPayment\Installer\Modules\CustomFieldInstaller;
use KlarnaPayment\Components\ConfigReader\ConfigReaderInterface;

use Monolog\Logger;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class PaymentMethodRouteDecorator extends AbstractPaymentMethodRoute
{
    public function __construct(
        protected readonly AbstractPaymentMethodRoute $decorator,
        protected readonly PaymentHelperInterface $paymentHelper,
        protected readonly MediaHelperInterface $mediaHelper,
        protected readonly CartService $cartService,
        protected readonly CreateExtendedSessionRequestHydratorInterface $requestHydrator,
        protected readonly UpdateExtendedSessionRequestHydratorInterface $requestUpdateHydrator,
        protected readonly ClientInterface $client,
        protected readonly AddressStructHydratorInterface $addressHydrator,
        protected readonly Logger $logger,
        protected readonly SalesChannelContextPersister $salesChannelContextPersister,
        protected readonly SystemConfigService $systemConfigService,
        protected readonly ConfigReaderInterface $configReader,
        protected readonly string $appSecret
    ) {
    }

    public function getDecorated(): AbstractPaymentMethodRoute
    {
        return $this->decorator;
    }

    #[Route(path: '/store-api/payment-method', name: 'store-api.payment.method', defaults: ['_entity' => 'payment_method'], methods: ['GET', 'POST'])]
    public function load(Request $request, SalesChannelContext $salesChannelContext, Criteria $criteria): PaymentMethodRouteResponse
    {
        $paymentMethodRouteResponse = $this->getDecorated()->load($request, $salesChannelContext, $criteria);
        $this->paymentMethodsResult = $paymentMethodRouteResponse->getObject();

        $routeScope = is_array($request->attributes->get('_routeScope')) ? $request->attributes->get('_routeScope')[0] : $request->attributes->get('_routeScope');

        if ($routeScope !== 'store-api' || !$this->paymentHelper->isKlarnaPaymentsEnabled($salesChannelContext)) {
            return $paymentMethodRouteResponse;
        }

        $this->logger->info('klarna-composable-frontend start', [
            'routeScope' => $routeScope,
            'request' => $request
        ]);

        $this->startKlarnaSession($salesChannelContext);

        return new PaymentMethodRouteResponse($this->paymentMethodsResult);
    }

    private function startKlarnaSession(SalesChannelContext $salesChannelContext): void
    {
        $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $payload = $this->loadPayloadFromSalesChannelApiContext($salesChannelContext);

        $response = isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY])
            && isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID])
            ? (new GenericResponse())->assign(['httpStatus' => Response::HTTP_BAD_REQUEST])
            : $this->createOrUpdateKlarnaSession($cart, $salesChannelContext);

        if ($this->isValidResponseStatus($response) && !$this->hasValidKlarnaSession($salesChannelContext)) {
            $this->addKlarnaSessionToShopware($response->getResponse(), $salesChannelContext);
        }

        $this->removeDisabledKlarnaPaymentMethods($salesChannelContext);

        $this->filterPayNowMethods($salesChannelContext);

        $this->updateGlobalPurchaseFlowSystemConfig($salesChannelContext);
    }

    private function removeDisabledKlarnaPaymentMethods(SalesChannelContext $salesChannelContext): void
    {
        $payload = $this->loadPayloadFromSalesChannelApiContext($salesChannelContext);

        if($payload === [] 
            || !isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY])
            && !isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_PAYMENT_METHOD_CATEGORIES])) {
            return;
        }

        $sessionPayload = $payload[CustomFieldInstaller::KLARNA_SESSION_KEY];
        $mediaHelper = $this->mediaHelper;

        $isKlarnaExpress = isset($sessionPayload[KlarnaExpressCheckoutController::KLARNA_EXPRESS_SESSION_KEY]);
        $this->setPaymentMethodsResult(
            $this->getPaymentMethodsResult()->filter(
                static function (PaymentMethodEntity $paymentMethod) use ($sessionPayload, $isKlarnaExpress, $mediaHelper) {
                    if (array_key_exists($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_EXPRESS_CHECKOUT_CODES) && !$isKlarnaExpress) {
                        return false;
                    }

                    if (!array_key_exists($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES)) {
                        return true;
                    }

                    foreach ($sessionPayload[UpdateSessionRequestHydratorInterface::KLARNA_PAYMENT_METHOD_CATEGORIES] as $paymentCategory) {
                        if ($paymentCategory['identifier'] === PaymentMethodInstaller::KLARNA_PAYMENTS_CODES[$paymentMethod->getId()]) {
                            $media = $mediaHelper->createNewMedia($paymentCategory['asset_urls']['standard']);
                            $paymentMethod->setName($paymentCategory['name']);
                            $paymentMethod->setTranslated(
                                array_merge(
                                    $paymentMethod->getTranslated(),
                                    ['name' => $paymentCategory['name']]
                                )
                            );
                            $paymentMethod->setMediaId($media->getId());
                            $paymentMethod->setMedia($media);
                            

                            return true;
                        }
                    }

                    return false;
                }
            )
        );
    }

    private function filterPayNowMethods(SalesChannelContext $salesChannelContext): void
    {
        $payload = $this->loadPayloadFromSalesChannelApiContext($salesChannelContext);

        if($payload === [] 
            || !isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY])
            && !isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_PAYMENT_METHOD_CATEGORIES])) {
            return;
        }

        foreach ($payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_PAYMENT_METHOD_CATEGORIES] as $paymentCategory) {
            if ($paymentCategory['identifier'] === PaymentMethodInstaller::KLARNA_PAYMENTS_PAY_NOW_CODE) {
                $this->removeSeparatePayNowKlarnaPaymentMethods();

                return;
            }
        }

        $this->removeCombinedKlarnaPaymentPayNowMethod();
    }

    private function removeSeparatePayNowKlarnaPaymentMethods(): void
    {
        $this->setPaymentMethodsResult(
            $this->getPaymentMethodsResult()->filter(
                static function (PaymentMethodEntity $paymentMethod) {
                    if (!array_key_exists($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES)) {
                        return true;
                    }

                    return in_array($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES_WITH_PAY_NOW_COMBINED, true);
                }
            )
        );
    }

    private function removeCombinedKlarnaPaymentPayNowMethod(): void
    {
        $this->setPaymentMethodsResult(
            $this->getPaymentMethodsResult()->filter(
                static function (PaymentMethodEntity $paymentMethod) {
                    if (!array_key_exists($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES)) {
                        return true;
                    }

                    return $paymentMethod->getId() !== PaymentMethodInstaller::KLARNA_PAY_NOW;
                }
            )
        );
    }

    private function removeAllKlarnaPaymentMethods(): void
    {
        $this->setPaymentMethodsResult(
            $this->getPaymentMethodsResult()->filter(
                static function (PaymentMethodEntity $paymentMethod) {
                    if (array_key_exists($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES)) {
                        return false;
                    }

                    return true;
                }
            )
        );
    }

    private function createOrUpdateKlarnaSession(Cart $cart, SalesChannelContext $salesChannelContext): GenericResponse
    {
        $response = $this->hasValidKlarnaSession($salesChannelContext)
            ? $this->updateKlarnaSession($cart, $salesChannelContext)
            : $this->createKlarnaSession($cart, $salesChannelContext);

        if ($this->isValidResponseStatus($response)) {
            return $response;
        }

        $this->removeAllKlarnaPaymentMethods();

        return $response;
    }

    private function isValidResponseStatus(GenericResponse $response): bool
    {
        return in_array($response->getHttpStatus(), [Response::HTTP_OK, Response::HTTP_NO_CONTENT], true);
    }

    private function createKlarnaSession(Cart $cart, SalesChannelContext $salesChannelContext): GenericResponse
    {
        $request = $this->requestHydrator->hydrate($cart, $salesChannelContext);

        return $this->client->request($request, $salesChannelContext->getContext());
    }

    private function updateKlarnaSession(Cart $cart, SalesChannelContext $salesChannelContext): GenericResponse
    {
        $payload = $this->loadPayloadFromSalesChannelApiContext($salesChannelContext);

        if(!isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY])
            && !isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID])
        ){
            return (new GenericResponse())->assign(['httpStatus' => Response::HTTP_BAD_REQUEST]);
        }

        $sessionId = $payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID];

        $request = $this->requestUpdateHydrator->hydrate($sessionId, $cart, $salesChannelContext);

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

    private function hasValidKlarnaSession(SalesChannelContext $salesChannelContext): bool
    {
        $payload = $this->loadPayloadFromSalesChannelApiContext($salesChannelContext);

        return isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY])
            && isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID])
            && isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_CLIENT_TOKEN])
            && isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_PAYMENT_METHOD_CATEGORIES])
            && isset($payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_ADDRESS_HASH]) 
            && $payload[CustomFieldInstaller::KLARNA_SESSION_KEY][UpdateSessionRequestHydratorInterface::KLARNA_ADDRESS_HASH] === $this->getAddressHash($salesChannelContext);
    }

    private function updateGlobalPurchaseFlowSystemConfig(SalesChannelContext $salesChannelContext): void
    {
        $paymentMethodIds = $this->paymentMethodsResult->getEntities()->getIds();

        if (!$this->hasKlarnaPayment($paymentMethodIds)) {
            return;
        }

        $configRelatedSalesChannelId = $salesChannelContext->getSalesChannel()->getId();

        $currentConfig = $this->configReader->read($configRelatedSalesChannelId, false)
            ->get(ConfigReaderInterface::CONFIG_ACTIVE_GLOBALPURCHASEFLOW, null);

        if ($currentConfig === null) {
            $currentConfig               = $this->configReader->read()->get(ConfigReaderInterface::CONFIG_ACTIVE_GLOBALPURCHASEFLOW);
            $configRelatedSalesChannelId = null;
        }

        $newConfig = \in_array(PaymentMethodInstaller::KLARNA_PAY, $paymentMethodIds, true);

        if ($currentConfig === $newConfig) {
            return;
        }

        $settingKey = \sprintf('%s%s', ConfigReaderInterface::SYSTEM_CONFIG_DOMAIN, ConfigReaderInterface::CONFIG_ACTIVE_GLOBALPURCHASEFLOW);

        $this->systemConfigService->set($settingKey, $newConfig, $configRelatedSalesChannelId);
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

    private function loadPayloadFromSalesChannelApiContext(SalesChannelContext $salesChannelContext): array
    {
        $token = $salesChannelContext->getToken();
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $customerId = $salesChannelContext->getCustomer()?->getId();

        return $this->salesChannelContextPersister->load($token, $salesChannelId, $customerId);
    }

    private function hasKlarnaPayment(array $paymentMethodIds): bool
    {
        $klarnaPaymentIds = \array_keys(PaymentMethodInstaller::KLARNA_PAYMENTS_CODES);

        foreach ($klarnaPaymentIds as $klarnaPaymentId) {
            if (\in_array($klarnaPaymentId, $paymentMethodIds, true)) {
                return true;
            }
        }

        return false;
    }

    private function getPaymentMethodsResult(): EntitySearchResult
    {
        return $this->paymentMethodsResult;
    }

    private function setPaymentMethodsResult(EntitySearchResult $paymentMethodsResult): void
    {
        $this->paymentMethodsResult = $paymentMethodsResult;
    }
}