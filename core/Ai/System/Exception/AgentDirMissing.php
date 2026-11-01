<?php

namespace Maws\Ai\System\Exception;

class AgentDirMissing extends AiMisuse
{
    public function __construct(string $agentClass)
    {
        parent::__construct("У агента {$agentClass} не задан каталог объявлений (\$agentDir)");
    }
}
