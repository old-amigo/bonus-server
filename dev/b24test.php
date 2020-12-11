<?php

declare(strict_types=1);
require_once 'vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;


$log = new Logger('name');
$log->pushHandler(new StreamHandler('logs/b24-api-client-debug.log', Logger::DEBUG));
$log->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor(true, true));

$client = HttpClient::create(['http_version' => '2.0']);

try {
    $core = (new \Bitrix24\SDK\Core\CoreBuilder())
        ->withLogger($log)
        ->withWebhookUrl('https://b24-ze7zs7.bitrix24.ru/rest/1/zh1zvyl4o3pjbkwv/')
        ->build();


    $res = $core->call('user.current');
    var_dump($res->getResponseData()->getResult()->getResultData());
    var_dump($res->getResponseData()->getResult()->getResultData()['ID']);
    var_dump($res->getResponseData()->getResult()->getResultData()['EMAIL']);
} catch (\Throwable $exception) {
    print(sprintf('ошибка: %s', $exception->getMessage()) . PHP_EOL);
    print(sprintf('тип: %s', get_class($exception)) . PHP_EOL);
    print(sprintf('trace: %s', $exception->getTraceAsString()) . PHP_EOL);
}