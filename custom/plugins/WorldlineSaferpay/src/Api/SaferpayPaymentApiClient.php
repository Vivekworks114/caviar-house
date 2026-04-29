<?php declare(strict_types=1);

namespace Worldline\Saferpay\Api;

use Shopware\Core\Framework\Uuid\Uuid;
use Worldline\Saferpay\Api\Exception\ApiRequestException;

class SaferpayPaymentApiClient
{
    const SPEC_VERSION = '1.45';

    const URI_PROD = 'https://www.saferpay.com/api';
    const URI_TEST = 'https://test.saferpay.com/api';

    const ENDPOINT_ALIAS_DELETE = '/Payment/v1/Alias/Delete';
    const ENDPOINT_PAYMENT_PAGE_INITIALIZE = '/Payment/v1/PaymentPage/Initialize';
    const ENDPOINT_PAYMENT_PAGE_ASSERT = '/Payment/v1/PaymentPage/Assert';
    const ENDPOINT_TRANSACTION_INITIALIZE = '/Payment/v1/Transaction/Initialize';
    const ENDPOINT_TRANSACTION_AUTHORIZE = '/Payment/v1/Transaction/Authorize';
    const ENDPOINT_TRANSACTION_CAPTURE = '/Payment/v1/Transaction/Capture';
    const ENDPOINT_TRANSACTION_CANCEL = '/Payment/v1/Transaction/Cancel';
    const ENDPOINT_TRANSACTION_ASSERT_CAPTURE = '/Payment/v1/Transaction/AssertCapture';
    const ENDPOINT_TRANSACTION_REFUND = '/Payment/v1/Transaction/Refund';

    const TRANSACTION_STATUS_AUTHORIZED = 'AUTHORIZED';
    const TRANSACTION_STATUS_CAPTURED = 'CAPTURED';
    const TRANSACTION_STATUS_CANCELED = 'CANCELED';
    const TRANSACTION_STATUS_PENDING = 'PENDING';

    const ORDER_ITEM_TYPE_DIGITAL = 'DIGITAL';
    const ORDER_ITEM_TYPE_PHYSICAL = 'PHYSICAL';
    const ORDER_ITEM_TYPE_GIFTCARD = 'GIFTCARD';
    const ORDER_ITEM_TYPE_DISCOUNT = 'DISCOUNT';
    const ORDER_ITEM_TYPE_SHIPPINGFEE = 'SHIPPINGFEE';
    const ORDER_ITEM_TYPE_SURCHARGE = 'SURCHARGE';

    private string $customerId;
    private string $terminalId;
    private string $username;
    private string $password;
    private string $shopInfo;
    private bool $testMode;

    public function __construct(
        string $customerId,
        string $terminalId,
        string $username,
        string $password,
        string $shopInfo,
        bool $testMode
    ) {
        $this->customerId = $customerId;
        $this->terminalId = $terminalId;
        $this->username = $username;
        $this->password = $password;
        $this->shopInfo = $shopInfo;
        $this->testMode = $testMode;
    }

