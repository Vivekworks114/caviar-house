<?php declare(strict_types=1);

namespace Worldline\Saferpay\Service;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Worldline\Saferpay\Api\Exception\ApiRequestException;
use Worldline\Saferpay\Api\SaferpayPaymentApiClient;
use Worldline\Saferpay\Checkout\Payment\Cart\PaymentHandler\SaferpayPaymentHandler;
use Worldline\Saferpay\LockableTrait;
use Worldline\Saferpay\WorldlineSaferpay;

class PaymentService
{
    use LockableTrait;

    private const SAFERPAY_TRANSACTION_ID_PREFIX = 'ef64031a-7a21-4499-8bb3-ced90e72b8e5';

    private OrderTransactionStateHandler $orderTransactionStateHandler;
    private OrderTransactionCaptureStateHandler $orderTransactionCaptureStateHandler;
    private OrderTransactionCaptureRefundStateHandler $orderTransactionCaptureRefundStateHandler;
    private EntityRepository $orderTransactionRepository;
    private EntityRepository $orderTransactionCaptureRepository;
    private EntityRepository $orderTransactionCaptureRefundRepository;
    private EntityRepository $paymentMethodRepository;
    private EntityRepository $paymentMethodTranslationRepository;
    private EntityRepository $stateMachineStateRepository;
    private EntityRepository $customerRepository;
    private RouterInterface $router;
    private TranslatorInterface $translator;
    private SystemConfigService $systemConfigService;

