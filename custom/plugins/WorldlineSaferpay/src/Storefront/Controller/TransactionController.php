<?php declare(strict_types=1);

namespace Worldline\Saferpay\Storefront\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Worldline\Saferpay\Service\PaymentService;
use Worldline\Saferpay\WorldlineSaferpay;

/**
 * @noinspection PhpUnused
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class TransactionController extends StorefrontController
{
    public function __construct(
        private readonly EntityRepository $orderTransactionRepository,
        private readonly EntityRepository $stateMachineStateRepository,
        private readonly PaymentService $paymentService,
        private readonly LoggerInterface $logger
    ){}

    #[Route(
        path: '/saferpay/transaction/{transactionId}/pay',
        name: 'frontend.worldline-saferpay.transaction.pay',
        requirements: ['transactionId' => '[0-9a-f]+'],
        defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true],
        methods: ['GET']
    )]
    public function pay(
        string $transactionId,
        CustomerEntity $customer,
        SalesChannelContext $salesChannelContext
    ): Response {
        $orderTransaction = $this->orderTransactionRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('id', $transactionId))
                ->addAssociation('order.orderCustomer'),
            $salesChannelContext->getContext()
        )->first();

        if (!$orderTransaction instanceof OrderTransactionEntity) {
            throw new NotFoundHttpException('Order transaction not found', null, 1715954743);
        }

        if ($orderTransaction->getOrder()?->getOrderCustomer()?->getCustomerId() !== $customer->getId()) {
            throw new AccessDeniedHttpException('Order transaction/owner mismatch', null, 1715954750);
        }

        $openStateId = $this->fetchOrderTransactionOpenStateId($salesChannelContext->getContext());
        if ($orderTransaction->getStateId() !== $openStateId) {
            return $this->redirectToRoute(
                'frontend.checkout.finish.page',
                [
                    'orderId' => $orderTransaction->getOrderId()
                ]
            );
        }

        $customFields = $orderTransaction->getCustomFields() ?: [];
        $redirectUrl = $customFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_REDIRECT_URL] ?? null;

        if (!$redirectUrl) {
            throw new NotFoundHttpException('Order transaction not processed via Saferpay', null, 1715957819);
        }

        return $this->render('views/saferpay/transaction/pay.html.twig', [
            'orderTransaction' => $orderTransaction,
            'redirectUrl' => $redirectUrl
        ]);
    }

    #[Route(
        path: '/saferpay/transaction/{transactionId}/finalize',
        name: 'frontend.worldline-saferpay.transaction.finalize',
        requirements: ['transactionId' => '[0-9a-f]+'],
        defaults: ['_loginRequired' => false],
        methods: ['GET']
    )]
    public function finalize(string $transactionId, SalesChannelContext $salesChannelContext, Request $request): Response
    {
        $orderTransaction = $this->fetchOrderTransactionById($transactionId, $salesChannelContext->getContext());
        if (!$orderTransaction instanceof OrderTransactionEntity) {
            throw new NotFoundHttpException('Order transaction not found', null, 1717665517);
        }

        $customFields = $orderTransaction->getCustomFields() ?: [];

        $returnUrl = $customFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_RETURN_URL] ?? null;
        if (!$returnUrl) {
            throw new NotFoundHttpException('Order transaction has no finalization URL', null, 1717665781);
        }

        if (hash('sha256', $returnUrl) !== $request->query->get('returnUrlHash')) {
            throw new AccessDeniedHttpException('Order transaction returnUrlHash invalid', null, 1717667449);
        }

        if ($request->query->get('cancel') === '1') {
            $returnUrl .= '&cancel=1';
        }

        return $this->render('views/saferpay/transaction/finalize.html.twig', [
            'orderTransaction' => $orderTransaction,
            'returnUrl' => $returnUrl
        ]);
    }

    /**
     * @noinspection PhpUnused
     */
    #[Route(
        path: '/saferpay/transaction/{transactionId}/delete-scd',
        name: 'frontend.worldline-saferpay.transaction.delete-scd',
        requirements: ['transactionId' => '[0-9a-f]+'],
        defaults: ['_loginRequired' => true],
        methods: ['GET']
    )]
    public function deleteScdAlias(string $transactionId, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $orderTransaction = $this->fetchOrderTransactionById($transactionId, $salesChannelContext->getContext());
        if (!$orderTransaction instanceof OrderTransactionEntity) {
            throw new NotFoundHttpException('Order transaction not found', null, 1731678838);
        }

        if (
            !$salesChannelContext->getCustomerId()
            || $orderTransaction->getOrder()->getOrderCustomer()->getCustomerId() !== $salesChannelContext->getCustomerId()
        ) {
            throw new AccessDeniedHttpException('Order transaction does not belong to customer', null, 1731678838);
        }

        try {
            $this->paymentService->deleteScdAlias($orderTransaction, $salesChannelContext);
            $this->addFlash(self::SUCCESS, $this->trans('worldline.saferpay.scd.deleteSuccess'));
        } catch (\Throwable $throwable) {
            $errorMessage = 'An error occurred while deleting Saferpay Secure Card Data alias: '
                . PHP_EOL
                . $throwable->getMessage();

            $this->logger->error($errorMessage, ['throwable' => (string)$throwable]);

            $this->addFlash(self::DANGER, $this->trans('worldline.saferpay.scd.deleteError'));
        }

        return $this->redirectToRoute('frontend.account.payment.page');
    }

    private function fetchOrderTransactionById(string $orderTransactionId, Context $context): ?OrderTransactionEntity
    {
        return $this->orderTransactionRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('id', $orderTransactionId))
                ->addAssociation('order.orderCustomer'),
            $context
        )->first();
    }

    private function fetchOrderTransactionOpenStateId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria
            ->setLimit(1)
            ->addFilter(new EqualsFilter('technicalName', OrderTransactionStates::STATE_OPEN))
            ->addFilter(new EqualsFilter('stateMachine.technicalName', OrderTransactionStates::STATE_MACHINE));

        $id = $this->stateMachineStateRepository->searchIds($criteria, $context)->firstId();

        if (!$id) {
            throw new \RuntimeException(
                'Failed to fetch order transaction open state',
                1712763341
            );
        }

        return $id;
    }
}
