<?php

// Тест для core/System/Helpers/Date/Date.php.
// Создан командой: maws make test:core/System/Helpers/Date/Date.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Date/Date.php');

$run->subject('Maws\\System\\Helpers\\Date\\Date', 'fromArray', [
    [
        'format' => 'd.m.Y',
        'parseFormats' => ['d.m.Y', 'Y-m-d', 'Y-m-d H:i:s'],
        'thresholds' => ['justNow' => 60, 'minutes' => 3600, 'hours' => 86400, 'days' => 604800],
        'now' => 'только что',
        'ago' => '{n} {unit} назад',
        'in' => 'через {n} {unit}',
        'units' => [
            'minute' => ['минуту', 'минуты', 'минут'],
            'hour' => ['час', 'часа', 'часов'],
            'day' => ['день', 'дня', 'дней'],
        ],
    ],
]);

$run->fails('fromArray', [[]], 'Maws\\System\\Helpers\\Date\\Exception\\DateMisuse');
$run->fails('fromFile', ['нет-такого-конфига-дат.json'], 'Maws\\System\\Helpers\\Date\\Exception\\DateMisuse');
$run->gives('format', [0], '01.01.1970');
$run->gives('format', [0, 'Y-m-d H:i:s'], '1970-01-01 00:00:00');
$run->gives('format', [1792108800], '16.10.2026');
$run->gives('human', [1000, 1030], 'только что');
$run->gives('human', [1000, 1060], '1 минуту назад');
$run->gives('human', [1000, 1120], '2 минуты назад');
$run->gives('human', [1000, 1300], '5 минут назад');
$run->gives('human', [1000, 4600], '1 час назад');
$run->gives('human', [1000, 8200], '2 часа назад');
$run->gives('human', [1000, 19000], '5 часов назад');
$run->gives('human', [1000, 87400], '1 день назад');
$run->gives('human', [1000, 173800], '2 дня назад');
$run->gives('human', [1000, 433000], '5 дней назад');
$run->gives('human', [1000, 700000], '01.01.1970');
$run->gives('human', [1000, 700], 'через 5 минут');
$run->gives('utimestamp', ['16.10.2026'], 1792108800);
$run->gives('utimestamp', [' 16.10.2026 '], 1792108800);
$run->gives('utimestamp', [' 2026-10-16	'], 1792108800);
$run->gives('utimestamp', ['2026-10-16'], 1792108800);
$run->gives('utimestamp', ['2026-10-16 21:14:03'], 1792185243);
$run->gives('utimestamp', ['32.01.2026'], false);
$run->gives('utimestamp', ['16.10.2026 xxx'], false);
$run->gives('utimestamp', ['abc'], false);
$run->gives('utimestamp', [''], false);

$run->finish();
