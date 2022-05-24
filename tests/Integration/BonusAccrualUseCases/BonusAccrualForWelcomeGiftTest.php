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

class BonusAccrualForWelcomeGiftTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;
    private PredefinedConfiguration $conf;
    private ContactFields $contactUserFields;

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Bitrix24\SDK\Services\CRM\Userfield\Exceptions\UserfieldNotFoundException
     * @testdox Предустановленное пользовательское поле контакта «Баланс Бонусов» работает корректно
     */
    public function testWelcomeBonusGift(): void
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
        $deal = $dealService->get($newDealId)->deal();

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
                'баланс контакта %s - %s составляет %s бонусов и не совпадает с ожидаемым балансом %s бонусов после начисления велком-бонуса, он должен начисляться для новых контактов связанных со сделками на стадии new_order, тест для сделки %s-%s провален',
                $contact->NAME . ' ' . $contact->SECOND_NAME . ' ' . $contact->LAST_NAME,
                $contact->ID,
                $decimalFormatter->format($updatedBalance),
                $decimalFormatter->format($this->conf->getDefaultBonusWelcomeGift()),
                $deal->TITLE,
                $deal->ID
            )
        );
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Bitrix24\SDK\Services\CRM\Userfield\Exceptions\UserfieldNotFoundException
     * @testdox Предустановленное пользовательское поле контакта «Баланс Бонусов» работает корректно
     */
    public function testDoubleWelcomeBonusGift(): void
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
        $deal = $dealService->get($newDealId)->deal();

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
        $startBalance = $decimalParser->parse(
            $contact->getUserfieldByFieldName($this->contactUserFields->getBonusBalanceUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $startBalance->equals($this->conf->getDefaultBonusWelcomeGift()),
            sprintf(
                'баланс контакта %s - %s составляет %s бонусов и не совпадает с ожидаемым балансом %s бонусов после начисления велком-бонуса, он должен начисляться для новых контактов связанных со сделками на стадии new_order, тест для сделки %s-%s провален',
                $contact->NAME . ' ' . $contact->SECOND_NAME . ' ' . $contact->LAST_NAME,
                $contact->ID,
                $decimalFormatter->format($startBalance),
                $decimalFormatter->format($this->conf->getDefaultBonusWelcomeGift()),
                $deal->TITLE,
                $deal->ID
            )
        );

        // убеждаемся, что повторного начисления велком-бонуса не произойдёт
        // создали вторую сделку и на стадии new_order
        $secondDealId = $dealService->add((new DealBuilder())->build($dealDeliveryCategoryId, $newContactId, $dealNewOrderStageId))->getId(
        );
        $deal = $dealService->get($secondDealId)->deal();

        // перечитываем контакт и проверяем, что у него НЕ обновился баланс
        $contact = $contactService->get($newContactId)->contact();
        $finalBalance = $decimalParser->parse(
            $contact->getUserfieldByFieldName($this->contactUserFields->getBonusBalanceUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $finalBalance->equals($this->conf->getDefaultBonusWelcomeGift()),
            sprintf(
                'итоговый баланс контакта %s - %s составляет %s бонусов и не совпадает с ожидаемым балансом %s бонусов после начисления велком-бонуса, он должен начисляться для новых контактов связанных со сделками на стадии new_order, но только один раз, тест для сделки %s-%s провален',
                $contact->NAME . ' ' . $contact->SECOND_NAME . ' ' . $contact->LAST_NAME,
                $contact->ID,
                $decimalFormatter->format($finalBalance),
                $decimalFormatter->format($this->conf->getDefaultBonusWelcomeGift()),
                $deal->TITLE,
                $deal->ID
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
    }
}