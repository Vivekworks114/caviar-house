<?php declare(strict_types = 1);

namespace Worldline\Saferpay\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Worldline\Saferpay\Api\SaferpayPaymentApiClient;
use Worldline\Saferpay\Service\PaymentService;
use Worldline\Saferpay\WorldlineSaferpay;

/**
 * @noinspection PhpUnused
 */
class OrderTransactionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onStateMachineTransition',
            'state_enter.order_transaction.state.paid' => 'onEnterPaidSate',
            'state_enter.order_transaction.state.cancelled' => 'onEnterCancelledSate'
        ];
    }

    private PaymentService $paymentService;
    private EntityRepository $orderTransactionRepository;
    private string $lastTransactionSateId = '';

    public function __construct(PaymentService $paymentService, EntityRepository $orderTransactionRepository)
    {
        $this->paymentService = $paymentService;
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * @noinspection PhpUnused
     */
    public function onStateMachineTransition(StateMachineTransitionEvent $event): void
    {
        if ($event->getEntityName() === OrderTransactionDefinition::ENTITY_NAME) {
            $this->lastTransactionSateId = $event->getFromPlace()->getId();
        }
    }

    /**
     * @throws \Worldline\Saferpay\Api\Exception\ApiRequestException
     * @noinspection PhpUnused
     */
    public function onEnterPaidSate(OrderStateMachineStateChangeEvent $event): void
    {
        $order = $event->getOrder();

        $orderTransaction = WorldlineSaferpay::getLastOrderTransactionHandledViaSaferpay($order);
        if (!$orderTransaction) {
            return;
        }

        if ($this->getSaferpayTransactionStatus($orderTransaction) === SaferpayPaymentApiClient::TRANSACTION_STATUS_CAPTURED) {
            return;
        }

        try {
            $this->paymentService->capture($orderTransaction, $order->getSalesChannel(), $event->getContext());
        } catch (\Exception $exception) {
            $this->restoreOrderTransactionState($orderTransaction->getId(), $this->lastTransactionSateId, $event->getContext());
            throw $exception;
        }
    }

    /**
     * @throws \Worldline\Saferpay\Api\Exception\ApiRequestException
     * @noinspection PhpUnused
     */
    public function onEnterCancelledSate(OrderStateMachineStateChangeEvent $event): void
    {
        $order = $event->getOrder();

        $orderTransaction = WorldlineSaferpay::getLastOrderTransactionHandledViaSaferpay($order);
        if (!$orderTransaction) {
            return;
        }

        if ($this->getSaferpayTransactionStatus($orderTransaction) === SaferpayPaymentApiClient::TRANSACTION_STATUS_CANCELED) {
            return;
        }

        try {
            $this->paymentService->cancel($orderTransaction, $order->getSalesChannel(), $event->getContext());
        } catch (\Exception $exception) {
            $this->restoreOrderTransactionState($orderTransaction->getId(), $this->lastTransactionSateId, $event->getContext());
            throw $exception;
        }
    }

    private function restoreOrderTransactionState(string $orderTransactionId, string $stateId, Context $context): void
    {
        if (!$stateId) {
            return;
        }

        $data = [['id' => $orderTransactionId, 'stateId' => $stateId]];
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
            $this->orderTransactionRepository->upsert($data, $context);
        });
    }

    private function getSaferpayTransactionStatus(OrderTransactionEntity $orderTransaction): ?string
    {
        $customFields = $orderTransaction->getCustomFields() ?: [];
        $status = $customFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_TRANSACTION_STATUS] ?? null;

        if (!$status || !is_string($status)) {
            return null;
        }

        return $status;
    }
}
