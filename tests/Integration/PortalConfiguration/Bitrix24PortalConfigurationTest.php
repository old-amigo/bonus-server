<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\Tests\Integration\PortalConfiguration;

use Bitrix24\SDK\Services\ServiceBuilder;
use PHPUnit\Framework\TestCase;
use Rarus\Interns\BonusServer\TrainingClassroom\Services\Bitrix24ApiClientServiceBuilder;

class Bitrix24PortalConfigurationTest extends TestCase
{
    private ServiceBuilder $serviceBuilder;

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


    public function setUp(): void
    {
        $this->serviceBuilder = Bitrix24ApiClientServiceBuilder::getServiceBuilder();
    }
}