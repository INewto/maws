<?php

namespace Maws\Ai\System\Exception;

class ToolNotImplemented extends AiMisuse
{
    public function __construct(string $tool, string $agentClass)
    {
        parent::__construct("Инструмент {$tool} объявлен в tools.json, но метода {$tool}() у агента {$agentClass} нет");
    }
}
