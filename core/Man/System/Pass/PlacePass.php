<?php

namespace Maws\Man\System\Pass;

use Maws\Man\Man;
use Maws\Man\System\Exception\OrganAssembly;
use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};

#[Subject(PlacePass::class, 'Scaffold::placePass', ['Public/Home'])]
#[Purpose('Пропуск в место: доказательство, что человек туда вошёл.')]
final class PlacePass
{
    private function __construct(private string $place)
    {
    }

    #[Purpose('Выписывает пропуск. Вызывается только из человека.')]
    #[Param('place', 'string', 'адрес места')]
    #[Returns('self', 'пропуск в это место')]
    #[Throws(OrganAssembly::class, 'пропуск выписан не человеком')]
    #[Effect(Effect::NONE)]
    #[Fails(['Public/Home'], OrganAssembly::class)]
    public static function create(string $place): self
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (($trace[1]['class'] ?? null) !== Man::class) {
            throw new OrganAssembly(__CLASS__, Man::class);
        }

        return new self($place);
    }

    #[Purpose('Отдаёт адрес места, в которое выписан пропуск.')]
    #[Returns('string', 'адрес места')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'Public/Home')]
    public function place(): string
    {
        return $this->place;
    }
}
