<?php

// Тест для core/Ai/System/AiAccess.php.
// Создан командой: maws make test:core/Ai/System/AiAccess.php
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

$run = new Maws\System\Test\Runner('core/Ai/System/AiAccess.php');

$run->subject('Maws\\Ai\\System\\AiAccess', 'Scaffold::aiAccess', ['dbaccess.json']);

$run->fixture(['dbaccess.json' => '{"tables":["public.test_table"]}']);

$run->fails('fromDir', ['/нет-такого-каталога'], 'Maws\\Ai\\System\\Exception\\AccessNotDeclared');
$run->gives('tables', [], [['public', 'test_table']]);

$run->finish();
