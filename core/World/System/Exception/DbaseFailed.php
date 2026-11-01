<?php

namespace Maws\World\System\Exception;

class DbaseFailed extends WorldMisuse
{
    public function __construct(string $sql, string $reason)
    {
        parent::__construct("Запрос не выполнен: {$reason} — {$sql}");
    }
}
