<?php

// Тест для core/Man/System/Pass/PlacePass.php.
// Создан командой: maws make test:core/Man/System/Pass/PlacePass.php
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

$run = new Maws\System\Test\Runner('core/Man/System/Pass/PlacePass.php');

$run->subject('Maws\\Man\\System\\Pass\\PlacePass', 'Scaffold::placePass', ['Public/Home']);

$run->fails('create', ['Public/Home'], 'Maws\\Man\\System\\Exception\\OrganAssembly');
$run->gives('place', [], 'Public/Home');

$run->finish();
