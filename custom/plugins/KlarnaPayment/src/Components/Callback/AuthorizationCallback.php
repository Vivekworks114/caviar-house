<?php /** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace KlarnaPayment\Components\Callback;

use http\Exception\RuntimeException;
use KlarnaPayment\Components\DataAbstractionLayer\Entity\Cart\KlarnaCartEntity;
use Monolog\Logger;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

class AuthorizationCallback
{
    public const AUTHORIZATION_TOKEN_FIELD = 'klarna_authorization_token';

    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly RequestStack $requestStack,
        private readonly EntityRepository $cartDataRepository,
        private readonly Logger $logger,
        private readonly EntityRepository $orderTransactionRepository
    ) {
    }

    /**
     * @throws ConstraintViolationException
     * @throws Throwable
     */
    public function handle(string $authorizationToken, SalesChannelContext $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.' . self::AUTHORIZATION_TOKEN_FIELD, $authorizationToken));

        $result = $this->orderTransactionRepository->search($criteria, $context->getContext())->getTotal();

        if ($result > 0) {
            $this->logger->notice('Tried to start authorization Callback more times', [
                'authorizationToken' => $authorizationToken
            ]);
            return;
        }

        $this->logger->notice('Start authorization Callback', [
            'authorizationToken' => $authorizationToken
        ]);

        $dataBag = new RequestDataBag([
            'tos' => true,
            'klarnaAuthorizationToken' => $authorizationToken,
        ]);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('cartToken', $context->getToken()));

        /** @var KlarnaCartEntity $cartData */
        $cartData = $this->cartDataRepository->search($criteria, $context->getContext())->first();

        if ($cartData instanceof KlarnaCartEntity && !empty($cartData->getPayload())) {
            $dataBag->add($cartData->getPayload());
        }

        $currentRequest = $this->requestStack->getCurrentRequest();

        if ($currentRequest === null) {
            $this->logger->info('Authorization Callback: current request is empty', [
                'authorizationToken' => $authorizationToken,
                'dataBag' => $dataBag
            ]);

            throw new RuntimeException('Current request is null in requestStack');
        }

        $currentRequest->request->add($dataBag->all());

        $orderId = $this->orderService->createOrder($dataBag, $context);
        // todo - test data bag
        $response = $this->paymentProcessor->pay($orderId, $currentRequest, $context);

        if ($response === null) {
            $this->logger->info('Authorization Callback: handle payment reponse is empty', [
                'authorizationToken' => $authorizationToken,
                'orderId' => $orderId,
                'dataBag' => $dataBag
            ]);

            throw new RuntimeException('Empty response for handling payment');
        }

        $this->logger->debug('Authorization Callback: Order created via authorization callback.', [
            'orderId' => $orderId,
            'authorizationToken' => $authorizationToken,
            'response' => $response,
            'dataBag' => $dataBag
        ]);

        // Follow the redirects to complete the payment
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($curl, CURLOPT_URL, $response->getTargetUrl());
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS, false);

        curl_exec($curl);
        curl_close($curl);

        if ($cartData instanceof KlarnaCartEntity) {
            $this->cartDataRepository->delete([['id' => $cartData->getUniqueIdentifier()]], $context->getContext());
        }
    }
}
