<?php declare(strict_types = 1);

namespace Worldline\Saferpay\Subscriber;

use Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Worldline\Saferpay\Checkout\Payment\Cart\PaymentHandler\SaferpayPaymentHandler;
use Worldline\Saferpay\Service\PaymentService;

/**
 * @noinspection PhpUnused
 */
class AccountPaymentMethodPageSubscriber implements EventSubscriberInterface
{
    private PaymentService $paymentService;

    public static function getSubscribedEvents(): array
    {
        return [
            AccountPaymentMethodPageLoadedEvent::class => 'onAccountPaymentMethodPageLoadedEvent'
        ];
    }

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @noinspection PhpUnused
     * @throws \Exception
     */
    public function onAccountPaymentMethodPageLoadedEvent(AccountPaymentMethodPageLoadedEvent $event): void
    {
        $customer = $event->getSalesChannelContext()->getCustomer();
        if (!$customer || $customer->getGuest()) {
            return;
        }

        $secureCardData = [];

        foreach ($event->getPage()->getPaymentMethods() as $paymentMethod) {
            if ($paymentMethod->getHandlerIdentifier() !== SaferpayPaymentHandler::class) {
                continue;
            }

            $secureCardData[$paymentMethod->getId()] = $this->paymentService->fetchStoredCustomerPaymentDataByPaymentMethodId(
                $paymentMethod->getId(),
                $event->getSalesChannelContext()->getCustomerId(),
                $event->getSalesChannelContext()->getContext()
            );
        }

        if ($secureCardData) {
            $event->getPage()->addArrayExtension('worldline_saferpay', [
                'secureCardData' => $secureCardData
            ]);
        }
    }
}
