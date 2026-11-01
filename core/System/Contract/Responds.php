<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
#[Purpose('Ответный вызов: аргументы → напечатанное тело и код ответа. Ставится у места, которое отвечает само и завершается ResponseSent.')]
final class Responds
{
    #[Param('args', 'array', 'аргументы вызова; только константные выражения и ссылки @имя/Token::*')]
    #[Param('body', 'string', 'ожидаемое тело ответа, байт в байт')]
    #[Param('status', 'int', 'ожидаемый код ответа; по умолчанию 200 — «код не выставлен» в веб-SAPI значит 200')]
    #[Param('before', 'array', 'шаги подготовки до вызова: [путь, аргументы] или [путь, аргументы, имя]')]
    public function __construct(
        public readonly array $args,
        public readonly string $body,
        public readonly int $status = 200,
        public readonly array $before = []
    ) {
    }
}
