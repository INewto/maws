<?php

namespace Maws\Man\Hands;

use Maws\Man\Body\Body;
use Maws\Man\System\Exception\OrganAssembly;
use Maws\Man\System\Exception\TakeWithoutEnter;
use Maws\Man\System\Pass\PlacePass;
use Maws\Screen\Screen;
use Maws\Screen\System\Exception\CommandNotDeclared;
use Maws\Screen\System\Exception\ConfigBroken;
use Maws\Screen\System\Exception\ConfigUnreadable;
use Maws\Screen\System\Exception\SceneMissing;
use Maws\System\Contract\{Effect, Param, Purpose, Returns, Throws};
use Maws\System\Cache\Policy;
use Maws\World\System\Dbase;
use Maws\World\System\DbaseRights;
use Maws\World\System\Exception\AccessNotDeclared;
use Maws\World\System\Exception\ContractBroken;
use Maws\World\System\Exception\ContractUnreadable;
use Maws\World\System\Exception\PlaceNotFound;
use Maws\World\System\Exception\ToolMissing;
use Maws\World\System\Place;
use Maws\World\System\Tools;
use Maws\World\World;
use stdClass;

#[Purpose('Руки: инструменты места и обращение к экрану.')]
final class Hands
{
    public readonly Body $body;
    public readonly World $world;
    public readonly Screen $screen;

    private function __construct($body, $world, $screen)
    {
        $this->body   = $body;
        $this->world  = $world;
        $this->screen = $screen;
    }

