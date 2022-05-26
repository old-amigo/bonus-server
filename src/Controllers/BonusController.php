<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Controllers;

use Bitrix24\SDK\Services\ServiceBuilder;
use Doctrine\DBAL;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Parser\DecimalMoneyParser;
use Rarus\Interns\BonusServer\Model\BonusManager;
use Rarus\Interns\BonusServer\Model\Configuration;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\Bitrix24ApiClientServiceBuilder;


class BonusController
{
    public static function setBonuceBalance(int $bx24user_id, float $value): void
    {
        $serviceBuilder = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $serviceBuilder->getCRMScope()->contact()->update($bx24user_id, ['UF_CRM_B_BALANCE' => $value]);

        $connectionParams = Configuration::getDBConfig(2);

        try {
            $conf = new DBAL\Configuration();
            $conn = DBAL\DriverManager::getConnection($connectionParams, $conf);
            $conn->update('users', ['bonuses' => $value], ['bx24_id' => $bx24user_id]);
        } catch (\Throwable $e) {
            //todo log this
        }
    }

    private static function getBonuseBalance(int $bx24user_id): string
    {
        $connectionParams = Configuration::getDBConfig(2);

        try {
            $conf = new DBAL\Configuration();
            $conn = DBAL\DriverManager::getConnection($connectionParams, $conf);
            return (string)$conn->fetchOne('SELECT bonuses FROM users WHERE bx24_id=?', [$bx24user_id]);
        } catch (\Throwable $e) {
            //todo log this
            throw $e;
        }
    }

    public static function handleNewDeal(array $params): void
    {
        $userBX24id = (int)$params['user_id'];
        $dealBX24id = (int)$params['deal_id'];

        if (User::getUserByBX24id($userBX24id) === []) {
            User::newUser($userBX24id);
            self::setBonuceBalance($userBX24id, 300);
        }
    }

    public static function handleBonusPayment(array $params): void
    {
        $userBX24id = (int)$params['user_id'];
        $dealBX24id = (int)$params['deal_id'];

        $decimalParser = new DecimalMoneyParser(new ISOCurrencies());
        $decimalFormatter = new DecimalMoneyFormatter(new ISOCurrencies());

        $b24Service = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $dealService = $b24Service->getCRMScope()->deal();
        $deal = $dealService->get($dealBX24id)->deal();

        $dealAmount = (float)$deal->OPPORTUNITY;
        $bonusesToPay = (float)$deal->UF_CRM_B_TO_PAY;
        $userBonuseBalance = (float)self::getBonuseBalance($userBX24id);
        $rows = $b24Service->getCRMScope()->dealProductRows()->get($dealBX24id)->getProductRows();

        //если бонусов к трате больше порогового значения или больше, чен на счету пользователя, обнулим это поле

        $maxBonusesToPay = $dealAmount * ((float)$_ENV['DEFAULT_BONUS_MAXIMUM_PAYMENT_PERCENTAGE'] / 100);
        if ($bonusesToPay > $userBonuseBalance || $bonusesToPay > $maxBonusesToPay) {
            $dealService->update($deal->ID, ['UF_CRM_B_TO_PAY' => '0']);
        }
        //иначе - спишем со счета и рассчитаем скидку


//        echo "<pre>";
//        print_r($rows);
//        echo "</pre>";
    }

    public static function handleDealWon(array $params): void
    {
        $userBX24id = (int)$params['user_id'];
        $dealBX24id = (int)$params['deal_id'];
        $b24Service = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $dealService = $b24Service->getCRMScope()->deal();
        $deal = $dealService->get($dealBX24id)->deal();


        if ($deal->UF_CRM_B_TO_PAY == 0 && $deal->UF_CRM_B_ACCRUED == 0) {
            $decimalParser = new DecimalMoneyParser(new ISOCurrencies());
            $decimalFormatter = new DecimalMoneyFormatter(new ISOCurrencies());

            $dealAmount = $decimalParser->parse($deal->OPPORTUNITY, $deal->CURRENCY_ID);
            $userBonuses = $decimalParser->parse(self::getBonuseBalance($userBX24id), $deal->CURRENCY_ID);
            $bonusesToAccrual = BonusManager::countBonucesToAccrual($dealAmount);

            $result = $bonusesToAccrual->add($userBonuses);
            $dealService->update($deal->ID, ['UF_CRM_B_ACCRUED' => $decimalFormatter->format($bonusesToAccrual),]);
            self::setBonuceBalance($userBX24id, (float)$decimalFormatter->format($result));
        }
    }
}