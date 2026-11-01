<?php

// Тест для core/World/System/DbaseSpelling.php.
// Создан командой: maws make test:core/World/System/DbaseSpelling.php
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

$run = new Maws\System\Test\Runner('core/World/System/DbaseSpelling.php');

$run->subject('Maws\\World\\System\\DbaseSpelling', 'fromDeclaration', ['public.test_table', 'dbase.json']);

$run->fails('fromDeclaration', ['test_table', 'dbase.json'], 'Maws\\World\\System\\Exception\\ContractBroken');
$run->fails('fromDeclaration', ['public.my-table', 'dbase.json'], 'Maws\\World\\System\\Exception\\ContractBroken');
$run->gives('schema', [], 'public');
$run->gives('table', [], 'test_table');
$run->gives('pair', [], ['public', 'test_table']);

$run->finish();
