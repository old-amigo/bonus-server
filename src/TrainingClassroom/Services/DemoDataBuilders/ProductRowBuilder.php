<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders;

use Bitrix24\SDK\Services\CRM\Product\Result\ProductItemResult;
use MoneyPHP\Percentage\Percentage;

class ProductRowBuilder
{
    private bool $isTaxIncluded;
    private Percentage $taxRate;

    public function __construct()
    {
        $this->isTaxIncluded = false;
        $this->taxRate = new Percentage('0');
    }

    /**
     * @param bool $isTaxIncluded
     *
     * @return ProductRowBuilder
     */
    public function withIsTaxIncluded(bool $isTaxIncluded): ProductRowBuilder
    {
        $this->isTaxIncluded = $isTaxIncluded;

        return $this;
    }

    /**
     * @param \MoneyPHP\Percentage\Percentage $taxRate
     *
     * @return ProductRowBuilder
     */
    public function withTaxRate(Percentage $taxRate): ProductRowBuilder
    {
        $this->taxRate = $taxRate;

        return $this;
    }

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
            'TAX_INCLUDED'          => $this->isTaxIncluded ? 'Y' : 'N',
            'TAX_RATE'              => (string)$this->taxRate->toRatio() * 10,
        ];
    }
}