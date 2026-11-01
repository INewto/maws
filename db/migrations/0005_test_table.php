<?php

use Maws\System\Migrations\Migration;

// 0005_test_table — таблица для показов: `app/Ai/Multi/Agent1` читает её и пишет в неё,
// инструмент `ExampleTool` из `make build` читает её на главной странице.
//
// Агенту нужна хоть одна таблица, объявленная в его dbaccess.json, иначе пример работы с базой
// не запускается на чистом проекте: каркас `make build` заводит место Home с инструментом
// ExampleTool, а своей таблицы у места нет. Инструменту она нужна по той же причине: Repository
// без разрешённой таблицы ничего не прочитает.
//
// Колонки ровно те, что нужны показам: id — адрес строки, name — то, что читается и пишется.
// Строки сеются здесь же: пустая таблица ничего не показывает — чтение печатает ноль строк,
// UPDATE не находит id = 1, а главная страница выводит пустой список.
//
// Ничего, кроме показов, на эту таблицу не опирается.
return new class () extends Migration {
    public function up(): array
    {
        return [
            'CREATE TABLE test_table (
                id bigint GENERATED ALWAYS AS IDENTITY,
                name varchar(64) NOT NULL,
                CONSTRAINT test_table_pkey PRIMARY KEY (id)
            )',

            "INSERT INTO test_table (name) VALUES ('first'), ('second'), ('third')",
        ];
    }

    public function down(): array
    {
        return [
            'DROP TABLE test_table',
        ];
    }
};
