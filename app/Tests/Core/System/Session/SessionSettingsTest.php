<?php

// Тест для core/System/Session/SessionSettings.php.
// Создан командой: maws make test:core/System/Session/SessionSettings.php
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

$run = new Maws\System\Test\Runner('core/System/Session/SessionSettings.php');

$run->subject('Maws\\System\\Session\\SessionSettings', 'fromArray', [['name' => 'maws_sid', 'lifetime' => 0, 'secure' => null, 'sameSite' => 'Lax']]);

$run->fails('fromArray', [
    [
        'path' => '/',
        'name' => 'maws_sid',
        'lifetime' => 0,
        'secure' => null,
        'sameSite' => 'Lax',
    ],
], 'Maws\\System\\Session\\Exception\\SessionMisuse');
$run->fails('fromArray', [['name' => 'maws_sid', 'lifetime' => 0, 'secure' => null]], 'Maws\\System\\Session\\Exception\\SessionMisuse');
$run->fails('fromArray', [['lifetime' => -1, 'name' => 'maws_sid', 'secure' => null, 'sameSite' => 'Lax']], 'Maws\\System\\Session\\Exception\\SessionMisuse');
$run->fails('fromArray', [['name' => '', 'lifetime' => 0, 'secure' => null, 'sameSite' => 'Lax']], 'Maws\\System\\Session\\Exception\\SessionMisuse');
$run->fails('fromArray', [['name' => '12345', 'lifetime' => 0, 'secure' => null, 'sameSite' => 'Lax']], 'Maws\\System\\Session\\Exception\\SessionMisuse');
$run->fails('fromArray', [['secure' => 'yes', 'name' => 'maws_sid', 'lifetime' => 0, 'sameSite' => 'Lax']], 'Maws\\System\\Session\\Exception\\SessionMisuse');
$run->fails('fromArray', [['name' => '__Host-maws', 'lifetime' => 0, 'secure' => null, 'sameSite' => 'Lax']], 'Maws\\System\\Session\\Exception\\SessionMisuse');
$run->fails('fromArray', [['sameSite' => 'None', 'name' => 'maws_sid', 'lifetime' => 0, 'secure' => null]], 'Maws\\System\\Session\\Exception\\SessionMisuse');
$run->fails('fromArray', [['sameSite' => 'Sometimes', 'name' => 'maws_sid', 'lifetime' => 0, 'secure' => null]], 'Maws\\System\\Session\\Exception\\SessionMisuse');
$run->fails('fromFile', ['/нет-такого-конфига.json'], 'Maws\\System\\Session\\Exception\\SessionMisuse');
$run->gives('toArray', [], ['name' => 'maws_sid', 'lifetime' => 0, 'secure' => null, 'sameSite' => 'Lax']);

$run->finish();
