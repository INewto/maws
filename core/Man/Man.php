<?php

namespace Maws\Man;

use Maws\Man\Body\Body;
use Maws\Man\Hands\Hands;
use Maws\Man\Head\Head;
use Maws\Man\Legs\Legs;
use Maws\Man\System\Exception\EnterVisitedPlace;
use Maws\Man\System\Exception\EnterWithoutWalk;
use Maws\Man\System\Pass\PlacePass;
use Maws\System\Contract\{Changes, Effect, Fails, Param, Purpose, Returns, Subject, Throws};
use Maws\World\World;

#[Subject(Man::class, 'Scaffold::man', [])]
#[Purpose('Человек: тело с органами и доступ к миру через них.')]
final class Man
{
    public readonly World $world;
    public readonly Body $body;
    public readonly Head $head;
    public readonly Hands $hands;
    public readonly Legs $legs;

    #[Purpose('Собирает тело и раздаёт его органы.')]
    #[Param('world', 'World', 'карта мира')]
    #[Param('screen', 'Screen', 'экран для показа сцен')]
    #[Effect(Effect::NONE)]
    public function __construct($world, $screen)
    {
        $this->world = $world;
        $this->body  = new Body($this, $world, $screen);
        $this->head  = $this->body->head;
        $this->hands = $this->body->hands;
        $this->legs  = $this->body->legs;
    }

    #[Purpose('Входит в место, если человек к нему подошёл и ещё там не был.')]
    #[Param('place', 'string', 'адрес места')]
    #[Returns('PlacePass', 'пропуск в это место')]
    #[Throws(EnterWithoutWalk::class, 'человек не подошёл к этому месту')]
    #[Throws(EnterVisitedPlace::class, 'человек уже был в этом месте')]
    #[Effect(Effect::NONE)]
    #[Fails(['Public/Home'], EnterWithoutWalk::class)]
    #[Changes(['Public/Home'], [['head->currentLocation', [], 'Public/Home']], before: [['legs->walk', ['Public/Home']]])]
    public function enterPlace($place): PlacePass
    {
        if ($this->head->nearPlace !== $place) {
            throw new EnterWithoutWalk($place, $this->head->nearPlace);
        }

        if (in_array($place, $this->head->visitedPlaces, true)) {
            throw new EnterVisitedPlace($place, $this->head->visitedPlaces);
        }

        // Вход — событие человека, а помнит о нём голова: память пополняется её же методом.
        $this->head->rememberEntry($place);

        return PlacePass::create($place);
    }

    private function __clone()
    {
    }
}
