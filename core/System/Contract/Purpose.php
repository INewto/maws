<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
#[Purpose('Зачем эта сущность существует: одна фраза, без пересказа реализации.')]
final class Purpose
{
    #[Param('text', 'string', 'назначение одной фразой')]
    public function __construct(public readonly string $text)
    {
    }
}
