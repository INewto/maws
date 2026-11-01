<?php

// Тест для core/Man/Man.php.
// Создан командой: maws make test:core/Man/Man.php
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

$run = new Maws\System\Test\Runner('core/Man/Man.php');

$run->subject('Maws\\Man\\Man', 'Scaffold::man');

$run->fails('enterPlace', ['Public/Home'], 'Maws\\Man\\System\\Exception\\EnterWithoutWalk');
$run->changes('enterPlace', ['Public/Home'], [['head->currentLocation', [], 'Public/Home']], [['legs->walk', ['Public/Home']]]);

$run->finish();
