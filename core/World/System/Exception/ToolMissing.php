<?php

namespace Maws\World\System\Exception;

class ToolMissing extends ToolException
{
    public function __construct(string $tool, string $file, string $reason)
    {
        parent::__construct("Инструмент {$tool} недоступен: {$file} — {$reason}");
    }

}
