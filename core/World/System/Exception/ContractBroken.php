<?php

namespace Maws\World\System\Exception;

class ContractBroken extends WorldException
{
    public function __construct(string $file, string $reason)
    {
        parent::__construct("Контракт места {$file} не разбирается: {$reason}");
    }

}
