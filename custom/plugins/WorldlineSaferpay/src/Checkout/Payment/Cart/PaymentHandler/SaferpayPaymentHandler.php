<?php declare(strict_types=1);

namespace Worldline\Saferpay\Checkout\Payment\Cart\PaymentHandler;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Worldline\Saferpay\Api\Exception\ApiRequestException;
use Worldline\Saferpay\LockableTrait;
use Worldline\Saferpay\Service\PaymentService;
use Worldline\Saferpay\WorldlineSaferpay;

class SaferpayPaymentHandler extends AbstractPaymentHandler
{
    use LockableTrait;

    private PaymentService $paymentService;
    private OrderTransactionCaptureRefundStateHandler $orderTransactionCaptureRefundStateHandler;
    private EntityRepository $orderTransactionCaptureRefundRepository;
    private EntityRepository $orderTransactionRepository;
    private LoggerInterface $logger;

    public function __construct(
        PaymentService $paymentService,
        OrderTransactionCaptureRefundStateHandler $orderTransactionCaptureRefundStateHandler,
        EntityRepository $orderTransactionCaptureRefundRepository,
        EntityRepository $orderTransactionRepository,
        LoggerInterface $logger
    ) {
        $this->paymentService = $paymentService;
        $this->orderTransactionCaptureRefundStateHandler = $orderTransactionCaptureRefundStateHandler;
        $this->orderTransactionCaptureRefundRepository = $orderTransactionCaptureRefundRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->logger = $logger;
    }

    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        return $type === PaymentHandlerType::REFUND;
    }

    public function validate(Cart $cart, RequestDataBag $dataBag, SalesChannelContext $context): ?Struct
    {
        $scdTransactionId = (string)$dataBag->get('worldline_saferpay_scd_transaction_id');
        $scd = (string)$dataBag->get('worldline_saferpay_scd');
        $scdAlias = (string)($scd === 'alias' && $scdTransactionId
            ? $this->fetchScdAliasFromOrderTransaction($scdTransactionId, $context->getContext())
            : null);

        $saferpayFieldsToken = (string)$dataBag->get('worldline_saferpay_fields_token');

        return new ArrayStruct([
            'scdTransactionId' => $scdTransactionId,
            'scd' => $scd,
            'scdAlias' => $scdAlias,
            'saferpayFieldsToken' => $saferpayFieldsToken
        ]);
    }

    /**
     * @throws \Exception
     */
    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): RedirectResponse {
        try {
            $orderTransaction = $this->fetchOrderTransaction($transaction->getOrderTransactionId(), $context);
            if (!$orderTransaction) {
                throw new \RuntimeException(
                    'Order transaction with ID ' . $transaction->getOrderTransactionId() . ' can not be found',
                    1749118700
                );
            }

            $validateData = $validateStruct->getVars();
            $scdAlias = $validateData['scdAlias'];
            $saferpayFieldsToken = $validateData['saferpayFieldsToken'];

            if ($saferpayFieldsToken || $scdAlias) {
                $redirectUrl = $this->paymentService->initializeTransaction(
                    $orderTransaction,
                    $orderTransaction->getOrder(),
                    $transaction->getReturnUrl(),
                    $saferpayFieldsToken,
                    $scdAlias,
                    $orderTransaction->getOrder()->getSalesChannel(),
                    $context
                );
            } else {
                $redirectUrl = $this->paymentService->initializePaymentPage(
                    $orderTransaction,
                    $orderTransaction->getOrder(),
                    $transaction->getReturnUrl(),
                    $orderTransaction->getOrder()->getSalesChannel(),
                    $context
                );
            }
        } catch (\Exception $exception) {
            $errorMessage = 'An error occurred while initializing Saferpay payment page: '
                            . PHP_EOL
                            . $exception->getMessage();

            $this->logger->error($errorMessage);

            throw PaymentException::asyncProcessInterrupted(
                $transaction->getOrderTransactionId(),
                $errorMessage
            );
        }

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @throws \Exception
     */
    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        $orderTransaction = $this->fetchOrderTransaction($transaction->getOrderTransactionId(), $context);
        $salesChannel = $orderTransaction->getOrder()->getSalesChannel();

        if ($this->acquireLock($orderTransaction->getId())) {
            try {
                $this->paymentService->authorize(
                    $orderTransaction,
                    $salesChannel,
                    $context
                );
            } catch (\Exception $exception) {
                if ($exception instanceof ApiRequestException && $exception->getErrorName() === ApiRequestException::ERROR_NAME_TRANSACTION_ABORTED) {
                    throw PaymentException::customerCanceled(
                        $orderTransaction->getId(),
                        'Customer canceled the payment on the Saferpay page'
                    );
                }

                $errorMessage = 'An error occurred while finalizing Saferpay payment: '
                    . PHP_EOL
                    . $exception->getMessage();

                $this->logger->error($errorMessage);

                throw PaymentException::asyncFinalizeInterrupted(
                    $orderTransaction->getId(),
                    $errorMessage,
                    $exception
                );
            }
        }
    }

    public function refund(RefundPaymentTransactionStruct $transaction, Context $context): void
    {
        $refundId = $transaction->getRefundId();

        $criteria = new Criteria([$refundId]);
        $criteria->addAssociation('transactionCapture');
        $criteria->addAssociation('transactionCapture.transaction');
        $criteria->addAssociation('transactionCapture.transaction.paymentMethod');
        $criteria->addAssociation('transactionCapture.transaction.order');
        $criteria->addAssociation('transactionCapture.transaction.order.currency');
        $criteria->addAssociation('transactionCapture.transaction.order.currency.isoCode');
        $criteria->addAssociation('transactionCapture.transaction.order.salesChannel');

        $refund = $this->orderTransactionCaptureRefundRepository->search($criteria, $context)->first();
        if (!$refund instanceof OrderTransactionCaptureRefundEntity) {
            throw PaymentException::refundInterrupted($refundId, 'Refund with given ID does not exist');
        }

        $salesChannel = $refund->getTransactionCapture()?->getTransaction()?->getOrder()?->getSalesChannel();
        if (!$salesChannel instanceof SalesChannelEntity) {
            throw PaymentException::refundInterrupted($refundId, 'Can not resolve sales channel from refund');
        }

        try {
            $this->paymentService->refund($refund, $salesChannel, $context);
        } catch (\Exception $exception) {
            $errorMessage = 'An error occurred during refund via Saferpay: '
                . PHP_EOL
                . $exception->getMessage();

            $this->logger->error($errorMessage);

            if ($exception instanceof ApiRequestException) {
                $response = @json_decode($exception->getResponseBody());
                
                if (isset($response->ErrorDetail[0]) && is_object($response) && is_array($response->ErrorDetail)) {
                    $errorMessage = $response->ErrorDetail[0];
                }
            }

            throw PaymentException::refundInterrupted(
                $refundId,
                $errorMessage,
                $exception
            );
        }
    }

    private function fetchScdAliasFromOrderTransaction(string $orderTransactionId, Context $context): ?string
    {
        $orderTransaction = $this->fetchOrderTransaction($orderTransactionId, $context);

        $customFields = $orderTransaction->getCustomFields();
        if (!$customFields) {
            return null;
        }

        return $customFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_SCD_ALIAS] ?? null;
    }

    private function fetchOrderTransaction(string $orderTransactionId, Context $context): ?OrderTransactionEntity
    {
        $orderTransaction = $this->orderTransactionRepository->search(
            (new Criteria([$orderTransactionId]))
                ->addAssociation('order')
                ->addAssociation('order.currency')
                ->addAssociation('order.lineItems')
                ->addAssociation('order.addresses.country')
                ->addAssociation('order.billingAddress.country')
                ->addAssociation('order.orderCustomer.customer')
                ->addAssociation('order.salesChannel'),
            $context
        )->first();

        if (!$orderTransaction instanceof OrderTransactionEntity) {
            return null;
        }

        return $orderTransaction;
    }
}
