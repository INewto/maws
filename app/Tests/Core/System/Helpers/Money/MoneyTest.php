<?php

// Тест для core/System/Helpers/Money/Money.php.
// Создан командой: maws make test:core/System/Helpers/Money/Money.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Money/Money.php');

$run->subject('Maws\\System\\Helpers\\Money\\Money', 'fromArray', [
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
$run->fails('fromFile', ['нет-такого-конфига-денег.json'], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->gives('format', [123456, 'RUB'], '1 234,56 ₽');
$run->gives('format', [0, 'RUB'], '0,00 ₽');
$run->gives('format', [5, 'RUB'], '0,05 ₽');
$run->gives('format', [-123456, 'RUB'], '-1 234,56 ₽');
$run->gives('format', [123456, 'USD'], '$1,234.56');
$run->gives('format', [123456, 'JPY'], '¥123,456');
$run->gives('format', [0, 'JPY'], '¥0');
$run->fails('format', [100, 'EUR'], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->gives('toMinor', ['1 234,56 ₽', 'RUB'], 123456);
$run->gives('toMinor', ['1234,56', 'RUB'], 123456);
$run->gives('toMinor', ['1 234,5', 'RUB'], 123450);
$run->gives('toMinor', ['-1 234,56', 'RUB'], -123456);
$run->gives('toMinor', ['$1,234.56', 'USD'], 123456);
$run->gives('toMinor', ['1 234,567', 'RUB'], false);
$run->gives('toMinor', ['1 234,56', 'JPY'], false);
$run->gives('toMinor', ['abc', 'RUB'], false);
$run->gives('toMinor', ['', 'RUB'], false);
$run->fails('toMinor', ['1', 'EUR'], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');
$run->gives('toWords', [123456, 'RUB'], 'одна тысяча двести тридцать четыре рубля 56 копеек');
$run->gives('toWords', [100, 'RUB'], 'один рубль 00 копеек');
$run->gives('toWords', [0, 'RUB'], 'ноль рублей 00 копеек');
$run->gives('toWords', [-150, 'RUB'], 'минус один рубль 50 копеек');
$run->gives('toWords', [123456, 'USD'], 'one thousand two hundred thirty-four dollars 56 cents');
$run->gives('toWords', [123456, 'JPY'], 'one hundred twenty-three thousand four hundred fifty-six yen');
$run->fails('toWords', [100, 'EUR'], 'Maws\\System\\Helpers\\Money\\Exception\\MoneyMisuse');

$run->finish();
