<?php

namespace Maws\Ai\System\Exception;

class AiDbasePlanless extends AiMisuse
{
    public function __construct(string $sql)
    {
        parent::__construct("Запрос не назвал ни одной таблицы, а стражу нечего сверять: {$sql}");
    }
}
