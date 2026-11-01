<?php

namespace Maws\Ai\System\Exception;

class AiDbaseUnsupported extends AiException
{
    public function __construct(string $engine)
    {
        parent::__construct(
            "Движок не поддерживается: нужен PostgreSQL, а в DSN указан \"{$engine}\""
        );
    }

}
