<?php declare(strict_types = 1);

namespace Worldline\Saferpay\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Worldline\Saferpay\WorldlineSaferpay;

/**
 * @noinspection PhpUnused
 */
class OrderTransactionCaptureSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'state_enter.order_transaction_capture.state.completed' => 'onEnterCompletedSate'
        ];
    }

    private EntityRepository $orderTransactionCaptureRepository;

    public function __construct(EntityRepository $orderTransactionCaptureRepository)
    {
        $this->orderTransactionCaptureRepository = $orderTransactionCaptureRepository;
    }

    /**
     * @noinspection PhpUnused
     */
    public function onEnterCompletedSate(OrderStateMachineStateChangeEvent $event): void
    {
        $order = $event->getOrder();

        $orderTransaction = WorldlineSaferpay::getLastOrderTransactionHandledViaSaferpay($order);
        if (!$orderTransaction) {
            return;
        }

        $orderTransactionCustomFields = $orderTransaction->getCustomFields() ?: [];

        $saferpayCaptureId = $orderTransactionCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_CAPTURE_ID] ?? null;
        if (!$saferpayCaptureId) {
            return;
        }

        $captures = $orderTransaction->getCaptures();
        if (!$captures) {
            return;
        }

        $captures->sort(function(OrderTransactionCaptureEntity $a, OrderTransactionCaptureEntity $b) {
            return $a->getCreatedAt() <=> $b->getCreatedAt();
        });

        $lastCapture = $captures->last();
        if (!$lastCapture instanceof OrderTransactionCaptureEntity) {
            return;
        }

        $this->updateOrderTransactionCaptureData($lastCapture->getId(), $saferpayCaptureId, $event->getContext());
    }

    private function updateOrderTransactionCaptureData(
        string $orderTransactionCaptureId,
        string $saferpayCaptureId,
        Context $context
    ): void {
        $data = [
            [
                'id' => $orderTransactionCaptureId,
                'externalReference' => $saferpayCaptureId
            ]
        ];

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($data): void {
            $this->orderTransactionCaptureRepository->update($data, $context);
        });
    }
}
