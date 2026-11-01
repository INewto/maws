<?php

// Тест для core/World/World.php.
// Создан командой: maws make test:core/World/World.php
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

$run = new Maws\System\Test\Runner('core/World/World.php');

$run->subject('Maws\\World\\World', 'new');

$run->fails('find', ['Nope/Nope'], 'Maws\\World\\System\\Exception\\DirectionNotFound');
$run->fails('find', ['Public/Nope'], 'Maws\\World\\System\\Exception\\PlaceNotFound');
$run->fails('find', ['Public'], 'Maws\\World\\System\\Exception\\PlaceBroken');
$run->fails('place', [999999], 'Maws\\World\\System\\Exception\\PlaceNotFound');

$run->finish();
