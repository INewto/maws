<?php

namespace Maws\System\Migrations\Exception;

use Maws\System\Exception\MawsMisuse;

/**
 * Отказ миграции — объяснение, а не авария: «применять нечего», «такой миграции нет»,
 * «база уже не та, откат невозможен». Наследуется от MawsMisuse, поэтому maws печатает
 * его строкой «Ошибка: …», без трейса в кроне.
 */
final class MigrationBroken extends MawsMisuse
{
    /**
     * Откат запрещён: снимок и живая база разошлись. Начало фразы дословное — по нему
     * отказ и узнают; в скобках — какая миграция и в чём именно расхождение.
     */
    public static function mismatch(string $migration, string $reason): self
    {
        return new self("миграция не совпадает с актуальным состоянием, откат невозможен ({$migration}: {$reason})");
    }

    /**
     * down() не вернул базу к состоянию до миграции. Откат отменён транзакцией,
     * база осталась как была: это ошибка в down(), а не расхождение снимка и базы.
     */
    public static function wrongDown(string $migration, string $reason): self
    {
        return new self("down() не возвращает базу к состоянию до миграции, откат отменён ({$migration}: {$reason})");
    }
}