    /**
     * @throws ApiRequestException
     */
    public function initializePaymentPage(
        string $orderId,
        array $paymentMethods,
        array $wallets,
        int $totalAmount,
        string $currencyCode,
        string $customerEmail,
        string $customerId,
        string $customerDateOfBirth,
        string $billingFirstname,
        string $billingLastname,
        string $billingCompany,
        string $billingGender,
        string $billingStreet,
        string $billingStreet2,
        string $billingZip,
        string $billingCity,
        string $billingCountryCode,
        string $billingPhone,
        string $billingVatNumber,
        string $billingCountrySubdivisionCode,
        string $deliveryFirstName,
        string $deliveryLastName,
        string $deliveryCompany,
        string $deliveryStreet,
        string $deliveryZip,
        string $deliveryCity,
        string $deliveryCountryCode,
        string $deliveryCountrySubdivisionCode,
        array $orderItems,
        string $returnUrl,
        string $successNotifyUrl,
        string $failNotifyUrl,
        string $description,
        string $cardFormHolderName,
        array $merchantEmails,
        bool $withLiabilityShift,
        bool $enableCustomerNotification,
        string $configSet,
        bool $force3ds,
        bool $savePaymentData,
    ): object {
        $payload = $this->createInitializePaymentPayload(
            orderId: $orderId,
            totalAmount: $totalAmount,
            currencyCode: $currencyCode,
            customerEmail: $customerEmail,
            customerId: $customerId,
            customerDateOfBirth: $customerDateOfBirth,
            billingFirstname: $billingFirstname,
            billingLastname: $billingLastname,
            billingCompany: $billingCompany,
            billingGender: $billingGender,
            billingStreet: $billingStreet,
            billingStreet2: $billingStreet2,
            billingZip: $billingZip,
            billingCity: $billingCity,
            billingCountryCode: $billingCountryCode,
            billingPhone: $billingPhone,
            billingVatNumber: $billingVatNumber,
            billingCountrySubdivisionCode: $billingCountrySubdivisionCode,
            deliveryFirstName: $deliveryFirstName,
            deliveryLastName: $deliveryLastName,
            deliveryCompany: $deliveryCompany,
            deliveryStreet: $deliveryStreet,
            deliveryZip: $deliveryZip,
            deliveryCity: $deliveryCity,
            deliveryCountryCode: $deliveryCountryCode,
            deliveryCountrySubdivisionCode: $deliveryCountrySubdivisionCode,
            orderItems: $orderItems,
            returnUrl: $returnUrl,
            description: $description,
            configSet: $configSet,
            force3ds: $force3ds,
        );

        $payload['PaymentMethods'] = $paymentMethods;
        $payload['Wallets'] = $wallets;

        $payload['BillingAddressForm'] = [
            'AddressSource' => 'NONE'
        ];

        $payload['DeliveryAddressForm'] = [
            'AddressSource' => 'NONE'
        ];

        $payload['Notification'] = [
            'MerchantEmails' => $merchantEmails,
            'SuccessNotifyUrl' => $successNotifyUrl,
            'FailNotifyUrl' => $failNotifyUrl
        ];

        if ($enableCustomerNotification) {
            $payload['Notification']['PayerEmail'] = $customerEmail;
        }

        $payload['Condition'] = $withLiabilityShift ? 'THREE_DS_AUTHENTICATION_SUCCESSFUL_OR_ATTEMPTED' : 'NONE';

        if ($cardFormHolderName) {
            $payload['CardForm'] = [
                'HolderName' => $cardFormHolderName
            ];
        }

        if ($savePaymentData) {
            $payload['RegisterAlias']['IdGenerator'] = 'RANDOM_UNIQUE';
        }

        $response = $this->sendRequest(
            static::ENDPOINT_PAYMENT_PAGE_INITIALIZE,
            $payload
        );

        if (
            !isset($response->RedirectUrl)
            || !is_string($response->RedirectUrl)
            || trim($response->RedirectUrl) === ''
        ) {
            throw new \RuntimeException(
                'Initialize payment page response has no valid property "RedirectUrl"',
                1656597985
            );
        }

        if (
            !isset($response->Token)
            || !is_string($response->Token)
            || trim($response->Token) === ''
        ) {
            throw new \RuntimeException(
                'Initialize payment page response has no valid property "Token"',
                1657111976
            );
        }

        return $response;
    }

    /**
     * @throws ApiRequestException
     */
    public function assertPaymentPage(string $token): object
    {
        $response = $this->sendRequest(
            static::ENDPOINT_PAYMENT_PAGE_ASSERT,
            [
                'Token' => $token
            ]
        );

        if (!isset($response->Transaction) || !is_object($response->Transaction)) {
            throw new \RuntimeException(
                'Assert payment page response has no valid property "Transaction"',
                1657113570
            );
        }

        if (
            !isset($response->Transaction->Status)
            || !is_string($response->Transaction->Status)
            || trim($response->Transaction->Status) === ''
        ) {
            throw new \RuntimeException(
                'Assert payment page response has no valid property "Transaction.Status"',
                1657113574
            );
        }

        if (
            !isset($response->Transaction->Id)
            || !is_string($response->Transaction->Id)
            || trim($response->Transaction->Id) === ''
        ) {
            throw new \RuntimeException(
                'Assert payment page response has no valid property "Transaction.Id"',
                1657113579
            );
        }

        if (
            !isset($response->Transaction->OrderId)
            || !is_string($response->Transaction->OrderId)
            || trim($response->Transaction->OrderId) === ''
        ) {
            throw new \RuntimeException(
                'Assert payment page response has no valid property "Transaction.OrderId"',
                1657210058
            );
        }

        if (!isset($response->PaymentMeans) || !is_object($response->PaymentMeans)) {
            throw new \RuntimeException(
                'Assert payment page response has no valid property "PaymentMeans"',
                1688639088
            );
        }

        if (!isset($response->PaymentMeans->Brand) || !is_object($response->PaymentMeans->Brand)) {
            throw new \RuntimeException(
                'Assert payment page response has no valid property "PaymentMeans.Brand"',
                1688639120
            );
        }

        if (
            isset($response->Liability)
            && (!is_object($response->Liability)
                || !isset($response->Liability->LiabilityShift)
                || !is_bool($response->Liability->LiabilityShift))
        ) {
            throw new \RuntimeException(
                'Assert payment page response has property "Liability" but no valid property "Liability.LiabilityShift"',
                1662114981
            );
        }

        return $response;
    }

