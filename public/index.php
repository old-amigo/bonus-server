<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;


$log = new Logger('name');
$log->pushHandler(new StreamHandler(dirname(__DIR__) . '/logs/webhook.log', Logger::DEBUG));
$log->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor(true, true));
$log->pushProcessor(new \Monolog\Processor\WebProcessor());
$log->pushProcessor(new \Monolog\Processor\IntrospectionProcessor());

print('<pre>');
var_dump('hello');

$log->debug(
    'req',
    [
        'req' => $_REQUEST,
    ]
);
