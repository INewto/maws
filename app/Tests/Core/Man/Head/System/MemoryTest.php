<?php

// Тест для core/Man/Head/System/Memory.php.
// Создан командой: maws make test:core/Man/Head/System/Memory.php
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

$run = new Maws\System\Test\Runner('core/Man/Head/System/Memory.php');

$run->subject('Maws\\Man\\Head\\System\\Memory', 'up');

$run->changes('set', ['probe', 'value'], [['data', [], ['probe' => 'value']]]);
$run->gives('get', ['probe'], 'value', [['set', ['probe', 'value']]]);
$run->gives('has', ['probe'], true, [['set', ['probe', 'value']]]);
$run->gives('has', ['nope'], false);

$run->finish();
