<?php

namespace Maws\World\System\Exception;

class DbaseUnreachable extends WorldException
{
    public function __construct(string $dsn, string $reason)
    {
        parent::__construct("База данных недоступна: {$dsn} — {$reason}");
    }

}
