<?php

use Maws\System\Migrations\Migration;

// 0002_drop_directions_main — убирает флаг «главное направление».
//
// Главную страницу сайта задаёт раздел (`areas.main`), а не направление: адрес
// разрешается по дереву разделов, и направления в адресе не участвуют. Колонка
// осталась от прежней модели и не читается ни одной строкой кода.
//
// up() возвращает операторы по порядку: сначала создаются таблицы, потом в них
// пишутся данные. Всё это уходит одной транзакцией вместе с записью в журнал.
//
// down() возвращает операторы отката — в обратном порядке к up(). Пишет их автор:
// только он знает, что делать с данными. После down() структура сверяется со
// снимком «до»; не сошлось — откат отменяется, база остаётся как была.
return new class () extends Migration {
    public function up(): array
    {
        return [
            'ALTER TABLE directions DROP COLUMN main',
        ];
    }

    public function down(): array
    {
        return [
            'ALTER TABLE directions ADD COLUMN main boolean NOT NULL DEFAULT false',
        ];
    }
};