    public function __construct(
        OrderTransactionStateHandler $orderTransactionStateHandler,
        OrderTransactionCaptureStateHandler $orderTransactionCaptureStateHandler,
        OrderTransactionCaptureRefundStateHandler $orderTransactionCaptureRefundStateHandler,
        EntityRepository $orderTransactionRepository,
        EntityRepository $orderTransactionCaptureRepository,
        EntityRepository $orderTransactionCaptureRefundRepository,
        EntityRepository $paymentMethodRepository,
        EntityRepository $paymentMethodTranslationRepository,
        EntityRepository $stateMachineStateRepository,
        EntityRepository $customerRepository,
        RouterInterface $router,
        TranslatorInterface $translator,
        SystemConfigService $systemConfigService
    ) {
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->orderTransactionCaptureStateHandler = $orderTransactionCaptureStateHandler;
        $this->orderTransactionCaptureRefundStateHandler = $orderTransactionCaptureRefundStateHandler;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->orderTransactionCaptureRepository = $orderTransactionCaptureRepository;
        $this->orderTransactionCaptureRefundRepository = $orderTransactionCaptureRefundRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentMethodTranslationRepository = $paymentMethodTranslationRepository;
        $this->stateMachineStateRepository = $stateMachineStateRepository;
        $this->customerRepository = $customerRepository;
        $this->router = $router;
        $this->translator = $translator;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @throws \Exception
     */
    public function initializePaymentPage(
        OrderTransactionEntity $orderTransaction,
        OrderEntity $order,
        string $returnUrl,
        SalesChannelEntity $salesChannel,
        Context $context
    ): string {
        /** @noinspection DuplicatedCode */
        $paymentApiClient = $this->createSaferpayPaymentApiClientForSalesChannel($salesChannel);

        $paymentMethod = $this->fetchPaymentMethodForOrderTransaction($orderTransaction, $context);
        $orderCustomer = $order->getOrderCustomer();
        $customer = $orderCustomer?->getCustomer();

        $billingAddress = $order->getBillingAddress();
        $deliveryAddress = $this->resolveOrderDeliveryAddress($order);

        $billingGender = $this->resolveGenderFromAddress($billingAddress);
        if (!$billingGender && $customer) {
            $billingGender = $this->resolveGenderFromCustomer($customer);
        }

        $notifyToken = $this->generateNotifyToken();
        $redirectUrlHandling = $this->getPaymentMethodRedirectUrlHandling($paymentMethod, $context);
        $finalizeUrl = $this->buildFinalizeUrl($orderTransaction, $returnUrl, $redirectUrlHandling);

        $response = $paymentApiClient->initializePaymentPage(
            orderId: $this->resolvePaymentReference($order, $salesChannel),
            paymentMethods: $this->getPaymentMethodPaymentMeans($paymentMethod, $context),
            wallets: $this->getPaymentMethodWallets($paymentMethod, $context),
            totalAmount: $this->amountToMinorUnit($orderTransaction->getAmount()->getTotalPrice()),
            currencyCode: $order->getCurrency()->getIsoCode(),
            customerEmail: (string)$orderCustomer?->getEmail(),
            customerId: (string)$orderCustomer?->getId(),
            customerDateOfBirth: (string)$customer?->getBirthday()?->format('Y-m-d'),
            billingFirstname: $billingAddress->getFirstName(),
            billingLastname: $billingAddress->getLastName(),
            billingCompany: (string)$billingAddress->getCompany(),
            billingGender: $billingGender,
            billingStreet: $billingAddress->getStreet(),
            billingStreet2: (string)$billingAddress->getAdditionalAddressLine1(),
            billingZip: $billingAddress->getZipcode(),
            billingCity: $billingAddress->getCity(),
            billingCountryCode: (string)$billingAddress->getCountry()?->getIso(),
            billingPhone: (string) $billingAddress->getPhoneNumber(),
            billingVatNumber: (string) $billingAddress->getVatId(),
            billingCountrySubdivisionCode: $this->resolveCountrySubdivisionCodeFromAddress($billingAddress),
            deliveryFirstName: $deliveryAddress->getFirstName(),
            deliveryLastName: $deliveryAddress->getLastName(),
            deliveryCompany: (string)$deliveryAddress->getCompany(),
            deliveryStreet: $deliveryAddress->getStreet(),
            deliveryZip: $deliveryAddress->getZipcode(),
            deliveryCity: $deliveryAddress->getCity(),
            deliveryCountryCode: (string)$deliveryAddress->getCountry()?->getIso(),
            deliveryCountrySubdivisionCode: $this->resolveCountrySubdivisionCodeFromAddress($deliveryAddress),
            orderItems: $this->resolveOrderItems($order),
            returnUrl: $finalizeUrl,
            successNotifyUrl: $this->buildSuccessNotifyUrl($orderTransaction, $notifyToken),
            failNotifyUrl: $this->buildFailNotifyUrl($orderTransaction, $notifyToken),
            description: $this->buildPaymentDescription($order, $salesChannel),
            cardFormHolderName: $this->getCardFormHolderNameBehavior($salesChannel),
            merchantEmails: $this->buildMerchantEmails($salesChannel),
            withLiabilityShift: $this->withLiabilityShift($paymentMethod, $context),
            enableCustomerNotification: (bool)$this->getPluginConfig('enableCustomerNotification', $salesChannel),
            configSet: $this->getPluginConfig('paymentPageConfigName', $salesChannel),
            force3ds: (bool)$this->getPluginConfig('force3ds', $salesChannel),
            savePaymentData: $this->shouldSavePaymentData($salesChannel, $customer),
        );

        $redirectUrl = $response->RedirectUrl;

        $this->persistSaferpayToken(
            $orderTransaction->getId(),
            $response->Token,
            WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
            $notifyToken,
            $redirectUrl,
            $redirectUrlHandling,
            $returnUrl,
            $context
        );

        return $redirectUrlHandling === WorldlineSaferpay::REDIRECT_URL_HANDLING_IFRAME
            ? $this->buildPayUrl($orderTransaction)
            : $redirectUrl;
        }

    /**
     * @throws \Exception
     */
    public function initializeTransaction(
        OrderTransactionEntity $orderTransaction,
        OrderEntity $order,
        string $returnUrl,
        string $saferpayFieldsToken,
        string $scdAlias,
        SalesChannelEntity $salesChannel,
        Context $context
    ): string {
        /** @noinspection DuplicatedCode */
        $paymentApiClient = $this->createSaferpayPaymentApiClientForSalesChannel($salesChannel);

        $paymentMethod = $this->fetchPaymentMethodForOrderTransaction($orderTransaction, $context);
        $orderCustomer = $order->getOrderCustomer();
        $customer = $orderCustomer?->getCustomer();

        $billingAddress = $order->getBillingAddress();
        $deliveryAddress = $this->resolveOrderDeliveryAddress($order);

        $billingGender = $this->resolveGenderFromAddress($billingAddress);
        if (!$billingGender && $customer) {
            $billingGender = $this->resolveGenderFromCustomer($customer);
        }

        $notifyToken = $this->generateNotifyToken();
        $redirectUrlHandling = $this->getPaymentMethodRedirectUrlHandling($paymentMethod, $context);
        $finalizeUrl = $this->buildFinalizeUrl($orderTransaction, $returnUrl, $redirectUrlHandling);

        $response = $paymentApiClient->initializeTransaction(
            orderId: $this->resolvePaymentReference($order, $salesChannel),
            totalAmount: $this->amountToMinorUnit($orderTransaction->getAmount()->getTotalPrice()),
            currencyCode: $order->getCurrency()->getIsoCode(),
            customerEmail: (string)$orderCustomer?->getEmail(),
            customerId: (string)$orderCustomer?->getId(),
            customerDateOfBirth: (string)$customer?->getBirthday()?->format('Y-m-d'),
            billingFirstname: $billingAddress->getFirstName(),
            billingLastname: $billingAddress->getLastName(),
            billingCompany: (string)$billingAddress->getCompany(),
            billingGender: $billingGender,
            billingStreet: $billingAddress->getStreet(),
            billingStreet2: (string)$billingAddress->getAdditionalAddressLine1(),
            billingZip: $billingAddress->getZipcode(),
            billingCity: $billingAddress->getCity(),
            billingCountryCode: (string)$billingAddress->getCountry()?->getIso(),
            billingPhone: (string) $billingAddress->getPhoneNumber(),
            billingVatNumber: (string) $billingAddress->getVatId(),
            billingCountrySubdivisionCode: $this->resolveCountrySubdivisionCodeFromAddress($billingAddress),
            deliveryFirstName: $deliveryAddress->getFirstName(),
            deliveryLastName: $deliveryAddress->getLastName(),
            deliveryCompany: (string)$deliveryAddress->getCompany(),
            deliveryStreet: $deliveryAddress->getStreet(),
            deliveryZip: $deliveryAddress->getZipcode(),
            deliveryCity: $deliveryAddress->getCity(),
            deliveryCountryCode: (string)$deliveryAddress->getCountry()?->getIso(),
            deliveryCountrySubdivisionCode: $this->resolveCountrySubdivisionCodeFromAddress($deliveryAddress),
            orderItems: $this->resolveOrderItems($order),
            returnUrl: $finalizeUrl,
            successNotifyUrl: $this->buildSuccessNotifyUrl($orderTransaction, $notifyToken),
            failNotifyUrl: $this->buildFailNotifyUrl($orderTransaction, $notifyToken),
            description: $this->buildPaymentDescription($order, $salesChannel),
            saferpayFieldsToken: $saferpayFieldsToken,
            configSet: $this->getPluginConfig('paymentPageConfigName', $salesChannel),
            force3ds: (bool)$this->getPluginConfig('force3ds', $salesChannel),
            paymentMeansAlias: $scdAlias,
        );

        $redirectUrl = $response->RedirectRequired ? $response->Redirect->RedirectUrl : $finalizeUrl;

        $this->persistSaferpayToken(
            $orderTransaction->getId(),
            $response->Token,
            WorldlineSaferpay::INTEGRATION_SAFERPAY_FIELDS,
            $notifyToken,
            $redirectUrl,
            $redirectUrlHandling,
            $returnUrl,
            $context
        );

        return $redirectUrlHandling === WorldlineSaferpay::REDIRECT_URL_HANDLING_IFRAME
            ? $this->buildPayUrl($orderTransaction)
            : $redirectUrl;
    }

    /**
     * @throws ApiRequestException
     */
    public function authorize(OrderTransactionEntity $orderTransaction, SalesChannelEntity $salesChannel, Context $context): void
    {
        $lock = $this->acquireLock($orderTransaction->getId() . '-authorize');
        if (!$lock) {
            return;
        }

        try {
            $tokenType = $orderTransaction->getCustomFields()[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_TOKEN_TYPE] ?? '';

            $token = $orderTransaction->getCustomFields()[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_TOKEN] ?? '';
            if (!is_string($token) || trim($token) === '') {
                throw new \RuntimeException(
                    'Can not authorize order transaction '
                    . $orderTransaction->getId()
                    . ' via Saferpay, because Saferpay token is missing',
                    1719319581
                );
            }

            $paymentMethod = $this->fetchPaymentMethodForOrderTransaction($orderTransaction, $context);
            $paymentMethodCustomFields = $this->getPaymentMethodCustomFields($paymentMethod, $context);

            /** @var OrderEntity $order */
            $order = $orderTransaction->getOrder();
            $orderCustomer = $order->getOrderCustomer();
            $customer = $orderCustomer?->getCustomer();
            if (!$customer) {
                $customer = $this->fetchCustomerById($orderCustomer->getCustomerId(), $context);
            }

            $paymentApiClient = $this->createSaferpayPaymentApiClientForSalesChannel($salesChannel);

            if ($tokenType === WorldlineSaferpay::INTEGRATION_SAFERPAY_FIELDS) {
                $authorizeOrAssertResponse = $paymentApiClient->authorizeTransaction(
                    $token,
                    $this->withLiabilityShift($paymentMethod, $context),
                    $this->shouldSavePaymentData($salesChannel, $customer)
                );
            } else {
                $authorizeOrAssertResponse = $paymentApiClient->assertPaymentPage($token);
            }

            $saferpayTransactionStatus = $authorizeOrAssertResponse->Transaction->Status;
            $saferpayTransactionId = $authorizeOrAssertResponse->Transaction->Id;
            $saferpayPaymentMethod = $authorizeOrAssertResponse->PaymentMeans->Brand->PaymentMethod ?? '';
            $saferpayPaymentName = $authorizeOrAssertResponse->PaymentMeans->Brand->Name ?? '';
            $saferpayPaymentDisplayText = $authorizeOrAssertResponse->PaymentMeans->DisplayText;
            $saferpayCaptureId = $authorizeOrAssertResponse->Transaction->CaptureId ?? '';

            $saferpayLiabilityShift = isset($authorizeOrAssertResponse->Liability)
                && $authorizeOrAssertResponse->Liability->LiabilityShift;

            $saferpayLiabilityAuthenticationType = isset($authorizeOrAssertResponse->Liability->ThreeDs)
                ? ($authorizeOrAssertResponse->Liability->ThreeDs->AuthenticationType ?? null)
                : null;

            $saferpayPaymentAlias = null;
            if (isset($authorizeOrAssertResponse->RegistrationResult)) {
                $saferpayPaymentAlias = isset($authorizeOrAssertResponse->RegistrationResult->Success)
                && $authorizeOrAssertResponse->RegistrationResult->Success
                && isset($authorizeOrAssertResponse->RegistrationResult->Alias)
                    ? ($authorizeOrAssertResponse->RegistrationResult->Alias->Id ?? null)
                    : null;
            }

            if ($saferpayPaymentAlias && $customer) {
                $storedPaymentData = $this->fetchStoredCustomerPaymentDataByPaymentMethodId(
                    $orderTransaction->getPaymentMethodId(),
                    $customer->getId(),
                    $context
                );

                if (isset($storedPaymentData[$saferpayPaymentAlias])) {
                    $saferpayPaymentAlias = null; // Ensure alias is only persisted once
                }
            }

            $liabilityShiftBehavior = $paymentMethodCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR] ?? '';

            if (
                $liabilityShiftBehavior === WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED
                && $this->supportsLiabilityShifting([$saferpayPaymentMethod])
                && !$saferpayLiabilityShift
            ) {
                $paymentApiClient->cancelTransaction($saferpayTransactionId);

                    throw new \RuntimeException(
                        'Transaction '
                        . $orderTransaction->getId()
                        . ' has no liability shift, but is required for payment method '
                        . $orderTransaction->getPaymentMethod()->getName(),
                        1662129549
                    );
                }

            if (
                $saferpayLiabilityAuthenticationType !== null
                && $this->getPluginConfig('force3ds', $salesChannel)
                && $saferpayLiabilityAuthenticationType !== 'STRONG_CUSTOMER_AUTHENTICATION'
            ) {
                $paymentApiClient->cancelTransaction($saferpayTransactionId);

                throw new \RuntimeException(
                    'Transaction '
                    . $orderTransaction->getId()
                    . ' was processed by the bank without a 3DS challenge, but 3-D Secure is configured to be forced',
                    1729166105
                );
            }

            $this->persistSaferpayTransactionData(
                $orderTransaction,
                $saferpayTransactionId,
                $saferpayTransactionStatus,
                $saferpayPaymentMethod,
                $saferpayCaptureId,
                $saferpayPaymentAlias,
                $saferpayPaymentName,
                $saferpayPaymentDisplayText,
                $context
            );

            if ($saferpayTransactionStatus === SaferpayPaymentApiClient::TRANSACTION_STATUS_CAPTURED) {
                $this->capture($orderTransaction, $salesChannel, $context);
                return;
            }

            if (
                $saferpayTransactionStatus !== SaferpayPaymentApiClient::TRANSACTION_STATUS_AUTHORIZED
                && $saferpayTransactionStatus !== SaferpayPaymentApiClient::TRANSACTION_STATUS_PENDING
            ) {
                throw new \RuntimeException(
                    'Transaction '
                    . $orderTransaction->getId()
                    . ' has an unsupported status after authorize attempt: '
                    . $saferpayTransactionStatus,
                    1657643350
                );
            }

            $captureBehavior = $this->getPluginConfig('captureBehavior' . $saferpayPaymentMethod, $salesChannel);
            if (!$captureBehavior || $captureBehavior === WorldlineSaferpay::CAPTURE_BEHAVIOR_AUTO) {
                $this->capture($orderTransaction, $salesChannel, $context);
                return;
            }

            $this->orderTransactionStateHandler->authorize($orderTransaction->getId(), $context);
        } catch (\Exception $exception) {
            if ($this->shouldThrowException($exception, $context)) {
                $this->persistSaferpayError($orderTransaction->getId(), $exception, $context);
                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws ApiRequestException
     */
    public function capture(OrderTransactionEntity $orderTransaction, SalesChannelEntity $salesChannel, Context $context): void
    {
        $lock = $this->acquireLock($orderTransaction->getId() . '-capture');
        if (!$lock) {
            return;
        }

        try {
            $saferpayTransactionId = $orderTransaction->getCustomFields()[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_TRANSACTION_ID] ?? '';
            if (!is_string($saferpayTransactionId) || trim($saferpayTransactionId) === '') {
                throw new \RuntimeException(
                    'Order transaction ' . $orderTransaction->getId() . ' has no Saferpay payment transaction ID',
                    1719320126
                );
            }

            $saferpayPaymentMethod = $orderTransaction->getCustomFields()[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_METHOD] ?? '';
            if (!is_string($saferpayPaymentMethod) || trim($saferpayPaymentMethod) === '') {
                throw new \RuntimeException(
                    'Order transaction ' . $orderTransaction->getId() . ' has no Saferpay payment method',
                    1691578476
                );
            }

            $saferpayCaptureId = $orderTransaction->getCustomFields()[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_CAPTURE_ID] ?? '';

            $shopwareVersion = $this->getShopwareVersion();
            $persistCaptureEntity = !$shopwareVersion || version_compare($shopwareVersion, '6.5.5.0', '>=');
            if ($persistCaptureEntity) {
                $transactionCaptureId = $this->fetchOrCreateTransactionCapture(
                    $orderTransaction->getId(),
                    $orderTransaction->getAmount(),
                    $saferpayTransactionId,
                    $context
                );
            }

            $paymentApiClient = $this->createSaferpayPaymentApiClientForSalesChannel($salesChannel);

            $saferpayCaptureStatus = $orderTransaction->getCustomFields()[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_TRANSACTION_STATUS] ?? '';
            if ($saferpayCaptureStatus !== SaferpayPaymentApiClient::TRANSACTION_STATUS_CAPTURED) {
                if ($saferpayCaptureId) {
                    $assertResponse = $paymentApiClient->assertCapture($saferpayCaptureId);
                    $saferpayCaptureStatus = $assertResponse->Status;
                } else {
                    $captureBehavior = $this->getPluginConfig('captureBehavior' . $saferpayPaymentMethod, $salesChannel);
                    if (!$captureBehavior) {
                        // The payment method does not support manual capturing, so there is nothing more to do via Saferpay API

                        if ($persistCaptureEntity) {
                            $this->persistSaferpayCaptureData($transactionCaptureId, $saferpayCaptureId, $context);
                            $this->orderTransactionCaptureStateHandler->complete($transactionCaptureId, $context);
                        }

                        $this->orderTransactionStateHandler->paid($orderTransaction->getId(), $context);

                        return;
                    }

                    $notifyToken = $orderTransaction->getCustomFields()[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_NOTIFY_TOKEN] ?? '';
                    if (!is_string($notifyToken) || trim($notifyToken) === '') {
                        throw new \RuntimeException(
                            'Order transaction '
                            . $orderTransaction->getId()
                            . ' can not be caputured, because Saferpay notify token is missing',
                            1719319912
                        );
                    }

                    try {
                        $captureResponse = $paymentApiClient->captureTransaction(
                            $saferpayTransactionId,
                            $this->router->generate(
                                'api.worldline-saferpay.notify-pending',
                                [
                                    'transactionId' => $orderTransaction->getId(),
                                    'notifyToken' => $notifyToken
                                ],
                                UrlGeneratorInterface::ABSOLUTE_URL
                            ),
                            $this->buildMerchantEmails($salesChannel)
                        );
                    } catch (ApiRequestException $apiRequestException) {
                        if ($apiRequestException->getErrorName() === ApiRequestException::ERROR_NAME_TRANSACTION_ALREADY_CAPTURED) {
                            $this->orderTransactionStateHandler->paid($orderTransaction->getId(), $context);
                            return;
                        }

                        throw $apiRequestException;
                    }

                    $saferpayCaptureId = $captureResponse->CaptureId ?? '';
                    $saferpayCaptureStatus = $captureResponse->Status;

                    $this->persistSaferpayTransactionData(
                        $orderTransaction,
                        $saferpayTransactionId,
                        $saferpayCaptureStatus,
                        null,
                        $saferpayCaptureId,
                        null,
                        null,
                        null,
                        $context
                    );
                }
            }

            if ($persistCaptureEntity) {
                $this->persistSaferpayCaptureData($transactionCaptureId, $saferpayCaptureId, $context);
            }

            if ($saferpayCaptureStatus === SaferpayPaymentApiClient::TRANSACTION_STATUS_PENDING) {
                $this->orderTransactionStateHandler->process($orderTransaction->getId(), $context);
                return;
            }

            if ($saferpayCaptureStatus !== SaferpayPaymentApiClient::TRANSACTION_STATUS_CAPTURED) {
                throw new \RuntimeException(
                    'Transaction '
                    . $orderTransaction->getId()
                    . ' has an unexpected capture status: '
                    . $saferpayCaptureStatus,
                    1657643350
                );
            }
            
            if ($persistCaptureEntity) {
                $this->orderTransactionCaptureStateHandler->complete($transactionCaptureId, $context);
            }

            $this->orderTransactionStateHandler->paid($orderTransaction->getId(), $context);
        } catch (\Exception $exception) {
            if ($this->shouldThrowException($exception, $context)) {
                $this->persistSaferpayError($orderTransaction->getId(), $exception, $context);
                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws ApiRequestException
     */
    public function cancel(OrderTransactionEntity $orderTransaction, SalesChannelEntity $salesChannel, Context $context): void
    {
        $lock = $this->acquireLock($orderTransaction->getId() . '-cancel');
        if (!$lock) {
            return;
        }

        try {
            $paymentApiClient = $this->createSaferpayPaymentApiClientForSalesChannel($salesChannel);

            $saferpayTransactionId = $orderTransaction->getCustomFields()[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_TRANSACTION_ID] ?? '';
            if (!is_string($saferpayTransactionId) || trim($saferpayTransactionId) === '') {
                // Saferpay transaction has not been started yet, so there is nothing to do via Saferpay API
                return;
            }

            $paymentApiClient->cancelTransaction($saferpayTransactionId);
        } catch (\Exception $exception) {
            if ($this->shouldThrowException($exception, $context)) {
                $this->persistSaferpayError($orderTransaction->getId(), $exception, $context);
                throw $exception;
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws ApiRequestException
     */
    public function deleteScdAlias(OrderTransactionEntity $orderTransaction, SalesChannelContext $salesChannelContext): void
    {
        $customFields = $orderTransaction->getCustomFields() ?: [];
        $scdAlias = $customFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_SCD_ALIAS] ?? null;
        if (!$scdAlias) {
            return;
        }

        $paymentApiClient = $this->createSaferpayPaymentApiClientForSalesChannel($salesChannelContext->getSalesChannel());
        $paymentApiClient->deletePaymentAlias($scdAlias);

        $this->orderTransactionRepository->update(
            [
                [
                    'id' => $orderTransaction->getId(),
                    'customFields' => [
                        WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_SCD_ALIAS => null
                    ]
                ]
            ],
            $salesChannelContext->getContext()
        );
    }

    /**
     * @throws ApiRequestException
     */
    public function refund(
        OrderTransactionCaptureRefundEntity $refund,
        SalesChannelEntity $salesChannel,
        Context $context
    ): void {
        /** @var OrderTransactionCaptureEntity $transactionCapture */
        $transactionCapture = $refund->getTransactionCapture();

        /** @var OrderTransactionEntity $transaction */
        $transaction = $transactionCapture->getTransaction();

        /** @var OrderEntity $order */
        $order = $transaction->getOrder();

        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $transaction->getPaymentMethod();

        if ($paymentMethod->getHandlerIdentifier() !== SaferpayPaymentHandler::class) {
            throw new \RuntimeException(
                'Can not refund ID '
                . $refund->getId()
                . ', because related order was not paid via Saferpay',
                1712763778
            );
        }

        $saferpayTransactionCaptureId = $transaction->getCustomFields()[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_CAPTURE_ID] ?? '';
        if (!$saferpayTransactionCaptureId) {
            throw new \RuntimeException(
                'Can not refund ID '
                . $refund->getId()
                . ', because related order transaction has no Saferpay capture ID',
                1712765352
            );
        }

        $paymentApiClient = $this->createSaferpayPaymentApiClientForSalesChannel($salesChannel);

        $configuredRefundReference = $this->getPluginConfig('refundReference', $salesChannel);
        if ($configuredRefundReference === 'ORDER_ID') {
            $orderId = $order->getId();
        } else {
            $orderId = $order->getOrderNumber();
        }

        $refundResponse = $paymentApiClient->refund(
            $orderId ?: $order->getId(),
            $saferpayTransactionCaptureId,
            $this->amountToMinorUnit($refund->getAmount()->getTotalPrice()),
            $order->getCurrency()->getIsoCode(),
            (string)$refund->getReason()
        );

        $saferpayRefundTransactionId = $refundResponse->Transaction->Id;
        $saferpayRefundTransactionCaptureId = $refundResponse->Transaction->CaptureId ?? null;

        $this->persistSaferpayRefundData(
            $refund->getId(),
            $saferpayRefundTransactionId,
            $saferpayRefundTransactionCaptureId,
            $context
        );

        if ($refundResponse->Transaction->Status === SaferpayPaymentApiClient::TRANSACTION_STATUS_CANCELED) {
            $this->orderTransactionCaptureRefundStateHandler->cancel($refund->getId(), $context);
            return;
        }

        if ($refundResponse->Transaction->Status === SaferpayPaymentApiClient::TRANSACTION_STATUS_CAPTURED) {
            $saferpayCaptureStatus = $refundResponse->Transaction->Status;
        } else if ($refundResponse->Transaction->Status === SaferpayPaymentApiClient::TRANSACTION_STATUS_AUTHORIZED) {
            try {
                $captureResponse = $paymentApiClient->captureTransaction(
                    $saferpayRefundTransactionId,
                    $this->router->generate(
                        'api.worldline-saferpay.notify-pending-refund',
                        [
                            'refundId' => $refund->getId()
                        ],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    ),
                    $this->buildMerchantEmails($salesChannel)
                );
            } catch (ApiRequestException $apiRequestException) {
                if ($apiRequestException->getErrorName() === ApiRequestException::ERROR_NAME_TRANSACTION_ALREADY_CAPTURED) {
                    return;
                }

                throw $apiRequestException;
            }

            $saferpayRefundTransactionCaptureId = $captureResponse->CaptureId ?? null;
            $saferpayCaptureStatus = $captureResponse->Status;
        } else {
            throw new \RuntimeException(
                'Refund transaction has an unsupported status after refund attempt: '
                . $refundResponse->Transaction->Status,
                1713436997
            );
        }

        $this->persistSaferpayRefundData(
            $refund->getId(),
            $saferpayRefundTransactionId,
            $saferpayRefundTransactionCaptureId,
            $context
        );

        if ($saferpayCaptureStatus === SaferpayPaymentApiClient::TRANSACTION_STATUS_PENDING) {
            $this->orderTransactionCaptureRefundStateHandler->process($refund->getId(), $context);
        } else if ($saferpayCaptureStatus === SaferpayPaymentApiClient::TRANSACTION_STATUS_CAPTURED) {
            $this->orderTransactionCaptureRefundStateHandler->complete($refund->getId(), $context);
        } else {
            throw new \RuntimeException(
                'Refund transaction '
                . $refund->getId()
                . ' has an unexpected capture status: '
                . $saferpayCaptureStatus,
                1713439768
            );
        }

        if ($refund->getAmount()->getTotalPrice() >= $order->getAmountTotal()) {
            $this->orderTransactionStateHandler->refund($transaction->getId(), $context);
        } else {
            $this->orderTransactionStateHandler->refundPartially($transaction->getId(), $context);
        }
    }

    public function getSavePaymentData(SalesChannelEntity $salesChannel): bool
    {
        return (bool)$this->getPluginConfig('savePaymentData', $salesChannel);
    }

    public function shouldSavePaymentData(SalesChannelEntity $salesChannel, ?CustomerEntity $customer): bool
    {
        return $this->getSavePaymentData($salesChannel) && $customer && !$customer->getGuest();
    }

    public function isSaferpayFieldsIntegration(PaymentMethodEntity $paymentMethod): bool
    {
        if ($paymentMethod->getHandlerIdentifier() !== SaferpayPaymentHandler::class) {
            return false;
        }

        $paymentMethodCustomFields = $paymentMethod->getCustomFields() ?: [];
        $saferpayIntegration = $paymentMethodCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION] ?? null;

        if ($saferpayIntegration !== WorldlineSaferpay::INTEGRATION_SAFERPAY_FIELDS) {
            return false;
        }

        $paymentMeans = $paymentMethodCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS] ?? null;
        if (!is_array($paymentMeans)) {
            $paymentMeans = [];
        }

        foreach ($paymentMeans as $paymentMean) {
            if (!in_array($paymentMean, WorldlineSaferpay::INTEGRATION_SAFERPAY_FIELDS_SUPPORTED_PAYMENT_METHODS)) {
                return false;
            }
        }

        return true;
    }

    public function getSaferpayFieldsJsUrl(SalesChannelEntity $salesChannel): string
    {
        return $this->isLiveMode($salesChannel)
            ? WorldlineSaferpay::URL_SAFERPAY_FIELDS_JS_LIVE
            : WorldlineSaferpay::URL_SAFERPAY_FIELDS_JS_TEST;
    }

    public function getSaferpayFieldsUrl(SalesChannelEntity $salesChannel): string
    {
        return $this->isLiveMode($salesChannel)
            ? WorldlineSaferpay::URL_SAFERPAY_FIELDS_LIVE
            : WorldlineSaferpay::URL_SAFERPAY_FIELDS_TEST;
    }

    public function getSaferpayFieldsAccessToken(SalesChannelEntity $salesChannel): string
    {
        $token = $this->isLiveMode($salesChannel)
            ? $this->getPluginConfig('saferpayFieldsAccessTokenLive', $salesChannel)
            : $this->getPluginConfig('saferpayFieldsAccessTokenTest', $salesChannel);

        if (!$token) {
            throw new \RuntimeException(
                'No Saferpay fields access token is configured',
                1715862921
            );
        }

        return $token;
    }

    public function getSaferpayCustomerId(SalesChannelEntity $salesChannel): string
    {
        return $this->isLiveMode($salesChannel)
            ? $this->getPluginConfig('customerIdLive', $salesChannel)
            : $this->getPluginConfig('customerIdTest', $salesChannel);
    }

    public function getCardFormHolderNameBehavior(SalesChannelEntity $salesChannel): string
    {
        $cardFormHolderNameBehavior = $this->getPluginConfig('paymentDataEntryFormCardHolderNameBehavior', $salesChannel);

        if ($cardFormHolderNameBehavior === 'MANDATORY') {
            return $cardFormHolderNameBehavior;
        }

        return 'NONE';
    }

    /**
     * @throws \Exception
     */
    public function fetchStoredCustomerPaymentDataByPaymentMethodId(
        string $paymentMethodId,
        ?string $customerId,
        Context $context
    ): array {
        if (!$customerId) {
            return [];
        }

        $storedPaymentData = [];

        $orderTransactions = $this->orderTransactionRepository->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customerId))
                ->addFilter(new EqualsFilter('paymentMethodId', $paymentMethodId))
                ->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [
                    new EqualsFilter('customFields.' . WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_SCD_ALIAS, null)
                ]))
                ->addFilter(new RangeFilter('createdAt', [
                    RangeFilter::GTE => (new \DateTime('-999 days', new \DateTimeZone('UTC')))->format('Y-m-d')
                ]))
                ->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING)),
            $context
        );

