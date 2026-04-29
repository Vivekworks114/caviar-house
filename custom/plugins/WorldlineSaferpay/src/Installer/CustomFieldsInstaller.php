<?php declare(strict_types=1);

namespace Worldline\Saferpay\Installer;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Worldline\Saferpay\WorldlineSaferpay;

class CustomFieldsInstaller
{
    public const FIELDSET_ID_PAYMENT_METHOD = 'b68e764adf244001b5c642c66d31491c';
    private EntityRepository $customFieldRepository;
    private EntityRepository $customFieldSetRepository;
    private array $customFields;
    private array $customFieldSets;

    public function __construct(
        EntityRepository $customFieldSetRepository,
        EntityRepository $customFieldRepository
    ) {
        $this->customFieldSetRepository = $customFieldSetRepository;
        $this->customFieldRepository = $customFieldRepository;

        $this->customFieldSets = [
            [
                'id' => self::FIELDSET_ID_PAYMENT_METHOD,
                'name' => 'worldline_saferpay_payment_method',
                'global' => true,
                'config' => [
                    'label' => [
                        'en-GB' => 'Saferpay',
                        'de-DE' => 'Saferpay',
                        Defaults::LANGUAGE_SYSTEM => 'Saferpay',
                    ],
                ],
                'relations' => [
                    [
                        'id' => '40fa22d5a3094bd195ff0983b45e041f',
                        'entityName' => 'payment_method',
                    ]
                ],
            ]
        ];

        $this->customFields = [
            [
                'id' => '4bcb2ebdfff24d46ae4a622c2b22e59b',
                'name' => WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS,
                'type' => CustomFieldTypes::JSON,
                'customFieldSetId' => self::FIELDSET_ID_PAYMENT_METHOD,
                'config' => [
                    'type' => 'select',
                    'label' => [
                        'en-GB' => 'Payment means',
                        'de-DE' => 'Zahlungsmittel',
                        Defaults::LANGUAGE_SYSTEM => 'Payment means',
                    ],
                    'helpText' => [
                        'en-GB' => 'Restricts the means of payment which are available on the Saferpay payment page for this payment method. If only one payment mean is set, the payment selection step will be skipped on the Saferpay payment page. If empty, all Saferpay payment means are available.',
                        'de-DE' => 'Schränkt die Zahlungsmittel ein, die auf der Saferpay-Zahlungsseite für diese Zahlungsart zur Verfügung stehen. Wenn nur ein Zahlungsmittel eingestellt ist, wird der Schritt der Zahlungsauswahl auf der Saferpay-Zahlungsseite übersprungen. Wenn leer, sind alle Saferpay Zahlungsmittel verfügbar.',
                        Defaults::LANGUAGE_SYSTEM => 'Restricts the means of payment which are available on the Saferpay payment page for this payment method. If only one payment mean is set, the payment selection step will be skipped on the Saferpay payment page. If empty, all Saferpay payment means are available.',
                    ],
                    'componentName' => 'sw-multi-select',
                    'customFieldType' => 'select',
                    'customFieldPosition' => 0,
                    'options' => [
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_ACCOUNT_TO_ACCOUNT,
                            'label' => [
                                'de-DE' => 'Account-to-Account Payments',
                                'en-GB' => 'Account-to-Account Payments',
                                Defaults::LANGUAGE_SYSTEM => 'Account-to-Account Payments',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_ALIPAY,
                            'label' => [
                                'de-DE' => 'Alipay',
                                'en-GB' => 'Alipay',
                                Defaults::LANGUAGE_SYSTEM => 'Alipay',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_AMERICAN_EXPRESS,
                            'label' => [
                                'de-DE' => 'American Express',
                                'en-GB' => 'American Express',
                                Defaults::LANGUAGE_SYSTEM => 'American Express',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_BANCONTACT,
                            'label' => [
                                'de-DE' => 'Bancontact',
                                'en-GB' => 'Bancontact',
                                Defaults::LANGUAGE_SYSTEM => 'Bancontact',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_BLIK,
                            'label' => [
                                'de-DE' => 'blik',
                                'en-GB' => 'blik',
                                Defaults::LANGUAGE_SYSTEM => 'blik',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_DINERS_CLUB,
                            'label' => [
                                'de-DE' => 'Diners Club/Discover',
                                'en-GB' => 'Diners Club/Discover',
                                Defaults::LANGUAGE_SYSTEM => 'Diners Club/Discover',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_DIRECT_DEBIT,
                            'label' => [
                                'de-DE' => 'Lastschrift',
                                'en-GB' => 'Direct Debit',
                                Defaults::LANGUAGE_SYSTEM => 'Direct Debit',
                            ]
                        ],
//                        [
//                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_E_PRZELEWY,
//                            'label' => [
//                                'de-DE' => 'e-przelewy',
//                                'en-GB' => 'e-przelewy',
//                                Defaults::LANGUAGE_SYSTEM => 'e-przelewy',
//                            ]
//                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_EPS,
                            'label' => [
                                'de-DE' => 'eps',
                                'en-GB' => 'eps',
                                Defaults::LANGUAGE_SYSTEM => 'eps',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_IDEAL,
                            'label' => [
                                'de-DE' => 'iDEAL',
                                'en-GB' => 'iDEAL',
                                Defaults::LANGUAGE_SYSTEM => 'iDEAL',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_JCB,
                            'label' => [
                                'de-DE' => 'JCB',
                                'en-GB' => 'JCB',
                                Defaults::LANGUAGE_SYSTEM => 'JCB',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_KLARNA,
                            'label' => [
                                'de-DE' => 'Klarna',
                                'en-GB' => 'Klarna',
                                Defaults::LANGUAGE_SYSTEM => 'Klarna',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_MAESTRO,
                            'label' => [
                                'de-DE' => 'Maestro',
                                'en-GB' => 'Maestro',
                                Defaults::LANGUAGE_SYSTEM => 'Maestro',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_MASTERCARD,
                            'label' => [
                                'de-DE' => 'Mastercard',
                                'en-GB' => 'Mastercard',
                                Defaults::LANGUAGE_SYSTEM => 'Mastercard',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_PAYPAL,
                            'label' => [
                                'de-DE' => 'PayPal',
                                'en-GB' => 'PayPal',
                                Defaults::LANGUAGE_SYSTEM => 'PayPal',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_PAYDIREKT,
                            'label' => [
                                'de-DE' => 'paydirekt',
                                'en-GB' => 'paydirekt',
                                Defaults::LANGUAGE_SYSTEM => 'paydirekt',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_POST_FINANCE_PAY,
                            'label' => [
                                'de-DE' => 'PostFinance Pay',
                                'en-GB' => 'PostFinance Pay',
                                Defaults::LANGUAGE_SYSTEM => 'PostFinance Pay',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_SOFORT,
                            'label' => [
                                'de-DE' => 'SOFORT Überweisung',
                                'en-GB' => 'SOFORT Banking',
                                Defaults::LANGUAGE_SYSTEM => 'SOFORT Banking',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_TWINT,
                            'label' => [
                                'de-DE' => 'TWINT',
                                'en-GB' => 'TWINT',
                                Defaults::LANGUAGE_SYSTEM => 'TWINT',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_UNIONPAY,
                            'label' => [
                                'de-DE' => 'UnionPay',
                                'en-GB' => 'UnionPay',
                                Defaults::LANGUAGE_SYSTEM => 'UnionPay',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_VISA,
                            'label' => [
                                'de-DE' => 'Visa',
                                'en-GB' => 'Visa',
                                Defaults::LANGUAGE_SYSTEM => 'Visa',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_WECHAT_PAY,
                            'label' => [
                                'de-DE' => 'WeChat Pay',
                                'en-GB' => 'WeChat Pay',
                                Defaults::LANGUAGE_SYSTEM => 'WeChat Pay',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_WL_CRYPTO_PAYMENTS,
                            'label' => [
                                'de-DE' => 'WL Crypto Payments',
                                'en-GB' => 'WL Crypto Payments',
                                Defaults::LANGUAGE_SYSTEM => 'WL Crypto Payments',
                            ]
                        ],
                    ]
                ],
            ],
            [
                'id' => 'b6ff9dff81474bac8bd4fe430c73fd50',
                'name' => WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS,
                'type' => CustomFieldTypes::JSON,
                'customFieldSetId' => self::FIELDSET_ID_PAYMENT_METHOD,
                'config' => [
                    'type' => 'select',
                    'label' => [
                        'en-GB' => 'Wallets',
                        'de-DE' => 'Wallets',
                        Defaults::LANGUAGE_SYSTEM => 'Wallets',
                    ],
                    'helpText' => [
                        'en-GB' => 'Sets which wallets are available on the Saferpay payment page for this payment method. If only one wallet and no payment mean is set, the payment selection step on the Saferpay payment page is skipped. If empty, no wallets are available.',
                        'de-DE' => 'Legt fest, welche Wallets auf der Saferpay-Zahlungsseite für diese Zahlungsart zur Verfügung stehen. Wenn nur eine Wallet und kein Zahlungsmittel eingestellt ist, wird der Schritt der Zahlungsauswahl auf der Saferpay-Zahlungsseite übersprungen. Wenn leer, sind keine Wallets verfügbar.',
                        Defaults::LANGUAGE_SYSTEM => 'Sets which wallets are available on the Saferpay payment page for this payment method. If only one wallet and no payment mean is set, the payment selection step on the Saferpay payment page is skipped. If empty, no wallets are available.',
                    ],
                    'componentName' => 'sw-multi-select',
                    'customFieldType' => 'select',
                    'customFieldPosition' => 50,
                    'options' => [
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_WALLET_APPLE_PAY,
                            'label' => [
                                'de-DE' => 'Apple Pay',
                                'en-GB' => 'Apple Pay',
                                Defaults::LANGUAGE_SYSTEM => 'Apple Pay',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_WALLET_CLICK_TO_PAY,
                            'label' => [
                                'de-DE' => 'Click to Pay',
                                'en-GB' => 'Click to Pay',
                                Defaults::LANGUAGE_SYSTEM => 'Click to Pay',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::SAFERPAY_WALLET_GOOGLE_PAY,
                            'label' => [
                                'de-DE' => 'Google Pay',
                                'en-GB' => 'Google Pay',
                                Defaults::LANGUAGE_SYSTEM => 'Google Pay',
                            ]
                        ],
                    ]
                ],
            ],
            [
                'id' => 'ba533c908baf46b0867221d7d6e005a8',
                'name' => WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR,
                'type' => CustomFieldTypes::SELECT,
                'customFieldSetId' => self::FIELDSET_ID_PAYMENT_METHOD,
                'config' => [
                    'label' => [
                        'en-GB' => 'Liability shift behavior for supported payment means',
                        'de-DE' => 'Verhalten bei Haftungsumkehr für unterstützte Zahlungsmittel',
                        Defaults::LANGUAGE_SYSTEM => 'Liability shift behavior for supported payment means',
                    ],
                    'helpText' => [
                        'en-GB' => 'Defines the behavior regarding liability shift. This option applies only to following payment means: ' . implode(', ', WorldlineSaferpay::LIABILITY_SHIFT_SUPPORTED_PAYMENT_METHODS),
                        'de-DE' => 'Legt das Verhalten bzgl. Haftungsverschiebung fest. Diese Option gilt nur für folgende Zahlungsmittel: ' . implode(', ', WorldlineSaferpay::LIABILITY_SHIFT_SUPPORTED_PAYMENT_METHODS),
                        Defaults::LANGUAGE_SYSTEM => 'Defines the behavior regarding liability shift. This option applies only to following payment means: ' . implode(', ', WorldlineSaferpay::LIABILITY_SHIFT_SUPPORTED_PAYMENT_METHODS),
                    ],
                    'componentName' => 'sw-single-select',
                    'customFieldPosition' => 100,
                    'options' => [
                        [
                            'value' => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED,
                            'label' => [
                                'de-DE' => 'Erforderlich (Zahlungen ohne Haftungsumkehr werden abgebrochen)',
                                'en-GB' => 'Required (payments without liability shift are canceled)',
                                Defaults::LANGUAGE_SYSTEM => 'Required (payments without liability shift are canceled)',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            'label' => [
                                'de-DE' => 'Ignorieren (Zahlungen werden unabhängig von der Haftungsumkehr akzeptiert)',
                                'en-GB' => 'Ignore (payments are accepted regardless of liability shift)',
                                Defaults::LANGUAGE_SYSTEM => 'Ignore (payments are accepted regardless of liability shift)',
                            ]
                        ],
                    ]
                ],
            ],
            [
                'id' => '55fe501781b04caca60cefa04acc57b3',
                'name' => WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION,
                'type' => CustomFieldTypes::SELECT,
                'customFieldSetId' => self::FIELDSET_ID_PAYMENT_METHOD,
                'config' => [
                    'label' => [
                        'en-GB' => 'Storefront integration',
                        'de-DE' => 'Storefront integration',
                        Defaults::LANGUAGE_SYSTEM => 'Storefront integration',
                    ],
                    'helpText' => [
                        'en-GB' => 'Defines how the payment method is to be integrated into the storefront. This option applies only to following payment means: ' . implode(', ', WorldlineSaferpay::LIABILITY_SHIFT_SUPPORTED_PAYMENT_METHODS),
                        'de-DE' => 'Legt fest, wie die Zahlungsmethode im Storefront integriert werden soll. Diese Option gilt nur für folgende Zahlungsmittel: ' . implode(', ', WorldlineSaferpay::LIABILITY_SHIFT_SUPPORTED_PAYMENT_METHODS),
                        Defaults::LANGUAGE_SYSTEM => 'Defines how the payment method is to be integrated into the storefront. This option applies only to following payment means: ' . implode(', ', WorldlineSaferpay::INTEGRATION_SAFERPAY_FIELDS_SUPPORTED_PAYMENT_METHODS),
                    ],
                    'componentName' => 'sw-single-select',
                    'customFieldPosition' => 150,
                    'options' => [
                        [
                            'value' => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                            'label' => [
                                Defaults::LANGUAGE_SYSTEM => 'Payment Page',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::INTEGRATION_SAFERPAY_FIELDS,
                            'label' => [
                                Defaults::LANGUAGE_SYSTEM => 'Saferpay Fields',
                            ]
                        ],
                    ]
                ],
            ],
            [
                'id' => 'decb9720f5894efca8ccc8da38870edc',
                'name' => WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_REDIRECT_URL_HANDLING,
                'type' => CustomFieldTypes::SELECT,
                'customFieldSetId' => self::FIELDSET_ID_PAYMENT_METHOD,
                'config' => [
                    'label' => [
                        'en-GB' => 'Redirect URL handling',
                        'de-DE' => 'Handhabung von Redirect-URLs',
                        Defaults::LANGUAGE_SYSTEM => 'Redirect URL handling',
                    ],
                    'helpText' => [
                        'en-GB' => 'Defines how redirect URLs should be handled. Not all payment methods support the Iframe integration (see https://docs.saferpay.com/home/integration-guide/iframe-integration-and-css).',
                        'de-DE' => 'Legt fest, wie Redirect URLs gehandhabt werden sollen. Nicht alle Zahlungsmittel unterstützen die Iframe-Integration (siehe https://docs.saferpay.com/home/integration-guide/iframe-integration-and-css).',
                        Defaults::LANGUAGE_SYSTEM => 'Defines how redirect URLs should be handled. Not all payment methods support the Iframe integration (see https://docs.saferpay.com/home/integration-guide/iframe-integration-and-css).',
                    ],
                    'componentName' => 'sw-single-select',
                    'customFieldPosition' => 150,
                    'options' => [
                        [
                            'value' => WorldlineSaferpay::REDIRECT_URL_HANDLING_REDIRECT,
                            'label' => [
                                'de-DE' => 'Umleiten',
                                'en-GB' => 'Redirect',
                                Defaults::LANGUAGE_SYSTEM => 'Redirect',
                            ]
                        ],
                        [
                            'value' => WorldlineSaferpay::REDIRECT_URL_HANDLING_IFRAME,
                            'label' => [
                                Defaults::LANGUAGE_SYSTEM => 'Iframe',
                            ]
                        ],
                    ]
                ],
            ]
        ];
    }

    public function install(InstallContext $context): void
    {
        foreach ($this->customFieldSets as $customFieldSet) {
            $this->upsertCustomFieldSet($customFieldSet, $context->getContext());
        }

        foreach ($this->customFields as $customField) {
            $this->upsertCustomField($customField, $context->getContext());
        }
    }

    public function update(UpdateContext $context): void
    {
        foreach ($this->customFieldSets as $customFieldSet) {
            $this->upsertCustomFieldSet($customFieldSet, $context->getContext());
        }

        foreach ($this->customFields as $customField) {
            $this->upsertCustomField($customField, $context->getContext());
        }
    }

    public function activate(ActivateContext $context): void
    {
        foreach ($this->customFieldSets as $customFieldSet) {
            $this->activateCustomFieldSet($customFieldSet, $context->getContext());
        }

        foreach ($this->customFields as $customField) {
            $this->activateCustomField($customField, $context->getContext());
        }
    }

    public function deactivate(DeactivateContext $context): void
    {
        foreach ($this->customFieldSets as $customFieldSet) {
            $this->deactivateCustomFieldSet($customFieldSet, $context->getContext());
        }

        foreach ($this->customFields as $customField) {
            $this->deactivateCustomField($customField, $context->getContext());
        }
    }

    public function uninstall(UninstallContext $context): void
    {
        if ($context->keepUserData()) {
            foreach ($this->customFieldSets as $customFieldSet) {
                $this->deactivateCustomFieldSet($customFieldSet, $context->getContext());
            }

            foreach ($this->customFields as $customField) {
                $this->deactivateCustomField($customField, $context->getContext());
            }
        } else {
            foreach ($this->customFields as $customField) {
                $this->deleteCustomField($customField, $context->getContext());
            }

            foreach ($this->customFieldSets as $customFieldSet) {
                $this->deleteCustomFieldSet($customFieldSet, $context->getContext());
            }
        }
    }

    private function upsertCustomField(array $customField, Context $context): void
    {
        $this->customFieldRepository->upsert(
            [
                $customField
            ],
            $context
        );
    }

    private function activateCustomField(array $customField, Context $context): void
    {
        $customField['active'] = true;

        $this->customFieldRepository->upsert(
            [
                $customField
            ],
            $context
        );
    }

    private function deactivateCustomField(array $customField, Context $context): void
    {
        $customField['active'] = false;

        $this->customFieldRepository->upsert(
            [
                $customField
            ],
            $context
        );
    }

    private function deleteCustomField(array $customField, Context $context): void
    {
        $this->customFieldRepository->delete(
            [
                [
                    'id' => $customField['id']
                ]
            ],
            $context
        );
    }

    private function upsertCustomFieldSet(array $customFieldSet, Context $context): void
    {
        $this->customFieldSetRepository->upsert(
            [
                $customFieldSet
            ],
            $context
        );
    }

    private function activateCustomFieldSet(array $customFieldSet, Context $context): void
    {
        $customFieldSet['active'] = true;

        $this->customFieldSetRepository->upsert(
            [
                $customFieldSet
            ],
            $context
        );
    }

    private function deactivateCustomFieldSet(array $customFieldSet, Context $context): void
    {
        $customFieldSet['active'] = false;

        $this->customFieldSetRepository->upsert(
            [
                $customFieldSet
            ],
            $context
        );
    }

    private function deleteCustomFieldSet(array $customFieldSet, Context $context): void
    {
        $this->customFieldSetRepository->delete(
            [
                [
                    'id' => $customFieldSet['id']
                ]
            ],
            $context
        );
    }
}
