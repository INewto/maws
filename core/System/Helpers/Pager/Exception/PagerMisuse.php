<?php

namespace Maws\System\Helpers\Pager\Exception;

use Maws\System\Exception\MawsMisuse;

class PagerMisuse extends MawsMisuse
{
    public function __construct(string $reason)
    {
        parent::__construct("Пагинатор так не работает: {$reason}");
    }
}
