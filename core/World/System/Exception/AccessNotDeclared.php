<?php

namespace Maws\World\System\Exception;

class AccessNotDeclared extends WorldException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("Место {$from} не объявило доступ к месту {$to}");
    }

}
