<?php

// Тест для core/System/Helpers/Pager/Page.php.
// Создан командой: maws make test:core/System/Helpers/Pager/Page.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Pager/Page.php');

$run->subject('Maws\\System\\Helpers\\Pager\\Page', 'of', [[], 12, 5, 2]);

$run->fails('of', [[], 12, 0, 1], 'Maws\\System\\Helpers\\Pager\\Exception\\PagerMisuse');
$run->gives('rows', [], []);
$run->gives('total', [], 12);
$run->gives('perPage', [], 5);
$run->gives('number', [], 2);
$run->gives('pages', [], 3);
$run->gives('offset', [], 5);
$run->gives('has', [3], true);
$run->gives('has', [4], false);
$run->gives('next', [], 3);
$run->gives('previous', [], 1);

$run->finish();
