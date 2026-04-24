<?php declare(strict_types = 1);

namespace Worldline\Saferpay;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Worldline\Saferpay\Checkout\Payment\Cart\PaymentHandler\SaferpayPaymentHandler;
use Worldline\Saferpay\Installer\CustomFieldsInstaller;
use Worldline\Saferpay\Installer\PaymentMethodInstaller;

class WorldlineSaferpay extends Plugin
{
    public const VERSION = '3.1.1';

    public const SAFERPAY_PAYMENT_METHOD_ACCOUNT_TO_ACCOUNT = 'ACCOUNTTOACCOUNT';
    public const SAFERPAY_PAYMENT_METHOD_ALIPAY = 'ALIPAY';
    public const SAFERPAY_PAYMENT_METHOD_AMERICAN_EXPRESS = 'AMEX';
    public const SAFERPAY_PAYMENT_METHOD_BANCONTACT = 'BANCONTACT';
    public const SAFERPAY_PAYMENT_METHOD_BLIK = 'BLIK';
    public const SAFERPAY_PAYMENT_METHOD_BONUS_CARD = 'BONUS';
    public const SAFERPAY_PAYMENT_METHOD_DINERS_CLUB = 'DINERS';
    public const SAFERPAY_PAYMENT_METHOD_DIRECT_DEBIT = 'DIRECTDEBIT';
    /** @noinspection PhpUnused */
    public const SAFERPAY_PAYMENT_METHOD_E_PRZELEWY = 'EPRZELEWY';
    public const SAFERPAY_PAYMENT_METHOD_EPS = 'EPS';
    public const SAFERPAY_PAYMENT_METHOD_IDEAL = 'IDEAL';
    public const SAFERPAY_PAYMENT_METHOD_JCB = 'JCB';
    public const SAFERPAY_PAYMENT_METHOD_KLARNA = 'KLARNA';
    public const SAFERPAY_PAYMENT_METHOD_MAESTRO = 'MAESTRO';
    public const SAFERPAY_PAYMENT_METHOD_MASTERCARD = 'MASTERCARD';
    public const SAFERPAY_PAYMENT_METHOD_MY_ONE = 'MYONE';
    public const SAFERPAY_PAYMENT_METHOD_PAYPAL = 'PAYPAL';
    public const SAFERPAY_PAYMENT_METHOD_PAYDIREKT = 'PAYDIREKT';
    public const SAFERPAY_PAYMENT_METHOD_POST_FINANCE_CARD = 'POSTCARD';
    public const SAFERPAY_PAYMENT_METHOD_POST_FINANCE_E_FINANCE = 'POSTFINANCE';
    public const SAFERPAY_PAYMENT_METHOD_POST_FINANCE_PAY = 'POSTFINANCEPAY';
    public const SAFERPAY_PAYMENT_METHOD_SOFORT = 'SOFORT';
    public const SAFERPAY_PAYMENT_METHOD_TWINT = 'TWINT';
    public const SAFERPAY_PAYMENT_METHOD_UNIONPAY = 'UNIONPAY';
    public const SAFERPAY_PAYMENT_METHOD_VISA = 'VISA';
    public const SAFERPAY_PAYMENT_METHOD_WECHAT_PAY = 'WECHATPAY';
    public const SAFERPAY_PAYMENT_METHOD_WL_CRYPTO_PAYMENTS = 'WLCRYPTOPAYMENTS';

    public const SAFERPAY_WALLET_APPLE_PAY = 'APPLEPAY';
    public const SAFERPAY_WALLET_CLICK_TO_PAY = 'CLICKTOPAY';
    public const SAFERPAY_WALLET_GOOGLE_PAY = 'GOOGLEPAY';

    public const CUSTOM_FIELD_SAFERPAY_TRANSACTION_ID = 'worldline_saferpay_transaction_id';
    public const CUSTOM_FIELD_SAFERPAY_TRANSACTION_STATUS = 'worldline_saferpay_transaction_status';
    public const CUSTOM_FIELD_SAFERPAY_CAPTURE_ID = 'worldline_saferpay_capture_id';
    public const CUSTOM_FIELD_SAFERPAY_PAYMENT_METHOD = 'worldline_saferpay_payment_method';
    public const CUSTOM_FIELD_SAFERPAY_PAYMENT_NAME = 'worldline_saferpay_payment_name';
    public const CUSTOM_FIELD_SAFERPAY_PAYMENT_DISPLAY_TEXT = 'worldline_saferpay_payment_display_text';
    public const CUSTOM_FIELD_SAFERPAY_TOKEN = 'worldline_saferpay_token';
    public const CUSTOM_FIELD_SAFERPAY_NOTIFY_TOKEN = 'worldline_saferpay_notify_token';
    public const CUSTOM_FIELD_SAFERPAY_LAST_ERROR = 'worldline_saferpay_last_error';
    public const CUSTOM_FIELD_SAFERPAY_TOKEN_TYPE = 'worldline_saferpay_token_type';
    public const CUSTOM_FIELD_SAFERPAY_SCD_ALIAS = 'worldline_saferpay_scd_alias';

    public const CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS = 'worldline_saferpay_payment_means';
    public const CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR = 'worldline_saferpay_liability_shift_behavior';
    public const CUSTOM_FIELD_SAFERPAY_INTEGRATION = 'worldline_saferpay_integration';
    public const CUSTOM_FIELD_SAFERPAY_WALLETS = 'worldline_saferpay_wallets';
    public const CUSTOM_FIELD_SAFERPAY_REDIRECT_URL = 'worldline_saferpay_redirect_url';
    public const CUSTOM_FIELD_SAFERPAY_REDIRECT_URL_HANDLING = 'worldline_saferpay_redirect_url_handling';
    public const CUSTOM_FIELD_SAFERPAY_RETURN_URL = 'worldline_saferpay_return_url';

