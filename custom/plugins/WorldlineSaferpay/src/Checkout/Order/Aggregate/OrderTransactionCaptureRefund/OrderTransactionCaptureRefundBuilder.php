<?php declare(strict_types=1);

namespace Worldline\Saferpay\Checkout\Order\Aggregate\OrderTransactionCaptureRefund;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Worldline\Saferpay\WorldlineSaferpay;

class OrderTransactionCaptureRefundBuilder
{
    public function __construct(
        private readonly EntityRepository $orderTransactionCaptureRefundRepository,
        private readonly EntityRepository $stateMachineStateRepository,
        private readonly EntityRepository $orderLineItemRepository,
    ) {}

    public function createForOrderTransactionCapture(
        OrderTransactionCaptureEntity $orderTransactionCapture,
        array $positions,
        array $additionalPositions,
        Context $context
    ): string {
        $refundId = Uuid::randomHex();

        $refund = [
            'id' => $refundId,
            'stateId' => $this->fetchRefundOpenStateId($context),
            'captureId' => $orderTransactionCapture->getId(),
            'positions' => [],
        ];

        $amount = 0.0;
        $reasons = [];

        foreach ($positions as $position) {
            $quantity = (int)($position->quantity ?? null);
            if (!$quantity) {
                $quantity = 1;
            }

            $reason = (string)($position->reason ?? null);
            $orderLineItemId = (string)($position->orderLineItemId ?? null);

            if (!$orderLineItemId) {
                continue;
            }

            $orderLineItem = $this->fetchOrderLineItem($orderLineItemId, $context);

            $totalPrice = $orderLineItem->getUnitPrice() * $quantity;
            $amount += $totalPrice;

            if ($reason) {
                $reasons[] = $reason;
            }

            $refund['positions'][] = [
                'refundId' => $refundId,
                'orderLineItemId' => $orderLineItemId,
                'reason' => $reason ?: null,
                'quantity' => $quantity,
                'amount' => [
                    'unitPrice' => $orderLineItem->getUnitPrice(),
                    'totalPrice' => $totalPrice,
                    'quantity' => $quantity,
                    'calculatedTaxes' => [],
                    'taxRules' => [],
                ]
            ];
        }

        foreach ($additionalPositions as $additionalPosition) {
            if (!isset($refund['customFields'][WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_ADDITIONAL_POSITIONS])) {
                $refund['customFields'][WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_ADDITIONAL_POSITIONS] = [];
            }

            if (isset($additionalPosition->price)) {
                $amount += $additionalPosition->price;
            }

            $refund['customFields'][WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_ADDITIONAL_POSITIONS][] = $additionalPosition;
        }

        $refund['amount'] = [
            'unitPrice' => $amount,
            'totalPrice' => $amount,
            'quantity' => 1,
            'calculatedTaxes' => [],
            'taxRules' => [],
        ];

        if ($reasons) {
            $refund['reason'] = implode("\n\n", $reasons);
        }

        $this->orderTransactionCaptureRefundRepository->create([$refund], $context);

        return $refundId;
    }

    private function fetchRefundOpenStateId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria
            ->setLimit(1)
            ->addFilter(new EqualsFilter('technicalName', OrderTransactionCaptureRefundStates::STATE_OPEN))
            ->addFilter(new EqualsFilter('stateMachine.technicalName', OrderTransactionCaptureRefundStates::STATE_MACHINE));

        $id = $this->stateMachineStateRepository->searchIds($criteria, $context)->firstId();

        if (!$id) {
            throw new \RuntimeException(
                'Failed to fetch order transaction capture refund open state',
                1712760592
            );
        }

        return $id;
    }

    private function fetchOrderLineItem(string $id, Context $context): OrderLineItemEntity
    {
        $orderLineItem = $this->orderLineItemRepository->search(new Criteria([$id]), $context)->first();

        if (!$orderLineItem instanceof OrderLineItemEntity) {
            throw new \RuntimeException(
                'Failed to fetch order line item with ID ' . $id,
                1712760600
            );
        }

        return $orderLineItem;
    }
}
