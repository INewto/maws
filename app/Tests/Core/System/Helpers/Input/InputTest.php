<?php

// Тест для core/System/Helpers/Input/Input.php.
// Создан командой: maws make test:core/System/Helpers/Input/Input.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Input/Input.php');

$run->subject('Maws\\System\\Helpers\\Input\\Input', 'from', [
    [
        'product' => 'first',
        'count' => '3',
        'price' => '1.50',
        'tags' => ['a', 'b'],
        'blank' => '',
        'zero' => '012',
        'whole' => 5,
    ],
]);

$run->gives('string', ['product'], 'first');
$run->gives('string', ['count'], '3');
$run->gives('string', ['blank'], false);
$run->gives('string', ['tags'], false);
$run->gives('string', ['missing'], false);
$run->gives('int', ['count'], 3);
$run->gives('int', ['whole'], 5);
$run->gives('int', ['zero'], false);
$run->gives('int', ['price'], false);
$run->gives('int', ['product'], false);
$run->gives('int', ['missing'], false);
$run->gives('float', ['price'], 1.5);
$run->gives('float', ['count'], 3.0);
$run->gives('float', ['whole'], 5.0);
$run->gives('float', ['product'], false);
$run->gives('float', ['missing'], false);
$run->gives('oneOf', ['product', ['second', 'first']], 'first');
$run->gives('oneOf', ['product', ['second']], false);
$run->gives('oneOf', ['missing', ['second', 'first']], false);
$run->fails('oneOf', ['product', []], 'Maws\\System\\Helpers\\Input\\Exception\\InputMisuse');
$run->gives('array', ['tags'], ['a', 'b']);
$run->gives('array', ['product'], false);
$run->gives('array', ['missing'], false);
$run->gives('has', ['product'], true);
$run->gives('has', ['blank'], true);
$run->gives('has', ['missing'], false);
$run->gives('all', [], [
    'product' => 'first',
    'count' => '3',
    'price' => '1.50',
    'tags' => ['a', 'b'],
    'blank' => '',
    'zero' => '012',
    'whole' => 5,
]);

$run->finish();
