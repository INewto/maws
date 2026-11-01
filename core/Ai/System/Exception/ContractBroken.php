<?php

namespace Maws\Ai\System\Exception;

class ContractBroken extends AiException
{
    public function __construct(string $file, string $reason)
    {
        parent::__construct("Объявление агента {$file} не разбирается: {$reason}");
    }

}
