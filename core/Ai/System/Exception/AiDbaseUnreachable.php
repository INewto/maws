<?php

namespace Maws\Ai\System\Exception;

class AiDbaseUnreachable extends AiException
{
    public function __construct(string $dsn, string $reason)
    {
        parent::__construct("База данных недоступна: {$dsn} — {$reason}");
    }

}
