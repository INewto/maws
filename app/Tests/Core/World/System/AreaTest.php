<?php

// Тест для core/World/System/Area.php.
// Создан командой: maws make test:core/World/System/Area.php
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

$run = new Maws\System\Test\Runner('core/World/System/Area.php');

$run->subject('Maws\\World\\System\\Area', 'Maws\\World\\System\\Area::fromRow', [
    [
        'id' => 1,
        'name' => 'имя',
        'place_id' => 2,
        'main' => true,
        'parent_id' => 0,
        'title' => 'заголовок',
        'url' => 'адрес',
        'description' => 'описание',
        'keywords' => 'ключевые слова',
        'og_title' => 'og-заголовок',
        'og_description' => 'og-описание',
        'og_url' => 'og-адрес',
        'og_type' => 'og-тип',
        'og_site_name' => 'og-имя сайта',
        'og_image' => 'og-картинка',
        'og_image_width' => 640,
        'og_image_height' => 480,
        'og_image_alt' => 'og-подпись',
    ],
]);

$run->gives('fields', [], [
    'id' => 1,
    'name' => 'имя',
    'place_id' => 2,
    'main' => true,
    'parent_id' => 0,
    'title' => 'заголовок',
    'url' => 'адрес',
    'description' => 'описание',
    'keywords' => 'ключевые слова',
    'og_title' => 'og-заголовок',
    'og_description' => 'og-описание',
    'og_url' => 'og-адрес',
    'og_type' => 'og-тип',
    'og_site_name' => 'og-имя сайта',
    'og_image' => 'og-картинка',
    'og_image_width' => 640,
    'og_image_height' => 480,
    'og_image_alt' => 'og-подпись',
]);
$run->gives('id', [], 1);
$run->gives('name', [], 'имя');
$run->gives('placeId', [], 2);
$run->gives('isMain', [], true);
$run->gives('parent', [], 0);
$run->gives('url', [], 'адрес');

$run->finish();
