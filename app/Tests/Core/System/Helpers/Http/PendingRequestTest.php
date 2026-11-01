<?php

// Тест для core/System/Helpers/Http/PendingRequest.php.
// Создан командой: maws make test:core/System/Helpers/Http/PendingRequest.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Http/PendingRequest.php');

$run->subject('Maws\\System\\Helpers\\Http\\PendingRequest', 'new');

$run->gives('withHeaders', [['X-A' => 'b']], '@subject');
$run->gives('withToken', ['секрет'], '@subject');
$run->gives('withBasicAuth', ['Аня', 'секрет'], '@subject');
$run->gives('withQueryParameters', [['page' => 2]], '@subject');
$run->gives('withUserAgent', ['MAWS'], '@subject');
$run->gives('withCookies', [['sid' => 'abc'], 'example.test'], '@subject');
$run->gives('withBody', ['{"a":1}', 'application/json'], '@subject');
$run->gives('acceptJson', [], '@subject');
$run->gives('asJson', [], '@subject');
$run->gives('asForm', [], '@subject');
$run->gives('timeout', [5], '@subject');
$run->gives('timeout', [0], '@subject');
$run->fails('timeout', [-1], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');
$run->gives('connectTimeout', [3], '@subject');
$run->fails('connectTimeout', [-1], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');
$run->gives('withoutRedirecting', [], '@subject');
$run->gives('maxRedirects', [2], '@subject');
$run->gives('maxRedirects', [0], '@subject');
$run->fails('maxRedirects', [-1], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');
$run->gives('retry', [3], '@subject');
$run->gives('retry', [2, 500], '@subject');
$run->fails('retry', [-1], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');
$run->fails('retry', [2, -1], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');
$run->gives('withOptions', [['verify' => false]], '@subject');
$run->fails('send', ['POST', 'http://example.test/users', ['name' => 'Аня']], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse', [['withBody', ['{"name":"Аня"}']]]);
$run->fails('send', ['POST', 'http://example.test/users', ['sum' => NAN]], 'Maws\\System\\Helpers\\Http\\Exception\\HttpMisuse');
$run->fails('send', ['GET', 'http://127.0.0.1:1/'], 'Maws\\System\\Helpers\\Http\\Exception\\HttpUnreachable', [['timeout', [2]]]);

$run->finish();
