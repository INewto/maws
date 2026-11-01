<?php

namespace Maws\System\Cli\Build;

use Maws\System\Cli\Build\Exception\BuildBroken;
use Maws\System\Cli\MakeDirection\MakeDirection;
use Maws\System\Cli\MakePlace\MakePlace;
use Maws\System\Cli\MakeTool\MakeTool;
use Maws\System\Cli\Migrate\Migrate;
use Maws\System\Cli\Support\Support;
use Maws\System\Contract\Effect;
use Maws\System\Contract\Purpose;
use Maws\System\Contract\Throws;
use Maws\World\System\DbConnector;

/**
 * Сборка каркаса с нуля: миграция, направление Public, места Home и SecondPlace, главная
 * страница, инструменты ExampleTool и ExampleTool_1, нужда Home в инструменте SecondPlace.
 *
 * Сборка не дополняет существующее: сначала проверяются ВСЕ шаги, и если хоть что-то
 * уже создано — она не начинается и перечисляет найденное. Иначе «сборка» на живом
 * проекте тихо перезаписала бы чужую сцену или настройки места.
 */
class Build
{
    private const DIRECTION = 'Public';
    private const PLACE     = 'Home';

    // Имя инструмента — имя класса, и make tool получает его через Support::askName, который
    // делает ucfirst. Каркас собирает ровно то же написание: иначе инструмент, созданный
    // каркасом, не повторить командой make tool, а на Linux регистр в имени каталога, файла
    // и класса значим — ExampleTool и exampleTool там два разных инструмента.
    private const TOOL      = 'ExampleTool';

    // Второй инструмент места. Нужен не странице, а объяснениям: в документации
    // settings.json показывает list из двух инструментов, а needs — подмножество,
    // которое достаётся просящему. На одном инструменте этот урок не показать.
    private const TOOL_1    = 'ExampleTool_1';
    private const AREA      = 'Главная страница';

    // Второе место в направлении — ради нужды: Home объявляет, что берёт у него инструмент,
    // и settings.json показывает не только list, но и needs. Без второго места нужду не на
    // чем показать, и пример в документации пришлось бы выдумывать. Раздела у места нет
    // намеренно: раздел задаёт адрес страницы, а нужда — разрешение на инструмент, в меню
    // она не ведёт.
    private const PLACE_1   = 'SecondPlace';

    // Таблица из миграции 0005: инструмент каркаса читает её, чтобы страница сразу
    // показывала записи, а не заглушку.
    private const TABLE = 'test_table';

