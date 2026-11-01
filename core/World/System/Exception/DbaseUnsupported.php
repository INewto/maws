<?php

namespace Maws\World\System\Exception;

class DbaseUnsupported extends WorldException
{
    public function __construct(string $engine)
    {
        parent::__construct(
            "Движок не поддерживается: нужен PostgreSQL, а в DSN указан \"{$engine}\""
        );
    }

}
