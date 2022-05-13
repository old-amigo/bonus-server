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
use Rarus\Interns\BonusServer\TrainingClassroom\Services\DemoDataBuilders\ContactBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\LoggerBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\ContactFields;

class ContactFieldsTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;
    private PredefinedConfiguration $conf;
    private ContactFields $contactUserField;

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Bitrix24\SDK\Services\CRM\Userfield\Exceptions\UserfieldNotFoundException
     * @testdox Предустановленное пользовательское поле контакта «Баланс Бонусов» работает корректно
     */
    public function testSetBonusBalanceToContact(): void
    {
        $contactService = $this->serviceBuilder->getCRMScope()->contact();
        $decimalParser = new DecimalMoneyParser(new ISOCurrencies());

        // добавили контакт
        $contact = $contactService->get($contactService->add((new ContactBuilder())->build())->getId())->contact();

        // записали бонусы
        $bonuses = new Money('313443', $this->conf->getDefaultBonusCurrency());
        $this->contactUserField->setBonusBalance($contact->ID, $bonuses);

        // получили баланс контакта из Б24
        // todo дожать вопрос с автоматическим приведением типов
        $bonusBalance = $decimalParser->parse(
            $contactService->get($contact->ID)->contact()->getUserfieldByFieldName(
                $this->contactUserField->getBonusBalanceUserFieldName()
            ),
            $this->conf->getDefaultBonusCurrency()
        );

        $this->assertEquals($bonuses, $bonusBalance);
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
    }
}