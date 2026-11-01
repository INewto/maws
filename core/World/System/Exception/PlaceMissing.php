<?php

namespace Maws\World\System\Exception;

/**
 * Отказ раздела: в мире нет ни одного места. Раздел наследует место — без места ему
 * нечего наследовать, а завести место здесь нельзя: это дело make place, у раздела
 * своих файлов нет.
 */
class PlaceMissing extends WorldMisuse
{
    public function __construct()
    {
        parent::__construct('Не найдено ни одного места в мире');
    }
}
