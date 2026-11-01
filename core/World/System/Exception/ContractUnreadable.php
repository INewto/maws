<?php

namespace Maws\World\System\Exception;

class ContractUnreadable extends WorldException
{
    public function __construct(string $file)
    {
        parent::__construct("Контракт места не читается: {$file}");
    }

}
