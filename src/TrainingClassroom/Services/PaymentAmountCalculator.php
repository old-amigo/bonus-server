<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services;

use Money\Money;

class PaymentAmountCalculator
{
    private PredefinedConfiguration $conf;

    /**
     * @param \Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration $conf
     */
    public function __construct(PredefinedConfiguration $conf)
    {
        $this->conf = $conf;
    }

    /**
     * Случайная сумма разрешённая к оплате по сделке
     *
     * @param \Money\Money $dealOpportunity
     *
     * @return \Money\Money
     * @throws \Exception
     */
    public function getAvailableRandomBonusPayment(Money $dealOpportunity): Money
    {
        return $this->conf->getDefaultBonusMaximumPaymentPercentage()->calculateVatFor($dealOpportunity)->divide(random_int(2, 10));
    }
}