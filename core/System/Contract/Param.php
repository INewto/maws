<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
#[Purpose('Один параметр метода: имя, тип и уточнение. Ставится по разу на параметр.')]
final class Param
{
    #[Param('name', 'string', 'имя параметра как в сигнатуре')]
    #[Param('type', 'string', 'тип параметра строкой')]
    #[Param('note', 'string', 'уточнение смысла; пусто — если имя говорит само')]
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $note = ''
    ) {
    }
}