    #[Purpose('Собирает каркас с нуля: применяет миграцию, создаёт направление, два места, главную страницу, три инструмента и нужду одного места в другом.')]
    #[Throws(BuildBroken::class, 'часть каркаса уже создана, классы приложения не автозагружаемы или шаг не выполнился')]
    #[Effect(Effect::FS_READ)]
    #[Effect(Effect::FS_WRITE)]
    #[Effect(Effect::DB_WRITE)]
    public static function run(): void
    {
        echo "=== Сборка каркаса ===\n\n";

        $problems = self::problems();

        if ($problems !== []) {
            echo "Ошибка: каркас уже частично создан, сборка не начата:\n";

            foreach ($problems as $problem) {
                echo "  - {$problem}\n";
            }

            exit(1);
        }

        // Проверка идёт после «уже создано»: на живом проекте сборка всё равно не
        // начинается, а на пустом — это первый шанс увидеть, что классы не найдутся.
        $broken = self::autoloadProblems();

        if ($broken !== []) {
            echo "Ошибка: классы приложения не совпадают с путём, сборка не начата:\n";

            foreach ($broken as $problem) {
                echo "  - {$problem}\n";
            }

            exit(1);
        }

        Migrate::run();

        echo "\n--- Направление " . self::DIRECTION . " ---\n";

        // Строка в directions нужна месту: оно встаёт в направление по id.
        $directionId = MakeDirection::create(self::DIRECTION);

        if ($directionId === null) {
            throw new BuildBroken('направление ' . self::DIRECTION . ' не создано');
        }

        echo "\n--- Место " . self::PLACE . " ---\n";

        $placeId = MakePlace::create(self::DIRECTION, self::PLACE, self::TOOL);

        if ($placeId === null) {
            throw new BuildBroken('место ' . self::DIRECTION . '/' . self::PLACE . ' не создано');
        }

        // Раздел идёт после места: главная ведёт в место, и её place_id — это id, который
        // появился только что. Обратный порядок завёл бы раздел, ведущий в никуда.
        echo "\n--- Раздел " . self::AREA . " ---\n";

        self::mainArea($placeId);

        echo "\n--- Инструмент " . self::TOOL . " ---\n";

        // Инструмент собирается показным: пространство места уже вызывает его serve(),
        // а сам он читает test_table через Repository и печатает строки шаблоном.
        if (!MakeTool::create(self::DIRECTION, self::PLACE, self::TOOL, ['public.' . self::TABLE], null, true)) {
            throw new BuildBroken('инструмент ' . self::TOOL . ' не создан');
        }

        echo "
--- Инструмент " . self::TOOL_1 . " ---
";

        // Обычная заготовка: ни таблиц, ни разметки строк. Её дело — быть вторым именем
        // в settings.json, чтобы подмножество в needs показывалось на живом месте.
        if (!MakeTool::create(self::DIRECTION, self::PLACE, self::TOOL_1)) {
            throw new BuildBroken('инструмент ' . self::TOOL_1 . ' не создан');
        }

        echo "\n--- Место " . self::PLACE_1 . " ---\n";

        // Место заводится со своим инструментом: пространство сразу вызывает его serve(),
        // а не остаётся с заглушкой.
        if (MakePlace::create(self::DIRECTION, self::PLACE_1, self::TOOL) === null) {
            throw new BuildBroken('место ' . self::DIRECTION . '/' . self::PLACE_1 . ' не создано');
        }

        echo "\n--- Инструмент " . self::TOOL . " в " . self::PLACE_1 . " ---\n";

        // Тот же инструмент, что у Home, но в другом месте: имя одно, а классы разные —
        // каталог места входит в пространство имён. Заодно видно, что needs называет
        // инструмент по имени, не путая его с одноимённым своим.
        if (!MakeTool::create(self::DIRECTION, self::PLACE_1, self::TOOL)) {
            throw new BuildBroken('инструмент ' . self::TOOL . ' в ' . self::PLACE_1 . ' не создан');
        }

        echo "\n--- Нужда " . self::PLACE . " в " . self::PLACE_1 . " ---\n";

        // Нужда пишется последней: сначала у соседа должен появиться инструмент, иначе
        // settings.json объявлял бы нужду в том, чего ещё нет.
        self::declareNeeds(self::PLACE, self::PLACE_1, [self::TOOL]);

        echo "\nГотово: каркас собран, направление #{$directionId}.\n";
    }

    /**
     * Что сборка заводит: место → его инструменты. Один список и для проверки, и для
     * сборки — иначе проверка и создание разошлись бы, и «уже создано» перестало бы
     * совпадать с тем, что сборка делает.
     */
    private static function places(): array
    {
        return [
            self::PLACE   => [self::TOOL, self::TOOL_1],
            self::PLACE_1 => [self::TOOL],
        ];
    }

