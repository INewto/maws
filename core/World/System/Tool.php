<?php

namespace Maws\World\System;

use Maws\System\Contract\{Effect, Param, Purpose};

#[Purpose('Общая основа инструмента места: имя и база с правами этого инструмента.')]
abstract class Tool
{
    #[Purpose('Запоминает имя инструмента и его базу.')]
    #[Param('toolName', 'string', 'имя инструмента')]
    #[Param('db', 'Dbase', 'база с правами этого инструмента')]
    #[Effect(Effect::NONE)]
    final public function __construct($toolName, protected Dbase $db)
    {
    }
}
