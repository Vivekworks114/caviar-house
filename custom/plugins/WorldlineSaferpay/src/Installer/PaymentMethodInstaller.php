<?php declare(strict_types=1);

namespace Worldline\Saferpay\Installer;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Worldline\Saferpay\Checkout\Payment\Cart\PaymentHandler\SaferpayPaymentHandler;
use Worldline\Saferpay\WorldlineSaferpay;

class PaymentMethodInstaller
{
    private EntityRepository $paymentMethodRepository;
    private array $paymentMethods;
    private array $obsoletePaymentMethodIds;

    public function __construct(
        string $pluginId,
        EntityRepository $paymentMethodRepository
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;

        $this->paymentMethods = [
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => 'abbbaf6f2dc946afa7498179c7ff03cf',
                'technicalName' => 'worldline_saferpay_account_to_account',
                'translations' => [
                    'de-DE' => [
                        'name' => 'Direktüberweisung per Account-to-Account Payments',
                        'description' => 'Direktüberweisung per Account-to-Account Payments (Saferpay)'
                    ],
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Direct bank transfer via Account-to-Account Payments',
                        'description' => 'Direct bank transfer via Account-to-Account Payments (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_ACCOUNT_TO_ACCOUNT
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '6cb99dba89ff4b52bad500c3ecc29dae',
                'technicalName' => 'worldline_saferpay_alipay',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Alipay+',
                        'description' => 'Alipay+ (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_ALIPAY
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => 'b850b901d02441a1b9cc0344f3134d27',
                'technicalName' => 'worldline_saferpay_american_express',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'American Express',
                        'description' => 'American Express (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_AMERICAN_EXPRESS
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => 'cdc86472cd5d40fcbc38dfa28f618a6b',
                'technicalName' => 'worldline_saferpay_apple_pay',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Apple Pay',
                        'description' => 'Apple Pay (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [
                                WorldlineSaferpay::SAFERPAY_WALLET_APPLE_PAY
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '273d0cc048824459b735e30c31b50d8c',
                'technicalName' => 'worldline_saferpay_bancontact',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Bancontact',
                        'description' => 'Bancontact (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_BANCONTACT
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => 'd37a31eaae5645e1bb6941c59ce3fbaa',
                'technicalName' => 'worldline_saferpay_blik',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'blik',
                        'description' => 'blik (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_BLIK
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '57cafe6372354701b58dd0fe47b74461',
                'technicalName' => 'worldline_saferpay_click_to_pay',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Click to Pay',
                        'description' => 'Click to Pay (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [
                                WorldlineSaferpay::SAFERPAY_WALLET_CLICK_TO_PAY
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => 'fa0feb3df2e141b08c12735c96cfbe47',
                'technicalName' => 'worldline_saferpay_credit_card',
                'translations' => [
                    'de-DE' => [
                        'name' => 'Kredit-/Debitkarte',
                        'description' => 'Kredit-/Debitkarte (Saferpay)'
                    ],
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Credit/debit card',
                        'description' => 'Credit/debit card (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_AMERICAN_EXPRESS,
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_DINERS_CLUB,
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_JCB,
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_MAESTRO,
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_MASTERCARD,
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_VISA,
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => 'e861dc9bc4f74bacb7b600cefd342139',
                'technicalName' => 'worldline_saferpay_diners_club',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Diners Club/Discover',
                        'description' => 'Diners Club/Discover (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_DINERS_CLUB
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '734929e3090b4605ad4fd807ee3f78b4',
                'technicalName' => 'worldline_saferpay_direct_debit',
                'translations' => [
                    'de-DE' => [
                        'name' => 'Lastschrift',
                        'description' => 'Lastschrift (Saferpay)'
                    ],
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Direct debit',
                        'description' => 'Direct debit (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_DIRECT_DEBIT
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
//            [
//                'pluginId' => $pluginId,
//                'handlerIdentifier' => SaferpayPaymentHandler::class,
//                'position' => -99,
//                'afterOrderEnabled' => true,
//                'id' => '442fe71714be4c558fadadcf46442198',
//                'translations' => [
//                    Defaults::LANGUAGE_SYSTEM => [
//                        'name' => 'e-przelewy',
//                        'description' => 'e-przelewy (Saferpay)',
//                        'customFields' => [
//                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
//                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_E_PRZELEWY
//                            ],
//                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
//                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
//                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
//                        ],
//                    ]
//                ]
//            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '5c76a0da4d7d44bdb11a37d42d85fc08',
                'technicalName' => 'worldline_saferpay_eps',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'eps',
                        'description' => 'eps (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_EPS
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '643fa392239c4937876463136e1dede6',
                'technicalName' => 'worldline_saferpay_google_pay',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Google Pay',
                        'description' => 'Google Pay (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [
                                WorldlineSaferpay::SAFERPAY_WALLET_GOOGLE_PAY
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '2ab668aec94a4a918e58117896b53e19',
                'technicalName' => 'worldline_saferpay_ideal',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'iDEAL',
                        'description' => 'iDEAL (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_IDEAL
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '9a3ac5329d9845df8548a6d382d1da94',
                'technicalName' => 'worldline_saferpay_jcb',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'JCB',
                        'description' => 'JCB (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_JCB
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => 'f4dbdf3eaf3642eca22401325ba16c67',
                'technicalName' => 'worldline_saferpay_klarna',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Klarna',
                        'description' => 'Klarna (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_KLARNA
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '6944b7ffcf8949dcba1ea4ceeaa9eaaa',
                'technicalName' => 'worldline_saferpay_maestro',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Maestro',
                        'description' => 'Maestro (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_MAESTRO
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => 'f383553fdaab4419bf8923cae9472511',
                'technicalName' => 'worldline_saferpay_mastercard',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Mastercard',
                        'description' => 'Mastercard (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_MASTERCARD
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '1e11a39ffa76412694b95fab0bd71cc8',
                'technicalName' => 'worldline_saferpay_paydirekt',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'paydirekt',
                        'description' => 'paydirekt (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_PAYDIREKT
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '72bfcf565a1546f6846898cd59c84df3',
                'technicalName' => 'worldline_saferpay_paypal',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'PayPal',
                        'description' => 'PayPal (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_PAYPAL
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '3f058e3b69ce423f9208a9b50d8fbf61',
                'technicalName' => 'worldline_saferpay_postfinance_pay',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'PostFinance Pay',
                        'description' => 'PostFinance Pay (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_POST_FINANCE_PAY
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '427d0b5a9e2a44f287d918a97cfe3e55',
                'technicalName' => 'worldline_saferpay_sofort',
                'translations' => [
                    'de-DE' => [
                        'name' => 'SOFORT Überweisung',
                        'description' => 'SOFORT Überweisung (Saferpay)'
                    ],
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'SOFORT Banking',
                        'description' => 'SOFORT Banking (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_SOFORT
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => 'e9410c1b34094591b1bf4266809b4269',
                'technicalName' => 'worldline_saferpay_twint',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'TWINT',
                        'description' => 'TWINT (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_TWINT
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '73d5ae51484340e6a5a3771b3719b78f',
                'technicalName' => 'worldline_saferpay_unionpay',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'UnionPay',
                        'description' => 'UnionPay (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_UNIONPAY
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '8061d4865573406ebd8860954ef18091',
                'technicalName' => 'worldline_saferpay_visa',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Visa',
                        'description' => 'Visa (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_VISA
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_REQUIRED,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '75cac2787f964d9aae185c6f34d5c719',
                'technicalName' => 'worldline_saferpay_wechat_pay',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'WeChat Pay',
                        'description' => 'WeChat Pay (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_WECHAT_PAY
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ],
            [
                'pluginId' => $pluginId,
                'handlerIdentifier' => SaferpayPaymentHandler::class,
                'position' => -99,
                'afterOrderEnabled' => true,
                'id' => '9d7c33c5bc6246a199a9ed649529b201',
                'technicalName' => 'worldline_saferpay_wl_crypto',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'WL Crypto Payments',
                        'description' => 'WL Crypto Payments (Saferpay)',
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => [
                                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_WL_CRYPTO_PAYMENTS
                            ],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_WALLETS => [],
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_LIABILITY_SHIFT_BEHAVIOR => WorldlineSaferpay::LIABILITY_SHIFT_BEHAVIOR_IGNORE,
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_INTEGRATION => WorldlineSaferpay::INTEGRATION_PAYMENT_PAGE,
                        ],
                    ]
                ]
            ]
        ];

        $this->obsoletePaymentMethodIds = [
            '9747d74274874e49ab81e3c5b622af94', // Bonus
            '108604c0805b443694615ec59213fa06', // myOne
            'c8fda1f7408c44c0a62dc9a51bf4426a', // PostFinance Card
            'c77a1998c08b4320aea13a8b802d750e', // PostFinance E-Finance
            '43d4e2a22dc9498d9b3db9a63cc759e3', // MasterPass
            '442fe71714be4c558fadadcf46442198', // e-przelewy
            'b0ecc05dad0b4458b14085b9f96d3dae', // giropay
            'd96192eccbac43239cc0d43ffba624ad', // giropay/paydirekt
        ];
    }

