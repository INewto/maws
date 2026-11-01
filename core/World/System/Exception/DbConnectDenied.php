<?php

namespace Maws\World\System\Exception;

class DbConnectDenied extends WorldException
{
    public function __construct()
    {
        parent::__construct("Подключение к базе данных было запрещено");
    }
}
