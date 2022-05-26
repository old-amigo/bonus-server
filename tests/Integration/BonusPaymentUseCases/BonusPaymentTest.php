<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Tests\Integration\BonusPaymentUseCases;

use Bitrix24\SDK\Services\CRM\Common\Result\AbstractCrmItem;
use Bitrix24\SDK\Services\ServiceBuilder;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;
use MoneyPHP\Percentage\Percentage;
use PHPUnit\Framework\TestCase;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\Bitrix24ApiClientServiceBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ContactBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\DealBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ProductBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ProductRowBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ProductRowsBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\LoggerBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PaymentAmountCalculator;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\ContactFields;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\DealFields;
use Traversable;

class BonusPaymentTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;
    private PredefinedConfiguration $conf;
    private ContactFields $contactUserFields;
    private DealFields $dealUserFields;
    private PaymentAmountCalculator $calc;
    private ProductBuilder $productBuilder;
    private const PRODUCT_100_WITHOUT_VAT_XML_ID = 'rarus-traning-bs-product-100-without-vat';

    /**
     * @return \Traversable
     */
    public function predefinedDealsDataProvider(): Traversable
    {
        $conf = new PredefinedConfiguration();
        $serviceBuilder = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $productBuilder = new ProductBuilder($conf->getDefaultBonusCurrency());
        $contactBuilder = new ContactBuilder();
        $dealCategoriesService = $serviceBuilder->getCRMScope()->dealCategory();
        $dealStageService = $serviceBuilder->getCRMScope()->dealCategoryStage();
        $dealService = $serviceBuilder->getCRMScope()->deal();
        $dealUserFields = new DealFields(
            $serviceBuilder->getCRMScope()->dealUserfield(),
            $dealService,
            LoggerBuilder::getLogger()
        );

        // продукт за 100 руб
        // добавили продукт если ещё нет
        if ($serviceBuilder->getCRMScope()->product()->countByFilter(['=XML_ID' => self::PRODUCT_100_WITHOUT_VAT_XML_ID]) === 0) {
            $productId = $serviceBuilder->getCRMScope()->product()->add(
                $productBuilder
                    ->withXmlId(self::PRODUCT_100_WITHOUT_VAT_XML_ID)
                    ->withPrice(new Money(10000, $conf->getDefaultBonusCurrency()))
                    ->withIsVatIncluded(false)
                    ->build()
            )->getId();
        } else {
            $productId = $serviceBuilder->getCRMScope()->product()->list(
                [],
                [
                    '=XML_ID' => self::PRODUCT_100_WITHOUT_VAT_XML_ID,
                ],
                ['*']
            )->getProducts()[0]->ID;
        }

        // добавили контакт
        $contactId = $serviceBuilder->getCRMScope()->contact()->add($contactBuilder->build()[0])->getId();

        // добавили сделку в категории «доставка» в стадии «новый заказ» связанную с контактом
        $dealDeliveryCategoryId = $conf->getDealDeliveryCategoryId(
            $dealCategoriesService->list([], [], [], 1)->getDealCategories()
        );
        $dealStages = $dealStageService->list($dealDeliveryCategoryId)->getDealCategoryStages();
        $dealNewOrderStageId = $conf->getDealStageNewOrderStatusId($dealStages);
        $dealCompletedStageId = $conf->getDealStageOrderDeliveredStatusId($dealStages);


        // создали сделку на стадии new_order
        $dealId = $dealService->add((new DealBuilder())->build($dealDeliveryCategoryId, $contactId, $dealNewOrderStageId))->getId();

        // добавили табличную часть к сделке на основании существующих продуктов
        $productRows[] = (new ProductRowBuilder())
            ->withIsTaxIncluded(false)
            ->withTaxRate(new Percentage('0'))
            ->build(
                $dealId,
                $serviceBuilder->getCRMScope()->product()->get($productId)->product(),
                1
            );
        $serviceBuilder->getCRMScope()->dealProductRows()->set($dealId, $productRows);

        // выставляем сумму платежа бонусами
        $expectedBonusPayment = new Money(2000, $conf->getDefaultBonusCurrency());
        $dealUserFields->setPayWithBonuses(
            $dealId,
            $expectedBonusPayment
        );
        $expectedDealOpportunity = new Money(8000, $conf->getDefaultBonusCurrency());

        $expectedContactWelcomeBonus = $conf->getDefaultBonusWelcomeGift();

        $welcome = 1;

        // ждём начисления велком-бонуса
        sleep($conf->getBonusProcessingWaitingTimeout());

        $expectedProductRowsState[] = [
            'PRICE'            => 80,
            'PRICE_EXCLUSIVE'  => 80,
            'PRICE_NETTO'      => 100,
            'PRICE_BRUTTO'     => 100,
            'PRICE_ACCOUNT'    => '80.00',
            'QUANTITY'         => 1,
            'DISCOUNT_TYPE_ID' => 1,
            'DISCOUNT_RATE'    => 20,
            'DISCOUNT_SUM'     => 20,
            'TAX_RATE'         => 0,
            'TAX_INCLUDED'     => 'N',
        ];

        yield 'одна строка ТЧ, 1 шт, НДС нет' => [
            $contactId,
            $expectedContactWelcomeBonus,
            $dealId,
            $expectedDealOpportunity,
            $expectedBonusPayment,
            $expectedProductRowsState,
        ];
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Bitrix24\SDK\Services\CRM\Userfield\Exceptions\UserfieldNotFoundException
     * @testdox      Тестируем частичную оплату бонусами сделки с одним продуктом в ТЧ
     * @dataProvider predefinedDealsDataProvider
     */
    public function testBonusPaymentForOneProductRow(
        int $contactId,
        Money $expectedContactWelcomeBonus,
        int $dealId,
        Money $expectedDealOpportunity,
        Money $expectedBonusPayment,
        array $expectedProductRowsState
    ): void {
        $decimalParser = new DecimalMoneyParser(new ISOCurrencies());
        $decimalFormatter = new DecimalMoneyFormatter(new ISOCurrencies());
        $dealDeliveryCategoryId = $this->conf->getDealDeliveryCategoryId(
            $this->serviceBuilder->getCRMScope()->dealCategory()->list([], [], [], 1)->getDealCategories()
        );
        $dealStages = $this->serviceBuilder->getCRMScope()->dealCategoryStage()->list($dealDeliveryCategoryId)->getDealCategoryStages();
        $dealBonusPaymentStageId = $this->conf->getDealStageBonusPaymentStatusId($dealStages);

        // вот-тут сработал веб-хук к бонусному серверу и если есть, то начислился велком-бонус
        // для отладки тестов прикидываемся бонус-сервером
        if ($this->conf->isBonusServerEmulationActive()) {
            $this->contactUserFields->setBonusBalance(
                $contactId,
                $this->conf->getDefaultBonusWelcomeGift()
            );
        }

        // проверяем, что начислили велком-бонус
        $contact = $this->serviceBuilder->getCRMScope()->contact()->get($contactId)->contact();
        $contactBalance = $decimalParser->parse(
            $contact->getUserfieldByFieldName($this->contactUserFields->getBonusBalanceUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $contactBalance->equals($expectedContactWelcomeBonus),
            sprintf(
                'баланс контакта %s не совпадает с ожидаемым балансом %s после начисления велком-бонуса, он должен начисляться для новых контактов связанных со сделками на стадии new_order',
                $decimalFormatter->format($contactBalance),
                $decimalFormatter->format($this->conf->getDefaultBonusWelcomeGift())
            )
        );

        // передвинули сделку на стадию «пора платить»
        $updateResult = $this->serviceBuilder->getCRMScope()->deal()->update(
            $dealId,
            [
                'STAGE_ID' => $dealBonusPaymentStageId,
            ]
        )->isSuccess();
        $this->assertTrue($updateResult);

        // для отладки тестов прикидываемся бонус-сервером
        if ($this->conf->isBonusServerEmulationActive()) {
            // выставляем для контакта ожидаемый балнанс бонусов: велком бонус - сумма платежа
            $this->contactUserFields->setBonusBalance(
                $contactId,
                $this->conf->getDefaultBonusWelcomeGift()->subtract($expectedBonusPayment)
            );

            // выставляем в сделке сумму списания
            $this->dealUserFields->setDebitedBonuses(
                $dealId,
                $expectedBonusPayment
            );
        }
        // подождали, пока БС обработает сделку, что ожидаем:
        sleep($this->conf->getBonusProcessingWaitingTimeout());


        // 1. уменьшил welcome-баланс у контакта списав сумму платежа из суммы welcome-бонуса
        $contact = $this->serviceBuilder->getCRMScope()->contact()->get($contactId)->contact();
        $contactBalance = $decimalParser->parse(
            $contact->getUserfieldByFieldName($this->contactUserFields->getBonusBalanceUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $contactBalance->equals($this->conf->getDefaultBonusWelcomeGift()->subtract($expectedBonusPayment)),
            sprintf(
                'для контакта %s-%s итоговый баланс %s не совпадает с ожидаемым балансом %s, ожидаемый баланс это: wеlcome-бонус %s - сумма платежа %s',
                $contact->NAME . ' ' . $contact->SECOND_NAME . ' ' . $contact->LAST_NAME,
                $contact->ID,
                $decimalFormatter->format($contactBalance),
                $decimalFormatter->format($this->conf->getDefaultBonusWelcomeGift()->subtract($expectedBonusPayment)),
                $decimalFormatter->format($this->conf->getDefaultBonusWelcomeGift()),
                $decimalFormatter->format($expectedBonusPayment)
            )
        );

        // 2. указал сумму платежа в поле «списано» в сделке
        $deal = $this->serviceBuilder->getCRMScope()->deal()->get($dealId)->deal();
        $debitedBonuses = $decimalParser->parse(
            $deal->getUserfieldByFieldName($this->dealUserFields->getDebitedBonusesUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $expectedBonusPayment->equals($debitedBonuses),
            sprintf(
                'для сделки %s - %s на сумму %s не совпадает сумма списанных бонусов в поле «Списано бонусов», ожидали %s, получили %s',
                $deal->TITLE,
                $deal->ID,
                $deal->OPPORTUNITY,
                $decimalFormatter->format($expectedBonusPayment),
                $decimalFormatter->format($debitedBonuses)
            )
        );

        // 3. уменьшил сумму сделки применив монетарную скидку
        if ($this->conf->isBonusServerEmulationActive()) {
            // для отладки тестов прикидываемся бонус-сервером
            // подменяем сделку для прохождения теста
            $fakeDeal = new AbstractCrmItem(
                [
                    'OPPORTUNITY' => $decimalFormatter->format($expectedDealOpportunity),
                    'TITLE'       => $deal->TITLE,
                    'ID'          => $deal->ID,
                ]
            );
            $deal = $fakeDeal;
        }
        $this->assertTrue(
            $expectedDealOpportunity->equals(
                $decimalParser->parse(
                    $deal->OPPORTUNITY,
                    $this->conf->getDefaultBonusCurrency()
                )
            ),
            sprintf(
                'для сделки %s - %s на сумму %s не совпадает ожидаемая сумма сделки %s и реальная %s',
                $deal->TITLE,
                $deal->ID,
                $deal->OPPORTUNITY,
                $decimalFormatter->format($expectedDealOpportunity),
                $deal->OPPORTUNITY
            )
        );

        // 4. модифицировали табличную часть
        $actualProductRows = $this->serviceBuilder->getCRMScope()->dealProductRows()->get($dealId)->getProductRows();
        foreach ($actualProductRows as $cnt => $actRow) {
            //todo посмотреть, имеет ли смысл получения продукта
            $expRow = $expectedProductRowsState[$cnt];
            // для отладки тестов прикидываемся бонус-сервером
            // подменяем строку ТЧ для прохождения теста
            if ($this->conf->isBonusServerEmulationActive()) {
                $fakeRow = new AbstractCrmItem(
                    [
                        'ID'               => $actRow->ID,
                        'PRODUCT_NAME'     => $actRow->PRODUCT_NAME,
                        'PRICE'            => $expRow['PRICE'],
                        'QUANTITY'         => $expRow['QUANTITY'],
                        'PRICE_EXCLUSIVE'  => $expRow['PRICE_EXCLUSIVE'],
                        'PRICE_NETTO'      => $expRow['PRICE_NETTO'],
                        'PRICE_BRUTTO'     => $expRow['PRICE_BRUTTO'],
                        'PRICE_ACCOUNT'    => $expRow['PRICE_ACCOUNT'],
                        'DISCOUNT_SUM'     => $expRow['DISCOUNT_SUM'],
                        'DISCOUNT_TYPE_ID' => $expRow['DISCOUNT_TYPE_ID'],
                        'DISCOUNT_RATE'    => $expRow['DISCOUNT_RATE'],
                    ]
                );
                $actRow = $fakeRow;
            }
            print('----------' . PHP_EOL);
            print('           ID  | Название                   | Q | PRICE | P_EXCLUSIVE | P_NETTO | P_BRUTTO | P_ACCOUNT | D_SUM |D_TYPE_ID | D_RATE |' . PHP_EOL);
            print(sprintf(
                    'expected - %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |',
                    $actRow->ID,
                    substr($actRow->PRODUCT_NAME, 0, 30) . '…',
                    $expRow['QUANTITY'],
                    $expRow['PRICE'],
                    $expRow['PRICE_EXCLUSIVE'],
                    $expRow['PRICE_NETTO'],
                    $expRow['PRICE_BRUTTO'],
                    $expRow['PRICE_ACCOUNT'],
                    $expRow['DISCOUNT_SUM'],
                    $expRow['DISCOUNT_TYPE_ID'],
                    $expRow['DISCOUNT_RATE'],
                ) . PHP_EOL);
            print(sprintf(
                    'actual   - %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |',
                    $actRow->ID,
                    substr($actRow->PRODUCT_NAME, 0, 30) . '…',
                    $actRow->QUANTITY,
                    $actRow->PRICE,
                    $actRow->PRICE_EXCLUSIVE,
                    $actRow->PRICE_NETTO,
                    $actRow->PRICE_BRUTTO,
                    $actRow->PRICE_ACCOUNT,
                    $actRow->DISCOUNT_SUM,
                    $actRow->DISCOUNT_TYPE_ID,
                    $actRow->DISCOUNT_RATE,
                ) . PHP_EOL);


            // начинаем сравнивать построчно и выводиь человеческие пояснения
            // количество
            $this->assertEquals(
                $expRow['QUANTITY'],
                $actRow->QUANTITY,
                sprintf(
                    'для строки ТЧ %s - %s не совпадает количество (QUANTITY) %s с текущим %s',
                    $actRow->PRODUCT_NAME,
                    $actRow->ID,
                    $expRow['QUANTITY'],
                    $actRow->QUANTITY,
                )
            );
            // итоговая цена
            $this->assertEquals(
                $expRow['PRICE'],
                $actRow->PRICE,
                sprintf(
                    'для строки ТЧ %s - %s не совпадает ожидаемое значение цены (PRICE) %s с текущей %s, именно её видит клиент, к ней применены все скидки и налоги',
                    $actRow->PRODUCT_NAME,
                    $actRow->ID,
                    $expRow['PRICE'],
                    $actRow->PRICE,
                )
            );
            // PRICE_EXCLUSIVE цена без налога, но со скидкой
            $this->assertEquals(
                $expRow['PRICE_EXCLUSIVE'],
                $actRow->PRICE_EXCLUSIVE,
                sprintf(
                    'для строки ТЧ %s - %s не совпадает ожидаемое значение цены (PRICE_EXCLUSIVE) %s с текущей %s, цена без налога, но со скидкой',
                    $actRow->PRODUCT_NAME,
                    $actRow->ID,
                    $expRow['PRICE_EXCLUSIVE'],
                    $actRow->PRICE_EXCLUSIVE,
                )
            );
            // PRICE_NETTO цена без налога и без скидки
            $this->assertEquals(
                $expRow['PRICE_NETTO'],
                $actRow->PRICE_NETTO,
                sprintf(
                    'для строки ТЧ %s - %s не совпадает ожидаемое значение цены (PRICE_NETTO) %s с текущей %s, цена без налога, но со скидкой',
                    $actRow->PRODUCT_NAME,
                    $actRow->ID,
                    $expRow['PRICE_NETTO'],
                    $actRow->PRICE_NETTO,
                )
            );
            // PRICE_BRUTTO цена с налогом, но без скидки
            $this->assertEquals(
                $expRow['PRICE_BRUTTO'],
                $actRow->PRICE_BRUTTO,
                sprintf(
                    'для строки ТЧ %s - %s не совпадает ожидаемое значение цены (PRICE_BRUTTO) %s с текущей %s, цена с налогом, но без скидки',
                    $actRow->PRODUCT_NAME,
                    $actRow->ID,
                    $expRow['PRICE_BRUTTO'],
                    $actRow->PRICE_BRUTTO,
                )
            );
            // PRICE_ACCOUNT цена с налогом, но без скидки
            $this->assertEquals(
                $expRow['PRICE_ACCOUNT'],
                $actRow->PRICE_ACCOUNT,
                sprintf(
                    'для строки ТЧ %s - %s не совпадает ожидаемое значение цены (PRICE_ACCOUNT) %s с текущей %s, цена отформатированная для вывода в отчётах',
                    $actRow->PRODUCT_NAME,
                    $actRow->ID,
                    $expRow['PRICE_ACCOUNT'],
                    $actRow->PRICE_ACCOUNT,
                )
            );

            // скидки
            $this->assertEquals(
                $expRow['DISCOUNT_SUM'],
                $actRow->DISCOUNT_SUM,
                sprintf(
                    'для строки ТЧ %s - %s не совпадает ожидаемое значение (DISCOUNT_SUM) %s с текущим %s, это сума скидки',
                    $actRow->PRODUCT_NAME,
                    $actRow->ID,
                    $expRow['DISCOUNT_SUM'],
                    $actRow->DISCOUNT_SUM,
                )
            );

            // тип скидки
            $this->assertEquals(
                $expRow['DISCOUNT_TYPE_ID'],
                $actRow->DISCOUNT_TYPE_ID,
                sprintf(
                    'для строки ТЧ %s - %s не совпадает ожидаемое значение (DISCOUNT_TYPE_ID) %s с текущим %s, это тип скидки',
                    $actRow->PRODUCT_NAME,
                    $actRow->ID,
                    $expRow['DISCOUNT_TYPE_ID'],
                    $actRow->DISCOUNT_TYPE_ID,
                )
            );
            // процент скидки
            $this->assertEquals(
                $expRow['DISCOUNT_RATE'],
                $actRow->DISCOUNT_RATE,
                sprintf(
                    'для строки ТЧ %s - %s не совпадает ожидаемое значение (DISCOUNT_RATE) %s с текущим %s, это процент скидки',
                    $actRow->PRODUCT_NAME,
                    $actRow->ID,
                    $expRow['DISCOUNT_RATE'],
                    $actRow->DISCOUNT_RATE,
                )
            );
        }
    }

    public function setUp(): void
    {
        $this->serviceBuilder = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $this->conf = new PredefinedConfiguration();
        $this->calc = new PaymentAmountCalculator($this->conf);
        $this->contactUserFields = new ContactFields(
            $this->serviceBuilder->getCRMScope()->contactUserfield(),
            $this->serviceBuilder->getCRMScope()->contact(),
            LoggerBuilder::getLogger()
        );
        $this->dealUserFields = new DealFields(
            $this->serviceBuilder->getCRMScope()->dealUserfield(),
            $this->serviceBuilder->getCRMScope()->deal(),
            LoggerBuilder::getLogger()
        );
        $this->productBuilder = new ProductBuilder($this->conf->getDefaultBonusCurrency());
    }
}