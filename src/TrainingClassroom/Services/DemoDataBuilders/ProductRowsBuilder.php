<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders;

use Bitrix24\SDK\Services\CRM\Product\Result\ProductItemResult;

class ProductRowsBuilder
{
    private ProductRowBuilder $productRowBuilder;

    /**
     * @param \Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ProductRowBuilder $productRowBuilder
     */
    public function __construct(ProductRowBuilder $productRowBuilder)
    {
        $this->productRowBuilder = $productRowBuilder;
    }

    /**
     * @param int   $dealId
     * @param int   $productRowsCount
     * @param array $b24Products
     *
     * @return array
     * @throws \Exception
     */
    public function build(
        int $dealId,
        int $productRowsCount,
        array $b24Products
    ): array {
        $productRows = [];
        for ($i = 0; $i < $productRowsCount; $i++) {
            $randomProduct = $b24Products[random_int(0, count($b24Products) - 1)];
            $productQuantity = random_int(1, 5);
            $productRows[] = $this->productRowBuilder->build($dealId, $randomProduct, $productQuantity);
        }

        return $productRows;
    }
}