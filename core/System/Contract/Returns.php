<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
#[Purpose('Что метод возвращает. Для void не ставить — тип уже виден в сигнатуре.')]
final class Returns
{
    #[Param('type', 'string', 'тип возвращаемого значения строкой')]
    #[Param('note', 'string', 'уточнение смысла возврата; пусто — если имя говорит само')]
    public function __construct(
        public readonly string $type,
        public readonly string $note = ''
    ) {
    }
}
