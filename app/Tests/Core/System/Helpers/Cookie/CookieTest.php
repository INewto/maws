<?php

// Тест для core/System/Helpers/Cookie/Cookie.php.
// Создан командой: maws make test:core/System/Helpers/Cookie/Cookie.php
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

$run = new Maws\System\Test\Runner('core/System/Helpers/Cookie/Cookie.php');

$run->subject('Maws\\System\\Helpers\\Cookie\\Cookie', 'new');

$run->gives('create', ['sid', 'abc'], 'sid=abc; Path=/; Secure; HttpOnly; SameSite=Lax');
$run->gives('create', ['sid', 'abc', 1445412480], 'sid=abc; Expires=Wed, 21 Oct 2015 07:28:00 GMT; Path=/; Secure; HttpOnly; SameSite=Lax');
$run->gives('create', ['sid', 'Аня'], 'sid=%D0%90%D0%BD%D1%8F; Path=/; Secure; HttpOnly; SameSite=Lax');
$run->gives('create', ['sid', 'a b;c'], 'sid=a%20b%3Bc; Path=/; Secure; HttpOnly; SameSite=Lax');
$run->gives('create', ['sid', 'abc', null, '/', 'example.test'], 'sid=abc; Path=/; Domain=example.test; Secure; HttpOnly; SameSite=Lax');
$run->gives('create', ['sid', 'abc', null, '/', null, false, false, null], 'sid=abc; Path=/');
$run->gives('create', ['sid', 'abc', null, '/', null, true, true, 'strict'], 'sid=abc; Path=/; Secure; HttpOnly; SameSite=Strict');
$run->gives('create', ['__Host-sid', 'abc'], '__Host-sid=abc; Path=/; Secure; HttpOnly; SameSite=Lax');
$run->fails('create', ['__Host-sid', 'abc', null, '/', 'example.test'], 'Maws\\System\\Helpers\\Cookie\\Exception\\CookieMisuse');
$run->fails('create', ['__Host-sid', 'abc', null, '/app'], 'Maws\\System\\Helpers\\Cookie\\Exception\\CookieMisuse');
$run->fails('create', ['__Secure-sid', 'abc', null, '/', null, false], 'Maws\\System\\Helpers\\Cookie\\Exception\\CookieMisuse');
$run->fails('create', ['sid name', 'abc'], 'Maws\\System\\Helpers\\Cookie\\Exception\\CookieMisuse');
$run->fails('create', ['', 'abc'], 'Maws\\System\\Helpers\\Cookie\\Exception\\CookieMisuse');
$run->fails('create', ['sid', 'abc', null, 'app'], 'Maws\\System\\Helpers\\Cookie\\Exception\\CookieMisuse');
$run->fails('create', ['sid', 'abc', null, '/', null, false, true, 'None'], 'Maws\\System\\Helpers\\Cookie\\Exception\\CookieMisuse');
$run->fails('create', ['sid', 'abc', null, '/', null, true, true, 'Laxa'], 'Maws\\System\\Helpers\\Cookie\\Exception\\CookieMisuse');
$run->gives('forget', ['sid'], 'sid=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; Secure; HttpOnly; SameSite=Lax');
$run->gives('forget', ['sid', '/app', 'example.test'], 'sid=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/app; Domain=example.test; Secure; HttpOnly; SameSite=Lax');
$run->fails('forget', ['sid name'], 'Maws\\System\\Helpers\\Cookie\\Exception\\CookieMisuse');

$run->finish();
