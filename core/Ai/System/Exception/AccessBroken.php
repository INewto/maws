<?php

namespace Maws\Ai\System\Exception;

class AccessBroken extends AiException
{
    public function __construct(string $file, string $reason)
    {
        parent::__construct("Реестр прав агента {$file} не разбирается: {$reason}");
    }

}
