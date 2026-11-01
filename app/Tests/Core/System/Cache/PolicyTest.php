<?php

// Тест для core/System/Cache/Policy.php.
// Создан командой: maws make test:core/System/Cache/Policy.php
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

$run = new Maws\System\Test\Runner('core/System/Cache/Policy.php');

$run->subject('Maws\\System\\Cache\\Policy', 'Scaffold::policy', ['plain/settings.json']);

$run->fixture([
    'plain/settings.json' => '{"tools": {"list": []}}',
    'bad-ttl/settings.json' => '{"tools": {"list": []}, "cache": {"ttl": "60"}}',
    'bad-scope/settings.json' => '{"tools": {"list": []}, "cache": {"ttl": 60, "scope": "everyone"}}',
    'extra/settings.json' => '{"tools": {"list": []}, "cache": {"ttl": 60, "vary": ["Accept-Language"]}}',
]);

$run->fails('fromDir', ['@sandbox/bad-ttl'], 'Maws\\System\\Cache\\Exception\\CacheMisuse');
$run->fails('fromDir', ['@sandbox/bad-scope'], 'Maws\\System\\Cache\\Exception\\CacheMisuse');
$run->fails('fromDir', ['@sandbox/extra'], 'Maws\\System\\Cache\\Exception\\CacheMisuse');
$run->gives('ttl', [], null);
$run->gives('isPublic', [], false);

$run->finish();
