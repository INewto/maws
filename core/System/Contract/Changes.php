<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
#[Purpose('Мутирующий вызов: аргументы → проверки состояния после. Ставится по разу на случай.')]
final class Changes
{
    #[Param('args', 'array', 'аргументы вызова; только константные выражения и ссылки @имя/Token::*')]
    #[Param('then', 'array', 'проверки после вызова: [путь, аргументы, ожидание]')]
    #[Param('before', 'array', 'шаги подготовки до вызова: [путь, аргументы] или [путь, аргументы, имя]')]
    public function __construct(
        public readonly array $args,
        public readonly array $then,
        public readonly array $before = []
    ) {
    }
}
