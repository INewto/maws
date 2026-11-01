<?php

// Тест для core/System/Test/Invariants.php.
// Создан командой: maws make test:core/System/Test/Invariants.php
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

$run = new Maws\System\Test\Runner('core/System/Test/Invariants.php');

$run->subject('Maws\\System\\Test\\Invariants', 'final', ['Maws\\Man\\Man']);

$run->gives('final', ['Maws\\Man\\Man'], true);
$run->gives('final', ['Maws\\Man\\Body\\Body'], true);
$run->gives('final', ['Maws\\Man\\Head\\Head'], true);
$run->gives('final', ['Maws\\Man\\Hands\\Hands'], true);
$run->gives('final', ['Maws\\Man\\Legs\\Legs'], true);
$run->gives('organs', ['Maws\\Man\\Man'], true);
$run->gives('organs', ['Maws\\Man\\Body\\Body'], true);
$run->gives('organs', ['Maws\\Man\\Head\\Head'], true);
$run->gives('organs', ['Maws\\Man\\Hands\\Hands'], true);
$run->gives('organs', ['Maws\\Man\\Legs\\Legs'], true);
$run->gives('bodyless', ['Maws\\Man\\Man'], true);
$run->gives('bodyless', ['Maws\\Man\\Body\\Body'], true);
$run->gives('bodyless', ['Maws\\Man\\Head\\Head'], true);
$run->gives('bodyless', ['Maws\\Man\\Hands\\Hands'], true);
$run->gives('bodyless', ['Maws\\Man\\Legs\\Legs'], true);
$run->gives('cloned', ['Maws\\Man\\Man'], true);
$run->gives('cloned', ['Maws\\Man\\Body\\Body'], true);
$run->gives('cloned', ['Maws\\Man\\Head\\Head'], true);
$run->gives('cloned', ['Maws\\Man\\Hands\\Hands'], true);
$run->gives('cloned', ['Maws\\Man\\Legs\\Legs'], true);
$run->gives('stripped', ['Maws\\Man\\Man'], true);
$run->gives('stripped', ['Maws\\Man\\Body\\Body'], true);
$run->gives('stripped', ['Maws\\Man\\Head\\Head'], true);
$run->gives('stripped', ['Maws\\Man\\Hands\\Hands'], true);
$run->gives('stripped', ['Maws\\Man\\Legs\\Legs'], true);

$run->finish();
