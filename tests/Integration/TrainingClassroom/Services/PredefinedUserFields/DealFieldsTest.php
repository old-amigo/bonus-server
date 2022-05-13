<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Tests\Integration\TrainingClassroom\Services\PredefinedUserFields;

use Bitrix24\SDK\Services\ServiceBuilder;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Money;
use Money\Parser\DecimalMoneyParser;
use PHPUnit\Framework\TestCase;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\Bitrix24ApiClientServiceBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DealCategoryQueries;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DealStageQueries;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ContactBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\DealBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\LoggerBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\ContactFields;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\DealFields;

class DealFieldsTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;
    private PredefinedConfiguration $conf;
    private DealFields $dealUserField;
    private DealStageQueries $b24DealStageQueries;
    private DealCategoryQueries $b24DealCategoryQueries;

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Bitrix24\SDK\Services\CRM\Userfield\Exceptions\UserfieldNotFoundException
     * @testdox Предустановленное пользовательское поле контакта «К оплате бонусами» работает корректно
     */
    public function testSetPayWithBonuses(): void
    {
        $categories = $this->serviceBuilder->getCRMScope()->dealCategory()
            ->list([], [], [], 0)->getDealCategories();
        $deliveryCategoryId = $this->b24DealCategoryQueries->getDeliveryCategoryId($categories);

        $b24DeliveryCategoryStages = $this->serviceBuilder->getCRMScope()->dealCategoryStage()
            ->list($deliveryCategoryId)->getDealCategoryStages();
        $decimalParser = new DecimalMoneyParser(new ISOCurrencies());

        // добавили контакт
        $contact = $this->serviceBuilder->getCRMScope()->contact()->get(
            $this->serviceBuilder->getCRMScope()->contact()->add((new ContactBuilder())->build())->getId()
        )->contact();
        $b24Contacts = $this->serviceBuilder->getCRMScope()->contact()
            ->list([], [], [], 0)->getContacts();
        $randomContactId = (int)array_column($b24Contacts, 'ID')[random_int(0, count($b24Contacts) - 1)];

        // создали сделку
        $dealId = $this->serviceBuilder->getCRMScope()->deal()->add(
            (new DealBuilder())->build(
                $deliveryCategoryId,
                $randomContactId,
                $this->conf->getDealStageNewOrderStatusId($b24DeliveryCategoryStages),
            )
        )->getId();

        // выставляем сколько бонусов было потрачено
        $bonuses = new Money('313443', $this->conf->getDefaultBonusCurrency());
        $this->dealUserField->setPayWithBonuses($dealId, $bonuses);

        // убедились, что бонусы сохранились куда надо
        $bonusBalance = $decimalParser->parse(
            $this->serviceBuilder->getCRMScope()->deal()->get($dealId)->deal()->getUserfieldByFieldName(
                $this->dealUserField->getPayWithBonusesUserFieldName()
            ),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertEquals($bonuses, $bonusBalance);
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Bitrix24\SDK\Services\CRM\Userfield\Exceptions\UserfieldNotFoundException
     * @testdox Предустановленное пользовательское поле контакта «К оплате бонусами» работает корректно
     */
    public function testSetAccruedBonuses(): void
    {
        $categories = $this->serviceBuilder->getCRMScope()->dealCategory()
            ->list([], [], [], 0)->getDealCategories();
        $deliveryCategoryId = $this->b24DealCategoryQueries->getDeliveryCategoryId($categories);

        $b24DeliveryCategoryStages = $this->serviceBuilder->getCRMScope()->dealCategoryStage()
            ->list($deliveryCategoryId)->getDealCategoryStages();
        $decimalParser = new DecimalMoneyParser(new ISOCurrencies());

        // добавили контакт
        $contact = $this->serviceBuilder->getCRMScope()->contact()->get(
            $this->serviceBuilder->getCRMScope()->contact()->add((new ContactBuilder())->build())->getId()
        )->contact();
        $b24Contacts = $this->serviceBuilder->getCRMScope()->contact()
            ->list([], [], [], 0)->getContacts();
        $randomContactId = (int)array_column($b24Contacts, 'ID')[random_int(0, count($b24Contacts) - 1)];

        // создали сделку
        $dealId = $this->serviceBuilder->getCRMScope()->deal()->add(
            (new DealBuilder())->build(
                $deliveryCategoryId,
                $randomContactId,
                $this->conf->getDealStageNewOrderStatusId($b24DeliveryCategoryStages),
            )
        )->getId();

        // выставляем сколько бонусов было потрачено
        $bonuses = new Money('313443', $this->conf->getDefaultBonusCurrency());
        $this->dealUserField->setAccruedBonuses($dealId, $bonuses);

        // убедились, что бонусы сохранились куда надо
        $bonusBalance = $decimalParser->parse(
            $this->serviceBuilder->getCRMScope()->deal()->get($dealId)->deal()->getUserfieldByFieldName(
                $this->dealUserField->getAccruedBonusesUserFieldName()
            ),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertEquals($bonuses, $bonusBalance);
    }

    public function setUp(): void
    {
        $this->serviceBuilder = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $this->conf = new PredefinedConfiguration();
        $this->dealUserField = new DealFields(
            $this->serviceBuilder->getCRMScope()->dealUserfield(),
            $this->serviceBuilder->getCRMScope()->deal(),
            LoggerBuilder::getLogger()
        );

        $this->b24DealStageQueries = new DealStageQueries();
        $this->b24DealCategoryQueries = new DealCategoryQueries($this->conf);
    }
}