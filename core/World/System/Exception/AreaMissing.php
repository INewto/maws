<?php

namespace Maws\World\System\Exception;

/**
 * Отказ раздела: в таблице areas нет ни одной строки. Заводить раздел не от чего —
 * корневую строку ставит миграция, и без неё дереву не на чем расти.
 */
class AreaMissing extends WorldMisuse
{
    public function __construct()
    {
        parent::__construct(
            'В таблице areas нет ни одного раздела — дереву не на чем расти. '
            . 'Корневую строку заводит миграция 0001_initial_schema.'
        );
    }
}
