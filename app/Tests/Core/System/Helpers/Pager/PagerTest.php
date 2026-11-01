<?php

// Тест для core/System/Helpers/Pager/Pager.php.
// Создан командой: maws make test:core/System/Helpers/Pager/Pager.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Pager/Pager.php');

$run->subject('Maws\\System\\Helpers\\Pager\\Pager', 'fixture', ['dbase.json']);

$run->fixture(['dbase.json' => '{"tables":["public.test_table"]}']);

$run->fails('perPage', [0], 'Maws\\System\\Helpers\\Pager\\Exception\\PagerMisuse');
$run->fails('at', [0], 'Maws\\System\\Helpers\\Pager\\Exception\\PagerMisuse');
$run->fails('fetch', ['SELECT id, name FROM public.test_table'], 'Maws\\System\\Helpers\\Pager\\Exception\\PagerMisuse');
$run->fails('fetch', ['SELECT id, name FROM public.test_table LIMIT 1'], 'Maws\\System\\Helpers\\Pager\\Exception\\PagerMisuse', [['perPage', [20]]]);

$run->finish();
