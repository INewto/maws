<?php

// Тест для core/System/Helpers/Response/Response.php.
// Создан командой: maws make test:core/System/Helpers/Response/Response.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Response/Response.php');

$run->subject('Maws\\System\\Helpers\\Response\\Response', 'new');

$run->fails('send', ['тело', 700], 'Maws\\System\\Helpers\\Response\\Exception\\ResponseMisuse');
$run->fails('send', ['тело', 99], 'Maws\\System\\Helpers\\Response\\Exception\\ResponseMisuse');
$run->responds('send', ['<h1>Ответ места</h1>'], '<h1>Ответ места</h1>', 200);
$run->responds('json', [['ok' => true]], '{"ok":true}', 200);

$run->finish();
