<?php

namespace Maws\System\Helpers\Http\Exception;

use Maws\System\Exception\MawsException;
use Throwable;

class HttpUnreachable extends MawsException
{
    public function __construct(
        private string $method,
        private string $url,
        Throwable $reason
    ) {
        parent::__construct(
            "Запрос {$method} {$url} не выполнен: " . $reason->getMessage(),
            0,
            $reason
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function url(): string
    {
        return $this->url;
    }

}
