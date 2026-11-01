<?php

// Тест для core/Ai/System/AiDbase.php.
// Создан командой: maws make test:core/Ai/System/AiDbase.php
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

$run = new Maws\System\Test\Runner('core/Ai/System/AiDbase.php');

$run->subject('Maws\\Ai\\System\\AiDbase', 'Scaffold::aiDbase', ['dbaccess.json']);

$run->fixture(['dbaccess.json' => '{"tables":["public.test_table","public.scenes"]}']);

$run->gives('all', ['SELECT * FROM public.test_table LIMIT 0'], []);
$run->fails('all', ['SELECT * FROM public.areas'], 'Maws\\Ai\\System\\Exception\\AiDbaseDenied');
$run->fails('all', ['SELECT 1'], 'Maws\\Ai\\System\\Exception\\AiDbasePlanless');
$run->fails('all', ['CREATE TABLE public.probe_x (id int)'], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse');
$run->changes('all', ['SELECT * FROM public.test_table LIMIT 0'], [['inTransaction', [], false]]);
$run->gives('one', ['SELECT * FROM public.test_table LIMIT 0'], null);
$run->fails('one', ['SELECT * FROM public.areas'], 'Maws\\Ai\\System\\Exception\\AiDbaseDenied');
$run->fails('one', ['SELECT 1'], 'Maws\\Ai\\System\\Exception\\AiDbasePlanless');
$run->fails('one', ['TRUNCATE public.test_table'], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse');
$run->changes('one', ['SELECT * FROM public.test_table LIMIT 0'], [['inTransaction', [], false]]);
$run->gives('run', ['DELETE FROM public.test_table WHERE false'], 0);
$run->fails('run', ['DELETE FROM public.areas'], 'Maws\\Ai\\System\\Exception\\AiDbaseDenied');
$run->fails('run', ['TRUNCATE public.test_table'], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse');
$run->changes('run', ['DELETE FROM public.test_table WHERE false'], [['inTransaction', [], false]]);
$run->gives('selectUnplanned', ['SELECT 1'], '@statement');
$run->gives('selectUnplanned', ['SELECT to_char(now(), \'DD.MM.YY\')'], '@statement');
$run->fails('selectUnplanned', ['DELETE FROM public.test_table'], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse');
$run->fails('selectUnplanned', ['SELECT 1; SELECT 2'], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse');
$run->fails('selectUnplanned', ['SELECT * FROM public.test_table'], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse');
$run->fails('selectUnplanned', ['SELECT query_to_xml(\'SELECT 1 FROM public.areas\', true, false, \'\')'], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse');
$run->fails('selectUnplanned', ['SELECT pg_read_file(\'/etc/passwd\')'], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse');
$run->changes('selectUnplanned', ['SELECT 1'], [['inTransaction', [], false]]);
$run->fails('beginTransaction', [], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse', [['beginTransaction', []]]);
$run->changes('beginTransaction', [], [['inTransaction', [], true]]);
$run->fails('commit', [], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse');
$run->changes('commit', [], [['inTransaction', [], false]], [['beginTransaction', []]]);
$run->fails('rollBack', [], 'Maws\\Ai\\System\\Exception\\AiDbaseMisuse');
$run->changes('rollBack', [], [['inTransaction', [], false]], [['beginTransaction', []]]);
$run->gives('inTransaction', [], false);
$run->gives('inTransaction', [], true, [['beginTransaction', []]]);

$run->finish();
