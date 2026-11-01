<?php

// Тест для core/System/Crabic/Motion.php.
// Создан командой: maws make test:core/System/Crabic/Motion.php
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

$run = new Maws\System\Test\Runner('core/System/Crabic/Motion.php');

$run->subject('Maws\\System\\Crabic\\Motion', 'new');

$run->fails('describe', [['id' => 'x']], 'Maws\\System\\Crabic\\Exception\\CrabicBroken');
$run->fails('describe', [
    [
        'id' => 'abcdefgh',
        't0' => 0,
        'dt' => 3000,
        'w' => 800,
        'h' => 600,
        'events' => 1,
        'keys' => 0,
        'distinct' => 0,
        'pts' => [[0, 900, 10]],
    ],
], 'Maws\\System\\Crabic\\Exception\\CrabicBroken');
$run->gives('cells', [[[0, 10, 10], [100, 30, 10], [200, 60, 30], [300, 90, 30], [400, 120, 50]]], 5);
$run->gives('cells', [[[0, 10, 10], [40, 12, 11], [80, 14, 12], [120, 16, 13]]], 2);
$run->gives('cells', [[]], 0);
$run->gives('curve', [[[0, 10, 10], [100, 30, 10], [200, 60, 30], [300, 90, 30], [400, 120, 50]]], 1043);
$run->gives('curve', [[[0, 10, 10], [100, 110, 10]]], 1000);
$run->gives('curve', [[[0, 10, 10]]], 0);
$run->gives('fingerprint', [[], 20, 100], '99aa06d3014798d86001c324468d497f');
$run->gives('verdict', [
    [],
    ['at' => 4000, 'dt' => 3000, 't0' => 0, 'points' => 0, 'curve' => 0, 'keys' => 0],
    0,
], 'empty');
$run->gives('verdict', [
    [],
    [
        'at' => 1000,
        'dt' => 3000,
        't0' => 0,
        'points' => 20,
        'curve' => 1043,
        'keys' => 0,
    ],
    0,
], 'time');
$run->gives('verdict', [
    [],
    [
        'at' => 1500,
        'dt' => 1400,
        't0' => 0,
        'points' => 20,
        'curve' => 1043,
        'keys' => 0,
    ],
    0,
], null);
$run->gives('verdict', [
    [['at' => 3000, 'dt' => 3000, 't0' => 0, 'points' => 20, 'curve' => 1043]],
    ['at' => 3500, 'dt' => 3000, 't0' => 3000, 'points' => 20, 'curve' => 1043],
    0,
], 'time');
$run->gives('verdict', [
    [['at' => 3000, 'dt' => 3000, 't0' => 0, 'points' => 20, 'curve' => 1000]],
    ['at' => 3100, 'dt' => 100, 't0' => 3000, 'points' => 20, 'curve' => 1043],
    0,
], 'motion');
$run->gives('verdict', [
    [['at' => 3000, 'dt' => 3000, 't0' => 0, 'points' => 20, 'curve' => 1043]],
    ['at' => 3100, 'dt' => 100, 't0' => 3000, 'points' => 20, 'curve' => 1043],
    0,
], null);
$run->gives('verdict', [
    [['at' => 3000, 'dt' => 3000, 't0' => 0, 'points' => 20, 'curve' => 1043]],
    ['at' => 9000, 'dt' => 3000, 't0' => 3000, 'points' => 20, 'curve' => 1043],
    0,
], null);

$run->finish();
