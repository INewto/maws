<?php

namespace Maws\World\System\Exception;

class WorkspacesMissing extends WorldMisuse
{
    public function __construct(string $direction, string $file)
    {
        parent::__construct("Не найдена база рабочих пространств для направления {$direction}: {$file}");
    }
}
