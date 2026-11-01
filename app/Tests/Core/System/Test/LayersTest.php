<?php

// Тест для core/System/Test/Layers.php.
// Создан командой: maws make test:core/System/Test/Layers.php
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

$run = new Maws\System\Test\Runner('core/System/Test/Layers.php');

$run->subject('Maws\\System\\Test\\Layers', 'new');

$run->gives('helperCalls', [], []);

$run->finish();
