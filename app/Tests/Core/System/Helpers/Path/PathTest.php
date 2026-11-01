<?php

// Тест для core/System/Helpers/Path/Path.php.
// Создан командой: maws make test:core/System/Helpers/Path/Path.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Path/Path.php');

$run->subject('Maws\\System\\Helpers\\Path\\Path', 'new');

$run->gives('join', ['a', 'b', 'c'], 'a/b/c');
$run->gives('join', ['/a/', '/b'], '/a/b');
$run->gives('join', ['/', 'a'], '/a');
$run->gives('join', ['', 'a'], 'a');
$run->gives('join', ['a', ''], 'a');
$run->gives('normalize', ['/a/./b/../c//'], '/a/c');
$run->gives('normalize', ['C:\\a\\.\\b'], 'C:/a/b');
$run->gives('normalize', ['\\\\server\\share\\a\\..\\b'], '//server/share/b');
$run->gives('normalize', ['/'], '/');
$run->gives('normalize', ['a/..'], '.');
$run->gives('normalize', [''], '');
$run->gives('normalize', ['C:./a'], 'a');
$run->fails('normalize', ['../123'], 'Maws\\System\\Helpers\\Path\\Exception\\PathFailure');
$run->fails('normalize', ['a/../../b'], 'Maws\\System\\Helpers\\Path\\Exception\\PathFailure');
$run->gives('isAbsolute', ['/a'], true);
$run->gives('isAbsolute', ['C:\\a'], true);
$run->gives('isAbsolute', ['C:/a'], true);
$run->gives('isAbsolute', ['C:a'], false);
$run->gives('isAbsolute', ['a/b'], false);
$run->gives('base', ['/a/b.txt'], 'b.txt');
$run->gives('base', ['a/b/'], 'b');
$run->gives('base', ['/'], '');
$run->gives('dirname', ['/a/b.txt'], '/a');
$run->gives('dirname', ['/a'], '/');
$run->gives('dirname', ['b.txt'], '');
$run->gives('extension', ['/a/b.tar.gz'], 'gz');
$run->gives('extension', ['/a/b'], '');
$run->gives('extension', ['/a/.htaccess'], '');
$run->gives('withoutExtension', ['/a/b.tar.gz'], '/a/b.tar');
$run->gives('withoutExtension', ['/a/b'], '/a/b');
$run->gives('withoutExtension', ['C:\\a\\.htaccess'], 'C:\\a\\.htaccess');
$run->gives('inside', ['/root/a/b', '/root'], true);
$run->gives('inside', ['/root', '/root'], true);
$run->gives('inside', ['/root/../etc', '/root'], false);
$run->gives('inside', ['/root2/a', '/root'], false);
$run->gives('inside', ['C:/Root/a', 'C:/Root'], true);
$run->gives('inside', ['C:/Root2/a', 'C:/Root'], false);
$run->gives('inside', ['/root/a', ''], false);
$run->gives('inside', ['a.txt', '.'], true);
$run->gives('inside', ['a/b', './'], true);
$run->gives('inside', ['/a', '.'], false);
$run->fails('inside', ['../a', '/root'], 'Maws\\System\\Helpers\\Path\\Exception\\PathFailure');

$run->finish();
