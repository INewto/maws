<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
#[Purpose('Как собрать получателя контракта: фабрика строит корень, path ведёт от корня до получателя.')]
final class Subject
{
    #[Param('class', 'string', 'класс получателя — то, что проверяем')]
    #[Param('factory', 'string', 'статический метод, строящий КОРЕНЬ, строкой "Класс::метод"')]
    #[Param('args', 'array', 'аргументы фабрики; только константные выражения')]
    #[Param('path', 'string', 'цепочка свойств через "->" от корня до получателя; пусто — получатель и есть корень')]
    public function __construct(
        public readonly string $class,
        public readonly string $factory,
        public readonly array $args = [],
        public readonly string $path = ''
    ) {
    }
}
