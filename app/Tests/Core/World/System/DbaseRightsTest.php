<?php

// Тест для core/World/System/DbaseRights.php.
// Создан командой: maws make test:core/World/System/DbaseRights.php
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

$run = new Maws\System\Test\Runner('core/World/System/DbaseRights.php');

$run->subject('Maws\\World\\System\\DbaseRights', 'Scaffold::dbaseRights', ['dbase.json']);

$run->fixture(['dbase.json' => '{"tables":["public.test_table"]}']);

$run->fails('fromDir', ['/нет-такого-каталога'], 'Maws\\World\\System\\Exception\\ContractUnreadable');
$run->gives('tables', [], [['public', 'test_table']]);

$run->finish();
