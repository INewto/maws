<?php

namespace Maws\World\System\Exception;

class WorkspaceNotDeclared extends WorldMisuse
{
    public function __construct(string $class, string $file)
    {
        parent::__construct(
            "Класс рабочего пространства {$class} не объявлен: {$file} — "
            . 'файл должен объявлять этот класс'
        );
    }
}
