<?php

// это файл с конфигурационными данными (внутренние переменные)

const CORE_DIR = __DIR__ . '/../core';

const APP_DIR    = __DIR__;
const WORLD_DIR  = APP_DIR . '/World';
const SCREEN_DIR = APP_DIR . '/Screen';
const AI_DIR     = APP_DIR . '/Ai';

const DEV = true;

// Язык сайта по умолчанию: код из двух строчных латинских букв. В каталогах с этим именем
// лежат шаблоны сцен и мест, а печенье site_language перекрывает его для посетителя.
const LANGUAGE = 'ru';

const LOGS_FILE = __DIR__ . '/../logs/maws.log';

const MIGRATIONS_DIR = __DIR__ . '/../db/migrations';

// Реквизиты своей базы: шаг 2 в README. DSN начинается с pgsql:, сама база должна
// существовать заранее — таблицы создаст миграция, пустую базу она не заводит.
const DBASE_DSN      = 'pgsql:host=127.0.0.1;port=5432;dbname=';
const DBASE_USER     = '';
const DBASE_PASSWORD = '';
