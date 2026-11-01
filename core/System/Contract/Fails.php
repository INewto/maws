<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
#[Purpose('Отказной вызов: аргументы → ожидаемое исключение. Ставится по разу на случай.')]
final class Fails
{
    #[Param('args', 'array', 'аргументы вызова; только константные выражения и ссылки @имя/Token::*')]
    #[Param('exception', 'string', 'класс ожидаемого исключения')]
    #[Param('before', 'array', 'шаги подготовки до вызова: [путь, аргументы] или [путь, аргументы, имя]')]
    public function __construct(
        public readonly array $args,
        public readonly string $exception,
        public readonly array $before = []
    ) {
    }
}