    /**
     * Дописывает в settings.json места нужду в чужом инструменте. Нужда — объявление
     * потребителя: место само говорит, что берёт и у кого. Без неё чужой инструмент не
     * выдаётся (AccessNotDeclared), поэтому файл пишется тем же способом, что и список
     * своих инструментов: читается, дополняется и кладётся обратно целиком.
     */
    private static function declareNeeds(string $place, string $neighbour, array $tools): void
    {
        $file = self::placeDir($place) . '/settings.json';
        $code = @file_get_contents($file);

        if ($code === false) {
            throw new BuildBroken('настройки места ' . $place . ' не читаются');
        }

        $settings = \json5_decode($code, true);

        if (!is_array($settings) || !isset($settings['tools'])) {
            throw new BuildBroken('в настройках места ' . $place . ' нет раздела "tools"');
        }

        $settings['tools']['needs'] = [self::DIRECTION . '/' . $neighbour => $tools];

        Support::put($file, Support::SETTINGS_HEADER . Support::jsonPretty($settings));

        echo 'Место ' . $place . ' объявило нужду в ' . self::DIRECTION . '/' . $neighbour
            . ': ' . implode(', ', $tools) . ".\n";
    }

    /** Что уже создано. Список целиком, а не первая находка: чинить всё равно всё сразу. */
    private static function problems(): array
    {
        $problems = [];

        if (is_dir(WORLD_DIR . '/' . self::DIRECTION)) {
            $problems[] = 'каталог направления ' . self::DIRECTION . ' уже есть (app/World/' . self::DIRECTION . ')';
        }

        if (self::hasDirection()) {
            $problems[] = 'направление ' . self::DIRECTION . ' уже есть в таблице directions';
        }

        if (is_dir(Support::scenesDir() . '/' . self::DIRECTION)) {
            $problems[] = 'сцена ' . self::DIRECTION . ' уже есть (app/Screen/Scenes/' . self::DIRECTION . ')';
        }

        foreach (self::places() as $place => $tools) {
            if (is_dir(self::placeDir($place))) {
                $problems[] = 'каталог места ' . self::DIRECTION . '/' . $place . ' уже есть';
            }

            if (self::hasPlace($place)) {
                $problems[] = 'место ' . self::DIRECTION . '/' . $place . ' уже есть в таблице places';
            }

            foreach ($tools as $tool) {
                if (is_dir(self::placeDir($place) . '/Tools/' . $tool)) {
                    $problems[] = 'инструмент ' . $tool . ' уже есть в месте ' . self::DIRECTION . '/' . $place;
                }
            }
        }

        $main = self::mainAreaRow();

        if ($main !== null && $main['place'] !== 0) {
            $problems[] = 'главная страница уже ведёт в место #' . $main['place'];
        }

        return $problems;
    }

    private static function placeDir(string $place): string
    {
        return WORLD_DIR . '/' . self::DIRECTION . '/Places/' . $place;
    }

    /**
     * Классы приложения должны лежать там, откуда их возьмёт автозагрузка: имя файла —
     * это имя класса, а путь от app/ — его пространство имён. Файлы без объявления
     * класса (конфиг, шаблоны подсказок, пустые заготовки) не проверяются: их грузят
     * не как классы.
     */
    private static function autoloadProblems(): array
    {
        $problems = [];
        $files    = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(APP_DIR, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if ($file->getExtension() !== 'php' || str_ends_with($path, '.tpl.php')) {
                continue;
            }

            $rel = substr($path, strlen(str_replace('\\', '/', APP_DIR)) + 1);

            if (str_starts_with($rel, 'Tests/') || $rel === 'config.php'
                || str_ends_with($rel, 'instruction.prompt.php')) {
                continue;
            }

            $class = self::declaredClass($path);

            if ($class === null) {
                continue;
            }

            $expected = 'App\\' . str_replace('/', '\\', substr($rel, 0, -4));

            if ($class !== $expected) {
                $problems[] = "{$rel}: объявлен {$class}, ожидался {$expected}";
            }
        }

        sort($problems);

        return $problems;
    }

    /**
     * Полное имя класса, объявленного в файле: пространство имён плюс имя. Читается
     * разбором, а не загрузкой — сборка не должна выполнять чужой код ради проверки.
     */
    private static function declaredClass(string $file): ?string
    {
        $code      = @file_get_contents($file);
        $tokens    = $code === false ? [] : token_get_all($code);
        $namespace = '';
        $class     = null;
        $count     = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = self::nameAfter($tokens, $i);
                continue;
            }

