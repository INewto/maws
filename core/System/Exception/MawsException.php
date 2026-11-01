<?php

namespace Maws\System\Exception;

abstract class MawsException extends \Exception
{
    public function status(): int
    {
        return 500;
    }
}
