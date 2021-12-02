<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services;

use Bitrix24\SDK\Services\CRM\Deal\Result\DealCategoryItemResult;
use Bitrix24\SDK\Services\CRM\Deal\Result\DealCategoryStageItemResult;
use Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException;

class PredefinedConfiguration
{
    protected const STAGE_NEW_ORDER = 'new_order';
    protected const STAGE_BONUS_PAYMENT = 'bonus_payment';
    protected const STAGE_ORDER_DELIVERED = 'order_delivered';
    protected const STAGE_ORDER_CANCELLED = 'order_cancelled';

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
    public function getDealCategoryId(array $categories): int
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
}