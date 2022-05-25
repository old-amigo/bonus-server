<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders;

use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Ramsey\Uuid\Uuid;

class ProductBuilder
{
    private Money $price;
    private string $xmlId;
    private string $name;

    /**
     * @param \Money\Currency $currency
     *
     * @throws \Exception
     */
    public function __construct(Currency $currency)
    {
        $this->price = new Money(random_int(20000, 250000), $currency);
        $this->xmlId = Uuid::uuid4()->toString();
    }

    /**
     * @param \Money\Money $price
     *
     * @return \Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ProductBuilder
     */
    public function withPrice(Money $price): ProductBuilder
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @param string $xmlId
     *
     * @return \Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ProductBuilder
     */
    public function withXmlId(string $xmlId): ProductBuilder
    {
        $this->xmlId = $xmlId;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return ProductBuilder
     */
    public function withName(string $name): ProductBuilder
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function build(): array
    {
        $decimalMoneyFormatter = new DecimalMoneyFormatter(new ISOCurrencies());

        return [
            'ACTIVE'          => 'Y',
            'PRICE'           => $decimalMoneyFormatter->format($this->price),
            'NAME'            => $this->name,
            'XML_ID'          => $this->xmlId,
            'CURRENCY_ID'     => $this->price->getCurrency()->getCode(),
            'DETAIL_PICTURE'  => null,
            'PREVIEW_PICTURE' => null,
            'MEASURE'         => null,
            'SECTION_ID'      => null,
            'SORT'            => null,
            'VAT_ID'          => null,
        ];
    }
}