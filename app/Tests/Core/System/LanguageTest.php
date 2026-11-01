<?php

// Тест для core/System/Language.php.
// Создан командой: maws make test:core/System/Language.php
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

$run = new Maws\System\Test\Runner('core/System/Language.php');

$run->subject('Maws\\System\\Language', 'new');

$run->fixture(['tpl/en/a.php' => 'en', 'tpl/ru/a.php' => 'ru']);

$run->gives('isCode', ['ru'], true);
$run->gives('isCode', ['en'], true);
$run->gives('isCode', ['EN'], false);
$run->gives('isCode', ['english'], false);
$run->gives('isCode', ['../x'], false);
$run->gives('isCode', [42], false);
$run->gives('pick', [[], 'ru'], 'ru');
$run->gives('pick', [['site_language' => 'en'], 'ru'], 'en');
$run->gives('pick', [['site_language' => 'EN'], 'ru'], 'ru');
$run->gives('pick', [['site_language' => 'english'], 'ru'], 'ru');
$run->gives('pick', [['site_language' => '../x'], 'ru'], 'ru');
$run->gives('pick', [['site_language' => 42], 'ru'], 'ru');
$run->gives('file', ['@sandbox/tpl', 'a.php', 'en'], '@sandbox/tpl/en/a.php');
$run->gives('file', ['@sandbox/tpl', 'a.php', 'de'], '@sandbox/tpl/ru/a.php');
$run->gives('file', ['@sandbox/tpl', 'b.php', 'ru'], null);

$run->finish();
