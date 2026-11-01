<?php

namespace Maws\World\System\Exception;

class AreaBroken extends WorldMisuse
{
    public function __construct(int $areaId, string $reason)
    {
        $where = $areaId === 0 ? 'строка areas' : "раздел {$areaId}";

        parent::__construct("{$where}: {$reason}");
    }
}
