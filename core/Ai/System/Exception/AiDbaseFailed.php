<?php

namespace Maws\Ai\System\Exception;

class AiDbaseFailed extends AiMisuse
{
    public function __construct(string $sql, string $reason)
    {
        parent::__construct("Запрос не выполнен: {$reason} — {$sql}");
    }
}
