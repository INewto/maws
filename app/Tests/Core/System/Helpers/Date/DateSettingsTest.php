<?php

// Тест для core/System/Helpers/Date/DateSettings.php.
// Создан командой: maws make test:core/System/Helpers/Date/DateSettings.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Date/DateSettings.php');

$run->subject('Maws\\System\\Helpers\\Date\\DateSettings', 'fromArray', [
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

$run->fails('fromArray', [
    [
        'thresholds' => ['justNow' => 60, 'minutes' => 30, 'hours' => 86400, 'days' => 604800],
        'format' => 'd.m.Y',
        'parseFormats' => ['d.m.Y', 'Y-m-d', 'Y-m-d H:i:s'],
        'now' => 'только что',
        'ago' => '{n} {unit} назад',
        'in' => 'через {n} {unit}',
        'units' => [
            'minute' => ['минуту', 'минуты', 'минут'],
            'hour' => ['час', 'часа', 'часов'],
            'day' => ['день', 'дня', 'дней'],
        ],
    ],
], 'Maws\\System\\Helpers\\Date\\Exception\\DateMisuse');
$run->fails('fromArray', [
    [
        'timeZone' => 'UTC',
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
], 'Maws\\System\\Helpers\\Date\\Exception\\DateMisuse');
$run->fails('fromArray', [
    [
        'units' => [
            'minute' => ['минуту', 'минут'],
            'hour' => ['час', 'часа', 'часов'],
            'day' => ['день', 'дня', 'дней'],
        ],
        'format' => 'd.m.Y',
        'parseFormats' => ['d.m.Y', 'Y-m-d', 'Y-m-d H:i:s'],
        'thresholds' => ['justNow' => 60, 'minutes' => 3600, 'hours' => 86400, 'days' => 604800],
        'now' => 'только что',
        'ago' => '{n} {unit} назад',
        'in' => 'через {n} {unit}',
    ],
], 'Maws\\System\\Helpers\\Date\\Exception\\DateMisuse');
$run->fails('fromArray', [
    [
        'ago' => '{n} назад',
        'format' => 'd.m.Y',
        'parseFormats' => ['d.m.Y', 'Y-m-d', 'Y-m-d H:i:s'],
        'thresholds' => ['justNow' => 60, 'minutes' => 3600, 'hours' => 86400, 'days' => 604800],
        'now' => 'только что',
        'in' => 'через {n} {unit}',
        'units' => [
            'minute' => ['минуту', 'минуты', 'минут'],
            'hour' => ['час', 'часа', 'часов'],
            'day' => ['день', 'дня', 'дней'],
        ],
    ],
], 'Maws\\System\\Helpers\\Date\\Exception\\DateMisuse');
$run->gives('toArray', [], [
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
]);

$run->finish();
