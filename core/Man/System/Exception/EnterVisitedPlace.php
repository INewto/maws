<?php

namespace Maws\Man\System\Exception;

class EnterVisitedPlace extends ManMisuse
{
    public function __construct(string $place, array $visitedPlaces)
    {
        parent::__construct(
            "Нарушен порядок walk → enter: место {$place} уже посещено за этот запрос. Путь: "
            . implode(' → ', $visitedPlaces)
        );
    }
}
