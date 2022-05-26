<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Model;

use Dotenv\Dotenv;
use Money\Money;


class BonusManager
{
    public static function countBonucesToAccrual(Money $money): Money
    {
        $dotenv = Dotenv::createImmutable(dirname(__DIR__, 2));
        $dotenv->load();

        return $money->multiply($_ENV['DEFAULT_BONUS_ACCRUAL_PERCENTAGE'] / 100);
    }
}