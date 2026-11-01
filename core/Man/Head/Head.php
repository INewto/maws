<?php

namespace Maws\Man\Head;

use Maws\Man\Head\System\Memory;
use Maws\Man\Body\Body;
use Maws\Man\System\Exception\OrganAssembly;
use Maws\System\Cache\Policy;
use Maws\System\Contract\{Changes, Effect, Fails, Gives, Has, Param, Purpose, Responds, Returns, Subject, Throws};
use Maws\System\Exception\ResponseSent;
use Maws\System\Language;
use Maws\System\Path;
use Maws\World\System\Area;
use Maws\World\System\DbConnector;
use Maws\World\System\Exception\AreaBroken;
use Maws\World\System\Exception\DbConnectDenied;
use Maws\World\System\Exception\PlaceNotFound;
use Maws\World\System\Exception\WorkspaceNotDeclared;
use Maws\World\System\Place;
use Maws\World\World;

#[Subject(Head::class, 'Maws\System\Test\Scaffold::man', [], 'head')]
#[Purpose('Голова: узнаёт раздел по адресу, ставит его место и область, открывает рабочее пространство. Ходить она не умеет — ходят ноги.')]
final class Head
{
    /**
     * Имя главной страницы в адресе. Главная живёт по пустому адресу, поэтому своего
     * сегмента у неё нет; «home» — унаследованное человеческое имя, по которому её ещё
     * зовут. Сегмент ничего не добавляет к смыслу и потому уводится редиректом.
     */
    private const HOME_ALIAS = 'home';

    public readonly Body $body;
    public readonly World $world;

    #[Purpose('Место, в которое ведёт текущий раздел. null — адрес ещё не разбирали.')]
    public ?Place $currentPlace = null;

    #[Purpose('Раздел (область), которого достиг адрес. null — адрес ещё не разбирали.')]
    public ?Area $currentArea = null;

    #[Purpose('Адрес, где человек стоит. Ставит маршрут (место входа) и вход в место.')]
    public ?string $currentLocation = null;

    #[Purpose('Адрес, к которому подошли ноги. Записывает голова по их слову.')]
    public ?string $nearPlace = null;

    #[Purpose('Адреса, в которых человек уже был. Пополняет голова, когда человек входит.')]
    public array $visitedPlaces = [];

    private function __construct($body, $world)
    {
        $this->body  = $body;
        $this->world = $world;
    }

    #[Purpose('Собирает голову. Вызывается только из тела.')]
    #[Param('body', 'Body', 'тело, которому принадлежит голова')]
    #[Param('world', 'World', 'карта мира')]
    #[Returns('self', 'голова с пустым текущим местом')]
    #[Throws(OrganAssembly::class, 'голову собирают не из тела')]
    #[Effect(Effect::NONE)]
    public static function create($body, $world): self
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        if (($trace[1]['class'] ?? null) !== Body::class) {
            http_response_code(500);
            throw new OrganAssembly(__CLASS__, Body::class);
        }

