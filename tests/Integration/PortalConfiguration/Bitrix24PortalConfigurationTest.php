<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Tests\Integration\PortalConfiguration;

use Bitrix24\SDK\Services\ServiceBuilder;
use PHPUnit\Framework\TestCase;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\Bitrix24ApiClientServiceBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\LoggerBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\ContactFields;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedUserFields\DealFields;

class Bitrix24PortalConfigurationTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;
    private PredefinedConfiguration $conf;
    private ContactFields $b24ContactFields;
    private DealFields $b24DealFields;

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @testdox Смогли подключиться к порталу по API
     */
    public function testBitrix24PortalConnected(): void
    {
        $this->assertGreaterThan(
            1,
            count(
                $this->serviceBuilder->getMainScope()->main()->getUserProfile()->
                getResponseData()->getResult()->getResultData()
            )
        );
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @testdox Смогли подключиться к порталу по API
     */
    public function testBitrix24CrmHasContacts(): void
    {
        $this->assertGreaterThanOrEqual(
            1,
            $this->serviceBuilder->getCRMScope()->contact()->countByFilter([]),
            'В Битрикс24 нет контактов, требуется их добавить, выполните в консоли команду «php bin/console generate:contacts» '
        );
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @testdox Смогли подключиться к порталу по API
     */
    public function testBitrix24CrmHasProducts(): void
    {
        $this->assertGreaterThan(
            1,
            $this->serviceBuilder->getCRMScope()->product()->countByFilter(),
            'В Битрикс24 нет товаров, требуется их добавить в товарный каталог, выполните в консоли команду «php bin/console  generate:products»'
        );
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     * @testdox Есть дефолтная категория в которой будет вестись вся работа
     */
    public function testDefaultCategoryExists(): void
    {
        $this->assertGreaterThanOrEqual(
            1,
            $this->conf->getDealDeliveryCategoryId(
                $this->serviceBuilder->getCRMScope()->dealCategory()->list([], [], [], 1)->getDealCategories()
            )
        );
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     * @testdox В направлении delivery есть стадия new_order
     */
    public function testDealStageNewOrderExists(): void
    {
        $this->assertNotEmpty(
            $this->conf->getDealStageNewOrderStatusId(
                $this->serviceBuilder->getCRMScope()->dealCategoryStage()->list(
                    $this->conf->getDealDeliveryCategoryId(
                        $this->serviceBuilder->getCRMScope()->dealCategory()->list([], [], [], 1)->getDealCategories()
                    )
                )->getDealCategoryStages()
            )
        );
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     * @testdox В направлении delivery есть стадия bonus_payment
     */
    public function testDealStageBonusPaymentExists(): void
    {
        $this->assertNotEmpty(
            $this->conf->getDealStageBonusPaymentStatusId(
                $this->serviceBuilder->getCRMScope()->dealCategoryStage()->list(
                    $this->conf->getDealDeliveryCategoryId(
                        $this->serviceBuilder->getCRMScope()->dealCategory()->list([], [], [], 1)->getDealCategories()
                    )
                )->getDealCategoryStages()
            )
        );
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     * @testdox В направлении delivery есть стадия order_delivered
     */
    public function testDealStageOrderDeliveredExists(): void
    {
        $this->assertNotEmpty(
            $this->conf->getDealStageOrderDeliveredStatusId(
                $this->serviceBuilder->getCRMScope()->dealCategoryStage()->list(
                    $this->conf->getDealDeliveryCategoryId(
                        $this->serviceBuilder->getCRMScope()->dealCategory()->list([], [], [], 1)->getDealCategories()
                    )
                )->getDealCategoryStages()
            )
        );
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     * @testdox В направлении delivery есть стадия order_canceled
     */
    public function testDealStageOrderCancelledExists(): void
    {
        $this->assertNotEmpty(
            $this->conf->getDealStageOrderCancelledStatusId(
                $this->serviceBuilder->getCRMScope()->dealCategoryStage()->list(
                    $this->conf->getDealDeliveryCategoryId(
                        $this->serviceBuilder->getCRMScope()->dealCategory()->list([], [], [], 1)->getDealCategories()
                    )
                )->getDealCategoryStages()
            )
        );
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     * @testdox У контакта созданы служебные пользовательские поля
     */
    public function testContactHasPredefinedUserFields(): void
    {
        $this->b24ContactFields->assertUserFieldsExists();
        $this->assertTrue(true);
    }

    /**
     * @throws \Bitrix24\SDK\Core\Exceptions\BaseException
     * @throws \Bitrix24\SDK\Core\Exceptions\TransportException
     * @throws \Rarus\Interns\BonusServer\TrainingClassroom\Exceptions\WrongBitrix24ConfigurationException
     * @testdox У сделки созданы служебные пользовательские поля
     */
    public function testDealHasPredefinedUserFields(): void
    {
        $this->b24DealFields->assertUserFieldsExists();
        $this->assertTrue(true);
    }

    /**
     * @covers  \Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration::getDefaultBonusAccrualPercentage
     * @testdox Дефолтный процент начисления бонусов от успешной сделки
     */
    public function testDefaultBonusAccrualPercentageExists(): void
    {
        $this->conf->getDefaultBonusAccrualPercentage();
        $this->assertTrue(true);
    }

    /**
     * @covers  \Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration::getDefaultBonusAccrualPercentage
     * @testdox Дефолтный процент максимально-возможной частичной оплаты сделки бонусами
     */
    public function testDefaultBonusMaximumPaymentPercentageExists(): void
    {
        $this->conf->getDefaultBonusMaximumPaymentPercentage();
        $this->assertTrue(true);
    }

    /**
     * @covers  \Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration::getDefaultBonusWelcomeGift
     * @testdox Дефолтный размер велком-бонуса для новых контактов задан
     */
    public function testDefaultBonusWelcomeGiftExists(): void
    {
        $this->conf->getDefaultBonusWelcomeGift();
        $this->assertTrue(true);
    }

    /**
     * @covers  \Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration::isBonusServerEmulationActive
     * @testdox В конфиге есть флаг эмуляции работы БС
     */
    public function testIsBonusServerEmulationActive(): void
    {
        $this->conf->isBonusServerEmulationActive();
        $this->assertTrue(true);
    }

    public function setUp(): void
    {
        $this->serviceBuilder = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $this->conf = new PredefinedConfiguration();
        $this->b24ContactFields = new ContactFields(
            $this->serviceBuilder->getCRMScope()->contactUserfield(),
            $this->serviceBuilder->getCRMScope()->contact(),
            LoggerBuilder::getLogger()
        );
        $this->b24DealFields = new DealFields(
            $this->serviceBuilder->getCRMScope()->dealUserfield(),
            $this->serviceBuilder->getCRMScope()->deal(),
            LoggerBuilder::getLogger()
        );
    }
}