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
    private ContactFields $contactUserFields;
    private DealFields $dealUserFields;

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
        // убедились, что поле «баланс бонусов» пусто
        $contact = $contactService->get($newContactId)->contact();
        $this->assertSame(
            $contact->getUserfieldByFieldName($this->contactUserFields->getBonusBalanceUserFieldName()),
            '0',
            sprintf('в поле %s должен быть 0', $this->contactUserFields->getBonusBalanceUserFieldName())
        );

        // добавили сделку в категории «доставка» в стадии «новый заказ» связанную с контактом
        $dealDeliveryCategoryId = $this->conf->getDealDeliveryCategoryId(
            $dealCategoriesService->list([], [], [], 1)->getDealCategories()
        );
        $dealStages = $dealStageService->list($dealDeliveryCategoryId)->getDealCategoryStages();
        $dealNewOrderStageId = $this->conf->getDealStageNewOrderStatusId($dealStages);
        $dealCompletedStageId = $this->conf->getDealStageOrderDeliveredStatusId($dealStages);
        // создали сделку и на стадии new_order
        $newDealId = $dealService->add((new DealBuilder())->build($dealDeliveryCategoryId, $newContactId, $dealNewOrderStageId))->getId();

        // вот-тут сработал веб-хук к бонусному серверу и если есть, то начислился велком-бонус
        // для отладки тестов прикидываемся бонус-сервером
        if ($this->conf->isBonusServerEmulationActive()) {
            $this->contactUserFields->setBonusBalance(
                $newContactId,
                $this->conf->getDefaultBonusWelcomeGift()
            );
        }
        // на внутренний счёт клиента
        // обновился суммарный баланс бонусов в пользовательском поле клиента
        sleep($this->conf->getBonusProcessingWaitingTimeout());

        // перечитываем контакт и проверяем, что у него обновился баланс
        $contact = $contactService->get($newContactId)->contact();
        $updatedBalance = $decimalParser->parse(
            $contact->getUserfieldByFieldName($this->contactUserFields->getBonusBalanceUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $updatedBalance->equals($this->conf->getDefaultBonusWelcomeGift()),
            sprintf(
                'баланс контакта %s не совпадает с ожидаемым балансом %s после начисления велком-бонуса, он должен начисляться для новых контактов связанных со сделками на стадии new_order',
                $decimalFormatter->format($updatedBalance),
                $decimalFormatter->format($this->conf->getDefaultBonusWelcomeGift())
            )
        );

        // добавили табличную часть к сделке на основании существующих продуктов
        $products = $this->serviceBuilder->getCRMScope()->product()
            ->list([], [], [], 0)->getProducts();
        $productRows = (new ProductRowsBuilder(
            new ProductRowBuilder()
        ))->build($newDealId, 5, $products);
        $this->serviceBuilder->getCRMScope()->dealProductRows()->set($newDealId, $productRows);

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

        // для отладки тестов прикидываемся бонус-сервером
        if ($this->conf->isBonusServerEmulationActive()) {
            // выставляем для контакта ожидаемый балнанс бонусов
            $this->contactUserFields->setBonusBalance(
                $newContactId,
                $this->conf->getDefaultBonusWelcomeGift()->add($calculatedBonus)
            );
            // выставляем в сделке ожидаемый баланс бонусов
            $this->dealUserFields->setAccruedBonuses(
                $newDealId,
                $calculatedBonus
            );
        }

        // перечитываем контакт и проверяем, что у него обновился баланс: начисление со сделки + велком-бонус
        $contact = $contactService->get($newContactId)->contact();
        $contactFinalBalance = $decimalParser->parse(
            $contact->getUserfieldByFieldName($this->contactUserFields->getBonusBalanceUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $calculatedBonus->equals($contactFinalBalance->subtract($this->conf->getDefaultBonusWelcomeGift())),
            sprintf(
                'для сделки %s - %s на сумму %s не совпадает рассчётная сумма начисленных бонусов %s по проценту %s с текущей %s из поля контакта с id %s связанного со сделкой за вычетом велком-бонуса',
                $deal->TITLE,
                $deal->ID,
                $decimalFormatter->format($dealAmount),
                $decimalFormatter->format($calculatedBonus),
                $this->conf->getDefaultBonusAccrualPercentage()->format(),
                $decimalFormatter->format($updatedBalance),
                $deal->CONTACT_ID
            )
        );

        // перечитываем сделку и проверяем, что в сделке проапдейтили поле «начислено бонусов»
        $deal = $dealService->get($newDealId)->deal();
        $dealAccuredBonuses = $decimalParser->parse(
            $deal->getUserfieldByFieldName($this->dealUserFields->getAccruedBonusesUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $dealAccuredBonuses->equals($calculatedBonus),
            sprintf(
                'для сделки %s - %s на сумму %s в поле «Начислено бонусов» сумма %s начисленных бонусов не совпадает с рассчётным количеством бонусов %s',
                $deal->TITLE,
                $deal->ID,
                $deal->OPPORTUNITY,
                $decimalFormatter->format($dealAccuredBonuses),
                $decimalFormatter->format($calculatedBonus)
            )
        );
    }

    public function setUp(): void
    {
        $this->serviceBuilder = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $this->conf = new PredefinedConfiguration();
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
    }
}