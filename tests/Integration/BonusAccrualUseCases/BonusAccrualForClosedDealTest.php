<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Tests\Integration\BonusAccrualUseCases;

use Bitrix24\SDK\Services\ServiceBuilder;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;
use PHPUnit\Framework\TestCase;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\Bitrix24ApiClientServiceBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ContactBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\DealBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ProductRowBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ProductRowsBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\LoggerBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\ContactFields;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\DealFields;

class BonusAccrualForClosedDealTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;
    private PredefinedConfiguration $conf;
    private ContactFields $contactUserField;
    private DealFields $dealFields;

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Bitrix24\SDK\Services\CRM\Userfield\Exceptions\UserfieldNotFoundException
     * @testdox Предустановленное пользовательское поле контакта «Баланс Бонусов» работает корректно
     */
    public function testAccrualBonusTransaction(): void
    {
        $decimalParser = new DecimalMoneyParser(new ISOCurrencies());
        $decimalFormatter = new DecimalMoneyFormatter(new ISOCurrencies());
        $contactService = $this->serviceBuilder->getCRMScope()->contact();
        $dealService = $this->serviceBuilder->getCRMScope()->deal();
        $dealCategoriesService = $this->serviceBuilder->getCRMScope()->dealCategory();
        $dealStageService = $this->serviceBuilder->getCRMScope()->dealCategoryStage();

        // добавили контакт
        $newContactId = $contactService->add((new ContactBuilder())->build())->getId();

        // добавили сделку в категории «доставка» в стадии «новый заказ» связанную с контактом
        $dealDeliveryCategoryId = $this->conf->getDealDeliveryCategoryId(
            $dealCategoriesService->list([], [], [], 1)->getDealCategories()
        );
        $dealStages = $dealStageService->list($dealDeliveryCategoryId)->getDealCategoryStages();
        $dealNewOrderStageId = $this->conf->getDealStageNewOrderStatusId($dealStages);
        $dealCompletedStageId = $this->conf->getDealStageOrderDeliveredStatusId($dealStages);
        $newDealId = $dealService->add((new DealBuilder())->build($dealDeliveryCategoryId, $newContactId, $dealNewOrderStageId))->getId();

        // добавили табличную часть к сделке на основании существующих продуктов
        $products = $this->serviceBuilder->getCRMScope()->product()
            ->list([], [], [], 0)->getProducts();
        $productRows = (new ProductRowsBuilder(
            new ProductRowBuilder()
        ))->build($newDealId, 5, $products);
        $this->serviceBuilder->getCRMScope()->dealProductRows()->set($newDealId, $productRows);

        // убедились, что поле «начислено бонусов» пусто
        $contact = $contactService->get($newContactId)->contact();
        $this->assertSame(
            $contact->getUserfieldByFieldName($this->contactUserField->getBonusBalanceUserFieldName()),
            '0',
            sprintf('в поле %s должен быть 0', $this->contactUserField->getBonusBalanceUserFieldName())
        );

        // рассчитали сумму бонусного начисления
        $deal = $dealService->get($newDealId)->deal();
        $dealAmount = $decimalParser->parse($deal->OPPORTUNITY, $deal->CURRENCY_ID);
        $calculatedBonus = $this->conf->getDefaultBonusAccrualPercentage()->calculateVatFor($dealAmount);

        // передвинули сделку на стадию «выиграли»
        $updateResult = $dealService->update(
            $deal->ID,
            [
                'STAGE_ID' => $dealCompletedStageId,
            ]
        )->isSuccess();
        $this->assertTrue($updateResult);

        // подождали, пока БС обработает сделку
        // бонусный сервер:
        // 1. начисляет бонус контакту у себя в БД по конкретной сделке
        // 2. обновляет суммарный баланс бонусов у контакта в пользовательском поле
        // 3. фиксирует в сделке в пользовательском поле сумму начисленных бонусов по этой сделке
        sleep($this->conf->getBonusProcessingWaitingTimeout());

        // перечитываем контакт и проверяем, что у него обновился баланс
        $contact = $contactService->get($newContactId)->contact();
        $updatedBalance = $decimalParser->parse(
            $contact->getUserfieldByFieldName($this->contactUserField->getBonusBalanceUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $calculatedBonus->equals($updatedBalance),
            sprintf(
                'для сделки %s - %s на сумму %s не совпадает рассчётная сумма начисленных бонусов %s по проценту %s с текущей %s из поля контакта с id %s связанного со сделкой',
                $deal->TITLE,
                $deal->ID,
                $decimalFormatter->format($dealAmount),
                $decimalFormatter->format($calculatedBonus),
                $this->conf->getDefaultBonusAccrualPercentage()->format(),
                $decimalFormatter->format($updatedBalance),
                $deal->CONTACT_ID
            )
        );
        //todo перечитываем сделку и проверяем, что в сделке проапдейтили поле «начислено бонусов»
    }

    public function setUp(): void
    {
        $this->serviceBuilder = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $this->conf = new PredefinedConfiguration();
        $this->contactUserField = new ContactFields(
            $this->serviceBuilder->getCRMScope()->contactUserfield(),
            $this->serviceBuilder->getCRMScope()->contact(),
            LoggerBuilder::getLogger()
        );
        $this->dealFields = new DealFields(
            $this->serviceBuilder->getCRMScope()->dealUserfield(),
            $this->serviceBuilder->getCRMScope()->deal(),
            LoggerBuilder::getLogger()
        );
    }
}