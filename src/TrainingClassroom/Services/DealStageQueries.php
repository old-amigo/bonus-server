<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services;

use Bitrix24\SDK\Services\CRM\Deal\Result\DealCategoryStageItemResult;
use Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException;

class DealStageQueries
{
    /**
     * @param string                        $b24DealStageName
     * @param DealCategoryStageItemResult[] $b24DealStages
     *
     * @return \Bitrix24\SDK\Services\CRM\Deal\Result\DealCategoryStageItemResult
     * @throws WrongBitrix24ConfigurationException
     */
    public function getDealStageByName(string $b24DealStageName, array $b24DealStages): DealCategoryStageItemResult
    {
        foreach ($b24DealStages as $stage) {
            if ($stage->NAME === $b24DealStageName) {
                return $stage;
            }
        }
        throw new WrongBitrix24ConfigurationException(sprintf('стадия сделок по имени %s не найдена', $b24DealStageName));
    }
}