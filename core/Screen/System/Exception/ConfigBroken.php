<?php

namespace Maws\Screen\System\Exception;

class ConfigBroken extends ScreenException
{
    public function __construct(string $reason)
    {
        parent::__construct("Конфиг экрана не даёт нужного: {$reason}");
    }

}
