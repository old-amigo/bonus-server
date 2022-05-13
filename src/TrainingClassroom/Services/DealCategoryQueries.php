<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services;

use Bitrix24\SDK\Services\CRM\Deal\Result\DealCategoryItemResult;

class DealCategoryQueries
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
     * @param DealCategoryItemResult[] $categories
     *
     * @return int
     */
    public function getDeliveryCategoryId(array $categories): int
    {
        return (int)array_column($categories, 'ID', 'NAME')[$this->conf->getDefaultCategoryName()];
    }
}