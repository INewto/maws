<?php

// Тест для core/Screen/Screen.php.
// Создан командой: maws make test:core/Screen/Screen.php
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

$run = new Maws\System\Test\Runner('core/Screen/Screen.php');

$run->subject('Maws\\Screen\\Screen', 'new');

$run->gives('getScene', [null], 'Default');
$run->fails('getScene', ['Nope/Thing'], 'Maws\\Screen\\System\\Exception\\ConfigBroken');
$run->fails('screenInterface', [null, 'redirect', ['url' => '/x', 'status' => 200]], 'Maws\\Screen\\System\\Exception\\TransferBroken');
$run->fails('screenInterface', ['Error', 'nope'], 'Maws\\Screen\\System\\Exception\\CommandNotDeclared');
$run->responds('screenInterface', [null, 'redirect', '/x'], '', 301);
$run->responds('screenInterface', [null, 'redirect', ['url' => '/x', 'status' => 303]], '', 303);

$run->finish();
