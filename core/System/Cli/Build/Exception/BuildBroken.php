<?php

namespace Maws\System\Cli\Build\Exception;

use Maws\System\Exception\MawsMisuse;

/**
 * Шаг сборки не выполнился. Наследуется от MawsMisuse, поэтому maws печатает
 * его строкой «Ошибка: …» без трейса — это отказ, а не авария.
 */
final class BuildBroken extends MawsMisuse
{
}
