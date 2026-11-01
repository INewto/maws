<?php

namespace Maws\Man\Body;

use Maws\Man\Hands\Hands;
use Maws\Man\Head\Head;
use Maws\Man\Legs\Legs;
use Maws\Man\Man;
use Maws\Man\System\Exception\OrganAssembly;
use Maws\System\Contract\{Effect, Param, Purpose, Throws};

#[Purpose('Тело человека: собирает и держит органы.')]
final class Body
{
    public readonly Man $man;
    public readonly Head $head;
    public readonly Hands $hands;
    public readonly Legs $legs;

    #[Purpose('Собирает голову, руки и ноги. Вызывается только из человека.')]
    #[Param('man', 'Man', 'человек, которому принадлежит тело')]
    #[Param('world', 'World', 'карта мира')]
    #[Param('screen', 'Screen', 'экран для показа сцен')]
    #[Throws(OrganAssembly::class, 'тело собирают не из человека')]
    #[Effect(Effect::NONE)]
    public function __construct($man, $world, $screen)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (($trace[1]['class'] ?? null) !== Man::class) {
            throw new OrganAssembly(__CLASS__, Man::class);
        }

        // Тело помнит, чьё оно: органам нужен человек целиком, а не только они сами.
        // Голова отдаёт его рабочему пространству — так оно получает человека из рук
        // того, кто его собрал, а не догадывается о нём.
        $this->man   = $man;
        $this->head  = Head::create($this, $world);
        $this->hands = Hands::create($this, $world, $screen);
        $this->legs  = Legs::create($this, $world);
    }

    private function __clone()
    {
    }
}
