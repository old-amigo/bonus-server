<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Rarus\Interns\BonusServer\Controllers\BonusController;
use Rarus\Interns\BonusServer\Controllers\User;
use Rarus\Interns\BonusServer\Router;

$dotenv = Dotenv::createImmutable(dirname(__DIR__, 1));
$dotenv->load();


$log = new Logger('name');
$log->pushHandler(new StreamHandler(dirname(__DIR__) . '/logs/webhook.log', Logger::DEBUG));
$log->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor(true, true));
$log->pushProcessor(new \Monolog\Processor\WebProcessor());
$log->pushProcessor(new \Monolog\Processor\IntrospectionProcessor());

$router = new Router();

$router->get('/', function () {
    echo "App root";
});

$router->get('/users', User::class . '::getUsers');
$router->get('/bx24Users', User::class . '::bx24Users');
$router->post('/handleNewDeal', BonusController::class . '::handleNewDeal');

$hook = getenv('BITRIX24_WEBHOOK');


$router->run();
$log->debug(
    'req',
    [
        'req' => $_REQUEST,
    ]
);
