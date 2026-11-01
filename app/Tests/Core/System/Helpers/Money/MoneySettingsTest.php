<?php

// Тест для core/System/Helpers/Money/MoneySettings.php.
// Создан командой: maws make test:core/System/Helpers/Money/MoneySettings.php
// Перезаписывается при каждом запуске команды — правьте контракт в источнике.

$root = __DIR__;

for ($i = 0; $i < 20 && !is_file($root . '/maws.php'); $i++) {
    $root = dirname($root);
}

if (!is_file($root . '/maws.php')) {
    fwrite(STDERR, 'Не найден корень проекта выше ' . __DIR__ . "\n");
    exit(1);
}

require_once $root . '/app/config.php';
require_once $root . '/vendor/autoload.php';

$run = new Maws\System\Test\Runner('core/System/Helpers/Money/MoneySettings.php');

$run->subject('Maws\\System\\Helpers\\Money\\MoneySettings', 'fromArray', [
    [
        'RUB' => [
            'symbol' => '₽',
            'minor' => 2,
            'after' => true,
            'space' => true,
            'decimal' => ',',
            'thousands' => ' ',
            'lang' => 'rus',
            'unit' => ['рубль', 'рубля', 'рублей'],
            'subunit' => ['копейка', 'копейки', 'копеек'],
        ],
        'USD' => [
            'symbol' => '$',
            'minor' => 2,
            'after' => false,
            'space' => false,
            'decimal' => '.',
            'thousands' => ',',
            'lang' => 'en',
            'unit' => ['dollar', 'dollars', 'dollars'],
            'subunit' => ['cent', 'cents', 'cents'],
        ],
        'JPY' => [
            'symbol' => '¥',
            'minor' => 0,
            'after' => false,
            'space' => false,
            'decimal' => '.',
            'thousands' => ',',
            'lang' => 'en',
            'unit' => ['yen', 'yen', 'yen'],
            'subunit' => ['sen', 'sen', 'sen'],
        ],
    ],
]);

$run->fails('fromArray', [[]], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->fails('fromArray', [
    [
        'RUB' => [
            'minor' => -1,
            'symbol' => '₽',
            'after' => true,
            'space' => true,
            'decimal' => ',',
            'thousands' => ' ',
            'lang' => 'rus',
            'unit' => ['рубль', 'рубля', 'рублей'],
            'subunit' => ['копейка', 'копейки', 'копеек'],
        ],
    ],
], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->fails('fromArray', [
    [
        'RUB' => [
            'decimal' => ',',
            'thousands' => ',',
            'symbol' => '₽',
            'minor' => 2,
            'after' => true,
            'space' => true,
            'lang' => 'rus',
            'unit' => ['рубль', 'рубля', 'рублей'],
            'subunit' => ['копейка', 'копейки', 'копеек'],
        ],
    ],
], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->fails('fromArray', [
    [
        'RUB' => [
            'simbol' => '₽',
            'symbol' => '₽',
            'minor' => 2,
            'after' => true,
            'space' => true,
            'decimal' => ',',
            'thousands' => ' ',
            'lang' => 'rus',
            'unit' => ['рубль', 'рубля', 'рублей'],
            'subunit' => ['копейка', 'копейки', 'копеек'],
        ],
    ],
], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->fails('fromArray', [
    [
        'RUB' => [
            'unit' => ['рубль', 'рублей'],
            'symbol' => '₽',
            'minor' => 2,
            'after' => true,
            'space' => true,
            'decimal' => ',',
            'thousands' => ' ',
            'lang' => 'rus',
            'subunit' => ['копейка', 'копейки', 'копеек'],
        ],
    ],
], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->fails('fromArray', [
    [
        'RUB' => [
            'lang' => 'de',
            'symbol' => '₽',
            'minor' => 2,
            'after' => true,
            'space' => true,
            'decimal' => ',',
            'thousands' => ' ',
            'unit' => ['рубль', 'рубля', 'рублей'],
            'subunit' => ['копейка', 'копейки', 'копеек'],
        ],
    ],
], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->fails('fromArray', [
    [
        [
            'symbol' => '₽',
            'minor' => 2,
            'after' => true,
            'space' => true,
            'decimal' => ',',
            'thousands' => ' ',
            'lang' => 'rus',
            'unit' => ['рубль', 'рубля', 'рублей'],
            'subunit' => ['копейка', 'копейки', 'копеек'],
        ],
    ],
], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->gives('currency', ['RUB'], [
    'symbol' => '₽',
    'minor' => 2,
    'after' => true,
    'space' => true,
    'decimal' => ',',
    'thousands' => ' ',
    'lang' => 'rus',
    'unit' => ['рубль', 'рубля', 'рублей'],
    'subunit' => ['копейка', 'копейки', 'копеек'],
]);
$run->fails('currency', ['EUR'], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->gives('toArray', [], [
    'RUB' => [
        'symbol' => '₽',
        'minor' => 2,
        'after' => true,
        'space' => true,
        'decimal' => ',',
        'thousands' => ' ',
        'lang' => 'rus',
        'unit' => ['рубль', 'рубля', 'рублей'],
        'subunit' => ['копейка', 'копейки', 'копеек'],
    ],
    'USD' => [
        'symbol' => '$',
        'minor' => 2,
        'after' => false,
        'space' => false,
        'decimal' => '.',
        'thousands' => ',',
        'lang' => 'en',
        'unit' => ['dollar', 'dollars', 'dollars'],
        'subunit' => ['cent', 'cents', 'cents'],
    ],
    'JPY' => [
        'symbol' => '¥',
        'minor' => 0,
        'after' => false,
        'space' => false,
        'decimal' => '.',
        'thousands' => ',',
        'lang' => 'en',
        'unit' => ['yen', 'yen', 'yen'],
        'subunit' => ['sen', 'sen', 'sen'],
    ],
]);

$run->finish();
