<?php

namespace Maws\Screen\System\Exception;

class CommandNotDeclared extends ScreenMisuse
{
    public function __construct(string $command)
    {
        parent::__construct("Команда экрана {$command} не объявлена");
    }
}
