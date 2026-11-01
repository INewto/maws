<?php

namespace Maws\World\System\Exception;

class DbaseMisuse extends WorldMisuse
{
    public function __construct(string $reason)
    {
        parent::__construct("Обращение с базой неверно: {$reason}");
    }
}
