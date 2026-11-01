<?php

// Тест для core/System/Helpers/Csrf/Csrf.php.
// Создан командой: maws make test:core/System/Helpers/Csrf/Csrf.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Csrf/Csrf.php');

$run->subject('Maws\\System\\Helpers\\Csrf\\Csrf', 'new');

$run->gives('check', ['abc', 'abc'], true);
$run->gives('check', ['', ''], false);
$run->gives('check', [null, null], false);
$run->gives('check', ['abc', null], false);
$run->gives('check', ['abc', 'abd'], false);

$run->finish();