    public const CUSTOM_FIELD_SAFERPAY_ADDITIONAL_POSITIONS = 'worldline_saferpay_additional_positions';

    public const LIABILITY_SHIFT_BEHAVIOR_REQUIRED = 'required';
    public const LIABILITY_SHIFT_BEHAVIOR_IGNORE = 'ignore';

    public const LIABILITY_SHIFT_SUPPORTED_PAYMENT_METHODS = [
        self::SAFERPAY_PAYMENT_METHOD_AMERICAN_EXPRESS,
        self::SAFERPAY_PAYMENT_METHOD_BANCONTACT,
        self::SAFERPAY_PAYMENT_METHOD_DINERS_CLUB,
        self::SAFERPAY_PAYMENT_METHOD_JCB,
        self::SAFERPAY_PAYMENT_METHOD_MAESTRO,
        self::SAFERPAY_PAYMENT_METHOD_MASTERCARD,
        self::SAFERPAY_PAYMENT_METHOD_UNIONPAY,
        self::SAFERPAY_PAYMENT_METHOD_VISA
    ];

    public const INTEGRATION_PAYMENT_PAGE = 'paymentPage';
    public const INTEGRATION_SAFERPAY_FIELDS = 'saferpayFields';

    public const INTEGRATION_SAFERPAY_FIELDS_SUPPORTED_PAYMENT_METHODS = [
        self::SAFERPAY_PAYMENT_METHOD_AMERICAN_EXPRESS,
        self::SAFERPAY_PAYMENT_METHOD_BANCONTACT,
        self::SAFERPAY_PAYMENT_METHOD_DINERS_CLUB,
        self::SAFERPAY_PAYMENT_METHOD_JCB,
        self::SAFERPAY_PAYMENT_METHOD_MAESTRO,
        self::SAFERPAY_PAYMENT_METHOD_MASTERCARD,
        self::SAFERPAY_PAYMENT_METHOD_VISA
    ];

    public const REDIRECT_URL_HANDLING_REDIRECT = 'redirect';
    public const REDIRECT_URL_HANDLING_IFRAME = 'iframe';

    public const URL_SAFERPAY_FIELDS_JS_TEST = 'https://test.saferpay.com/Fields/lib/1/saferpay-fields.js';
    public const URL_SAFERPAY_FIELDS_JS_LIVE = 'https://www.saferpay.com/Fields/lib/1/saferpay-fields.js';
    public const URL_SAFERPAY_FIELDS_TEST = 'https://test.saferpay.com/Fields';
    public const URL_SAFERPAY_FIELDS_LIVE = 'https://www.saferpay.com/Fields';

    /**
     * @noinspection PhpUnused
     */
    public const CAPTURE_BEHAVIOR_MANUAL = 'manual';
    public const CAPTURE_BEHAVIOR_AUTO = 'auto';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $this->getCustomFieldsInstaller()->install($installContext);
        $this->getPaymentMethodInstaller($installContext->getContext())->install($installContext);
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->getCustomFieldsInstaller()->update($updateContext);
        $this->getPaymentMethodInstaller($updateContext->getContext())->update($updateContext);
    }

    public function activate(Plugin\Context\ActivateContext $activateContext): void
    {
        parent::activate($activateContext);
        $this->getCustomFieldsInstaller()->activate($activateContext);
    }

    public function deactivate(Plugin\Context\DeactivateContext $deactivateContext): void
    {
        parent::deactivate($deactivateContext);
        $this->getCustomFieldsInstaller()->deactivate($deactivateContext);
        $this->getPaymentMethodInstaller($deactivateContext->getContext())->deactivate($deactivateContext);
    }

    public function uninstall(Plugin\Context\UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);
        $this->getCustomFieldsInstaller()->uninstall($uninstallContext);
        $this->getPaymentMethodInstaller($uninstallContext->getContext())->uninstall($uninstallContext);
    }

    private function getCustomFieldsInstaller(): CustomFieldsInstaller
    {
        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        /** @var EntityRepository $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field.repository');

        return new CustomFieldsInstaller($customFieldSetRepository, $customFieldRepository);
    }

    private function getPaymentMethodInstaller(Context $context): PaymentMethodInstaller
    {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        return new PaymentMethodInstaller($this->getPluginId($context), $paymentRepository);
    }

    private function getPluginId(Context $context): string
    {
        /** @var Plugin\Util\PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(Plugin\Util\PluginIdProvider::class);
        return $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);
    }

    public static function getLastOrderTransactionHandledViaSaferpay(OrderEntity $order): ?OrderTransactionEntity
    {
        $transactions = $order->getTransactions();
        if (!$transactions->count()) {
            return null;
        }

        $transactions->sort(function(OrderTransactionEntity $a, OrderTransactionEntity $b) {
            return $a->getCreatedAt() <=> $b->getCreatedAt();
        });

        $lastTransaction = $transactions->last();
        if (!$lastTransaction) {
            return null;
        }

        $paymentMethod = $lastTransaction->getPaymentMethod();
        if (!$paymentMethod || $paymentMethod->getHandlerIdentifier() !== SaferpayPaymentHandler::class) {
            return null;
        }

        return $lastTransaction;
    }
}
