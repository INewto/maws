<?php

namespace Maws\Ai\System\Exception;

class AccessNotDeclared extends AiMisuse
{
    public function __construct(string $file)
    {
        parent::__construct("Агенту запрошена база, но реестр прав не объявлен: {$file}");
    }
}
