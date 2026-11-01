<?php

namespace Maws\Screen\System\Exception;

class ConfigUnreadable extends ScreenException
{
    public function __construct(string $file)
    {
        parent::__construct("Конфиг экрана не читается: {$file}");
    }

}