        return new self($body, $world);
    }

    #[Purpose('Узнаёт раздел по адресу, ставит его как текущий и запоминает место, в которое раздел ведёт.')]
    #[Param('url', 'string', 'адрес из запроса без ведущего слеша, например "" для главной')]
    #[Returns('string', 'адрес места, в которое ведёт раздел: "Направление/Место"')]
    #[Throws(PlaceNotFound::class, 'сегмент адреса не нашёлся среди разделов или место раздела нет в базе')]
    #[Throws(AreaBroken::class, 'строка areas не разбирается или главная страница не одна')]
    #[Throws(DbConnectDenied::class, 'дверь в базу открыта не голове и не базе')]
    #[Throws(ResponseSent::class, 'адрес не канонический: голова уже отправила редирект')]
    #[Effect(Effect::DB_READ)]
    #[Effect(Effect::INPUT)]
    #[Effect(Effect::HTTP)]
    #[Effect(Effect::SCREEN)]
    #[Gives([''], 'Public/Home')]
    #[Responds(['home'], '', 301)]
    #[Fails(['nope'], PlaceNotFound::class)]
    #[Fails(['nope/nope'], PlaceNotFound::class)]
    public function getWorkspace($url)
    {
        if (!is_string($url)) {
            throw new PlaceNotFound('(адрес не строка)');
        }

        // Язык выбирается до разбора адреса: шаблоны сцены и места ищутся в его каталоге,
        // а политика кэша зависит от того, пришёл ли он из печенья.
        Language::detect($_COOKIE);

        [$place, $area] = $this->identifyArea($url);

        return $this->arrive($place, $area);
    }

    #[Purpose('Сообщает место и раздел как текущие. Человек считается стоящим в месте раздела: место входа — то, куда привёл маршрут.')]
    #[Param('place', 'Place', 'место, найденное по адресу')]
    #[Param('area', 'Area', 'раздел, чьё это место')]
    #[Returns('string', 'адрес места вида "Направление/Место"')]
    #[Throws(PlaceNotFound::class, 'раздел ведёт в другое место, а не в переданное')]
    #[Effect(Effect::NONE)]
    public function arrive($place, $area)
    {
        if ($area->placeId() !== $place->id()) {
            throw new PlaceNotFound(
                "раздел \"{$area->name()}\" (id {$area->id()}) ведёт не в {$place->way()}"
            );
        }

        $this->currentPlace    = $place;
        $this->currentArea     = $area;
        $this->currentLocation = $place->way();

        Memory::up()->set('currentPlace', $place->way());
        Memory::up()->set('currentArea', $area->id());
        Memory::up()->set('currentLocation', $place->way());

        return $place->way();
    }

    #[Purpose('Открывает рабочее пространство места текущего раздела и передаёт ему ход.')]
    #[Returns('mixed', 'то, что вернул startWork() рабочего пространства')]
    #[Throws(PlaceNotFound::class, 'голова ещё не разбирала адрес — текущего места нет')]
    #[Throws(WorkspaceNotDeclared::class, 'место не объявило класс рабочего пространства — в Workspace.php другое имя')]
    #[Effect(Effect::FS_READ)]
    public function initWorkspace()
    {
        $place = $this->currentPlace;

        if ($place === null) {
            throw new PlaceNotFound('(место не определено: голова ещё не разбирала адрес)');
        }

        // Место без раздела не показывается: область передаётся ровно та, что нашёл роутинг.
        $area = $this->currentArea;

        $classname = self::workspaceClass($place);

        // Человек — не голова: рабочие пространства ждут человека, и голова берёт его
        // у тела, которому принадлежит. Так же его получает и прогон.
        $workspace = new $classname($place, $this->body->man, $area);

        return $workspace->startWork();
    }

    #[Purpose('Отдаёт шапку страницы текущего раздела: все колонки раздела плоским списком. Экран их не разбирает и не группирует — ключ это имя колонки.')]
    #[Returns('array', 'имя колонки → значение; пустой массив, если раздел ещё не найден')]
    #[Effect(Effect::NONE)]
    #[Has([], ['id', 'name', 'place_id', 'main', 'parent_id', 'url'], before: [['head->getWorkspace', ['']]])]
    public function pageMeta(): array
    {
        return $this->currentArea?->fields() ?? [];
    }

    #[Purpose('Отдаёт политику кэша текущего места: место объявляет её в settings.json, голова знает место. Без места — «не хранить».')]
    #[Returns('Policy', 'объявленная политика или no-store')]
    #[Effect(Effect::FS_READ)]
    public function cachePolicy(): Policy
    {
        return $this->currentPlace === null ? Policy::none() : Policy::fromDir($this->currentPlace->dir());
    }

    #[Purpose('Забывает текущее место, раздел и пройденный путь. Нужен прогону: роутинг там собирают ради раздела, а начинать случай чужим адресом незачем.')]
    #[Effect(Effect::NONE)]
    #[Changes([], [['head->currentPlace', [], null], ['head->currentArea', [], null], ['head->currentLocation', [], null], ['head->visitedPlaces', [], []]])]
    public function forget(): void
    {
        $this->currentPlace    = null;
        $this->currentArea     = null;
        $this->currentLocation = null;
        $this->nearPlace       = null;
        $this->visitedPlaces   = [];

        Memory::up()->set('currentPlace', null);
        Memory::up()->set('currentArea', null);
        Memory::up()->set('currentLocation', null);
    }

    #[Purpose('Запоминает, к какому месту подошли ноги.')]
    #[Param('place', 'string', 'адрес места')]
    #[Effect(Effect::NONE)]
    public function noticeNear($place): void
    {
        $this->nearPlace = $place;
    }

    #[Purpose('Запоминает, что человек вошёл в место.')]
    #[Param('place', 'string', 'адрес места')]
    #[Effect(Effect::NONE)]
    public function rememberEntry($place): void
    {
        $this->currentLocation = $place;
        $this->visitedPlaces[] = $place;

        Memory::up()->set('currentLocation', $place);
    }

    #[Purpose('Узнаёт раздел по адресу: сверяет регистр сегментов, спускается по дереву разделов в базе и дочитывает og последнего раздела. Ничего не запоминает — это делает arrive().')]
    #[Param('url', 'string', 'адрес из запроса')]
    #[Returns('array', 'пара: место, в которое ведёт раздел, и сам раздел с прочитанными og')]
    #[Throws(PlaceNotFound::class, 'сегмент адреса не нашёлся среди разделов')]
    #[Throws(AreaBroken::class, 'строка areas не разбирается или главная страница не одна')]
    #[Effect(Effect::DB_READ)]
    #[Effect(Effect::HTTP)]
    #[Effect(Effect::SCREEN)]
    private function identifyArea($url): array
    {
        $given = $this->pieces($url);
        $rest  = $this->lowered($given);

        $root = $this->readRoot();

        // Главная живёт по пустому адресу: "home" — её человеческое имя, а собственный
        // url у неё может быть и непустым. И то и другое — дубль канонического "/":
        // ведущие сегменты главной из адреса снимаются, хвост остаётся как был.
        // Снимаются ВСЕ ведущие, а не один: "home/home" — тот же мусор, что и "home",
        // и уводить его вторым переходом значило бы обещать один редирект, а делать два.
        $rootName = $root->url() === '' ? self::HOME_ALIAS : $root->url();

        while ($rest !== [] && $rest[0] === $rootName) {
            $rest = array_slice($rest, 1);
        }

        // Один редирект на канонический адрес: и регистр сегментов, и снятая главная
        // уходят вместе. Двух подряд быть не должно — второй адрес уже канонический.
        if ($rest !== $given) {
            $this->sendAway($rest);
        }

        // Дальше адрес решает дерево разделов. Первый сегмент — не обязательно ребёнок
        // главного: он может быть и самой главной, но такую главную сняли выше. Спуск
        // идёт от корня, поэтому адрес, начатый не с раздела верхнего уровня, просто не
        // находится: раздел с таким адресом в базе есть, но дойти до него нельзя.
        // Редиректа здесь нет — уводить некуда, адрес не сокращается, а неверен.
        $roadmap = [$this->section($root)];
        $area    = $root;

        foreach ($rest as $piece) {
            $area = $this->readChild($area->id(), $piece);

            $roadmap[] = $this->section($area);
        }

        Memory::up()->set('roadmap', $roadmap);

        return [$this->world->place($area->placeId()), $area];
    }

    private function pieces($url): array
    {
        $url = trim($url, '/');

        if ($url === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $url), 'strlen'));
    }

    private function lowered(array $pieces): array
    {
        return array_map('strtolower', $pieces);
    }

    #[Purpose('Читает главный раздел — тот, где main = 1. Главная во всей базе ровно одна: две записи означают, что корень нельзя выбрать однозначно.')]
    #[Returns('Area', 'главный раздел сайта')]
    #[Throws(AreaBroken::class, 'главной страницы нет или их больше одной')]
    #[Effect(Effect::DB_READ)]
    private function readRoot(): Area
    {
        $stmt = DbConnector::pdo()->prepare('SELECT * FROM areas WHERE main = true');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (count($rows) !== 1) {
            throw new AreaBroken(0, 'главная страница должна быть ровно одна (main = 1), найдено: ' . count($rows));
        }

        return Area::fromRow($rows[0]);
    }

    #[Purpose('Читает дочерний раздел по родителю и сегменту адреса.')]
    #[Param('parent', 'int', 'id родительского раздела')]
    #[Param('piece', 'string', 'сегмент адреса строчными')]
    #[Returns('Area', 'раздел с этим сегментом')]
    #[Throws(PlaceNotFound::class, 'у этого родителя нет раздела с таким сегментом')]
    #[Effect(Effect::DB_READ)]
    private function readChild(int $parent, string $piece): Area
    {
        $stmt = DbConnector::pdo()->prepare('SELECT * FROM areas WHERE parent_id = ? AND url = ?');
        $stmt->execute([$parent, $piece]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new PlaceNotFound('(раздела нет: ' . $piece . ')');
        }

        return Area::fromRow($row);
    }

    private function section(Area $area): array
    {
        return [
            'area_id'     => $area->id(),
            'area_name'   => $area->name(),
            'area_url'    => $area->url(),
            'area_parent' => $area->parent(),
            'area_main'   => $area->isMain(),
            'place_id'    => $area->placeId(),
        ];
    }

    #[Purpose('Отправляет на канонический адрес: голова решает, куда идти, руки нажимают кнопку пульта, экран отдаёт ответ.')]
    #[Param('pieces', 'array', 'куски канонического адреса')]
    #[Effect(Effect::SCREEN)]
    private function sendAway(array $pieces): never
    {
        $rest = $_GET;
        unset($rest['way']);

        $tail  = implode('/', $pieces);
        $query = $tail === '' ? '' : 'way=' . $tail;

        if ($rest !== []) {
            $query .= ($query === '' ? '' : '&') . http_build_query($rest);
        }

        // Сцены у редиректа нет, поэтому адрес экрана не передаётся: кнопка сама знает, что делать.
        $this->body->hands->useScreenInterface(null, 'redirect', '/' . ($query === '' ? '' : '?' . $query));
    }

    /**
     * Ищет класс рабочего пространства: файл места подключается, а класс в нём называется
     * полным именем — тем, что выводится из направления и места. Автозагрузка здесь не
     * спрашивается: она включает файл при каждом обращении к несуществующему классу, и
     * ошибка «место не объявило класс» превратилась бы в «class not found».
     */
    private static function workspaceClass(Place $place): string
    {
        $class = $place->workspaceClass();
        $file  = $place->dir() . '/Workspace.php';

        if (!is_file($file)) {
            throw new WorkspaceNotDeclared($class, Path::normalize($file));
        }

        require_once $file;

        if (!class_exists($class, false)) {
            throw new WorkspaceNotDeclared($class, Path::normalize($file));
        }

        return $class;
    }

    private function __clone()
    {
    }
}
