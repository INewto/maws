<?php

// Тест для core/System/Path.php.
// Создан командой: maws make test:core/System/Path.php
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

$run = new Maws\System\Test\Runner('core/System/Path.php');

$run->subject('Maws\\System\\Path', 'new');

$run->gives('normalize', ['/a/./b/../c//'], '/a/c');
$run->gives('normalize', ['C:\\a\\.\\b'], 'C:/a/b');
$run->gives('normalize', ['\\\\server\\share\\a\\..\\b'], '//server/share/b');
$run->gives('normalize', ['/'], '/');
$run->gives('normalize', ['a/..'], '.');
$run->gives('normalize', [''], '');
$run->gives('normalize', ['C:./a'], 'a');
$run->fails('normalize', ['../123'], 'Maws\\System\\Exception\\PathFailure');
$run->fails('normalize', ['a/../../b'], 'Maws\\System\\Exception\\PathFailure');

$run->finish();
