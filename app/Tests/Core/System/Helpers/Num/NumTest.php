<?php

// Тест для core/System/Helpers/Num/Num.php.
// Создан командой: maws make test:core/System/Helpers/Num/Num.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Num/Num.php');

$run->subject('Maws\\System\\Helpers\\Num\\Num', 'new');

$run->gives('format', [1234.5, 2], '1 234,50');
$run->gives('format', [1234.5, 0], '1 235');
$run->gives('format', [-1234.5, 2], '-1 234,50');
$run->gives('format', [0, 2], '0,00');
$run->gives('format', [1234567.891, 2], '1 234 567,89');
$run->gives('format', [1234, 0, ',', ''], '1234');
$run->gives('format', [1234.5, 2, '.', ','], '1,234.50');
$run->fails('format', [1234.5, -1], 'Maws\\System\\Helpers\\Num\\Exception\\NumMisuse');
$run->fails('format', [1234.5, 2, ',', ','], 'Maws\\System\\Helpers\\Num\\Exception\\NumMisuse');
$run->gives('parse', ['1 234,56'], 1234.56);
$run->gives('parse', ['1 234,56'], 1234.56);
$run->gives('parse', ['1 234,5'], 1234.5);
$run->gives('parse', ['1234'], 1234.0);
$run->gives('parse', ['-0,5'], -0.5);
$run->gives('parse', [' 12,5 '], 12.5);
$run->gives('parse', ['1,234'], 1.234);
$run->gives('parse', [''], false);
$run->gives('parse', ['abc'], false);
$run->gives('parse', ['1e3'], false);
$run->gives('parse', ['12,'], false);
$run->gives('parse', ['1.234,5'], false);
$run->gives('clamp', [5, 0, 10], 5);
$run->gives('clamp', [15, 0, 10], 10);
$run->gives('clamp', [-5, 0, 10], 0);
$run->gives('clamp', [1.5, 0, 1], 1);
$run->fails('clamp', [5, 10, 0], 'Maws\\System\\Helpers\\Num\\Exception\\NumMisuse');
$run->gives('toWords', ['3221', 'rus'], 'три тысячи двести двадцать один');
$run->gives('toWords', ['1234325', 'rus'], 'один миллион двести тридцать четыре тысячи триста двадцать пять');
$run->gives('toWords', ['999', 'rus'], 'девятьсот девяносто девять');
$run->gives('toWords', ['1000', 'rus'], 'одна тысяча');
$run->gives('toWords', ['2000', 'rus'], 'две тысячи');
$run->gives('toWords', ['5000', 'rus'], 'пять тысяч');
$run->gives('toWords', ['21000', 'rus'], 'двадцать одна тысяча');
$run->gives('toWords', ['11000', 'rus'], 'одиннадцать тысяч');
$run->gives('toWords', ['0', 'rus'], 'ноль');
$run->gives('toWords', ['-5', 'rus'], 'минус пять');
$run->gives('toWords', ['3221', 'en'], 'three thousand two hundred twenty-one');
$run->gives('toWords', ['1234325', 'en'], 'one million two hundred thirty-four thousand three hundred twenty-five');
$run->gives('toWords', ['21', 'en'], 'twenty-one');
$run->gives('toWords', ['0', 'en'], 'zero');
$run->gives('toWords', ['-5', 'en'], 'minus five');
$run->fails('toWords', ['3221', 'de'], 'Maws\\System\\Helpers\\Num\\Exception\\NumMisuse');
$run->fails('toWords', ['12,5', 'rus'], 'Maws\\System\\Helpers\\Num\\Exception\\NumMisuse');
$run->fails('toWords', ['abc', 'rus'], 'Maws\\System\\Helpers\\Num\\Exception\\NumMisuse');
$run->fails('toWords', ['', 'rus'], 'Maws\\System\\Helpers\\Num\\Exception\\NumMisuse');
$run->fails('toWords', ['1234567890123456789', 'rus'], 'Maws\\System\\Helpers\\Num\\Exception\\NumMisuse');

$run->finish();
