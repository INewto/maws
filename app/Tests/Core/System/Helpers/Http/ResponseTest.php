<?php

// Тест для core/System/Helpers/Http/Response.php.
// Создан командой: maws make test:core/System/Helpers/Http/Response.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Http/Response.php');

$run->subject('Maws\\System\\Helpers\\Http\\Response', 'fixture', [200, '{"user":{"name":"Аня"},"tags":["a","b"]}', ['X-Trace' => 'abc']]);

$run->gives('status', [], 200);
$run->gives('reason', [], 'OK');
$run->gives('body', [], '{"user":{"name":"Аня"},"tags":["a","b"]}');
$run->gives('header', ['X-Trace'], 'abc');
$run->gives('header', ['nope'], '');
$run->gives('headers', [], ['X-Trace' => ['abc']]);
$run->gives('method', [], '');
$run->gives('url', [], '');
$run->gives('successful', [], true);
$run->gives('ok', [], true);
$run->gives('redirect', [], false);
$run->gives('clientError', [], false);
$run->gives('serverError', [], false);
$run->gives('failed', [], false);
$run->gives('throw', [], '@subject');
$run->gives('json', ['user.name'], 'Аня');
$run->gives('json', ['tags'], ['a', 'b']);
$run->gives('json', ['nope', 'запас'], 'запас');
$run->gives('json', ['nope', null], null);
$run->fails('json', ['nope'], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');
$run->gives('handlerStats', [], []);

$run->finish();
