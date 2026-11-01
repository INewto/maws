<?php

// Тест для core/System/Helpers/Csv/Csv.php.
// Создан командой: maws make test:core/System/Helpers/Csv/Csv.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Csv/Csv.php');

$run->subject('Maws\\System\\Helpers\\Csv\\Csv', 'new');

$run->gives('encode', [[['a', 'b'], ['1', '2']]], 'a,b
1,2
');
$run->gives('encode', [[['а, б', 'с']]], '"а, б",с
');
$run->gives('encode', [[['он сказал "да"']]], '"он сказал ""да"""
');
$run->gives('encode', [[['первая', 'вторая']], ';'], 'первая;вторая
');
$run->gives('encode', [[['id', 'имя'], ['1', 'Аня']], ',', true], '﻿id,имя
1,Аня
');
$run->gives('encode', [[['a' => '1', 'b' => '2'], ['a' => '3', 'b' => '4']]], 'a,b
1,2
3,4
');
$run->gives('encode', [[]], '');
$run->fails('encode', [[['a' => '1'], ['b' => '2']]], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');
$run->fails('encode', [[['a', 'b'], ['a' => '1', 'b' => '2']]], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');
$run->fails('encode', [[['a']], ''], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');
$run->fails('encode', [[['a']], ',, '], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');
$run->fails('encode', [[['a']], '"'], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');
$run->gives('decode', ['a,b
1,2
'], [['a', 'b'], ['1', '2']]);
$run->gives('decode', ['a;b
1;2
', ';'], [['a', 'b'], ['1', '2']]);
$run->gives('decode', ['﻿id,имя
1,Аня
'], [['id', 'имя'], ['1', 'Аня']]);
$run->gives('decode', ['a,b

1,2
'], [['a', 'b'], ['1', '2']]);
$run->gives('decode', ['"а, б",с
'], [['а, б', 'с']]);
$run->gives('decode', ['"первая
вторая",x
'], [['первая
вторая', 'x']]);
$run->gives('decode', [''], []);
$run->gives('decode', ['���
���
', ',', 'Windows-1251'], [['имя'], ['Аня']]);
$run->fails('decode', ['�'], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');
$run->fails('decode', ['a,b', ',, '], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');
$run->gives('table', ['id,имя
1,Аня
2,Ёж
'], [['id' => '1', 'имя' => 'Аня'], ['id' => '2', 'имя' => 'Ёж']]);
$run->gives('table', ['id;имя
1;Аня
', ';'], [['id' => '1', 'имя' => 'Аня']]);
$run->gives('table', [''], []);
$run->fails('table', ['id,id
1,2
'], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');
$run->fails('table', ['id,
1,2
'], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');
$run->fails('table', ['id,имя
1
'], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');
$run->fails('table', ['id,имя
1,Аня,лишнее
'], 'Maws\\System\\Helpers\\Csv\\Exception\\CsvMisuse');

$run->finish();
