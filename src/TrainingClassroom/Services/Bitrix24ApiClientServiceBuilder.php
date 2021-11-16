<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services;

use Bitrix24\SDK\Services\ServiceBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class Bitrix24ApiClientServiceBuilder
{
    private string $webhookUrl;
    private LoggerInterface $logger;

    /**
     * @param string                   $webhookUrl
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(string $webhookUrl, LoggerInterface $logger)
    {
        $this->webhookUrl = $webhookUrl;
        $this->logger = $logger;
    }

    /**
     * @return \Bitrix24\SDK\Services\ServiceBuilder
     * @throws \Bitrix24\SDK\Core\Exceptions\InvalidArgumentException
     */
    public function build(): ServiceBuilder
    {
        $core = (new \Bitrix24\SDK\Core\CoreBuilder())
            ->withLogger($this->logger)
            ->withWebhookUrl($this->webhookUrl)
            ->build();
        $batch = new \Bitrix24\SDK\Core\Batch($core, $this->logger);

        return new ServiceBuilder($core, $batch, $this->logger);
    }

    /**
     * @return \Bitrix24\SDK\Services\ServiceBuilder
     * @throws \Bitrix24\SDK\Core\Exceptions\InvalidArgumentException
     */
    public static function getServiceBuilder(): ServiceBuilder
    {
        //todo накинуть проверок
        $log = new Logger('demo-data-generator');
        $log->pushHandler(new StreamHandler($_ENV['LOGS_FILE'], (int)$_ENV['LOGS_LEVEL']));
        $log->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor(true, true));

        return (new self(
            (string)$_ENV['BITRIX24_WEBHOOK'],
            $log
        ))->build();
    }
}