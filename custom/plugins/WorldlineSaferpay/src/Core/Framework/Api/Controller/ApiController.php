<?php declare(strict_types=1);

namespace Worldline\Saferpay\Core\Framework\Api\Controller;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Worldline\Saferpay\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundBuilder;
use Worldline\Saferpay\LockableTrait;
use Worldline\Saferpay\Service\PaymentService;
use Worldline\Saferpay\WorldlineSaferpay;

/**
 * @noinspection PhpUnused
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class ApiController extends AbstractController
{
    use LockableTrait;

    private EntityRepository $orderRepository;
    private EntityRepository $orderTransactionRepository;
    private OrderTransactionStateHandler $orderTransactionStateHandler;
    private OrderTransactionCaptureRefundStateHandler $orderTransactionCaptureRefundStateHandler;
    private OrderTransactionCaptureRefundBuilder $orderTransactionCaptureRefundBuilder;
    private PaymentRefundProcessor $paymentRefundProcessor;
    private PaymentService $paymentService;

    public function __construct(
        EntityRepository $orderRepository,
        EntityRepository $orderTransactionRepository,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        OrderTransactionCaptureRefundStateHandler $orderTransactionCaptureRefundStateHandler,
        OrderTransactionCaptureRefundBuilder $orderTransactionCaptureRefundBuilder,
        PaymentRefundProcessor $paymentRefundProcessor,
        PaymentService $paymentService
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->orderTransactionCaptureRefundStateHandler = $orderTransactionCaptureRefundStateHandler;
        $this->orderTransactionCaptureRefundBuilder = $orderTransactionCaptureRefundBuilder;
        $this->paymentRefundProcessor = $paymentRefundProcessor;
        $this->paymentService = $paymentService;
    }

    #[Route(
        path: '/api/_action/worldline-saferpay/refund/{orderId}',
        name: 'api.worldline-saferpay.refund',
        requirements: ['version' => '\d+', 'orderId' => '[0-9a-f]+'],
        methods: ['POST']
    )]
    public function refund(string $orderId, Request $request, Context $context): Response
    {
        $requestContent = $request->getContent();
        if (!$requestContent || !is_string($requestContent)) {
            throw new BadRequestHttpException('Request content is missing');
        }

        $requestData = @json_decode($requestContent);
        if (
            !$requestData instanceof \stdClass
            || !isset($requestData->retoureData)
            || !$requestData->retoureData instanceof \stdClass
        ) {
            throw new BadRequestHttpException('retoureData is missing or invalid');
        }

        $hasPositions = isset($requestData->retoureData->positions) && is_array($requestData->retoureData->positions);
        $hasAdditionalPositions = isset($requestData->retoureData->additionalPositions) && is_array($requestData->retoureData->additionalPositions);

        if (!$hasPositions && $hasAdditionalPositions) {
            throw new BadRequestHttpException('retoureData.positions or retoureData.additionalPositions is required');
        }

        if (!$hasAdditionalPositions) {
            $requestData->retoureData->additionalPositions = [];
        }

        $order = $this->orderRepository->search(
            (new Criteria([$orderId]))
                ->addAssociation('transactions')
                ->addAssociation('transactions.captures')
                ->addAssociation('salesChannel')
                ->addAssociation('transactions.paymentMethod'),
            $context
        )->first();

        if (!$order instanceof OrderEntity) {
            throw new NotFoundHttpException('Order ' . $orderId . ' not found');
        }

        $transaction = WorldlineSaferpay::getLastOrderTransactionHandledViaSaferpay($order);
        if (!$transaction instanceof OrderTransactionEntity) {
            throw new BadRequestHttpException('Order ' . $orderId . ' was not paid via Saferpay');
        }

        $captures = $transaction->getCaptures();
        if (!$captures) {
            throw new BadRequestHttpException('Order ' . $orderId . ' has no order transaction capture');
        }

        $captures->sort(function(OrderTransactionCaptureEntity $a, OrderTransactionCaptureEntity $b) {
            return $a->getCreatedAt() <=> $b->getCreatedAt();
        });

        $capture = $captures->last();
        if (!$capture instanceof OrderTransactionCaptureEntity) {
            throw new BadRequestHttpException('Order ' . $orderId . ' has no order transaction capture');
        }

        $refundId = $this->orderTransactionCaptureRefundBuilder->createForOrderTransactionCapture(
            $capture,
            $requestData->retoureData->positions,
            $requestData->retoureData->additionalPositions ?: [],
            $context
        );

        $this->paymentRefundProcessor->processRefund($refundId, $context);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws \Worldline\Saferpay\Api\Exception\ApiRequestException
     * @noinspection PhpUnused
     */
    #[Route(
        path: '/api/_action/worldline-saferpay/notify/{transactionId}/success',
        name: 'api.worldline-saferpay.notify.success',
        requirements: ['version' => '\d+', 'transactionId' => '[0-9a-f]+'],
        defaults: ['auth_required' => false],
        methods: ['GET']
    )]
    public function notifySuccess(string $transactionId, Request $request, Context $context): Response
    {
        $transaction = $this->ensureValidTransaction($transactionId, $request->get('notifyToken', ''), $context);

        if ($this->acquireLock($transactionId)) {
            $this->paymentService->authorize($transaction, $transaction->getOrder()->getSalesChannel(), $context);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @noinspection PhpUnused
     */
    #[Route(
        path: '/api/_action/worldline-saferpay/notify/{transactionId}/fail',
        name: 'api.worldline-saferpay.notify.fail',
        requirements: ['version' => '\d+', 'transactionId' => '[0-9a-f]+'],
        defaults: ['auth_required' => false],
        methods: ['GET']
    )]
    public function notifyFail(string $transactionId, Request $request, Context $context): Response
    {
        $this->ensureValidTransaction($transactionId, $request->get('notifyToken', ''), $context);

        if ($this->acquireLock($transactionId)) {
            $this->orderTransactionStateHandler->fail($transactionId, $context);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws \Worldline\Saferpay\Api\Exception\ApiRequestException
     * @noinspection PhpUnused
     */
    #[Route(
        path: '/api/_action/worldline-saferpay/notify-pending/{transactionId}',
        name: 'api.worldline-saferpay.notify-pending',
        requirements: ['version' => '\d+', 'transactionId' => '[0-9a-f]+'],
        defaults: ['auth_required' => false],
        methods: ['GET']
    )]
    public function notifyPending(string $transactionId, Request $request, Context $context): Response
    {
        $transaction = $this->ensureValidTransaction($transactionId, $request->get('notifyToken', ''), $context);

        if ($this->acquireLock($transactionId)) {
            $this->paymentService->capture($transaction, $transaction->getOrder()->getSalesChannel(), $context);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @noinspection PhpUnused
     */
    #[Route(
        path: '/api/_action/worldline-saferpay/notify-pending-refund/{refundId}',
        name: 'api.worldline-saferpay.notify-pending-refund',
        requirements: ['version' => '\d+', 'refundId' => '[0-9a-f]+'],
        defaults: ['auth_required' => false],
        methods: ['GET']
    )]
    public function notifyPendingRefund(string $refundId, Context $context): Response
    {
        $this->orderTransactionCaptureRefundStateHandler->complete($refundId, $context);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function ensureValidTransaction(
        string $transactionId,
        string $notifyToken,
        Context $context
    ): OrderTransactionEntity {
        $notifyToken = trim($notifyToken);
        if (!$notifyToken) {
            throw new BadRequestHttpException('Query parameter "notifyToken" is missing');
        }

        $orderTransaction = $this->orderTransactionRepository->search(
            (new Criteria([$transactionId]))
                ->addAssociation('order')
                ->addAssociation('order.salesChannel')
                ->addAssociation('order.orderCustomer'),
            $context
        )->first();

        if (!$orderTransaction instanceof OrderTransactionEntity) {
            throw new NotFoundHttpException('Order transaction ' . $transactionId . ' not found');
        }

        $orderNotifyToken = trim((string) ($orderTransaction->getCustomFields()[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_NOTIFY_TOKEN] ?? ''));
        if ($notifyToken !== $orderNotifyToken) {
            throw new AccessDeniedHttpException('Given notifyToken does not belong to given order transaction');
        }

        if (!$orderTransaction->getOrder() instanceof OrderEntity) {
            throw new NotFoundHttpException('Order transaction ' . $transactionId . ' does not belong to an order');
        }

        if (!$orderTransaction->getOrder()->getSalesChannel() instanceof SalesChannelEntity) {
            throw new NotFoundHttpException('Order transaction ' . $transactionId . ' does not belong to a sales channel');
        }

        return $orderTransaction;
    }
}
