<?php

// Тест для core/System/Helpers/Token/Token.php.
// Создан командой: maws make test:core/System/Helpers/Token/Token.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Token/Token.php');

$run->subject('Maws\\System\\Helpers\\Token\\Token', 'new');

$run->fails('url', [15], 'Maws\\System\\Helpers\\Token\\Exception\\TokenMisuse');
$run->fails('url', [0], 'Maws\\System\\Helpers\\Token\\Exception\\TokenMisuse');
$run->fails('url', [-1], 'Maws\\System\\Helpers\\Token\\Exception\\TokenMisuse');
$run->fails('hex', [15], 'Maws\\System\\Helpers\\Token\\Exception\\TokenMisuse');
$run->fails('hex', [0], 'Maws\\System\\Helpers\\Token\\Exception\\TokenMisuse');
$run->fails('digits', [5], 'Maws\\System\\Helpers\\Token\\Exception\\TokenMisuse');
$run->fails('digits', [0], 'Maws\\System\\Helpers\\Token\\Exception\\TokenMisuse');
$run->fails('digits', [-1], 'Maws\\System\\Helpers\\Token\\Exception\\TokenMisuse');
$run->gives('same', ['abc', 'abc'], true);
$run->gives('same', ['abc', 'abd'], false);
$run->gives('same', ['abc', 'ab'], false);
$run->gives('same', ['', ''], false);
$run->gives('same', ['abc', ''], false);

$run->finish();
