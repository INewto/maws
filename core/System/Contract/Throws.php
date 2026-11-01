<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
#[Purpose('Исключение, которое метод бросает. Ставится по разу на каждый случай отказа.')]
final class Throws
{
    #[Param('exception', 'string', 'класс исключения')]
    #[Param('when', 'string', 'при каком условии бросается; пусто — если условие очевидно')]
    public function __construct(
        public readonly string $exception,
        public readonly string $when = ''
    ) {
    }
}
