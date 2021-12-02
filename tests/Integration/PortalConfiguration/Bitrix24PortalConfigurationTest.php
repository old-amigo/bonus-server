<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Tests\Integration\PortalConfiguration;

use Bitrix24\SDK\Services\ServiceBuilder;
use PHPUnit\Framework\TestCase;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\Bitrix24ApiClientServiceBuilder;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration;

class Bitrix24PortalConfigurationTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;
    /**
     * @var \Rarus\Interns\BonusServer\TrainingClassroom\Services\PredefinedConfiguration
     */
    private PredefinedConfiguration $conf;

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
        $this->assertGreaterThan(
            1,
            $this->serviceBuilder->getCRMScope()->contact()->countByFilter([]),
            'В Битрикс24 нет контактов, требуется их добавить'
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
            'В Битрикс24 нет товаров, требуется их добавить в товарный каталог'
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
            $this->conf->getDealCategoryId(
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
                    $this->conf->getDealCategoryId(
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
                    $this->conf->getDealCategoryId(
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
                    $this->conf->getDealCategoryId(
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
                    $this->conf->getDealCategoryId(
                        $this->serviceBuilder->getCRMScope()->dealCategory()->list([], [], [], 1)->getDealCategories()
                    )
                )->getDealCategoryStages()
            )
        );
    }

    public function setUp(): void
    {
        $this->serviceBuilder = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
        $this->conf = new PredefinedConfiguration();
    }
}