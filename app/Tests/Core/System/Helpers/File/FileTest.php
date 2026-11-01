<?php

// Тест для core/System/Helpers/File/File.php.
// Создан командой: maws make test:core/System/Helpers/File/File.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/File/File.php');

$run->subject('Maws\\System\\Helpers\\File\\File', 'in', ['@sandbox']);

$run->fixture(['read.txt' => 'привет']);

$run->fails('in', [''], 'Maws\\System\\Helpers\\File\\Exception\\FileMisuse');
$run->gives('read', ['read.txt'], 'привет');
$run->gives('read', ['./read.txt'], 'привет');
$run->gives('read', ['sub/../read.txt'], 'привет');
$run->gives('read', ['nope.txt'], false);
$run->fails('read', ['../read.txt'], 'Maws\\System\\Helpers\\File\\Exception\\FileMisuse');
$run->fails('read', ['/etc/passwd'], 'Maws\\System\\Helpers\\File\\Exception\\FileMisuse');
$run->fails('write', ['nope/new.txt', 'привет'], 'Maws\\System\\Helpers\\File\\Exception\\FileFailure');
$run->changes('write', ['new.txt', 'привет'], [['read', ['new.txt'], 'привет']]);
$run->changes('append', ['read.txt', ' мир'], [['read', ['read.txt'], 'привет мир']]);
$run->gives('exists', ['read.txt'], true);
$run->gives('exists', ['nope.txt'], false);
$run->gives('size', ['read.txt'], 12);
$run->gives('size', ['nope.txt'], false);
$run->gives('modified', ['nope.txt'], false);
$run->gives('names', [], ['read.txt']);
$run->fails('names', ['read.txt'], 'Maws\\System\\Helpers\\File\\Exception\\FileFailure');
$run->changes('ensureDir', ['sub/dir'], [['exists', ['sub/dir'], true]]);
$run->fails('delete', ['nope.txt'], 'Maws\\System\\Helpers\\File\\Exception\\FileFailure');
$run->changes('delete', ['read.txt'], [['exists', ['read.txt'], false]]);
$run->changes('copy', ['read.txt', 'copy.txt'], [['read', ['copy.txt'], 'привет']]);
$run->changes('move', ['read.txt', 'moved.txt'], [['read', ['moved.txt'], 'привет'], ['exists', ['read.txt'], false]]);

$run->finish();
