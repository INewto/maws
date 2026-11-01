<?php

// Тест для core/World/System/Tools.php.
// Создан командой: maws make test:core/World/System/Tools.php
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

$run = new Maws\System\Test\Runner('core/World/System/Tools.php');

$run->subject('Maws\\World\\System\\Tools', 'Maws\\World\\System\\Tools::of', ['@sandbox/ok', 'Public/Home']);

$run->fixture([
    'ok/settings.json' => '{"tools":{"list":["ExampleTool","SecondTool"],"needs":{"Public/Home":["SecondTool"]}}}',
    'bad/settings.json' => '{"tools":',
    'shape/settings.json' => '{"tools":{"list":"ExampleTool"}}',
    'alien/settings.json' => '{"tools":{"list":["ExampleTool"],"needs":{"Secret/Home":["ExampleTool"]}}}',
]);

$run->fails('of', ['@sandbox/missing', 'Public/Home'], 'Maws\\World\\System\\Exception\\ContractUnreadable');
$run->fails('of', ['@sandbox/bad', 'Public/Home'], 'Maws\\World\\System\\Exception\\ContractBroken');
$run->fails('of', ['@sandbox/shape', 'Public/Home'], 'Maws\\World\\System\\Exception\\ContractBroken');
$run->fails('of', ['@sandbox/alien', 'Public/Home'], 'Maws\\World\\System\\Exception\\ContractBroken');
$run->gives('list', [], ['ExampleTool', 'SecondTool']);
$run->gives('needs', [], ['Public/Home' => ['SecondTool']]);
$run->gives('for', ['Public/Home', ['SecondTool', 'ExampleTool']], ['SecondTool']);
$run->gives('for', ['Public/Home', ['ExampleTool']], []);
$run->fails('for', ['Public/Nope', ['SecondTool']], 'Maws\\World\\System\\Exception\\AccessNotDeclared');

$run->finish();
