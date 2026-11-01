<?php

namespace Maws\Ai\System\Exception;

class AiDbaseMisuse extends AiMisuse
{
    public function __construct(string $reason)
    {
        parent::__construct("Обращение с базой неверно: {$reason}");
    }
}
