<?php

namespace Maws\Man\System\Exception;

class TakeWithoutEnter extends ManMisuse
{
    public function __construct(string $place, ?string $currentLocation)
    {
        $where = $currentLocation === null
            ? 'человек ещё никуда не входил'
            : "человек находится в месте {$currentLocation}";

        parent::__construct(
            "Нарушен порядок enter → take: инструменты места {$place} запрошены без входа — {$where}"
        );
    }
}
