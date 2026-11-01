<?php

// Тест для core/Man/Legs/Legs.php.
// Создан командой: maws make test:core/Man/Legs/Legs.php
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

$run = new Maws\System\Test\Runner('core/Man/Legs/Legs.php');

$run->subject('Maws\\Man\\Legs\\Legs', 'Scaffold::man', [], 'legs');

$run->fails('walk', ['Nope/Nope'], 'Maws\\World\\System\\Exception\\DirectionNotFound');
$run->fails('walk', ['Public/Nope'], 'Maws\\World\\System\\Exception\\PlaceNotFound');
$run->changes('walk', ['Public/Home'], [['head->nearPlace', [], 'Public/Home']]);

$run->finish();