    /**
     * @throws ApiRequestException
     */
    public function initializeTransaction(
        string $orderId,
        int $totalAmount,
        string $currencyCode,
        string $customerEmail,
        string $customerId,
        string $customerDateOfBirth,
        string $billingFirstname,
        string $billingLastname,
        string $billingCompany,
        string $billingGender,
        string $billingStreet,
        string $billingStreet2,
        string $billingZip,
        string $billingCity,
        string $billingCountryCode,
        string $billingPhone,
        string $billingVatNumber,
        string $billingCountrySubdivisionCode,
        string $deliveryFirstName,
        string $deliveryLastName,
        string $deliveryCompany,
        string $deliveryStreet,
        string $deliveryZip,
        string $deliveryCity,
        string $deliveryCountryCode,
        string $deliveryCountrySubdivisionCode,
        array $orderItems,
        string $returnUrl,
        string $successNotifyUrl,
        string $failNotifyUrl,
        string $description,
        string $saferpayFieldsToken,
        string $configSet,
        bool $force3ds,
        ?string $paymentMeansAlias = null,
    ): object {
        $payload = $this->createInitializePaymentPayload(
            orderId: $orderId,
            totalAmount: $totalAmount,
            currencyCode: $currencyCode,
            customerEmail: $customerEmail,
            customerId: $customerId,
            customerDateOfBirth: $customerDateOfBirth,
            billingFirstname: $billingFirstname,
            billingLastname: $billingLastname,
            billingCompany: $billingCompany,
            billingGender: $billingGender,
            billingStreet: $billingStreet,
            billingStreet2: $billingStreet2,
            billingZip: $billingZip,
            billingCity: $billingCity,
            billingCountryCode: $billingCountryCode,
            billingPhone: $billingPhone,
            billingVatNumber: $billingVatNumber,
            billingCountrySubdivisionCode: $billingCountrySubdivisionCode,
            deliveryFirstName: $deliveryFirstName,
            deliveryLastName: $deliveryLastName,
            deliveryCompany: $deliveryCompany,
            deliveryStreet: $deliveryStreet,
            deliveryZip: $deliveryZip,
            deliveryCity: $deliveryCity,
            deliveryCountryCode: $deliveryCountryCode,
            deliveryCountrySubdivisionCode: $deliveryCountrySubdivisionCode,
            orderItems: $orderItems,
            returnUrl: $returnUrl,
            description: $description,
            configSet: $configSet,
            force3ds: $force3ds,
        );

        if ($saferpayFieldsToken) {
            $payload['PaymentMeans']['SaferpayFields']['Token'] = $saferpayFieldsToken;
        }

        if ($paymentMeansAlias) {
            $payload['PaymentMeans']['Alias']['Id'] = $paymentMeansAlias;
        }

        $payload['RedirectNotifyUrls'] = [
            'Success' => $successNotifyUrl,
            'Fail' => $failNotifyUrl
        ];

        $response = $this->sendRequest(
            static::ENDPOINT_TRANSACTION_INITIALIZE,
            $payload
        );

        if (!isset($response->RedirectRequired) || !is_bool($response->RedirectRequired)) {
            throw new \RuntimeException(
                'Initialize transaction response has no valid property "RedirectRequired"',
                1715873520
            );
        }

        if (
            $response->RedirectRequired
            && (
                !isset($response->Redirect)
                || !is_object($response->Redirect)
                || !isset($response->Redirect->RedirectUrl)
                || !is_string($response->Redirect->RedirectUrl)
                || trim($response->Redirect->RedirectUrl) === ''
            )
        ) {
            throw new \RuntimeException(
                'Initialize transaction response has no valid property "Redirect.RedirectUrl"',
                1715873669
            );
        }

        if (
            !isset($response->Token)
            || !is_string($response->Token)
            || trim($response->Token) === ''
        ) {
            throw new \RuntimeException(
                'Initialize transaction response has no valid property "Token"',
                1715869233
            );
        }

        return $response;
    }

