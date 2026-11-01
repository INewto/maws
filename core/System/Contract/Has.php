<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
#[Purpose('Успешный вызов: результат — массив, в котором есть перечисленные ключи. Значения и лишние ключи не проверяются: их задаёт тот, кто наполняет данные.')]
final class Has
{
    #[Param('args', 'array', 'аргументы вызова; только константные выражения и ссылки @имя/Token::*')]
    #[Param('keys', 'array', 'ключи, которые обязаны быть в результате')]
    #[Param('before', 'array', 'шаги подготовки до вызова: [путь, аргументы] или [путь, аргументы, имя]')]
    public function __construct(
        public readonly array $args,
        public readonly array $keys,
        public readonly array $before = []
    ) {
    }
}
