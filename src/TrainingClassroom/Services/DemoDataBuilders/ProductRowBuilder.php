<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders;

use Bitrix24\SDK\Services\CRM\Product\Result\ProductItemResult;

class ProductRowBuilder
{
    /**
     * @param int                                                         $dealId
     * @param \Bitrix24\SDK\Services\CRM\Product\Result\ProductItemResult $product
     * @param int                                                         $quantity
     *
     * @return array
     */
    public function build(int $dealId, ProductItemResult $product, int $quantity): array
    {
        return [
            'CUSTOMIZED'            => 'N',
            'DISCOUNT_RATE'         => 0,
            'DISCOUNT_SUM'          => 0,
            'DISCOUNT_TYPE_ID'      => 1,
            'MEASURE_NAME'          => $product->MEASURE,
            'ORIGINAL_PRODUCT_NAME' => $product->NAME,
            'OWNER_ID'              => $dealId,
            'OWNER_TYPE'            => 'D',
            'PRICE'                 => $product->PRICE,
            'PRICE_ACCOUNT'         => $product->PRICE,
            'PRICE_BRUTTO'          => $product->PRICE,
            'PRICE_EXCLUSIVE'       => $product->PRICE,
            'PRICE_NETTO'           => $product->PRICE,
            'PRODUCT_DESCRIPTION'   => $product->DESCRIPTION,
            'PRODUCT_ID'            => $product->ID,
            'PRODUCT_NAME'          => $product->NAME,
            'QUANTITY'              => $quantity,
            'TAX_INCLUDED'          => 'Y',
            'TAX_RATE'              => '20',
        ];
    }
}