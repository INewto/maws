<?php

// Тест для core/System/Helpers/Text/Text.php.
// Создан командой: maws make test:core/System/Helpers/Text/Text.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Text/Text.php');

$run->subject('Maws\\System\\Helpers\\Text\\Text', 'new');

$run->gives('length', ['Привет'], 6);
$run->gives('length', [''], 0);
$run->gives('length', ['Ёж'], 2);
$run->gives('limit', ['Привет мир', 6], 'Привет…');
$run->gives('limit', ['Привет', 6], 'Привет');
$run->gives('limit', ['Привет мир', 6, '...'], 'Привет...');
$run->gives('limit', ['Привет', 6, '...'], 'Привет');
$run->fails('limit', ['Привет', 0], 'Maws\\System\\Helpers\\Text\\Exception\\TextMisuse');
$run->fails('limit', ['Привет', -1], 'Maws\\System\\Helpers\\Text\\Exception\\TextMisuse');
$run->gives('upper', ['Привет'], 'ПРИВЕТ');
$run->gives('upper', ['hello'], 'HELLO');
$run->gives('upper', [''], '');
$run->gives('lower', ['ПРИВЕТ'], 'привет');
$run->gives('lower', ['Hello'], 'hello');
$run->gives('lower', [''], '');
$run->gives('title', ['иван петров'], 'Иван Петров');
$run->gives('title', ['КОРЗИНА ПОКУПОК'], 'Корзина Покупок');
$run->gives('title', [''], '');
$run->gives('squish', ['  Привет   мир  '], 'Привет мир');
$run->gives('squish', ['Привет
	мир'], 'Привет мир');
$run->gives('squish', [''], '');
$run->gives('slug', ['Привет мир'], 'privet-mir');
$run->gives('slug', ['Ёлка, ёж и мёд'], 'elka-ez-i-med');
$run->gives('slug', ['  --Уже--слаг--  '], 'uze-slag');
$run->gives('slug', [''], '');
$run->gives('plural', [1, ['товар', 'товара', 'товаров']], 'товар');
$run->gives('plural', [2, ['товар', 'товара', 'товаров']], 'товара');
$run->gives('plural', [4, ['товар', 'товара', 'товаров']], 'товара');
$run->gives('plural', [5, ['товар', 'товара', 'товаров']], 'товаров');
$run->gives('plural', [0, ['товар', 'товара', 'товаров']], 'товаров');
$run->gives('plural', [11, ['товар', 'товара', 'товаров']], 'товаров');
$run->gives('plural', [21, ['товар', 'товара', 'товаров']], 'товар');
$run->gives('plural', [102, ['товар', 'товара', 'товаров']], 'товара');
$run->gives('plural', [112, ['товар', 'товара', 'товаров']], 'товаров');
$run->gives('plural', [-1, ['товар', 'товара', 'товаров']], 'товар');
$run->fails('plural', [1, ['товар', 'товара']], 'Maws\\System\\Helpers\\Text\\Exception\\TextMisuse');
$run->fails('plural', [1, ['товар', 'товара', 'товаров', 'лишняя']], 'Maws\\System\\Helpers\\Text\\Exception\\TextMisuse');
$run->fails('plural', [1, ['one' => 'товар', 'few' => 'товара', 'many' => 'товаров']], 'Maws\\System\\Helpers\\Text\\Exception\\TextMisuse');

$run->finish();
