<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services;

use Bitrix24\SDK\Services\CRM\Deal\Result\DealCategoryItemResult;
use Bitrix24\SDK\Services\CRM\Deal\Result\DealCategoryStageItemResult;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Money;
use Money\Parser\DecimalMoneyParser;
use MoneyPHP\Percentage\Percentage;
use Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException;

class PredefinedConfiguration
{
    protected const STAGE_NEW_ORDER = 'new_order';
    protected const STAGE_BONUS_PAYMENT = 'bonus_payment';
    protected const STAGE_ORDER_DELIVERED = 'order_delivered';
    protected const STAGE_ORDER_CANCELLED = 'order_cancelled';

    /**
     * @return \Money\Currency
     */
    public function getDefaultBonusCurrency(): Currency
    {
        return new Currency('RUB');
    }

    /**
     * @return int
     */
    public function getBonusProcessingWaitingTimeout(): int
    {
        if (!array_key_exists('BONUS_PROCESSING_WAITING_TIMEOUT', $_ENV)) {
            throw new WrongBitrix24ConfigurationException(
                'в файле .env или .env.local не найден ключ BONUS_PROCESSING_WAITING_TIMEOUT со временем ожидания работы бонусного сервиса'
            );
        }

        return (int)$_ENV['BONUS_PROCESSING_WAITING_TIMEOUT'];
    }

    /**
     * @return string
     */
    public function getDefaultCategoryName(): string
    {
        return 'delivery';
    }

    /**
     * @param DealCategoryStageItemResult[] $stages
     *
     * @return int
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    public function getDealStageNewOrderStatusId(array $stages): string
    {
        return $this->getDealStageStatusIdByName($this::STAGE_NEW_ORDER, $stages);
    }

    /**
     * @param DealCategoryStageItemResult[] $stages
     *
     * @return int
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    public function getDealStageBonusPaymentStatusId(array $stages): string
    {
        return $this->getDealStageStatusIdByName($this::STAGE_BONUS_PAYMENT, $stages);
    }

    /**
     * @param DealCategoryStageItemResult[] $stages
     *
     * @return int
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    public function getDealStageOrderDeliveredStatusId(array $stages): string
    {
        return $this->getDealStageStatusIdByName($this::STAGE_ORDER_DELIVERED, $stages);
    }

    /**
     * @param DealCategoryStageItemResult[] $stages
     *
     * @return int
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    public function getDealStageOrderCancelledStatusId(array $stages): string
    {
        return $this->getDealStageStatusIdByName($this::STAGE_ORDER_CANCELLED, $stages);
    }

    /**
     * @param string                        $dealStageName
     * @param DealCategoryStageItemResult[] $stages
     *
     * @return string
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    private function getDealStageStatusIdByName(string $dealStageName, array $stages): string
    {
        foreach ($stages as $stage) {
            if ($stage->NAME === $dealStageName) {
                return $stage->STATUS_ID;
            }
        }

        throw new WrongBitrix24ConfigurationException(
            sprintf('stage with name %s not found', $dealStageName), 0, null,
            sprintf('добавьте в направление сделок %s стадию %s', $this->getDefaultCategoryName(), $dealStageName)
        );
    }

    /**
     * @param DealCategoryItemResult[] $categories
     *
     * @return int
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    public function getDealDeliveryCategoryId(array $categories): int
    {
        if (!array_key_exists($this->getDefaultCategoryName(), array_column($categories, 'ID', 'NAME'))) {
            throw new WrongBitrix24ConfigurationException(
                sprintf('не найдено направление сделок «%s»', $this->getDefaultCategoryName()),
                0,
                null,
                sprintf('добавьте руками в Битрикс24 направление сделок «%s»', $this->getDefaultCategoryName())
            );
        }

        return (int)array_column($categories, 'ID', 'NAME')[$this->getDefaultCategoryName()];
    }

    /**
     * Дефолтный процент начисления бонусов от успешной сделки
     *
     * @return \MoneyPHP\Percentage\Percentage
     */
    public function getDefaultBonusAccrualPercentage(): Percentage
    {
        return new Percentage($_ENV['DEFAULT_BONUS_ACCRUAL_PERCENTAGE']);
    }

    /**
     * Дефолтный процент максимально-возможной частичной оплаты сделки бонусами
     *
     * @return \MoneyPHP\Percentage\Percentage
     */
    public function getDefaultBonusMaximumPaymentPercentage(): Percentage
    {
        return new Percentage($_ENV['DEFAULT_BONUS_MAXIMUM_PAYMENT_PERCENTAGE']);
    }

    /**
     * Дефолтная сумма велком-бонуса для новых контактов
     *
     * @return \Money\Money
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    public function getDefaultBonusWelcomeGift(): Money
    {
        if (!array_key_exists('DEFAULT_BONUS_WELCOME_GIFT_AMOUNT', $_ENV)) {
            throw new WrongBitrix24ConfigurationException(
                'в файле .env или .env.local не найден ключ DEFAULT_BONUS_WELCOME_GIFT_AMOUNT с суммой велком-бонусов для новых контактов'
            );
        }

        return (new DecimalMoneyParser(new ISOCurrencies()))->parse(
            $_ENV['DEFAULT_BONUS_WELCOME_GIFT_AMOUNT'],
            $this->getDefaultBonusCurrency()
        );
    }

    /**
     * Флаг эмуляции работы бонусного сервера, нужен для отладки интеграционных тестов
     *
     * @return bool
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     */
    public function isBonusServerEmulationActive(): bool
    {
        if (!array_key_exists('IS_BONUS_SERVER_EMULATION_ACTIVE', $_ENV)) {
            throw new WrongBitrix24ConfigurationException(
                'в файле .env или .env.local не найден ключ IS_BONUS_SERVER_EMULATION_ACTIVE c флагом эмуляции работы бонусного сервера'
            );
        }

        return $_ENV['IS_BONUS_SERVER_EMULATION_ACTIVE'] === 'true';
    }
}