<?php

namespace Maws\Ai\System\Exception;

class AccessUnreadable extends AiException
{
    public function __construct(string $file)
    {
        parent::__construct("Реестр прав агента не читается: {$file}");
    }

}
