<?php

namespace Maws\Screen\System\Exception;

class TemplateMissing extends ScreenException
{
    public function __construct(string $template)
    {
        parent::__construct("Шаблон {$template} отсутствует");
    }
}
