<?php

namespace Maws\Ai\System\Exception;

class ContractUnreadable extends AiException
{
    public function __construct(string $file)
    {
        parent::__construct("Объявление агента не читается: {$file}");
    }

}
