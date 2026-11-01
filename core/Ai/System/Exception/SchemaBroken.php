<?php

namespace Maws\Ai\System\Exception;

class SchemaBroken extends AiException
{
    public function __construct(string $tool, string $reason)
    {
        parent::__construct("Схема аргументов инструмента {$tool} неверна: {$reason}");
    }

}
