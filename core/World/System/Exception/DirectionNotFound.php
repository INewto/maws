<?php

namespace Maws\World\System\Exception;

class DirectionNotFound extends WorldException
{
    public function __construct(string $direction)
    {
        parent::__construct("Направление не найдено: {$direction}");
    }

    public function status(): int
    {
        return 404;
    }
}
