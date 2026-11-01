<?php

namespace Maws\System\Test;

use Maws\Ai\Ai;
use Maws\Ai\System\AiAccess;
use Maws\Ai\System\AiDbase;
use Maws\Man\Head\System\Memory;
use Maws\Man\Man;
use Maws\Man\System\Pass\PlacePass;
use Maws\Screen\Screen;
use Maws\System\Cache\Policy;
use Maws\System\Contract\{Effect, Param, Purpose, Returns};
use Maws\System\Session\Session;
use Maws\System\Test\Exception\TestMisuse;
use Maws\World\System\Dbase;
use Maws\World\System\DbConnector;
use Maws\World\System\DbaseRights;
use Maws\World\System\Exception\PlaceNotFound;
use Maws\World\System\Place;
use Maws\World\System\Tool;
use Maws\World\World;

#[Purpose('Строитель корня для #[Subject]: собирает актёров и читает права из песочницы #[Fixture].')]
final class Scaffold
{
    private static string $sandbox = '';
    private static ?World $world = null;
    private static ?Screen $screen = null;

    #[Purpose('Начинает случай: назначает песочницу и обнуляет память головы, чтобы состояние не текло между случаями.')]
    #[Param('dir', 'string', 'каталог песочницы')]
    public static function begin(string $dir): void
    {
        self::$sandbox    = rtrim($dir, '/\\');
        Memory::$instance = null;
        DbConnector::reset();          // соединение общее на процесс: открытая транзакция иначе переживёт случай
        Session::reset();              // сессия тоже общая на процесс: иначе $_SESSION и её файл переживут случай
    }

    #[Purpose('Отдаёт каталог песочницы текущего случая.')]
    #[Returns('string', 'путь песочницы')]
    public static function sandbox(): string
    {
        return self::$sandbox;
    }

    #[Purpose('Отдаёт путь файла внутри песочницы.')]
    #[Param('file', 'string', 'имя файла внутри песочницы')]
    #[Returns('string', 'полный путь')]
    public static function path(string $file): string
    {
        return self::$sandbox . '/' . $file;
    }

    #[Purpose('Отдаёт карту мира — одну на прогон.')]
    #[Returns('World', 'карта мира')]
    public static function world(): World
    {
        if (self::$world === null) {
            self::$world = new World();
        }

        return self::$world;
    }

    #[Purpose('Отдаёт экран — один на прогон.')]
    #[Returns('Screen', 'экран')]
    public static function screen(): Screen
    {
        if (self::$screen === null) {
            self::$screen = new Screen();
        }

        return self::$screen;
    }

    #[Purpose('Собирает человека: тело, голова, руки, ноги.')]
    #[Returns('Man', 'человек, вошедший в мир')]
    public static function man(): Man
    {
        return new Man(self::world(), self::screen());
    }

    #[Purpose('Выдаёт пропуск в место: подводит человека, вводит его и отдаёт пропуск.')]
    #[Param('place', 'string', 'адрес места')]
    #[Returns('PlacePass', 'пропуск в это место')]
    public static function placePass(string $place): PlacePass
    {
        $man = self::man();
        $man->body->legs->walk($place);

        return $man->enterPlace($place);
    }

    #[Purpose('Собирает место по адресу — значение-объект с направлением и именем. Читается из базы: id и направление места берутся оттуда же, откуда их берёт голова.')]
    #[Param('place', 'string', 'адрес вида "Направление/Место"')]
    #[Returns('Place', 'место из базы')]
    public static function place(string $place): Place
    {
        $parts = explode('/', $place);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new TestMisuse("адрес места \"{$place}\" не той формы — нужен \"Направление/Место\"");
        }

        $stmt = DbConnector::pdo()->prepare(
            'SELECT p.id FROM places p JOIN directions d ON d.id = p.direction_id'
            . ' WHERE d.name = ? AND p.name = ?'
        );
        $stmt->execute([$parts[0], $parts[1]]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new TestMisuse("места {$place} нет в базе");
        }

