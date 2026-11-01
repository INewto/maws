<?php

// Тест для core/Man/Head/Head.php.
// Создан командой: maws make test:core/Man/Head/Head.php
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

$run = new Maws\System\Test\Runner('core/Man/Head/Head.php');

$run->subject('Maws\\Man\\Head\\Head', 'Maws\\System\\Test\\Scaffold::man', [], 'head');

$run->gives('getWorkspace', [''], 'Public/Home');
$run->fails('getWorkspace', ['nope'], 'Maws\\World\\System\\Exception\\PlaceNotFound');
$run->fails('getWorkspace', ['nope/nope'], 'Maws\\World\\System\\Exception\\PlaceNotFound');
$run->responds('getWorkspace', ['home'], '', 301);
$run->has('pageMeta', [], ['id', 'name', 'place_id', 'main', 'parent_id', 'url'], [['head->getWorkspace', ['']]]);
$run->changes('forget', [], [
    ['head->currentPlace', [], null],
    ['head->currentArea', [], null],
    ['head->currentLocation', [], null],
    ['head->visitedPlaces', [], []],
]);

$run->finish();
