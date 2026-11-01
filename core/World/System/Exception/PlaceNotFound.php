<?php

namespace Maws\World\System\Exception;

class PlaceNotFound extends WorldException
{
    public function __construct(string $place)
    {
        parent::__construct("Место не найдено: {$place}");
    }

    public function status(): int
    {
        return 404;
    }
}
