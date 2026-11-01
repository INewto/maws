<?php

namespace Maws\World\System\Exception;

class DbasePlanless extends WorldMisuse
{
    public function __construct(string $sql)
    {
        parent::__construct("Запрос не назвал ни одной таблицы, а стражу нечего сверять: {$sql}");
    }
}
