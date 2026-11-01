<?php

// Тест для core/System/Helpers/Json/Json.php.
// Создан командой: maws make test:core/System/Helpers/Json/Json.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Json/Json.php');

$run->subject('Maws\\System\\Helpers\\Json\\Json', 'new');

$run->gives('encode', [['a' => 'Аня']], '{"a":"Аня"}');
$run->gives('encode', [['url' => 'http://example.test/a/b']], '{"url":"http://example.test/a/b"}');
$run->gives('encode', [[]], '[]');
$run->gives('encode', [[1, 2]], '[1,2]');
$run->gives('encode', [null], 'null');
$run->gives('encode', [1.0], '1.0');
$run->gives('encode', [[1.0, 2]], '[1.0,2]');
$run->gives('encode', [['a' => 1], true], '{
    "a": 1
}');
$run->fails('encode', [NAN], 'Maws\\System\\Helpers\\Json\\Exception\\JsonMisuse');
$run->gives('decode', ['{"a":1}'], ['a' => 1]);
$run->gives('decode', ['{"a":{"b":[1,2]}}'], ['a' => ['b' => [1, 2]]]);
$run->gives('decode', ['[]'], []);
$run->gives('decode', ['"текст"'], 'текст');
$run->gives('decode', ['123'], 123);
$run->gives('decode', ['true'], true);
$run->gives('decode', ['null'], null);
$run->fails('decode', ['{'], 'Maws\\System\\Helpers\\Json\\Exception\\JsonMisuse');
$run->fails('decode', [''], 'Maws\\System\\Helpers\\Json\\Exception\\JsonMisuse');
$run->fails('decode', ['{"a":}'], 'Maws\\System\\Helpers\\Json\\Exception\\JsonMisuse');

$run->finish();
