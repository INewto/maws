<?php

namespace Maws\System\Cache\Exception;

use Maws\System\Exception\MawsMisuse;

class CacheMisuse extends MawsMisuse
{
    public function __construct(string $reason)
    {
        parent::__construct("Политика кэша не годится: {$reason}");
    }
}