    /**
     * @throws ApiRequestException
     */
    public function authorizeTransaction(string $token, bool $withLiabilityShift, bool $savePaymentData): object
    {
        $payload = [
            'Token' => $token,
            'Condition' => $withLiabilityShift ? 'THREE_DS_AUTHENTICATION_SUCCESSFUL_OR_ATTEMPTED' : 'NONE'
        ];

        if ($savePaymentData) {
            $payload['RegisterAlias']['IdGenerator'] = 'RANDOM_UNIQUE';
        }

        $response = $this->sendRequest(static::ENDPOINT_TRANSACTION_AUTHORIZE, $payload);

        if (!isset($response->Transaction) || !is_object($response->Transaction)) {
            throw new \RuntimeException(
                'Authorize transaction response has no valid property "Transaction"',
                1715946934
            );
        }

        if (
            !isset($response->Transaction->Status)
            || !is_string($response->Transaction->Status)
            || trim($response->Transaction->Status) === ''
        ) {
            throw new \RuntimeException(
                'Authorize transaction response has no valid property "Transaction.Status"',
                1715946936
            );
        }

        if (
            !isset($response->Transaction->Id)
            || !is_string($response->Transaction->Id)
            || trim($response->Transaction->Id) === ''
        ) {
            throw new \RuntimeException(
                'Authorize transaction response has no valid property "Transaction.Id"',
                1715946940
            );
        }

        if (
            !isset($response->Transaction->OrderId)
            || !is_string($response->Transaction->OrderId)
            || trim($response->Transaction->OrderId) === ''
        ) {
            throw new \RuntimeException(
                'Authorize transaction response has no valid property "Transaction.OrderId"',
                1715946945
            );
        }

        if (!isset($response->PaymentMeans) || !is_object($response->PaymentMeans)) {
            throw new \RuntimeException(
                'Authorize transaction response has no valid property "PaymentMeans"',
                1715946821
            );
        }

        if (!isset($response->PaymentMeans->Brand) || !is_object($response->PaymentMeans->Brand)) {
            throw new \RuntimeException(
                'Authorize transaction response has no valid property "PaymentMeans.Brand"',
                1715946861
            );
        }

        if (
            isset($response->Liability)
            && (!is_object($response->Liability)
                || !isset($response->Liability->LiabilityShift)
                || !is_bool($response->Liability->LiabilityShift))
        ) {
            throw new \RuntimeException(
                'Authorize transaction response has property "Liability" but no valid property "Liability.LiabilityShift"',
                1715946893
            );
        }

        return $response;
    }

    /**
     * @throws ApiRequestException
     */
    public function captureTransaction(
        string $transactionId,
        string $pendingNotifyUrl,
        array $merchantEmails
    ): object {
        $response = $this->sendRequest(
            static::ENDPOINT_TRANSACTION_CAPTURE,
            [
                'TransactionReference' => [
                    'TransactionId' => $transactionId
                ],
                'PendingNotification' => [
                    'NotifyUrl' => $pendingNotifyUrl,
                    'MerchantEmails' => $merchantEmails,
                ]
            ]
        );

        if (!isset($response->CaptureId) || !is_string($response->CaptureId) || trim($response->CaptureId) === '') {
            throw new \RuntimeException(
                'Capture transaction response has no valid property "CaptureId"',
                1657210376
            );
        }

        if (!isset($response->Status) || !is_string($response->Status) || trim($response->Status) === '') {
            throw new \RuntimeException(
                'Capture transaction response has no valid property "Status"',
                1657210388
            );
        }

        return $response;
    }

    /**
     * @throws ApiRequestException
     */
    public function cancelTransaction(string $transactionId): void
    {
        $this->sendRequest(
            static::ENDPOINT_TRANSACTION_CANCEL,
            [
                'TransactionReference' => [
                    'TransactionId' => $transactionId
                ]
            ]
        );
    }

