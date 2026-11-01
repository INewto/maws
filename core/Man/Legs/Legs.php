<?php

namespace Maws\Man\Legs;

use Maws\Man\Body\Body;
use Maws\Man\System\Exception\OrganAssembly;
use Maws\System\Contract\{Changes, Effect, Fails, Param, Purpose, Returns, Subject, Throws};
use Maws\World\System\Exception\DirectionNotFound;
use Maws\World\System\Exception\PlaceBroken;
use Maws\World\System\Exception\PlaceNotFound;
use Maws\World\World;

#[Subject(Legs::class, 'Scaffold::man', [], 'legs')]
#[Purpose('Ноги: подводят человека к месту.')]
final class Legs
{
    private readonly Body $body;
    private readonly World $world;

    private function __construct($body, $world)
    {
        $this->body  = $body;
        $this->world = $world;
    }

    #[Purpose('Собирает ноги. Вызывается только из тела.')]
    #[Param('body', 'Body', 'тело, которому принадлежат ноги')]
    #[Param('world', 'World', 'карта мира')]
    #[Returns('self', 'ноги, привязанные к телу и миру')]
    #[Throws(OrganAssembly::class, 'ноги собирают не из тела')]
    #[Effect(Effect::NONE)]
    public static function create($body, $world): self
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (($trace[1]['class'] ?? null) !== Body::class) {
            throw new OrganAssembly(__CLASS__, Body::class);
        }

        return new self($body, $world);
    }

    private function useHead()
    {
        return $this->body->head;
    }

    #[Purpose('Ставит человека рядом с местом, запоминая адрес как ближайший.')]
    #[Param('place', 'string', 'адрес места')]
    #[Throws(PlaceBroken::class, 'адрес не состоит ровно из двух непустых частей')]
    #[Throws(DirectionNotFound::class, 'направления нет в карте мира')]
    #[Throws(PlaceNotFound::class, 'места нет в списке мест направления')]
    #[Effect(Effect::NONE)]
    #[Fails(['Nope/Nope'], DirectionNotFound::class)]
    #[Fails(['Public/Nope'], PlaceNotFound::class)]
    #[Changes(['Public/Home'], [['head->nearPlace', [], 'Public/Home']])]
    public function walk($place)
    {
        // Ноги идут, а помнит об этом голова: она и записывает, к какому месту подошли.
        $this->useHead()->noticeNear($this->world->find($place)->way());
    }

    private function __clone()
    {
    }
}