        return self::world()->place((int) $id);
    }

    #[Purpose('Собирает рабочее пространство места по адресу.')]
    #[Param('place', 'string', 'адрес вида "Направление/Место"')]
    #[Param('man', 'Man|null', 'человек, от имени которого работает пространство')]
    #[Returns('object', 'рабочее пространство места')]
    public static function workspace(string $place, ?Man $man = null): object
    {
        $placeObject = self::place($place);
        $file        = $placeObject->dir() . '/Workspace.php';
        $class       = $placeObject->workspaceClass();

        if (!is_readable($file)) {
            throw new TestMisuse("место {$place} не объявило рабочее пространство: нет файла {$file}");
        }

        require_once $file;

        if (!class_exists($class, false)) {
            throw new TestMisuse("файл {$file} не объявил класс — проверь имя класса");
        }

        $man = $man ?? self::man();

        // Место и раздел прогон берёт тем же путём, что и роутинг: место без раздела
        // не собирается, а воркспейс ждёт область третьим аргументом. Роутинг ставит
        // и текущее место — но собирают его ради места, а не ради адреса входа:
        // дальше случай идёт сам, и чужая точка старта мешала бы ему.
        $area = Areafold::area(self::urlOf($placeObject), $man);

        $man->body->head->forget();

        return new $class($placeObject, $man, $area);
    }

    /**
     * Адрес РАЗДЕЛА, в котором стоит место.
     *
     * В базе у места нет адреса — адрес есть у раздела, и разделов с одним местом может
     * быть несколько. Кандидаты перебираются от самого короткого адреса к самому
     * глубокому: он должен разрешиться роутингом целиком, а не наоборот — роутинг
     * не всегда доходит до раздела, который вернёт БД (у главной страницы адрес пуст).
     * Если не разрешился ни один, место объявлено, но не стоит ни в одном разделе —
     * это ошибка объявления, а не прогона.
     */
    private static function urlOf(Place $place): string
    {
        $stmt = DbConnector::pdo()->prepare(
            'WITH RECURSIVE up AS ('
            . ' SELECT id, parent_id, url, 1 AS depth FROM areas WHERE place_id = ?'
            . ' UNION ALL'
            . ' SELECT p.id, p.parent_id, p.url, up.depth + 1 FROM areas p JOIN up ON p.id = up.parent_id'
            . ')'
            . ' SELECT id FROM up WHERE parent_id = 0 ORDER BY depth, id'
        );
        $stmt->execute([$place->id()]);
        $roots = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if ($roots === []) {
            throw new TestMisuse("места {$place->way()} нет ни в одном разделе — область не собрать");
        }

        $man = self::man();

        foreach ($roots as $id) {
            $url = self::pathOf((int) $id);

            try {
                $man->body->head->getWorkspace($url);
            } catch (PlaceNotFound $e) {
                continue;
            }

            $man->body->head->forget();

            return $url;
        }

        throw new TestMisuse("ни один раздел места {$place->way()} не разрешается роутингом — сверь url с деревом areas");
    }

    /** Собирает адрес раздела снизу вверх: сегменты от корня до него через "/". */
    private static function pathOf(int $id): string
    {
        $pieces = [];
        $cursor = $id;

        while ($cursor !== 0) {
            $stmt = DbConnector::pdo()->prepare('SELECT parent_id, url FROM areas WHERE id = ?');
            $stmt->execute([$cursor]);
            $row = $stmt->fetch();

            if (!$row) {
                throw new TestMisuse("дерево разделов порвано: нет строки areas #{$cursor}");
            }

            if ($row['url'] !== '') {
                array_unshift($pieces, $row['url']);
            }

            $cursor = (int) $row['parent_id'];
        }

        return implode('/', $pieces);
    }

    #[Purpose('Собирает инструмент места по адресу и имени: открывает рабочее пространство, подводит человека, входит и берёт инструменты.')]
    #[Param('place', 'string', 'адрес вида "Направление/Место"')]
    #[Param('tool', 'string', 'имя инструмента, например "ExampleTool"')]
    #[Returns('Tool', 'инструмент с базой под правами инструмента')]
    public static function tool(string $place, string $tool): Tool
    {
        $man         = self::man();
        $placeObject = self::place($place);

        // Инструмент живёт в месте, а место стоит в разделе: без раздела нет ни потолка,
        // ни сцены, поэтому человек входит через роутинг, а не мимо него.
        Areafold::area(self::urlOf($placeObject), $man);

        $man->body->legs->walk($place);
        $tools = $man->body->hands->takeTools($man->enterPlace($place));

        if ($tools === false || !isset($tools->$tool)) {
            throw new TestMisuse("место {$place} не объявило инструмент {$tool}");
        }

        if (!$tools->$tool instanceof Tool) {
            throw new TestMisuse("инструмент {$tool} места {$place} не наследует " . Tool::class);
        }

        return $tools->$tool;
    }

    #[Purpose('Читает права инструмента из файла песочницы.')]
    #[Param('file', 'string', 'имя файла прав в песочнице')]
    #[Returns('DbaseRights', 'права с разобранным списком таблиц')]
    public static function dbaseRights(string $file): DbaseRights
    {
        return DbaseRights::fromDir(self::dir($file));
    }

    #[Purpose('Собирает базу под правами инструмента из файла песочницы.')]
    #[Param('file', 'string', 'имя файла прав в песочнице')]
    #[Returns('Dbase', 'база со стражем по правам инструмента')]
    public static function dbase(string $file): Dbase
    {
        return new Dbase(self::dbaseRights($file));
    }

    #[Purpose('Читает политику кэша из settings.json в песочнице.')]
    #[Param('file', 'string', 'имя settings.json внутри песочницы')]
    #[Returns('Policy', 'политика места или no-store, если ключа "cache" нет')]
    public static function policy(string $file): Policy
    {
        return Policy::fromDir(self::dir($file));
    }

    #[Purpose('Читает права агента из файла песочницы.')]
    #[Param('file', 'string', 'имя файла прав в песочнице')]
    #[Returns('AiAccess', 'права с разобранным списком таблиц')]
    public static function aiAccess(string $file): AiAccess
    {
        return AiAccess::fromDir(self::dir($file));
    }

    #[Purpose('Собирает базу под правами агента из файла песочницы.')]
    #[Param('file', 'string', 'имя файла прав в песочнице')]
    #[Returns('AiDbase', 'база со стражем по правам агента')]
    public static function aiDbase(string $file): AiDbase
    {
        return new AiDbase(self::aiAccess($file), DBASE_DSN, DBASE_USER, DBASE_PASSWORD);
    }

    #[Purpose('Собирает агента-пустышку на песочнице: каталог агента и один инструмент, чтобы проверить проводку Ai без демонстрационного агента.')]
    #[Returns('Ai', 'агент, у которого agentDir — песочница, а sayHello отвечает строкой')]
    public static function ai(): Ai
    {
        return new class (self::sandbox()) extends Ai {
            public function __construct(string $agentDir)
            {
                $this->agentDir = $agentDir;
            }

            public function sayHello(array $arguments): string
            {
                return 'hello ' . $arguments['amount'];
            }
        };
    }

    private static function dir(string $file): string
    {
        if (self::$sandbox === '') {
            throw new TestMisuse('песочница не назначена: фабрика файла вызвана вне прогона');
        }

        return dirname(self::path($file));
    }
}