    /**
     * @throws ApiRequestException
     */
    public function assertCapture(string $captureId): object
    {
        $response = $this->sendRequest(
            static::ENDPOINT_TRANSACTION_ASSERT_CAPTURE,
            [
                'CaptureReference' => [
                    'CaptureId' => $captureId
                ]
            ]
        );

        if (
            !isset($response->Status)
            || !is_string($response->Status)
            || trim($response->Status) === ''
        ) {
            throw new \RuntimeException(
                'Assert transaction capture response has no valid property "Status"',
                1657646519
            );
        }

        return $response;
    }

    /**
     * @throws ApiRequestException
     */
    public function deletePaymentAlias(string $aliasId): object
    {
        return $this->sendRequest(
            static::ENDPOINT_ALIAS_DELETE,
            [
                'AliasId' => $aliasId
            ]
        );
    }

    /**
     * @throws ApiRequestException
     */
    public function refund(
        string $orderId,
        string $captureId,
        int $amount,
        string $currencyCode,
        string $description
    ): object {
        $payload = [
            'Refund' => [
                'Amount' => [
                    'Value' => (string)$amount,
                    'CurrencyCode' => $currencyCode
                ],
                'OrderId' => $orderId,
                'RestrictRefundAmountToCapturedAmount' => true,
            ],
            'CaptureReference' => [
                'CaptureId' => $captureId
            ]
        ];

        if (trim($description) !== '') {
            $payload['Refund']['Description'] = $description;
        }

        $response = $this->sendRequest(static::ENDPOINT_TRANSACTION_REFUND, $payload);

        if (!isset($response->Transaction) || !is_object($response->Transaction)) {
            throw new \RuntimeException(
                'Refund response has no valid property "Transaction"',
                1713436643
            );
        }

        if (
            !isset($response->Transaction->Status)
            || !is_string($response->Transaction->Status)
            || trim($response->Transaction->Status) === ''
        ) {
            throw new \RuntimeException(
                'Refund response has no valid property "Transaction.Status"',
                1713436646
            );
        }

        if (
            !isset($response->Transaction->Id)
            || !is_string($response->Transaction->Id)
            || trim($response->Transaction->Id) === ''
        ) {
            throw new \RuntimeException(
                'Refund response has no valid property "Transaction.Id"',
                1713436649
            );
        }

        return $response;
    }

