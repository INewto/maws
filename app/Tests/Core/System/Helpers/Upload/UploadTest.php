<?php

// Тест для core/System/Helpers/Upload/Upload.php.
// Создан командой: maws make test:core/System/Helpers/Upload/Upload.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Upload/Upload.php');

$run->subject('Maws\\System\\Helpers\\Upload\\Upload', 'accepts', ['photo.JPG', ['jpg']]);

$run->gives('accepts', ['shell.php.png', ['png']], true);
$run->gives('accepts', ['photo.JPG', ['.jpg']], true);
$run->gives('accepts', ['note.txt', ['jpg', 'png']], false);
$run->gives('accepts', ['noext', ['jpg']], false);
$run->gives('accepts', ['.htaccess', ['jpg']], false);
$run->fails('accepts', ['a.png', []], 'Maws\\System\\Helpers\\Upload\\Exception\\UploadMisuse');
$run->fails('accepts', ['a.svg', ['svg']], 'Maws\\System\\Helpers\\Upload\\Exception\\UploadMisuse');
$run->gives('fits', ['png', 'IMAGE/PNG'], true);
$run->gives('fits', ['txt', 'text/plain; charset=utf-8'], true);
$run->gives('fits', ['docx', 'application/zip'], true);
$run->gives('fits', ['png', 'text/x-php'], false);
$run->gives('fits', ['png', ''], false);
$run->fails('fits', ['svg', 'image/svg+xml'], 'Maws\\System\\Helpers\\Upload\\Exception\\UploadMisuse');
$run->gives('tooBig', [0, 0], false);
$run->gives('tooBig', [1000, 0], false);
$run->gives('tooBig', [1000, 1000], false);
$run->gives('tooBig', [1001, 1000], true);
$run->fails('tooBig', [0, -1], 'Maws\\System\\Helpers\\Upload\\Exception\\UploadMisuse');
$run->gives('refusal', [4], ['status' => 'error', 'message' => 'файл не выбран', 'name' => null]);
$run->gives('refusal', [1], [
    'status' => 'error',
    'message' => 'файл больше допустимого размера',
    'name' => null,
]);
$run->gives('refusal', [2], [
    'status' => 'error',
    'message' => 'файл больше допустимого размера',
    'name' => null,
]);
$run->gives('refusal', [3], [
    'status' => 'error',
    'message' => 'файл пришёл не полностью — отправку оборвали',
    'name' => null,
]);
$run->gives('refusal', [7], null);
$run->gives('refusal', [0], null);

$run->finish();
