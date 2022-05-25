<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Controllers;

use Bitrix24\SDK\Services\ServiceBuilder;
use Doctrine\DBAL;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\Bitrix24ApiClientServiceBuilder;


class BonusController
{
    private static function setBonuceBalance(int $bx24user_id, int $value): void
    {
        $serviceBuilder = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $serviceBuilder->getCRMScope()->contact()->update($bx24user_id, ['UF_CRM_B_BALANCE' => $value]);
    }

    private function getBonuceBalance(int $bx24user_id): void
    {

    }

    private function updateBonuceBalance(int $bx24user_id): void
    {

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

    public function handleBonusPayment(array $params): void
    {

    }

    public function handleDealWon(array $params): void
    {

    }
}