    #[Purpose('Собирает руки. Вызывается только из тела.')]
    #[Param('body', 'Body', 'тело, которому принадлежат руки')]
    #[Param('world', 'World', 'карта мира')]
    #[Param('screen', 'Screen', 'экран для показа сцен')]
    #[Returns('self', 'руки, привязанные к телу, миру и экрану')]
    #[Throws(OrganAssembly::class, 'руки собирают не из тела')]
    #[Effect(Effect::NONE)]
    public static function create($body, $world, $screen): self
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (($trace[1]['class'] ?? null) !== Body::class) {
            throw new OrganAssembly(__CLASS__, Body::class);
        }
        return new self($body, $world, $screen);
    }

    private function useHead()
    {
        return $this->body->head;
    }

    #[Purpose('Нажимает кнопку пульта: показывает сцену через экран. Шапку страницы — title, описание, og — знает голова, а не место: руки передают её экрану вместе с данными места, поэтому воркспейс о метаданных не знает и знать не должен.')]
    #[Param('screenType', 'string|null', 'адрес экрана; для кнопки redirect не нужен')]
    #[Param('command', 'string', 'кнопка пульта: showPage, show404 или redirect')]
    #[Param('data', 'mixed', 'данные для сцены; для redirect — адрес строкой или пара ["url" => адрес, "status" => код]')]
    #[Throws(CommandNotDeclared::class, 'пульт не знает такой кнопки')]
    #[Throws(ConfigUnreadable::class, 'не читается app/Screen/config.json')]
    #[Throws(ConfigBroken::class, 'нет сцены для направления')]
    #[Throws(SceneMissing::class, 'нет шаблона сцены')]
    #[Effect(Effect::SCREEN)]
    public function useScreenInterface($screenType, $command, $data = null)
    {
        // Шапка нужна только странице: у 404 и редиректа её не печатают.
        $meta = $command === 'showPage' ? $this->useHead()->pageMeta() : null;

        // Политика кэша идёт тем же путём, что шапка: место объявляет её в settings.json,
        // голова знает место, руки передают. У 404 и редиректа кэша нет вовсе.
        $cache = $command === 'showPage' ? $this->useHead()->cachePolicy() : Policy::none();

        return $this->screen->screenInterface($screenType, $command, $data, $meta, $cache);
    }

    #[Purpose('Показывает сцену ошибки через экран. Завершает процесс.')]
    #[Param('status', 'int', 'код ответа')]
    #[Param('message', 'string|null', 'текст сбоя; показывается только в DEV')]
    #[Throws(ConfigUnreadable::class, 'не читается app/Screen/config.json')]
    #[Throws(ConfigBroken::class, 'нет сцены "Error"')]
    #[Throws(SceneMissing::class, 'нет шаблона сцены')]
    #[Effect(Effect::SCREEN)]
    public function useScreenErrorInterface($status, $message = null)
    {
        return $this->screen->screenErrorInterface($status, $message);
    }

    #[Purpose('Отдаёт инструменты места: своё — целиком, чужое — только объявленное в "needs" первого места. Направление — граница доверия: место одного направления не может взять инструменты другого, поэтому нужда вне своего направления запрещена.')]
    #[Param('place', 'string', 'адрес места, чьи инструменты нужны')]
    #[Returns('object', 'объект со свойством tools: доступные инструменты')]
    #[Throws(AccessNotDeclared::class, 'первое место не объявило нужду в этом месте')]
    #[Throws(ContractUnreadable::class, 'нет settings.json')]
    #[Throws(ContractBroken::class, 'битый settings.json или нужда вне своего направления')]
    #[Effect(Effect::FS_READ)]
    public function getToolsFor($place)
    {
        // Права выдаёт ПЕРВОЕ место, в которое человек вошёл, а не то, где он стоит сейчас:
        // за запрос можно обойти несколько мест, и каждое следующее открывается только тем,
        // что объявило первое. Своё место видно целиком — это его собственные инструменты.
        // Пустой visitedPlaces — человек ещё никуда не входил: World::find откажет сам,
        // понятным текстом, а не TypeError.
        $firstPlace = $this->useHead()->visitedPlaces[0] ?? '';

        $caller = Tools::of($this->world->find($firstPlace)->dir(), $firstPlace);

        if ($place !== $firstPlace) {
            $asked = Tools::of($this->world->find($place)->dir(), $place);

            return (object) ['tools' => (object) [
                'list'  => $caller->for($place, $asked->list()),
                'needs' => (object) $asked->needs(),
            ]];
        }

        return (object) ['tools' => $caller->raw()];
    }

    #[Purpose('Выдаёт инструменты места по пропуску, вложив в каждый его базу с правами.')]
    #[Param('pass', 'PlacePass', 'пропуск, выписанный человеком')]
    #[Returns('object|false', 'инструменты места или false, если их нет')]
    #[Throws(TakeWithoutEnter::class, 'человек не в этом месте')]
    #[Throws(AccessNotDeclared::class, 'первое место не объявило нужду в этом месте')]
    #[Throws(ContractUnreadable::class, 'нет settings.json или dbase.json')]
    #[Throws(ContractBroken::class, 'битый settings.json или нужда вне своего направления')]
    #[Throws(ToolMissing::class, 'нет файла или класса инструмента')]
    #[Effect(Effect::FS_READ)]
    public function takeTools(PlacePass $pass)
    {
        $place = $pass->place();

        if ($this->useHead()->currentLocation !== $place) {
            throw new TakeWithoutEnter($place, $this->useHead()->currentLocation);
        }

        $toolsList = $this->getToolsFor($place);
        return $this->assembleTools($toolsList, $place);
    }

    #[Purpose('Собирает инструменты места в один объект, вкладывая в каждый его базу с правами.')]
    private function assembleTools($toolsList, $place)
    {
        if (empty($toolsList->tools->list)) {
            return false;
        }

        $placeObject = $this->world->find($place);
        $tools = new stdClass();

        foreach ($toolsList->tools->list as $tool) {
            $toolDir = $placeObject->dir() . "/Tools/{$tool}";
            $class   = self::toolClass($placeObject, $tool);

            $tools->$tool = new $class($tool, new Dbase(DbaseRights::fromDir($toolDir)));
        }

        return $tools;
    }

    /**
     * Ищет класс инструмента: файл подключается, а класс в нём называется полным именем —
     * тем, что выводится из направления, места и имени инструмента. Автозагрузка не
     * спрашивается: файл с этим путём уже подключён, а она включает его при каждом
     * обращении к несуществующему классу.
     */
    private static function toolClass(Place $place, string $tool): string
    {
        $file = $place->dir() . "/Tools/{$tool}/{$tool}.php";

        if (!file_exists($file)) {
            throw new ToolMissing($tool, $file, 'нет файла');
        }

        require_once $file;

        $class = $place->toolClass($tool);

        if (!class_exists($class, false)) {
            throw new ToolMissing($tool, $file, 'нет класса');
        }

        return $class;
    }

    private function __clone()
    {
    }
}
