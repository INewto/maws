<?php

// Тест для core/Ai/System/AiDbaseSpelling.php.
// Создан командой: maws make test:core/Ai/System/AiDbaseSpelling.php
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

$run = new Maws\System\Test\Runner('core/Ai/System/AiDbaseSpelling.php');

$run->subject('Maws\\Ai\\System\\AiDbaseSpelling', 'fromDeclaration', ['public.test_table', 'dbaccess.json']);

$run->fails('fromDeclaration', ['test_table', 'dbaccess.json'], 'Maws\\Ai\\System\\Exception\\AccessBroken');
$run->fails('fromDeclaration', ['public.my-table', 'dbaccess.json'], 'Maws\\Ai\\System\\Exception\\AccessBroken');
$run->gives('schema', [], 'public');
$run->gives('table', [], 'test_table');
$run->gives('pair', [], ['public', 'test_table']);

$run->finish();
