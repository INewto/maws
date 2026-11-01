<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
#[Purpose('Успешный вызов: аргументы → ожидаемое значение. Ставится по разу на случай.')]
final class Gives
{
    #[Param('args', 'array', 'аргументы вызова; только константные выражения и ссылки @имя/Token::*')]
    #[Param('expect', 'mixed', 'ожидаемое значение; Token::STATEMENT — «готовое выражение» (PDOStatement)')]
    #[Param('before', 'array', 'шаги подготовки до вызова: [путь, аргументы] или [путь, аргументы, имя]')]
    public function __construct(
        public readonly array $args,
        public readonly mixed $expect,
        public readonly array $before = []
    ) {
    }
}
