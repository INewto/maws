<?php

namespace Maws\Screen\System\Exception;

class SceneMissing extends ScreenException
{
    public function __construct(string $scene)
    {
        parent::__construct("Сцена {$scene} отсутствует");
    }

}
