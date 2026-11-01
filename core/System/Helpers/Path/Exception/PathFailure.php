<?php

namespace Maws\System\Helpers\Path\Exception;

use Maws\System\Exception\MawsException;

/**
 * Путь вышел за свой корень: «..» некуда схлопывать. Негодный вход, а не сбой: 400.
 *
 * У ядра своя копия — `Maws\System\Exception\PathFailure`: ядро не может звать хелпер, а хелпер
 * обязан удаляться, не роняя ядро.
 */
class PathFailure extends MawsException
{
    public function __construct(string $path)
    {
        parent::__construct("путь выходит за свой корень: {$path} — «..» некуда схлопывать");
    }

    public function status(): int
    {
        return 400;
    }
}
