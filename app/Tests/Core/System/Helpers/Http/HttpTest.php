<?php

// Тест для core/System/Helpers/Http/Http.php.
// Создан командой: maws make test:core/System/Helpers/Http/Http.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Http/Http.php');

$run->subject('Maws\\System\\Helpers\\Http\\Http', 'new');

$run->fails('timeout', [-1], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');
$run->fails('connectTimeout', [-1], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');
$run->fails('maxRedirects', [-1], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');
$run->fails('retry', [-1], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');

$run->finish();