    /**
     * @throws ApiRequestException
     */
    protected function sendRequest(
        string $endpoint,
        array $payload
    ): object {
        $allowUrlOpen = ini_get('allow_url_fopen');
        if ((is_string($allowUrlOpen) && $allowUrlOpen !== '1') || (is_bool($allowUrlOpen) && !$allowUrlOpen)) {
            throw new \RuntimeException(
                'The PHP setting "allow_url_fopen" is set to "Off", but must be "On". Please adjust your php.ini configuration file accordingly.',
                1695906694
            );
        }

        $this->addRequestHeaderToPayload($payload);

        $streamContext = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'method' => 'POST',
                'header' => 'Content-type: application/json' . "\r\n"
                    . 'Accept: application/json; charset=utf-8' . "\r\n"
                    . 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password) . "\r\n",
                'content' => json_encode($payload)
            ],
            'ssl' => [
                'verify_peer' => true,
            ]
        ]);

        $requestUri = $this->buildBaseUrl() . $endpoint;

        $responseBody = (string) file_get_contents($requestUri, false, $streamContext);

        if (
            !isset($http_response_header[0])
            || !is_string($http_response_header[0])
            || !str_contains($http_response_header[0], '200 OK')
        ) {
            throw new ApiRequestException(
                $requestUri,
                isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [],
                $responseBody,
                1656597979
            );
        }

        $responseObject = json_decode($responseBody);
        if (!is_object($responseObject)) {
            throw new \RuntimeException(
                'Failed to parse Saferpay API response body of endpoint '
                . $endpoint
                . ': '
                . $responseBody,
                1656597980
            );
        }

        return $responseObject;
    }

    protected function addRequestHeaderToPayload(array &$payload): void
    {
        $payload['RequestHeader'] = [
            'SpecVersion' => self::SPEC_VERSION,
            'CustomerId' => $this->customerId,
            'RequestId' => Uuid::randomHex(),
            'RetryIndicator' => 0,
            'ClientInfo' => [
                'ShopInfo' => $this->shopInfo
            ]
        ];
    }

    protected function buildBaseUrl(): string
    {
        if ($this->testMode) {
            return static::URI_TEST;
        }

        return static::URI_PROD;
    }

    protected function createInitializePaymentPayload(
        string $orderId,
        int $totalAmount,
        string $currencyCode,
        string $customerEmail,
        string $customerId,
        string $customerDateOfBirth,
        string $billingFirstname,
        string $billingLastname,
        string $billingCompany,
        string $billingGender,
        string $billingStreet,
        string $billingStreet2,
        string $billingZip,
        string $billingCity,
        string $billingCountryCode,
        string $billingPhone,
        string $billingVatNumber,
        string $billingCountrySubdivisionCode,
        string $deliveryFirstName,
        string $deliveryLastName,
        string $deliveryCompany,
        string $deliveryStreet,
        string $deliveryZip,
        string $deliveryCity,
        string $deliveryCountryCode,
        string $deliveryCountrySubdivisionCode,
        array  $orderItems,
        string $returnUrl,
        string $description,
        string $configSet = '',
        bool $force3ds = false
    ): array {
        $billingAddress = [
            'Email' => $customerEmail,
            'CountryCode' => $billingCountryCode
        ];

        if ($customerDateOfBirth) {
            $billingAddress['DateOfBirth'] = $customerDateOfBirth;
        }

        if ($billingFirstname) {
            $billingAddress['FirstName'] = $billingFirstname;
        }

        if ($billingLastname) {
            $billingAddress['LastName'] = $billingLastname;
        }

        if ($billingCompany) {
            $billingAddress['Company'] = $billingCompany;
        }

        if ($billingGender) {
            $billingAddress['Gender'] = $billingGender;
        }

        if ($billingStreet) {
            $billingAddress['Street'] = $billingStreet;
        }

        if ($billingStreet2) {
            $billingAddress['Street2'] = $billingStreet2;
        }

        if ($billingZip) {
            $billingAddress['Zip'] = $billingZip;
        }

        if ($billingCity) {
            $billingAddress['City'] = $billingCity;
        }

        if ($billingPhone) {
            $billingAddress['Phone'] = $billingPhone;
        }

        if ($billingVatNumber) {
            $billingAddress['VatNumber'] = $billingVatNumber;
        }

        if ($billingCountrySubdivisionCode) {
            $billingAddress['CountrySubdivisionCode'] = $billingCountrySubdivisionCode;
        }

        $deliveryAddress = [
            'Email' => $customerEmail, // Required for e-przelewy (@see https://docs.saferpay.com/home/integration-guide/payment-methods/eprzelewy),
            'CountryCode' => $deliveryCountryCode,
        ];

        if ($deliveryFirstName) {
            $deliveryAddress['FirstName'] = $deliveryFirstName;
        }

        if ($deliveryLastName) {
            $deliveryAddress['LastName'] = $deliveryLastName;
        }

        if ($deliveryCompany) {
            $deliveryAddress['Company'] = $deliveryCompany;
        }

        if ($deliveryStreet) {
            $deliveryAddress['Street'] = $deliveryStreet;
        }

        if ($deliveryZip) {
            $deliveryAddress['Zip'] = $deliveryZip;
        }

        if ($deliveryCity) {
            $deliveryAddress['City'] = $deliveryCity;
        }

        if ($deliveryCountrySubdivisionCode) {
            $deliveryAddress['CountrySubdivisionCode'] = $deliveryCountrySubdivisionCode;
        }

        $payload = [
            'TerminalId' => $this->terminalId,
            'Payment' => [
                'Amount' => [
                    'Value' => $totalAmount,
                    'CurrencyCode' => $currencyCode
                ],
                'OrderId' => $orderId,
                'Description' => $description
            ],
            'Payer' => [
                'Id' => $customerId,
                'BillingAddress' => $billingAddress,
                'DeliveryAddress' => $deliveryAddress
            ],
            'Order' => [
                'Items' => $orderItems
            ],
            'ReturnUrl' => [
                'Url' => $returnUrl
            ]
        ];

        if ($configSet) {
            $payload['ConfigSet'] = $configSet;
        }

        if ($force3ds) {
            $payload['Authentication']['ThreeDsChallenge'] = 'FORCE';
        }

        return $payload;
    }
}
