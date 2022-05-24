<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Tests\Integration\TrainingClassroom\Services;


use Money\Currency;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PaymentAmountCalculator;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration;

class PaymentAmountCalculatorTest extends TestCase
{
    private PaymentAmountCalculator $calc;

    /**
     * @testdox Случайная сумма разрешённая к оплате по сделке
     * @covers  \Rarus\Interns\BonusServer\TrainingClassroom\Services\PaymentAmountCalculator::getAvailableRandomBonusPayment
     * @return void
     * @throws \Exception
     */
    public function testGetAvailableRandomBonusPayment(): void
    {
        $total = new Money('100000', new Currency('RUB'));
        $payment = $this->calc->getAvailableRandomBonusPayment($total);
        $this->assertTrue(
            $payment->lessThan($total)
        );
    }

    public function setUp(): void
    {
        $this->calc = new PaymentAmountCalculator(new PredefinedConfiguration());
    }
}