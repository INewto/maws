<?php

namespace Maws\Man\System\Exception;

class EnterWithoutWalk extends ManMisuse
{
    public function __construct(string $place, ?string $nearPlace)
    {
        $where = $nearPlace === null
            ? 'человек ещё никуда не переходил'
            : "человек находится в месте {$nearPlace}";

        parent::__construct(
            "Нарушен порядок walk → enter: вход в место {$place} без перехода — {$where}"
        );
    }
}
