<?php

namespace Maws\System\Crabic\Exception;

use Maws\System\Exception\MawsMisuse;

/**
 * Пачка трекера не того вида. Это не сбой фреймворка: такие данные приходят снаружи каждый день,
 * и вызывающий не падает, а получает причину отказа — поэтому исключение ловится на месте.
 */
class CrabicBroken extends MawsMisuse
{
    public function __construct(string $reason)
    {
        parent::__construct("Пачка трекера не годится: {$reason}");
    }
}
