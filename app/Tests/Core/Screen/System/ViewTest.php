<?php

// Тест для core/Screen/System/View.php.
// Создан командой: maws make test:core/Screen/System/View.php
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

$run = new Maws\System\Test\Runner('core/Screen/System/View.php');

$run->subject('Maws\\Screen\\System\\View', 'new');

$run->fixture(['tpl.php' => '<?php print $data["name"];', 'tpl/ru/a.php' => 'ru']);

$run->gives('template', ['@sandbox/tpl', 'a.php'], '@sandbox/tpl/ru/a.php');
$run->gives('render', ['@sandbox/tpl.php', ['name' => 'Home']], 'Home');

$run->finish();
