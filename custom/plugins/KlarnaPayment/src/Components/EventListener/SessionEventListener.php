<?php

declare(strict_types=1);

namespace KlarnaPayment\Components\EventListener;

use KlarnaPayment\Components\CartHasher\CartHasherInterface;
use KlarnaPayment\Components\Client\ClientInterface;
use KlarnaPayment\Components\Client\Hydrator\Request\CreateSession\CreateSessionRequestHydratorInterface;
use KlarnaPayment\Components\Client\Hydrator\Request\UpdateSession\UpdateSessionRequestHydratorInterface;
use KlarnaPayment\Components\Client\Hydrator\Struct\Address\AddressStructHydrator;
use KlarnaPayment\Components\Client\Hydrator\Struct\Address\AddressStructHydratorInterface;
use KlarnaPayment\Components\Client\Hydrator\Struct\Customer\CustomerStructHydratorInterface;
use KlarnaPayment\Components\Client\Response\GenericResponse;
use KlarnaPayment\Components\Client\Struct\Attachment;
use KlarnaPayment\Components\ConfigReader\ConfigReaderInterface;
use KlarnaPayment\Components\Controller\Storefront\KlarnaExpressCheckoutController;
use KlarnaPayment\Components\Converter\CustomOrderConverter;
use KlarnaPayment\Components\Event\OrderCreatedThroughAuthorizationCallback;
use KlarnaPayment\Components\Extension\ErrorMessageExtension;
use KlarnaPayment\Components\Extension\SessionDataExtension;
use KlarnaPayment\Components\Factory\MerchantDataFactoryInterface;
use KlarnaPayment\Components\Helper\OrderFetcherInterface;
use KlarnaPayment\Components\Helper\PaymentHelper\PaymentHelperInterface;
use KlarnaPayment\Installer\Modules\PaymentMethodInstaller;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Event\RouteRequest\SetPaymentOrderRouteRequestEvent;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Page;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionEventListener implements EventSubscriberInterface
{
    /** @var PaymentHelperInterface */
    private $paymentHelper;

    /** @var CreateSessionRequestHydratorInterface */
    private $requestHydrator;

    /** @var UpdateSessionRequestHydratorInterface */
    private $requestUpdateHydrator;

    /** @var AddressStructHydratorInterface */
    private $addressHydrator;

    /** @var CustomerStructHydratorInterface */
    private $customerHydrator;

    /** @var ClientInterface */
    private $client;

    /** @var CartHasherInterface */
    private $cartHasher;

    /** @var MerchantDataFactoryInterface */
    private $merchantDataFactory;

    /** @var CustomOrderConverter */
    private $orderConverter;

    /** @var OrderFetcherInterface */
    private $orderFetcher;

    /** @var null|SessionInterface */
    private $session;

    /** @var RequestStack */
    private $requestStack;

    /** @var SystemConfigService */
    private $systemConfigService;

    /** @var ConfigReaderInterface */
    private $configReader;

    /** @var string */
    private $appSecret;

    public function __construct(
        PaymentHelperInterface $paymentHelper,
        CreateSessionRequestHydratorInterface $requestHydrator,
        UpdateSessionRequestHydratorInterface $requestUpdateHydrator,
        AddressStructHydratorInterface $addressHydrator,
        CustomerStructHydratorInterface $customerHydrator,
        ClientInterface $client,
        CartHasherInterface $cartHasher,
        MerchantDataFactoryInterface $merchantDataFactory,
        CustomOrderConverter $orderConverter,
        OrderFetcherInterface $orderFetcher,
        SystemConfigService $systemConfigService,
        ConfigReaderInterface $configReader,
        ?SessionInterface $session,
        RequestStack $requestStack,
        string $appSecret
    ) {
        $this->paymentHelper         = $paymentHelper;
        $this->requestHydrator       = $requestHydrator;
        $this->requestUpdateHydrator = $requestUpdateHydrator;
        $this->addressHydrator       = $addressHydrator;
        $this->customerHydrator      = $customerHydrator;
        $this->client                = $client;
        $this->cartHasher            = $cartHasher;
        $this->merchantDataFactory   = $merchantDataFactory;
        $this->orderConverter        = $orderConverter;
        $this->orderFetcher          = $orderFetcher;
        $this->systemConfigService   = $systemConfigService;
        $this->configReader          = $configReader;
        $this->appSecret             = $appSecret;
        $this->session               = $session;
        $this->requestStack          = $requestStack;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class           => 'startKlarnaSession',
            AccountEditOrderPageLoadedEvent::class          => 'startKlarnaSession',
            CheckoutOrderPlacedEvent::class                 => 'resetKlarnaSession',
            CustomerLoginEvent::class                       => 'resetKlarnaSession',
            SetPaymentOrderRouteRequestEvent::class         => 'resetKlarnaSession',
            OrderCreatedThroughAuthorizationCallback::class => 'resetKlarnaSession',
        ];
    }

    public function startKlarnaSession(PageLoadedEvent $event): void
    {
        $context = $event->getSalesChannelContext();

        if (!$this->paymentHelper->isKlarnaPaymentsEnabled($context)) {
            return;
        }

        if ($event instanceof CheckoutConfirmPageLoadedEvent) {
            $cart = $event->getPage()->getCart();
        } elseif ($event instanceof AccountEditOrderPageLoadedEvent) {
            /** @phpstan-ignore-next-line */
            $cart = $this->convertCartFromOrder($event->getPage()->getOrder(), $event->getContext());
        } else {
            return;
        }

        $response = $this->getSession()->has(KlarnaExpressCheckoutController::KLARNA_EXPRESS_SESSION_KEY)
            ? (new GenericResponse())->assign(['httpStatus' => Response::HTTP_BAD_REQUEST])
            : $this->createOrUpdateKlarnaSession($event, $cart, $context);

        if ($this->isValidResponseStatus($response) && !$this->hasValidKlarnaSession($context)) {
            $this->addKlarnaSessionToShopwareSession($response->getResponse(), $context);
        }

        $this->createSessionDataExtension($response, $event->getPage(), $cart, $context);
        $this->removeDisabledKlarnaPaymentMethods($event->getPage());
        $this->filterPayNowMethods($event->getPage());

        $this->updateGlobalPurchaseFlowSystemConfig($event->getPage(), $context);
    }

    public function resetKlarnaSession(): void
    {
        $this->getSession()->remove(UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID);
        $this->getSession()->remove(UpdateSessionRequestHydratorInterface::KLARNA_CLIENT_TOKEN);
        $this->getSession()->remove(UpdateSessionRequestHydratorInterface::KLARNA_PAYMENT_METHOD_CATEGORIES);
        $this->getSession()->remove(UpdateSessionRequestHydratorInterface::KLARNA_ADDRESS_HASH);
    }

    private function filterPayNowMethods(Struct $page): void
    {
        if (!($page instanceof Page)) {
            return;
        }

        /** @var null|SessionDataExtension $sessionData */
        $sessionData = $page->getExtension(SessionDataExtension::EXTENSION_NAME);

        if ($sessionData === null) {
            return;
        }

        foreach ($sessionData->getPaymentMethodCategories() as $paymentCategory) {
            if ($paymentCategory['identifier'] === PaymentMethodInstaller::KLARNA_PAYMENTS_PAY_NOW_CODE) {
                $this->removeSeparatePayNowKlarnaPaymentMethods($page);

                return;
            }
        }

        $this->removeCombinedKlarnaPaymentPayNowMethod($page);
    }

    private function createErrorMessageExtension(PageLoadedEvent $event): void
    {
        $errorMessageExtension = new ErrorMessageExtension(ErrorMessageExtension::GENERIC_ERROR);

        $event->getPage()->addExtension(ErrorMessageExtension::EXTENSION_NAME, $errorMessageExtension);
    }

    private function createSessionDataExtension(GenericResponse $response, Struct $page, Cart $cart, SalesChannelContext $context): void
    {
        if (!($page instanceof Page)) {
            return;
        }

        $config = $this->configReader->read($context->getSalesChannel()->getId());

        $sessionData = new SessionDataExtension();
        $sessionData->assign([
            'selectedPaymentMethodCategory' => $this->getKlarnaCodeFromPaymentMethod($context),
            'cartHash'                      => $this->cartHasher->generate($cart, $context),
            'useAuthorizationCallback'      => $config->get('kpUseAuthorizationCallback'),
        ]);

        if ($this->hasValidKlarnaSession($context)) {
            $sessionData->assign([
                'sessionId'               => $this->getSession()->get(UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID),
                'clientToken'             => $this->getSession()->get(UpdateSessionRequestHydratorInterface::KLARNA_CLIENT_TOKEN),
                'paymentMethodCategories' => $this->getSession()->get(UpdateSessionRequestHydratorInterface::KLARNA_PAYMENT_METHOD_CATEGORIES),
            ]);
        } elseif ($this->getSession()->has(KlarnaExpressCheckoutController::KLARNA_EXPRESS_SESSION_KEY)) {
            $sessionData->assign([
                'isKlarnaExpress' => true,
                'clientToken'     => $this->getSession()->get(UpdateSessionRequestHydratorInterface::KLARNA_CLIENT_TOKEN),
            ]);
        } elseif ($this->isValidResponseStatus($response)) {
            $sessionData->assign([
                'sessionId'               => $response->getResponse()['session_id'],
                'clientToken'             => $response->getResponse()['client_token'],
                'paymentMethodCategories' => $response->getResponse()['payment_method_categories'],
            ]);
        }

        if ($this->paymentHelper->isKlarnaPaymentsSelected($context)) {
            $extraMerchantData = $this->merchantDataFactory->getExtraMerchantData($sessionData, $cart, $context);

            if (!empty($extraMerchantData->getAttachment())) {
                $attachment = new Attachment();
                $attachment->assign([
                    'data' => $extraMerchantData->getAttachment(),
                ]);
            } else {
                $attachment = null;
            }

            $sessionData->assign([
                'customerData' => [
                    'billing_address'  => $this->addressHydrator->hydrateFromContext($context, AddressStructHydrator::TYPE_BILLING),
                    'shipping_address' => $this->addressHydrator->hydrateFromContext($context, AddressStructHydrator::TYPE_SHIPPING),
                    'customer'         => $this->customerHydrator->hydrate($context),
                    'merchant_data'    => $extraMerchantData->getMerchantData(),
                    'attachment'       => $attachment,
                ],
            ]);
        }

        $page->addExtension(SessionDataExtension::EXTENSION_NAME, $sessionData);
    }

    private function removeDisabledKlarnaPaymentMethods(Struct $page): void
    {
        if (!($page instanceof Page)) {
            return;
        }

        /** @var null|SessionDataExtension $sessionData */
        $sessionData = $page->getExtension(SessionDataExtension::EXTENSION_NAME);

        if ($sessionData === null) {
            return;
        }

        if (!method_exists($page, 'setPaymentMethods') || !method_exists($page, 'getPaymentMethods')) {
            return;
        }

        $isKlarnaExpress = $this->getSession()->has(KlarnaExpressCheckoutController::KLARNA_EXPRESS_SESSION_KEY);

        $page->setPaymentMethods(
            $page->getPaymentMethods()->filter(
                static function (PaymentMethodEntity $paymentMethod) use ($sessionData, $isKlarnaExpress) {
                    if (array_key_exists($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_EXPRESS_CHECKOUT_CODES) && !$isKlarnaExpress) {
                        return false;
                    }

                    if (!array_key_exists($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES)) {
                        return true;
                    }

                    foreach ($sessionData->getPaymentMethodCategories() as $paymentCategory) {
                        if ($paymentCategory['identifier'] === PaymentMethodInstaller::KLARNA_PAYMENTS_CODES[$paymentMethod->getId()]) {
                            $paymentMethod->setName($paymentCategory['name']);
                            $paymentMethod->setTranslated(
                                array_merge(
                                    $paymentMethod->getTranslated(),
                                    ['name' => $paymentCategory['name']]
                                )
                            );

                            return true;
                        }
                    }

                    return false;
                }
            )
        );
    }

    private function removeSeparatePayNowKlarnaPaymentMethods(Page $page): void
    {
        if (!method_exists($page, 'setPaymentMethods') || !method_exists($page, 'getPaymentMethods')) {
            return;
        }

        $page->setPaymentMethods(
            $page->getPaymentMethods()->filter(
                static function (PaymentMethodEntity $paymentMethod) {
                    if (!array_key_exists($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES)) {
                        return true;
                    }

                    return in_array($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES_WITH_PAY_NOW_COMBINED, true);
                }
            )
        );
    }

    private function removeCombinedKlarnaPaymentPayNowMethod(Page $page): void
    {
        if (!method_exists($page, 'setPaymentMethods') || !method_exists($page, 'getPaymentMethods')) {
            return;
        }

        $page->setPaymentMethods(
            $page->getPaymentMethods()->filter(
                static function (PaymentMethodEntity $paymentMethod) {
                    if (!array_key_exists($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES)) {
                        return true;
                    }

                    return $paymentMethod->getId() !== PaymentMethodInstaller::KLARNA_PAY_NOW;
                }
            )
        );
    }

    private function removeAllKlarnaPaymentMethods(Struct $page): void
    {
        if (!($page instanceof Page) || !method_exists($page, 'setPaymentMethods') || !method_exists($page, 'getPaymentMethods')) {
            return;
        }

        $page->setPaymentMethods(
            $page->getPaymentMethods()->filter(
                static function (PaymentMethodEntity $paymentMethod) {
                    if (array_key_exists($paymentMethod->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES)) {
                        return false;
                    }

                    return true;
                }
            )
        );
    }

    private function createOrUpdateKlarnaSession(PageLoadedEvent $event, Cart $cart, SalesChannelContext $context): GenericResponse
    {
        $response = $this->hasValidKlarnaSession($context)
            ? $this->updateKlarnaSession($this->getSession()->get(UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID), $cart, $context)
            : $this->createKlarnaSession($cart, $context);

        if ($this->isValidResponseStatus($response)) {
            return $response;
        }

        if ($this->paymentHelper->isKlarnaPaymentsSelected($context)) {
            $this->createErrorMessageExtension($event);
        }

        $this->removeAllKlarnaPaymentMethods($event->getPage());

        return $response;
    }

    private function isValidResponseStatus(GenericResponse $response): bool
    {
        return in_array($response->getHttpStatus(), [Response::HTTP_OK, Response::HTTP_NO_CONTENT], true);
    }

    private function createKlarnaSession(Cart $cart, SalesChannelContext $context): GenericResponse
    {
        $request = $this->requestHydrator->hydrate($cart, $context);

        return $this->client->request($request, $context->getContext());
    }

    private function updateKlarnaSession(string $sessionId, Cart $cart, SalesChannelContext $context): GenericResponse
    {
        $request = $this->requestUpdateHydrator->hydrate($sessionId, $cart, $context);

        return $this->client->request($request, $context->getContext());
    }

    private function getKlarnaCodeFromPaymentMethod(SalesChannelContext $context): string
    {
        if (!array_key_exists($context->getPaymentMethod()->getId(), PaymentMethodInstaller::KLARNA_PAYMENTS_CODES)) {
            return '';
        }

        return PaymentMethodInstaller::KLARNA_PAYMENTS_CODES[$context->getPaymentMethod()->getId()];
    }

    private function convertCartFromOrder(OrderEntity $orderEntity, Context $context): Cart
    {
        $order = $this->orderFetcher->getOrderFromOrder($orderEntity->getId(), $context);

        if ($order === null) {
            throw new \LogicException('could not find order via id');
        }

        return $this->orderConverter->convertOrderToCart($order, $context);
    }

    /**
     * @param array<string,mixed> $klarnaSession
     */
    private function addKlarnaSessionToShopwareSession(array $klarnaSession, SalesChannelContext $context): void
    {
        $this->getSession()->set(UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID, $klarnaSession['session_id']);
        $this->getSession()->set(UpdateSessionRequestHydratorInterface::KLARNA_CLIENT_TOKEN, $klarnaSession['client_token']);
        $this->getSession()->set(UpdateSessionRequestHydratorInterface::KLARNA_PAYMENT_METHOD_CATEGORIES, $klarnaSession['payment_method_categories']);
        $this->getSession()->set(UpdateSessionRequestHydratorInterface::KLARNA_ADDRESS_HASH, $this->getAddressHash($context));
    }

    private function hasValidKlarnaSession(SalesChannelContext $context): bool
    {
        return $this->getSession()->has(UpdateSessionRequestHydratorInterface::KLARNA_SESSION_ID)
            && $this->getSession()->has(UpdateSessionRequestHydratorInterface::KLARNA_CLIENT_TOKEN)
            && $this->getSession()->has(UpdateSessionRequestHydratorInterface::KLARNA_PAYMENT_METHOD_CATEGORIES)
            && $this->getSession()->has(UpdateSessionRequestHydratorInterface::KLARNA_ADDRESS_HASH) && $this->getSession()->get(UpdateSessionRequestHydratorInterface::KLARNA_ADDRESS_HASH) === $this->getAddressHash($context);
    }

    private function getAddressHash(SalesChannelContext $context): ?string
    {
        $customer = $this->addressHydrator->hydrateFromContext($context);

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

    // TODO: Remove me if compatibility is at least 6.4.2.0
    private function getSession(): SessionInterface
    {
        /** @phpstan-ignore-next-line */
        return $this->session ?? $this->requestStack->getSession();
    }

    private function updateGlobalPurchaseFlowSystemConfig(Struct $page, SalesChannelContext $context): void
    {
        if (!($page instanceof Page)) {
            return;
        }

        if (!method_exists($page, 'getPaymentMethods')) {
            return;
        }

        $paymentMethodIds = $page->getPaymentMethods()->getIds();

        if (!$this->hasKlarnaPayment($paymentMethodIds)) {
            return;
        }

        $configRelatedSalesChannelId = $context->getSalesChannel()->getId();

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
}
