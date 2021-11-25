<?php

declare(strict_types=1);

// подключили авторизацию по хуку
require 'tests/bootstrap.php';

try {
    $b24Service = \Rarus\Interns\BonusServer\TrainingClassroom\Services\Bitrix24ApiClientServiceBuilder::getServiceBuilder();

    // создали сделку
    // ВАМ ЭТО ДЕЛАТЬ НЕ НАДО, У ВАС СДЕЛКИ, ТОВАРЫ, КОНТАКТЫ, генерирует консольное приложение
    $b24DealId = $b24Service->getCRMScope()->deal()->add(
        [
            'TITLE' => 'тестовая сделка',
        ]
    )->getId();
    print(sprintf('тестовая сделка: %s', $b24DealId) . PHP_EOL);

    // создали табличную часть сделки - строки заказа
    // ВАМ ЭТО ДЕЛАТЬ НЕ НАДО, У ВАС СДЕЛКИ, ТОВАРЫ, КОНТАКТЫ, генерирует консольное приложение
    $b24Service->getCRMScope()->dealProductRows()->set(
        $b24DealId,
        [
            [
                'PRODUCT_NAME'          => 'пицца',
                'ORIGINAL_PRODUCT_NAME' => 'пицца',
                'PRICE'                 => 300,
                'QUANTITY'              => 2,
            ],
            [
                'PRODUCT_NAME'          => 'сок',
                'ORIGINAL_PRODUCT_NAME' => 'сок',
                'PRICE'                 => 200,
            ],
        ]
    );

    // вот тут вам пришёл вебхук содержащий ID сделки из вызова CRM-робота на нужной стадии
    $b24WebhookDealId = $b24DealId;

    // получаем сделку по её ID
    $b24Deal = $b24Service->getCRMScope()->deal()->get($b24WebhookDealId)->deal();
    print('--' . PHP_EOL);
    print(sprintf('id сделки: %s', $b24Deal->ID) . PHP_EOL);
    print(sprintf('title сделки: %s', $b24Deal->TITLE) . PHP_EOL);
    print(sprintf('сумма сделки: %s', $b24Deal->OPPORTUNITY) . PHP_EOL);
    print(print_r($b24Deal, true) . PHP_EOL);

    // получаем табличную часть сделки - продукты
    $b24DealProductRows = $b24Service->getCRMScope()->dealProductRows()->get($b24WebhookDealId)->getProductRows();
    print(print_r($b24DealProductRows, true) . PHP_EOL);


    // теперь сделаем самое простое, частичная оплата бонусами
    // план рассчёта
    // у нас есть сделка на 800 рублей

    // - пицца 2 шт. по 300 руб. скидка 0 руб. итого 600 руб.
    // - сок 1 шт. по 200 руб. скидка 0 руб. итого 200 руб.

    // клиент говорит: хочу оплатить бонусами часть заказа, спишите 100 бонусов.
    // сумму бонусов нужно ПРОПОРЦИОНАЛЬНО распределить между строками табличной части сделки
    //
    // вот тут вы пишите свой хитрый алгоритм распределения скидки, удачи
    //
    // я же применю свой - списать по 50 бонусов с каждой строчки
    // итого должно получиться:
    //
    // - пицца 2 шт. по 300 руб. скидка 25 руб. итого 550 руб.
    // - сок 1 шт. по 200 руб. скидка 50 руб. итого 150 руб.
    // ИТОГО ВСЕГО: 700 рублей к оплате и 100 рублей скидки.

    // модифицируем табличную часть сделки
    // вы вот тут вызываете свой алгоритм и получаете новую структуру табличной части


    // нам нужно её записать в Битрикс24
    $b24Service->getCRMScope()->dealProductRows()->set($b24WebhookDealId, [
        [
            // пицца
            'ID'               => $b24DealProductRows[0]->ID, // прокидываем ID строки табличной части первой строчки
            'PRODUCT_NAME'     => 'пицца',
            'QUANTITY'         => 2,
            // пицца стоила 300 руб шт, распределяем 50 бонусов по двум пиццам, поэтому на 1 пиццу будет 25
            // 300 - 25 = 275
            'PRICE_EXCLUSIVE'  => 275,          // цена без налога, но со скидкой
            'PRICE_ACCOUNT'    => '275.00',     // цена отформатированная для вывода в отчётах
            'PRICE_BRUTTO'     => 300,          // цена с налогом, но без скидки
            'PRICE_NETTO'      => 300,          // цена без налога и без скидки
            'PRICE'            => 275,          // цена конечная с учётом налогов и скидок
            // указываем скидку
            'DISCOUNT_TYPE_ID' => 1,        // тип скидки - монетарная скидка
            'DISCOUNT_SUM'     => '25.0',   // указываем абсолютная сумма
        ],
        [
            // сок
            'ID'               => $b24DealProductRows[1]->ID, // прокидываем ID строки табличной части второй строчки
            'PRODUCT_NAME'     => 'сок',
            'QUANTITY'         => 1,
            // сок стоил 200 руб шт, распределяем 50 бонусов по одному соку, поэтому будет 50 бонусов скидка
            // 200 - 50 = 150
            'PRICE_EXCLUSIVE'  => 150,          // цена без налога, но со скидкой
            'PRICE_ACCOUNT'    => '150.00',     // цена отформатированная для вывода в отчётах
            'PRICE_BRUTTO'     => 200,          // цена с налогом, но без скидки
            'PRICE_NETTO'      => 200,          // цена без налога и без скидки
            'PRICE'            => 150,          // цена конечная с учётом налогов и скидок
            // отдельно указываем скидку
            'DISCOUNT_TYPE_ID' => 1,        // тип скидки - монетарная скидка
            'DISCOUNT_SUM'     => '50.0',   // указываем абсолютная сумма
        ],
    ]);

    // повторно вычитываем сделку и табличную часть
    $b24Deal = $b24Service->getCRMScope()->deal()->get($b24WebhookDealId)->deal();
    print('--' . PHP_EOL);
    print(sprintf('id сделки: %s', $b24Deal->ID) . PHP_EOL);
    print(sprintf('title сделки: %s', $b24Deal->TITLE) . PHP_EOL);
    print(sprintf('сумма сделки: %s', $b24Deal->OPPORTUNITY) . PHP_EOL);

    // получаем табличную часть сделки - продукты
    $b24DealProductRows = $b24Service->getCRMScope()->dealProductRows()->get($b24WebhookDealId)->getProductRows();
    print(print_r($b24DealProductRows, true) . PHP_EOL);
} catch (\Throwable $exception) {
    print(sprintf('ошибка: %s', $exception->getMessage()) . PHP_EOL);
    print(sprintf('тип: %s', get_class($exception)) . PHP_EOL);
    print(sprintf('trace: %s', $exception->getTraceAsString()) . PHP_EOL);
}