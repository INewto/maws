<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
#[Purpose('Файлы песочницы, создаваемые до прогона: путь в песочнице → содержимое.')]
final class Fixture
{
    #[Param('files', 'array', 'пары «путь → содержимое»; путь относителен песочницы, доступной как Token::SANDBOX')]
    public function __construct(public readonly array $files = [])
    {
    }
}
