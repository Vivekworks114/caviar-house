<?php declare(strict_types = 1);

namespace Worldline\Saferpay\Subscriber;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Worldline\Saferpay\Service\PaymentService;
use Worldline\Saferpay\WorldlineSaferpay;

/**
 * @noinspection PhpUnused
 */
class CheckoutConfirmPageSubscriber implements EventSubscriberInterface
{
    private PaymentService $paymentService;
    private EntityRepository $orderTransactionRepository;

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoaded',
            AccountEditOrderPageLoadedEvent::class => 'onAccountEditOrderPageLoadedEvent'
        ];
    }

    public function __construct(PaymentService $paymentService, EntityRepository $orderTransactionRepository)
    {
        $this->paymentService = $paymentService;
        $this->orderTransactionRepository = $orderTransactionRepository;
    }

    /**
     * @noinspection PhpUnused
     * @throws \Exception
     */
    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $saferpayFieldsData = $this->buildSaferpayFieldsData($event->getSalesChannelContext());

        if ($saferpayFieldsData) {
            $event->getPage()->addArrayExtension('worldline_saferpay', $saferpayFieldsData);
        }
    }

    /**
     * @noinspection PhpUnused
     * @throws \Exception
     */
    public function onAccountEditOrderPageLoadedEvent(AccountEditOrderPageLoadedEvent $event): void
    {
        $saferpayFieldsData = $this->buildSaferpayFieldsData($event->getSalesChannelContext());

        if ($saferpayFieldsData) {
            $event->getPage()->addArrayExtension('worldline_saferpay', $saferpayFieldsData);
        }
    }

    /**
     * @throws \Exception
     */
    private function buildSaferpayFieldsData(SalesChannelContext $salesChannelContext): array
    {
        $selectedPaymentMethod = $salesChannelContext->getPaymentMethod();
        $salesChannel = $salesChannelContext->getSalesChannel();
        $customer = $salesChannelContext->getCustomer();

        if (!$this->paymentService->isSaferpayFieldsIntegration($selectedPaymentMethod)) {
            return [];
        }

        $customFields = $selectedPaymentMethod->getCustomFields() ?: [];

        $paymentMeans = $customFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS] ?? null;
        if (!is_array($paymentMeans)) {
            $paymentMeans = [];
        }

        $storedPaymentData = [];

        if ($this->paymentService->shouldSavePaymentData($salesChannel, $customer)) {
            $storedPaymentData = $this->paymentService->fetchStoredCustomerPaymentDataByPaymentMethodId(
                $selectedPaymentMethod->getId(),
                $salesChannelContext->getCustomerId(),
                $salesChannelContext->getContext()
            );
        }

        return [
            'saferpayFields' => true,
            'saferpayFieldsJs' => $this->paymentService->getSaferpayFieldsJsUrl($salesChannel),
            'saferpayFieldsUrl' => $this->paymentService->getSaferpayFieldsUrl($salesChannel),
            'saferpayFieldsAccessToken' => $this->paymentService->getSaferpayFieldsAccessToken($salesChannel),
            'saferpayCustomerId' => $this->paymentService->getSaferpayCustomerId($salesChannel),
            'saferpayPaymentMeans' => $paymentMeans,
            'storedPaymentData' => $storedPaymentData,
            'cardFormHolderNameBehavior' => $this->paymentService->getCardFormHolderNameBehavior($salesChannel)
        ];
    }
}
