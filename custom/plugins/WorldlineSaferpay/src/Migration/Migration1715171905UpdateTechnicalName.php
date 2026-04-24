<?php declare(strict_types=1);

namespace Worldline\Saferpay\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @noinspection PhpUnused
 */
class Migration1715171905UpdateTechnicalName extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1715171905;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_alipay' WHERE `id` = 0x6cb99dba89ff4b52bad500c3ecc29dae AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_american_express' WHERE `id` = 0xb850b901d02441a1b9cc0344f3134d27 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_apple_pay' WHERE `id` = 0xcdc86472cd5d40fcbc38dfa28f618a6b AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_bancontact' WHERE `id` = 0x273d0cc048824459b735e30c31b50d8c AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_bonus_card' WHERE `id` = 0x9747d74274874e49ab81e3c5b622af94 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_credit_card' WHERE `id` = 0xfa0feb3df2e141b08c12735c96cfbe47 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_diners_club' WHERE `id` = 0xe861dc9bc4f74bacb7b600cefd342139 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_direct_debit' WHERE `id` = 0x734929e3090b4605ad4fd807ee3f78b4 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_eps' WHERE `id` = 0x5c76a0da4d7d44bdb11a37d42d85fc08 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_google_pay' WHERE `id` = 0x643fa392239c4937876463136e1dede6 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_ideal' WHERE `id` = 0x2ab668aec94a4a918e58117896b53e19 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_jcb' WHERE `id` = 0x9a3ac5329d9845df8548a6d382d1da94 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_klarna' WHERE `id` = 0xf4dbdf3eaf3642eca22401325ba16c67 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_maestro' WHERE `id` = 0x6944b7ffcf8949dcba1ea4ceeaa9eaaa AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_mastercard' WHERE `id` = 0xf383553fdaab4419bf8923cae9472511 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_my_one' WHERE `id` = 0x108604c0805b443694615ec59213fa06 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_paydirekt' WHERE `id` = 0x1e11a39ffa76412694b95fab0bd71cc8 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_paypal' WHERE `id` = 0x72bfcf565a1546f6846898cd59c84df3 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_post_finance_card' WHERE `id` = 0xc8fda1f7408c44c0a62dc9a51bf4426a AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_post_finance_e_finance' WHERE `id` = 0xc77a1998c08b4320aea13a8b802d750e AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_sofort' WHERE `id` = 0x427d0b5a9e2a44f287d918a97cfe3e55 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_twint' WHERE `id` = 0xe9410c1b34094591b1bf4266809b4269 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_unionpay' WHERE `id` = 0x73d5ae51484340e6a5a3771b3719b78f AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_visa' WHERE `id` = 0x8061d4865573406ebd8860954ef18091 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);

        $connection->executeStatement(<<<SQL
UPDATE `payment_method` SET `technical_name` = 'worldline_saferpay_wl_crypto_payments' WHERE `id` = 0x9d7c33c5bc6246a199a9ed649529b201 AND (`technical_name` IS NULL OR `technical_name` = "");
SQL);
    }

    public function updateDestructive(Connection $connection): void
    {}
}
