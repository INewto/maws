<?php

// Тест для core/System/Helpers/Html/Html.php.
// Создан командой: maws make test:core/System/Helpers/Html/Html.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Html/Html.php');

$run->subject('Maws\\System\\Helpers\\Html\\Html', 'new');

$run->gives('escape', ['<b>Привет</b>'], '&lt;b&gt;Привет&lt;/b&gt;');
$run->gives('escape', ['a & b'], 'a &amp; b');
$run->gives('escape', ['a "b" \'c\''], 'a &quot;b&quot; &apos;c&apos;');
$run->gives('escape', [''], '');
$run->gives('unescape', ['&lt;b&gt;Привет&lt;/b&gt;'], '<b>Привет</b>');
$run->gives('unescape', ['&quot;a&quot; &apos;b&apos; &amp;'], '"a" \'b\' &');
$run->gives('unescape', ['просто текст'], 'просто текст');
$run->gives('strip', ['<b>a</b><i>b</i>'], 'ab');
$run->gives('strip', ['<b>a</b><i>b</i>', '<b>'], '<b>a</b>b');
$run->gives('strip', ['a < b'], 'a < b');
$run->gives('strip', [''], '');

$run->finish();