            if ($token[0] !== T_CLASS) {
                continue;
            }

            // «Имя::class» — не объявление, а ссылка на класс.
            $prev = $tokens[$i - 1] ?? null;

            if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                continue;
            }

            $j = $i + 1;

            while ($j < $count && is_array($tokens[$j])
                && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $j++;
            }

            $next = $tokens[$j] ?? null;

            // У анонимного класса имени нет — следующий значимый токен не слово.
            if (is_array($next) && $next[0] === T_STRING) {
                $class = $next[1];
                break;
            }
        }

        if ($class === null) {
            return null;
        }

        return $namespace === '' ? $class : $namespace . '\\' . $class;
    }

    /** Имя после «namespace»: один токен в PHP 8, но собирается по частям на всякий случай. */
    private static function nameAfter(array $tokens, int $at): string
    {
        $name  = '';
        $count = count($tokens);

        for ($i = $at + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
                continue;
            }

            break;
        }

        return $name;
    }

    /** Таблицы может не быть: её создаёт миграция, а проверка идёт до неё. */
    private static function tableExists(string $table): bool
    {
        $stmt = DbConnector::pdo()->prepare('SELECT to_regclass(?::text) IS NOT NULL');
        $stmt->execute(['public.' . $table]);

        return (bool) $stmt->fetchColumn();
    }

    private static function hasDirection(): bool
    {
        if (!self::tableExists('directions')) {
            return false;
        }

        $stmt = DbConnector::pdo()->prepare('SELECT 1 FROM directions WHERE name = ?');
        $stmt->execute([self::DIRECTION]);

        return (bool) $stmt->fetch();
    }

    private static function hasPlace(string $place): bool
    {
        if (!self::tableExists('places')) {
            return false;
        }

        $stmt = DbConnector::pdo()->prepare(
            'SELECT 1 FROM places p JOIN directions d ON d.id = p.direction_id'
            . ' WHERE d.name = ? AND p.name = ?'
        );
        $stmt->execute([self::DIRECTION, $place]);

        return (bool) $stmt->fetch();
    }

    /**
     * Главная страница — раздел с main = 1, ведущий в собранное место. Раздел без места
     * (place_id = 0) кладёт миграция: id места появляется только здесь, поэтому имя и
     * привязка дозаполняются на этом шаге. Раздела нет вовсе — он создаётся.
     */
    private static function mainArea(int $placeId): void
    {
        $area = self::mainAreaRow();

        if ($area === null) {
            $stmt = DbConnector::pdo()->prepare(
                'INSERT INTO areas (name, title, main, parent_id, place_id) VALUES (?, ?, true, 0, ?) RETURNING id'
            );
            $stmt->execute([self::AREA, self::AREA, $placeId]);
            $id = (int) $stmt->fetchColumn();

            echo "Раздел #{$id} «" . self::AREA . "» создан, ведёт в место #{$placeId}.\n";

            return;
        }

        // Раздел заняли между проверкой и записью — это говорит база: чужой place_id
        // команда не переписывает молча.
        if ($area['place'] !== 0) {
            throw new BuildBroken('главная страница уже ведёт в место #' . $area['place']);
        }

        $stmt = DbConnector::pdo()->prepare(
            'UPDATE areas SET name = ?, title = ?, main = true, place_id = ? WHERE id = ?'
        );
        $stmt->execute([self::AREA, self::AREA, $placeId, $area['id']]);

        echo "Раздел #{$area['id']} «" . self::AREA . "» ведёт в место #{$placeId}.\n";
    }

    /** Раздел с main = 1: его id и место, в которое он ведёт. Главная в базе ровно одна. */
    private static function mainAreaRow(): ?array
    {
        if (!self::tableExists('areas')) {
            return null;
        }

        $row = DbConnector::pdo()->query('SELECT id, place_id FROM areas WHERE main = true LIMIT 1')->fetch();

        return $row === false ? null : ['id' => (int) $row['id'], 'place' => (int) $row['place_id']];
    }
}
