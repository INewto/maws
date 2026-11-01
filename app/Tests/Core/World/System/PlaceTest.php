<?php

// Тест для core/World/System/Place.php.
// Создан командой: maws make test:core/World/System/Place.php
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

$run = new Maws\System\Test\Runner('core/World/System/Place.php');

$run->subject('Maws\\World\\System\\Place', 'Maws\\World\\System\\Place::fromRow', [['id' => 1, 'direction' => 'Public', 'name' => 'Home'], '/world']);

$run->gives('id', [], 1);
$run->gives('direction', [], 'Public');
$run->gives('name', [], 'Home');
$run->gives('way', [], 'Public/Home');
$run->gives('dir', [], '/world/Public/Places/Home');
$run->gives('workspaceClass', [], 'App\\World\\Public\\Places\\Home\\Workspace');
$run->gives('toolClass', ['ExampleTool'], 'App\\World\\Public\\Places\\Home\\Tools\\ExampleTool\\ExampleTool');

$run->finish();
