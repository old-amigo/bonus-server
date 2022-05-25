<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Tests\Integration\BonusPaymentUseCases;

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
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PaymentAmountCalculator;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\ContactFields;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\DealFields;

class CanceledBonusPaymentTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;
    private PredefinedConfiguration $conf;
    private ContactFields $contactUserFields;
    private DealFields $dealUserFields;
    private PaymentAmountCalculator $calc;

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Bitrix24\SDK\Services\CRM\Userfield\Exceptions\UserfieldNotFoundException
     * @testdox Тестируем отмену платежа для сделок без табличной части
     */
    public function testCanceledBonusPaymentForDealWithoutTableRows(): void
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
        $dealBonusPaymentStageId = $this->conf->getDealStageBonusPaymentStatusId($dealStages);
        // создали сделку на стадии new_order
        $dealId = $dealService->add(
            (new DealBuilder())
                ->build($dealDeliveryCategoryId, $newContactId, $dealNewOrderStageId)
        )->getId();

        // вот-тут сработал веб-хук к бонусному серверу и если есть, то начислился велком-бонус
        // для отладки тестов прикидываемся бонус-сервером
        if ($this->conf->isBonusServerEmulationActive()) {
            $this->contactUserFields->setBonusBalance(
                $newContactId,
                $this->conf->getDefaultBonusWelcomeGift()
            );
        }
        // на внутренний счёт клиента
        // обновился суммарный баланс бонусов в пользовательском поле клиента если есть велком-бонус
        sleep($this->conf->getBonusProcessingWaitingTimeout());
        $contact = $contactService->get($newContactId)->contact();
        $startBalance = $decimalParser->parse(
            $contact->getUserfieldByFieldName($this->contactUserFields->getBonusBalanceUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $startBalance->equals($this->conf->getDefaultBonusWelcomeGift()),
            sprintf(
                'баланс контакта %s не совпадает с ожидаемым балансом %s после начисления велком-бонуса, он должен начисляться для новых контактов связанных со сделками на стадии new_order',
                $decimalFormatter->format($startBalance),
                $decimalFormatter->format($this->conf->getDefaultBonusWelcomeGift())
            )
        );

        $dealOpportunity = new Money('500000', $this->conf->getDefaultBonusCurrency());
        // выставили сумму сделки и реалистичную сумму платежа, но не добавили табличную часть xDDDD
        $dealService->update(
            $dealId,
            [
                'OPPORTUNITY' => $decimalFormatter->format($dealOpportunity),
            ]
        );
        // выставили сумму платежа бонусами в сделке
        $this->dealUserFields->setPayWithBonuses(
            $dealId,
            $this->calc->getAvailableRandomBonusPayment($dealOpportunity)
        );
        $deal = $dealService->get($dealId)->deal();

        // передвинули сделку на стадию «оплачиваем бонусами»
        $updateResult = $dealService->update(
            $deal->ID,
            [
                'STAGE_ID' => $dealBonusPaymentStageId,
            ]
        )->isSuccess();
        $this->assertTrue($updateResult);

        // ждём, когда отработает БС
        sleep($this->conf->getBonusProcessingWaitingTimeout());

        // ожидания:
        // т.к. сделка без ТЧ, то мы должны её завернуть, скидку нельзя распределить, будет:
        // - баланс контакта не изменился
        $contact = $contactService->get($newContactId)->contact();
        $finalBalance = $decimalParser->parse(
            $contact->getUserfieldByFieldName($this->contactUserFields->getBonusBalanceUserFieldName()),
            $this->conf->getDefaultBonusCurrency()
        );
        $this->assertTrue(
            $startBalance->equals($finalBalance),
            sprintf(
                'баланс бонусов у контакта %s изменился, был %s, а стал %s, тест провален',
                $contact->ID,
                $decimalFormatter->format($startBalance),
                $decimalFormatter->format($finalBalance)
            )
        );
        // - сумма списанных бонусов нулевая
        $deal = $dealService->get($dealId)->deal();
        $debitedBonuses = $deal->getUserfieldByFieldName($this->dealUserFields->getDebitedBonusesUserFieldName());
        $this->assertEquals(
            '0',
            $debitedBonuses,
            sprintf(
                'по сделке %s - %s оплата бонусами должна была быть отменена, сумма списаных бонусов должна быть нулевой, а она %s',
                $deal->TITLE,
                $deal->ID,
                $debitedBonuses
            )
        );
        // - сумма начисленных бонусов нулевая
        $accruedBonuses = $deal->getUserfieldByFieldName($this->dealUserFields->getAccruedBonusesUserFieldName());
        $this->assertEquals(
            '0',
            $accruedBonuses,
            sprintf(
                'по сделке %s - %s оплата бонусами должна была быть отменена, сумма начисленных бонусов должна быть нулевой, а она %s',
                $deal->TITLE,
                $deal->ID,
                $accruedBonuses
            )
        );
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
    }
}