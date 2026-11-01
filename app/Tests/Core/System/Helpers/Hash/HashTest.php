<?php

// Тест для core/System/Helpers/Hash/Hash.php.
// Создан командой: maws make test:core/System/Helpers/Hash/Hash.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Hash/Hash.php');

$run->subject('Maws\\System\\Helpers\\Hash\\Hash', 'new');

$run->gives('verify', ['секрет', '$2y$10$VnNbMM4lfyYMt8xcfYB2UOjgeMdCMwDJJK0KbEbgfkjGFF8nUPRcO'], true);
$run->gives('verify', ['Секрет', '$2y$10$VnNbMM4lfyYMt8xcfYB2UOjgeMdCMwDJJK0KbEbgfkjGFF8nUPRcO'], false);
$run->gives('verify', ['секрет', ''], false);
$run->gives('verify', ['секрет', 'не хеш'], false);
$run->gives('needsRehash', ['$2y$10$VnNbMM4lfyYMt8xcfYB2UOjgeMdCMwDJJK0KbEbgfkjGFF8nUPRcO'], false);
$run->gives('needsRehash', ['не хеш'], true);
$run->gives('digest', ['abc'], 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad');
$run->gives('digest', [''], 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855');
$run->gives('digest', ['привет', 'md5'], '608333adc72f545078ede3aad71bfe74');
$run->fails('digest', ['abc', 'nope'], 'Maws\\System\\Helpers\\Hash\\Exception\\HashMisuse');
$run->gives('sign', ['данные', 'ключ'], '735e97ea09bca778813114ffb283c41115d47dabf70d31e857b29f089cd7a3b2');
$run->gives('sign', ['данные', 'ключ', 'md5'], '6585d1de097be89b632ecb5ac4274560');
$run->fails('sign', ['данные', ''], 'Maws\\System\\Helpers\\Hash\\Exception\\HashMisuse');
$run->fails('sign', ['данные', 'ключ', 'nope'], 'Maws\\System\\Helpers\\Hash\\Exception\\HashMisuse');
$run->gives('same', ['abc', 'abc'], true);
$run->gives('same', ['abc', 'abd'], false);
$run->gives('same', ['', ''], false);

$run->finish();
