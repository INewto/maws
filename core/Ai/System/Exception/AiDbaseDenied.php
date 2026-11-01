<?php

namespace Maws\Ai\System\Exception;

class AiDbaseDenied extends AiException
{
    public function __construct(array $table, array $allowed)
    {
        [$schema, $name] = $table;

        $shown = array_map(static fn (array $t): string => "{$t[0]}.{$t[1]}", $allowed);

        $tail = $allowed === []
            ? 'агенту не объявлена ни одна таблица'
            : 'разрешены: ' . implode(', ', $shown);

        parent::__construct("Запрос трогает таблицу {$schema}.{$name}, которой нет в объявлении агента ({$tail})");
    }

}
