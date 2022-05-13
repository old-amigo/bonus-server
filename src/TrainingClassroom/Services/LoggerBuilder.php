<?php

declare(strict_types=1);

namespace Rarus\Interns\BonusServer\TrainingClassroom\Services;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LoggerBuilder
{
    /**
     * @return \Psr\Log\LoggerInterface
     * @throws \Exception
     */
    public static function getLogger(): LoggerInterface
    {
        //todo накинуть проверок
        $log = new Logger('demo-data-generator');
        $log->pushHandler(new StreamHandler($_ENV['LOGS_FILE'], (int)$_ENV['LOGS_LEVEL']));
        $log->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor(true, true));

        return $log;
    }
}