        foreach ($orderTransactions->getEntities() as $orderTransactionEntity) {
            /** @var $orderTransactionEntity OrderTransactionEntity */
            $orderTransactionCustomFields = $orderTransactionEntity->getCustomFields() ?? [];
            if (!$orderTransactionCustomFields) {
                continue;
            }

            $scdAlias = $orderTransactionCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_SCD_ALIAS] ?? null;
            if (!$scdAlias || !is_string($scdAlias) || isset($storedPaymentData[$scdAlias])) {
                continue;
            }

            $storedPaymentData[$scdAlias] = [
                'scdAlias' => $orderTransactionCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_SCD_ALIAS],
                'name' => $orderTransactionCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_NAME] ?? null,
                'displayText' => $orderTransactionCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_DISPLAY_TEXT] ?? null,
                'method' => $orderTransactionCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_METHOD] ?? null,
                'transactionId' => $orderTransactionEntity->getId(),
            ];
        }

        return $storedPaymentData;
    }

    /**
     * @throws \Exception
     */
    protected function generateNotifyToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    protected function persistSaferpayToken(
        string $transactionId,
        string $saferpayToken,
        string $tokenType,
        string $notifyToken,
        string $redirectUrl,
        string $redirectUrlHandling,
        string $returnUrl,
        Context $context
    ): void {
        $orderTransactionEntity = $this->orderTransactionRepository->search(
            new Criteria([$transactionId]),
            $context
        )->first();

        if (!$orderTransactionEntity instanceof OrderTransactionEntity) {
            throw new \RuntimeException(
                'Can not find order transaction with ID ' . $transactionId,
                1666093024
            );
        }

        $context->scope(
            Context::SYSTEM_SCOPE,
            function(Context $context)
                use($orderTransactionEntity, $saferpayToken, $tokenType, $notifyToken, $redirectUrl, $redirectUrlHandling, $returnUrl)
            {
                $this->orderTransactionRepository->update(
                    [
                        [
                            'id' => $orderTransactionEntity->getId(),
                            'stateId' => $orderTransactionEntity->getStateId(),
                            'customFields' => [
                                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_TOKEN => $saferpayToken,
                                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_TOKEN_TYPE => $tokenType,
                                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_NOTIFY_TOKEN => $notifyToken,
                                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LAST_ERROR => '',
                                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_REDIRECT_URL => $redirectUrl,
                                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_REDIRECT_URL_HANDLING => $redirectUrlHandling,
                                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_RETURN_URL => $returnUrl
                            ],
                        ]
                    ],
                    $context
                );
            }
        );
    }

    protected function persistSaferpayTransactionData(
        OrderTransactionEntity $orderTransactionEntity,
        string $saferpayTransactionId,
        string $saferpayTransactionStatus,
        ?string $saferpayPaymentMethod,
        ?string $saferpayCaptureId,
        ?string $saferpayPaymentAlias,
        ?string $saferpayPaymentName,
        ?string $saferpayPaymentDisplayText,
        Context $context
    ): void {
        $transactionData = [
            'id' => $orderTransactionEntity->getId(),
            'stateId' => $orderTransactionEntity->getStateId(),
            'customFields' => [
                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_TRANSACTION_ID => $saferpayTransactionId,
                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_TRANSACTION_STATUS => $saferpayTransactionStatus,
                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LAST_ERROR => '',
            ],
        ];

        if (is_string($saferpayPaymentMethod) && $saferpayPaymentMethod) {
            $transactionData['customFields'][WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_METHOD] = $saferpayPaymentMethod;
        }

        if (is_string($saferpayCaptureId) && $saferpayCaptureId) {
            $transactionData['customFields'][WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_CAPTURE_ID] = $saferpayCaptureId;
        }

        if (is_string($saferpayPaymentAlias) && $saferpayPaymentAlias) {
            $transactionData['customFields'][WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_SCD_ALIAS] = $saferpayPaymentAlias;
        }

        if (is_string($saferpayPaymentName) && $saferpayPaymentName) {
            $transactionData['customFields'][WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_NAME] = $saferpayPaymentName;
        }

        if (is_string($saferpayPaymentDisplayText) && $saferpayPaymentDisplayText) {
            $transactionData['customFields'][WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_DISPLAY_TEXT] = $saferpayPaymentDisplayText;
        }

        $context->scope(
            Context::SYSTEM_SCOPE,
            function(Context $context) use($orderTransactionEntity, $transactionData) {
                $this->orderTransactionRepository->update(
                    [
                        $transactionData
                    ],
                    $context
                );
            }
        );

        $transactionCustomFields = $orderTransactionEntity->getCustomFields() ?: [];
        $transactionCustomFields = array_merge($transactionCustomFields, $transactionData['customFields']);
        $orderTransactionEntity->setCustomFields($transactionCustomFields);
    }

    protected function persistSaferpayCaptureData(
        string $captureId,
        ?string $saferpayCaptureId,
        Context $context
    ): void {
        if (!$saferpayCaptureId) {
            return;
        }

        $context->scope(
            Context::SYSTEM_SCOPE,
            function(Context $context) use($captureId, $saferpayCaptureId) {
                $captureData = [
                    'id' => $captureId,
                    'externalReference' => $saferpayCaptureId,
                ];

                $this->orderTransactionCaptureRepository->update(
                    [
                        $captureData
                    ],
                    $context
                );
            }
        );
    }

    protected function persistSaferpayRefundData(
        string $refundId,
        string $saferpayRefundTransactionId,
        ?string $saferpayCaptureId,
        Context $context
    ): void {
        $context->scope(
            Context::SYSTEM_SCOPE,
            function(Context $context) use($refundId, $saferpayRefundTransactionId, $saferpayCaptureId) {
                $refundData = [
                    'id' => $refundId,
                    'externalReference' => $saferpayRefundTransactionId,
                ];

                if ($saferpayCaptureId) {
                    $refundData['customFields'][WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_CAPTURE_ID] = $saferpayCaptureId;
                }

                $this->orderTransactionCaptureRefundRepository->update(
                    [
                        $refundData
                    ],
                    $context
                );
            }
        );
    }

    protected function persistSaferpayError(
        string $transactionId,
        \Exception $exception,
        Context $context
    ): void {
        $orderTransactionEntity = $this->orderTransactionRepository->search(
            new Criteria([$transactionId]),
            $context
        )->first();

        if (!$orderTransactionEntity instanceof OrderTransactionEntity) {
            return;
        }

        $context->scope(
            Context::SYSTEM_SCOPE,
            function(Context $context) use($orderTransactionEntity, $exception) {
                $this->orderTransactionRepository->update(
                    [
                        [
                            'id' => $orderTransactionEntity->getId(),
                            'stateId' => $orderTransactionEntity->getStateId(),
                            'customFields' => [
                                WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LAST_ERROR => '#' . $exception->getCode() . ': ' . $exception->getMessage()
                            ],
                        ]
                    ],
                    $context
                );
            }
        );
    }

    protected function createSaferpayPaymentApiClientForSalesChannel(SalesChannelEntity $salesChannel): SaferpayPaymentApiClient
    {
        $liveMode = $this->isLiveMode($salesChannel);

        if ($liveMode) {
            $terminalId = $this->getPluginConfig('terminalIdLive', $salesChannel);
            $username = $this->getPluginConfig('usernameLive', $salesChannel);
            $password = $this->getPluginConfig('passwordLive', $salesChannel);
        } else {
            $terminalId = $this->getPluginConfig('terminalIdTest', $salesChannel);
            $username = $this->getPluginConfig('usernameTest', $salesChannel);
            $password = $this->getPluginConfig('passwordTest', $salesChannel);
        }

        return new SaferpayPaymentApiClient(
            $this->getSaferpayCustomerId($salesChannel),
            $terminalId,
            $username,
            $password,
            $this->buildShopInfo(),
            !$liveMode
        );
    }

    protected function buildPaymentDescription(OrderEntity $order, SalesChannelEntity $salesChannel): string
    {
        return $salesChannel->getName()
                . "\n\n"
                . $this->translator->trans('account.orderNumber')
                . ' '
                . $order->getOrderNumber();
    }

    /**
     * @return string[]
     */
    protected function buildMerchantEmails(SalesChannelEntity $salesChannel): array
    {
        $merchantEmailsList = $this->getPluginConfig('merchantEmails', $salesChannel);
        if (!$merchantEmailsList) {
            return [];
        }

        $merchantEmails = [];

        foreach (explode(',', $merchantEmailsList) as $merchantEmail) {
            $merchantEmail = trim($merchantEmail);
            if ($merchantEmail) {
                $merchantEmails[] = $merchantEmail;
            }
        }

        return $merchantEmails;
    }

    protected function buildFinalizeUrl(
        OrderTransactionEntity $orderTransaction,
        string $returnUrl,
        string $redirectUrlHandling
    ): string {
        if ($redirectUrlHandling === WorldlineSaferpay::REDIRECT_URL_HANDLING_IFRAME) {
            return $this->router->generate(
                'frontend.worldline-saferpay.transaction.finalize',
                [
                    'transactionId' => $orderTransaction->getId(),
                    'returnUrlHash' => hash('sha256', $returnUrl)
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        return $returnUrl;
    }

    protected function buildPayUrl(OrderTransactionEntity $orderTransaction): string
    {
        return $this->router->generate(
            'frontend.worldline-saferpay.transaction.pay',
            [
                'transactionId' => $orderTransaction->getId()
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    protected function buildSuccessNotifyUrl(OrderTransactionEntity $orderTransaction, string $notifyToken): string
    {
        return $this->router->generate(
            'api.worldline-saferpay.notify.success',
            [
                'transactionId' => $orderTransaction->getId(),
                'notifyToken' => $notifyToken
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    protected function buildFailNotifyUrl(OrderTransactionEntity $orderTransaction, string $notifyToken): string
    {
        return $this->router->generate(
            'api.worldline-saferpay.notify.fail',
            [
                'transactionId' => $orderTransaction->getId(),
                'notifyToken' => $notifyToken
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    protected function getPluginConfig(string $key, SalesChannelEntity $salesChannel): string
    {
        $configValue = $this->systemConfigService->get('WorldlineSaferpay.config.' . $key, $salesChannel->getId()) ?: '';
        return trim((string) $configValue);
    }

    protected function getPaymentMethodCustomFields(PaymentMethodEntity $paymentMethod, Context $context): array
    {
        $customFields = [];

        if ($context->getLanguageId() !== Defaults::LANGUAGE_SYSTEM) {
            $paymentMethodDefaultTranslation = $this->paymentMethodTranslationRepository->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('paymentMethodId', $paymentMethod->getId()))
                    ->addFilter(new EqualsFilter('languageId', Defaults::LANGUAGE_SYSTEM)),
                $context
            )->first();

            if ($paymentMethodDefaultTranslation instanceof PaymentMethodTranslationEntity) {
                $customFields = $paymentMethodDefaultTranslation->getCustomFields();
            }
        }

        $actualCustomFields = $paymentMethod->getCustomFields();
        if (is_array($actualCustomFields)) {
            foreach ($actualCustomFields as $key => $value) {
                if (is_string($value)) {
                    $value = trim($value);
                }

                if (!empty($value)) {
                    $customFields[$key] = $value;
                }
            }
        }

        return $customFields;
    }

    protected function resolveOrderItems(OrderEntity $order): array
    {
        $orderItems = [];

        foreach ($order->getNestedLineItems() as $lineItem) {
            $type = $this->resolveOrderItemTypeFromLineItem($lineItem);
            if (!$type) {
                continue;
            }

            /** @var ?CalculatedTax $calculatedItemTax */
            $calculatedItemTax = $lineItem->getPrice()->getCalculatedTaxes()->first();

            $orderItem = [
                'Type' => $type,
                'Quantity' => $lineItem->getQuantity(),
                'Name' => $lineItem->getLabel(),
                'TaxRate' => $calculatedItemTax ? intval($calculatedItemTax->getTaxRate() * 100) : 0
            ];

            if ($type === SaferpayPaymentApiClient::ORDER_ITEM_TYPE_DISCOUNT || $type == SaferpayPaymentApiClient::ORDER_ITEM_TYPE_GIFTCARD) {
                $orderItem['DiscountAmount'] = $this->amountToMinorUnit((float) abs($lineItem->getUnitPrice()));
                $orderItem['UnitPrice'] = 0;
                $orderItem['TaxAmount'] = $calculatedItemTax ? $this->amountToMinorUnit((float) abs($calculatedItemTax->getTax())) : 0;
            } else {
                $orderItem['UnitPrice'] = $this->amountToMinorUnit($lineItem->getUnitPrice());
                $orderItem['TaxAmount'] = $calculatedItemTax ? $this->amountToMinorUnit($calculatedItemTax->getTax()) : 0;
            }

            $orderItems[] = $orderItem;
        }

        if ($order->getShippingTotal() > 0) {
            /** @var ?CalculatedTax $calculatedShippingTax */
            $calculatedShippingTax = $order->getShippingCosts()->getCalculatedTaxes()->first();

            $orderItems[] = [
                'Type' => SaferpayPaymentApiClient::ORDER_ITEM_TYPE_SHIPPINGFEE,
                'Quantity' => 1,
                'Name' => $this->translator->trans('document.lineItems.shippingCosts'),
                'TaxRate' => $calculatedShippingTax ? intval($calculatedShippingTax->getTaxRate() * 100) : 0,
                'TaxAmount' => $calculatedShippingTax ? $this->amountToMinorUnit($calculatedShippingTax->getTax()) : 0,
                'UnitPrice' => $this->amountToMinorUnit($order->getShippingCosts()->getTotalPrice())
            ];
        }

        return $orderItems;
    }

    protected function resolveOrderItemTypeFromLineItem(OrderLineItemEntity $lineItem): string
    {
        if (!$lineItem->getPrice() || $lineItem->getPrice()->getTotalPrice() === 0.0) {
            return '';
        }

        if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE) {
            if (method_exists($lineItem, 'getStates') && in_array('is-download', $lineItem->getStates())) {
                return SaferpayPaymentApiClient::ORDER_ITEM_TYPE_DIGITAL;
            }

            return SaferpayPaymentApiClient::ORDER_ITEM_TYPE_PHYSICAL;
        }

        if ($lineItem->getType() === LineItem::CREDIT_LINE_ITEM_TYPE) {
            return SaferpayPaymentApiClient::ORDER_ITEM_TYPE_GIFTCARD;
        }

        if (
            $lineItem->getType() === LineItem::PROMOTION_LINE_ITEM_TYPE
            || $lineItem->getType() === LineItem::DISCOUNT_LINE_ITEM
        ) {
            return SaferpayPaymentApiClient::ORDER_ITEM_TYPE_DISCOUNT;
        }

        if ($lineItem->getType() === LineItem::CUSTOM_LINE_ITEM_TYPE) {
            if ($lineItem->getPrice()->getTotalPrice() > 0) {
                return SaferpayPaymentApiClient::ORDER_ITEM_TYPE_SURCHARGE;
            } else {
                return SaferpayPaymentApiClient::ORDER_ITEM_TYPE_DISCOUNT;
            }
        }

        $types = [];

        if ($lineItem->getType() === LineItem::CONTAINER_LINE_ITEM) {
            foreach ($lineItem->getChildren() as $lineItemChild) {
                $types[] = $this->resolveOrderItemTypeFromLineItem($lineItemChild);
            }
        }

        $types = array_unique($types);
        if (count($types) === 1) {
            return $types[0];
        }

        foreach ($types as $type) {
            if ($type === SaferpayPaymentApiClient::ORDER_ITEM_TYPE_PHYSICAL) {
                return $type;
            }

            if ($type === SaferpayPaymentApiClient::ORDER_ITEM_TYPE_DIGITAL) {
                return $type;
            }
        }

        if ($lineItem->getPrice()->getTotalPrice() > 0) {
            return SaferpayPaymentApiClient::ORDER_ITEM_TYPE_SURCHARGE;
        } else {
            return SaferpayPaymentApiClient::ORDER_ITEM_TYPE_DISCOUNT;
        }
    }

    protected function resolveOrderDeliveryAddress(OrderEntity $order): OrderAddressEntity
    {
        $deliveryAddress = $order->getBillingAddress();

        $addresses = $order->getAddresses();
        if ($addresses) {
            foreach ($order->getAddresses() as $address) {
                /** @var $address OrderAddressEntity */
                if ($address->getId() === $order->getBillingAddressId()) {
                    continue;
                }

                $deliveryAddress = $address;
                break;
            }
        }

        return $deliveryAddress;
    }

    protected function resolveCountrySubdivisionCodeFromAddress(OrderAddressEntity $address): string
    {
        $countrySubdivisionCode = '';

        if ($address->getCountryState()) {
            $stateCode = $address->getCountryState()->getShortCode();
            if ($stateCode) {
                $stateCodeSegments = explode('-', $stateCode, 2);
                if (isset($stateCodeSegments[1]) && $stateCodeSegments[1]) {
                    $countrySubdivisionCode = $stateCodeSegments[1];
                }
            }
        }

        return $countrySubdivisionCode;
    }

    protected function resolveGenderFromAddress(OrderAddressEntity $address): string
    {
        /** @noinspection DuplicatedCode */
        if ($address->getCompany()) {
            return 'COMPANY';
        }

        if ($address->getSalutation()) {
            if ($address->getSalutation()->getSalutationKey() === 'mr') {
                return 'MALE';
            }

            if ($address->getSalutation()->getSalutationKey() === 'mrs') {
                return 'FEMALE';
            }

            if ($address->getSalutation()->getSalutationKey() !== 'undefined' && $address->getSalutation()->getSalutationKey() !== 'not_specified') {
                return 'DIVERSE';
            }
        }

        return '';
    }

    protected function resolveGenderFromCustomer(CustomerEntity $customer): string
    {
        /** @noinspection DuplicatedCode */
        if ($customer->getCompany()) {
            return 'COMPANY';
        }

        if ($customer->getSalutation()) {
            if ($customer->getSalutation()->getSalutationKey() === 'mr') {
                return 'MALE';
            }

            if ($customer->getSalutation()->getSalutationKey() === 'mrs') {
                return 'FEMALE';
            }

            if ($customer->getSalutation()->getSalutationKey() !== 'undefined' && $customer->getSalutation()->getSalutationKey() !== 'not_specified') {
                return 'DIVERSE';
            }
        }

        return '';
    }

    protected function supportsLiabilityShifting($paymentMeans): bool
    {
        if(!is_array($paymentMeans)) {
            return false;
        }

        foreach($paymentMeans as $paymentMean) {
            if(in_array($paymentMean, WorldlineSaferpay::LIABILITY_SHIFT_SUPPORTED_PAYMENT_METHODS)) {
                return true;
            }
        }

        return false;
    }

    protected function amountToMinorUnit(float $amount): int
    {
        return intval(round($amount * 100));
    }

    private function shouldThrowException(\Exception $exception, Context $context): bool
    {
        $throwException = true;

        if ($context->getSource() instanceof AdminApiSource) {
            $throwException = !$exception instanceof ApiRequestException
                || $exception->getBehaviour() !== ApiRequestException::BEHAVIOR_DO_NOT_RETRY; // Throw retryable exceptions even in AdminApi
        }

        return $throwException;
    }

    private function fetchOrCreateTransactionCapture(
        string $transactionId,
        CalculatedPrice $amount,
        string $saferpayTransactionId,
        Context $context
    ): string {
        $transactionCaptureId = Uuid::uuid5(self::SAFERPAY_TRANSACTION_ID_PREFIX, $saferpayTransactionId)
            ->getHex()
            ->toString();

        $existingId = $this->orderTransactionCaptureRepository->searchIds(
            new Criteria([$transactionCaptureId]),
            $context
        )->firstId();

        if ($existingId) {
            return $existingId;
        }

        $this->orderTransactionCaptureRepository->create(
            [
                [
                    'id' => $transactionCaptureId,
                    'orderTransactionId' => $transactionId,
                    'amount' => $amount,
                    'stateId' => $this->fetchOrderTransactionCapturePendingStateId($context)
                ]
            ],
            $context
        );

        return $transactionCaptureId;
    }

    private function fetchOrderTransactionCapturePendingStateId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria
            ->setLimit(1)
            ->addFilter(new EqualsFilter('technicalName', OrderTransactionCaptureStates::STATE_PENDING))
            ->addFilter(new EqualsFilter('stateMachine.technicalName', OrderTransactionCaptureStates::STATE_MACHINE));

        $id = $this->stateMachineStateRepository->searchIds($criteria, $context)->firstId();

        if (!$id) {
            throw new \RuntimeException(
                'Failed to fetch order transaction capture pending state',
                1712826936
            );
        }

        return $id;
    }

    private function getShopwareVersion(): string
    {
        /** @noinspection PhpMultipleClassDeclarationsInspection */
        return (string)\Composer\InstalledVersions::getVersion('shopware/core');
    }

    private function isLiveMode(SalesChannelEntity $salesChannel): bool
    {
        return $this->getPluginConfig('operationMode', $salesChannel) === 'live';
}

    private function withLiabilityShift(PaymentMethodEntity $paymentMethod, Context $context): bool
    {
        $paymentMethodCustomFields = $this->getPaymentMethodCustomFields($paymentMethod, $context);

        $liabilityShiftBehavior = $paymentMethodCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR] ?? '';

        $paymentMeans = $paymentMethodCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS] ?? [];
        if (!is_array($paymentMeans)) {
            $paymentMeans = [];
        }

        return $liabilityShiftBehavior === WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED
            && $this->supportsLiabilityShifting($paymentMeans);
    }

    private function fetchPaymentMethodForOrderTransaction(
        OrderTransactionEntity $orderTransaction,
        Context $context
    ): PaymentMethodEntity {
        $paymentMethod = $orderTransaction->getPaymentMethod();
        if (!$paymentMethod) {
            $paymentMethod = $this->paymentMethodRepository->search(
                new Criteria([$orderTransaction->getPaymentMethodId()]),
                $context
            )->first();
        }

        if (!$paymentMethod) {
            throw new \RuntimeException(
                'Failed to fetch payment method with ID ' . $orderTransaction->getPaymentMethodId(),
                1715951925
            );
        }

        return $paymentMethod;
    }

    private function fetchCustomerById(string $customerId, Context $context): ?CustomerEntity
    {
        return $this->customerRepository->search(new Criteria([$customerId]), $context)->first();
    }

    private function getPaymentMethodPaymentMeans(PaymentMethodEntity $paymentMethod, Context $context): array
    {
        $paymentMethodCustomFields = $this->getPaymentMethodCustomFields($paymentMethod, $context);

        $paymentMeans = $paymentMethodCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS] ?? [];
        if (!is_array($paymentMeans)) {
            $paymentMeans = [];
        }

        return $paymentMeans;
    }

    private function getPaymentMethodWallets(PaymentMethodEntity $paymentMethod, Context $context): array
    {
        $paymentMethodCustomFields = $this->getPaymentMethodCustomFields($paymentMethod, $context);

        $wallets = $paymentMethodCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS] ?? [];
        if (!is_array($wallets)) {
            $wallets = [];
        }

        return $wallets;
    }

    private function getPaymentMethodRedirectUrlHandling(PaymentMethodEntity $paymentMethod, Context $context): string
    {
        $paymentMethodCustomFields = $this->getPaymentMethodCustomFields($paymentMethod, $context);

        $redirectUrlHandling = $paymentMethodCustomFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_REDIRECT_URL_HANDLING] ?? null;

        if ($redirectUrlHandling === WorldlineSaferpay::REDIRECT_URL_HANDLING_IFRAME) {
            return WorldlineSaferpay::REDIRECT_URL_HANDLING_IFRAME;
        }

        return WorldlineSaferpay::REDIRECT_URL_HANDLING_REDIRECT;
    }

    private function buildShopInfo(): string
    {
        /** @noinspection all */
        return 'Shopware_'
            . $this->getShopwareVersion()
            . ':'
            . 'ArrabiataSolutions_'
            . WorldlineSaferpay::VERSION;
    }

    private function resolvePaymentReference(OrderEntity $order, SalesChannelEntity $salesChannel): string
    {
        $configuredPaymentReference = $this->getPluginConfig('paymentReference', $salesChannel);
        if ($configuredPaymentReference === 'ORDER_ID') {
            $orderId = $order->getId();
        } else {
            $orderId = $order->getOrderNumber();
        }

        return $orderId ?: $order->getId();
    }
}