    public function install(InstallContext $installContext): void
    {
        foreach ($this->paymentMethods as $paymentMethod) {
            $this->createPaymentMethodIfNotExists($paymentMethod, $installContext->getContext());
        }

        foreach ($this->obsoletePaymentMethodIds as $obsoletePaymentMethodId) {
            $this->deactivatePaymentMethodIfExists($obsoletePaymentMethodId, $installContext->getContext());
    }
    }

    public function update(UpdateContext $updateContext): void
    {
        foreach ($this->paymentMethods as $paymentMethod) {
            $this->createPaymentMethodIfNotExists($paymentMethod, $updateContext->getContext());
        }

        foreach ($this->obsoletePaymentMethodIds as $obsoletePaymentMethodId) {
            $this->deactivatePaymentMethodIfExists($obsoletePaymentMethodId, $updateContext->getContext());
    }
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->deactivateSaferpayPaymentMethods($deactivateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->deactivateSaferpayPaymentMethods($uninstallContext->getContext());
    }

    private function createPaymentMethodIfNotExists(array $paymentMethod, Context $context): void
    {
        /** @var PaymentMethodEntity $existingPaymentMethod */
        $existingPaymentMethod = $this->paymentMethodRepository->search(new Criteria([$paymentMethod['id']]), $context)->first();

        if ($existingPaymentMethod) {
            $this->updateExistingPaymentMethod($existingPaymentMethod, $context);
            return;
        }

        $this->paymentMethodRepository->upsert([$paymentMethod], $context);
    }

    private function updateExistingPaymentMethod(PaymentMethodEntity $existingPaymentMethod, Context $context): void
    {
        if ($existingPaymentMethod->getId() === 'fa0feb3df2e141b08c12735c96cfbe47') {
            $customFields = $existingPaymentMethod->getCustomFields() ?: [];
            if (
                !isset($customFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS])
                || !is_array($customFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS])
            ) {
                return;
            }

            $deprecatedPaymentMeans = [
                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_MY_ONE,
                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_BONUS_CARD,
                WorldlineSaferpay::SAFERPAY_PAYMENT_METHOD_POST_FINANCE_CARD
            ];

            $filteredPaymentMeans = [];
            foreach ($customFields[WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS] as $paymentMean) {
                if (in_array($paymentMean, $deprecatedPaymentMeans)) {
                    continue;
                }

                $filteredPaymentMeans[] = $paymentMean;
            }

            $this->paymentMethodRepository->update(
                [
                    [
                        'id' => $existingPaymentMethod->getId(),
                        'customFields' => [
                            WorldlineSaferpay::CUSTOM_FIELD_SAFERPAY_PAYMENT_MEANS => $filteredPaymentMeans
                        ]
                    ]
                ],
                $context
            );
        }
    }

    private function deactivatePaymentMethodIfExists(string $paymentMethodId, Context $context): void
    {
        $existingPaymentMethodId = $this->paymentMethodRepository->searchIds(
            new Criteria([$paymentMethodId]),
            $context
        )->firstId();

        if (!$existingPaymentMethodId) {
            return;
        }

        $this->paymentMethodRepository->update([
            [
                'id' => $existingPaymentMethodId,
                'active' => false,
            ]
        ], $context);
    }

    private function deactivateSaferpayPaymentMethods(Context $context): void
    {
        $paymentMethodIds = $this->fetchSaferpayPaymentMethodIds($context);
        if (!$paymentMethodIds) {
            return;
        }

        $paymentMethods = [];

        foreach ($paymentMethodIds as $id) {
            $paymentMethods[] = [
                'id' => $id,
                'active' => false
            ];
        }

        $this->paymentMethodRepository->update($paymentMethods, $context);
    }

    private function fetchSaferpayPaymentMethodIds(Context $context): ?array
    {
        $paymentCriteria = (new Criteria())->addFilter(
            new EqualsFilter('handlerIdentifier', SaferpayPaymentHandler::class)
        );

        return $this->paymentMethodRepository->searchIds($paymentCriteria, $context)->getIds();
    }
}
