<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services;

use Bitrix24\SDK\Services\ServiceBuilder;
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
}