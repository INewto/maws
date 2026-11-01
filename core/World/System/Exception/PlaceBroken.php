<?php

namespace Maws\World\System\Exception;

class PlaceBroken extends WorldMisuse
{
    public function __construct(string $way)
    {
        parent::__construct(
            "Адрес места \"{$way}\" не той формы — нужен \"Направление/Место\""
        );
    }
}
