<?php

// Тест для core/System/Cache/Cache.php.
// Создан командой: maws make test:core/System/Cache/Cache.php
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

$run = new Maws\System\Test\Runner('core/System/Cache/Cache.php');

$run->subject('Maws\\System\\Cache\\Cache', 'new');

$run->gives('line', [null, false], 'no-store');
$run->gives('line', [0, false], 'private, no-cache');
$run->gives('line', [0, true], 'public, no-cache');
$run->gives('line', [60, true], 'public, max-age=60');
$run->gives('line', [60, false], 'private, max-age=60');
$run->gives('notModified', ['"a"', '"a"'], true);
$run->gives('notModified', ['"a"', '"b", "a"'], true);
$run->gives('notModified', ['"a"', 'W/"a"'], true);
$run->gives('notModified', ['"a"', '*'], true);
$run->gives('notModified', ['"a"', ''], false);
$run->gives('notModified', ['"a"', '"b"'], false);

$run->finish();
