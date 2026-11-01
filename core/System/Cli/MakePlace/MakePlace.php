<?php

namespace Maws\System\Cli\MakePlace;

use Maws\System\Cli\Support\Support;
use Maws\System\Contract\Effect;
use Maws\System\Contract\Param;
use Maws\System\Contract\Purpose;
use Maws\System\Contract\Returns;
use Maws\World\System\DbConnector;

class MakePlace
{
    #[Purpose('Создаёт место в направлении и заводит каталог с рабочим пространством.')]
    public static function run(): void
    {
        echo "=== Создание нового места ===\n\n";

        $direction = Support::menu('Выберите направление места:', Support::directions());
        $name      = Support::askName('название места');

        // Строка в places есть — место уже заведено, и раздел у него свой. Каталог мог
        // быть удалён: тогда файлы восстанавливаются, а строка и привязка остаются те же.
        $placeId = self::rowId($direction, $name);

        if ($placeId !== null) {
            self::restore($direction, $name, $placeId);

            return;
        }

        // Каталог без строки — обратный перекос. Раздел тут ни при чём: место всё равно
        // не найдётся, пока нет строки, поэтому отказ идёт до любых вопросов.
        if (is_dir(WORLD_DIR . '/' . $direction . '/Places/' . $name)) {
            self::filesWithoutRow($direction, $name);

            return;
        }

        // Раздел не спрашивается: место от него не зависит — оно принадлежит направлению,
        // а раздел наследует место и привязывается к нему командой make area.
        if (self::create($direction, $name) === null) {
            return;
        }

        echo "Готово.\n";
    }

    /**
     * Восстанавливает файлы места по существующей строке в places. Пишется только
     * отсутствующее: правки в пространстве места и settings.json не затираются. Раздел не
     * перепривязывается — у места он уже есть, и второй занял бы чужой.
     */
    private static function restore(string $direction, string $name, int $placeId): void
    {
        $dir = WORLD_DIR . '/' . $direction . '/Places/' . $name;

        $workspace = is_file($dir . '/Workspace.php');
        $settings  = is_file($dir . '/settings.json');

        if ($workspace && $settings) {
            echo "Ошибка: место {$direction}/{$name} уже существует.\n";

            return;
        }

        echo "Строка #{$placeId} в places есть, а каталог {$direction}/Places/{$name}/ отсутствует или неполон.\n";
        echo "Файлы восстанавливаются в существующую строку, раздел не меняется.\n\n";

        try {
            Support::ensureDir($dir . '/Tools');
            self::ensureWorkspaces($direction);

            if (!$workspace) {
                self::createWorkspace($direction, $name, $dir);
            }

            if (!$settings) {
                self::createSettings($dir);
            }
        } catch (\RuntimeException $e) {
            // Строка чужая — снимать её нельзя: место останется неполным, но не исчезнет.
            echo "Ошибка: {$e->getMessage()}\n";
            echo "Строка #{$placeId} осталась: место по-прежнему неполное.\n";

            return;
        }

        echo "\nГотово: место #{$placeId} восстановлено.\n";
    }

    /**
     * Заводит место: строка в places, каталог с рабочим пространством и настройками.
     * Раздел здесь не заводится: место от него не зависит, а главную страницу ставит
     * make build — уже после того, как id места известен.
     */
    #[Purpose('Создаёт место в направлении и заводит каталог с рабочим пространством.')]
    #[Param('direction', 'string', 'направление, в котором заводится место')]
    #[Param('name', 'string', 'название места; так же называется каталог')]
    #[Param('tool', 'string|null', 'инструмент, который пространство вызывает; null — пространство оставляет заглушку и подсказку')]
    #[Returns('?int', 'id строки в places; null — место уже есть или направление не найдено')]
    #[Effect(Effect::FS_WRITE)]
    #[Effect(Effect::DB_WRITE)]
    public static function create(string $direction, string $name, ?string $tool = null): ?int
    {
        $dir = WORLD_DIR . '/' . $direction . '/Places/' . $name;

        $directionId = self::directionId($direction);

        if ($directionId === null) {
            echo "Ошибка: направления {$direction} нет в таблице directions — место не будет найдено.\n";
            return null;
        }

        // name в places не уникален: без проверки каталог, удалённый вручную, дал бы
        // вторую строку с тем же именем — и место задвоилось бы в меню.
        $existing = self::placeId($directionId, $name);

        if (is_dir($dir)) {
            if ($existing === null) {
                self::filesWithoutRow($direction, $name);
            } else {
                echo "Ошибка: место {$direction}/{$name} уже существует.\n";
            }

            return null;
        }

        if ($existing !== null) {
            echo "Ошибка: место {$direction}/{$name} уже есть в places (строка #{$existing}), а каталога нет.\n";
            echo "Запустите make place снова: он восстановит файлы в эту строку.\n";

            return null;
        }

        $placeId = self::insertPlace($name, $directionId);

        try {
            Support::ensureDir($dir . '/Tools');
            echo "Создан каталог: {$direction}/Places/{$name}/\n";

            self::ensureWorkspaces($direction);
            self::createWorkspace($direction, $name, $dir, $tool);
            self::createSettings($dir);
        } catch (\RuntimeException $e) {
            self::dropPlace($placeId);
            Support::removeDir($dir);

            echo "Строка #{$placeId} снята, каталог убран: место не создано.\n";
            echo "Ошибка: {$e->getMessage()}\n";

            return null;
        }

        return $placeId;
    }

    /** Каталог есть, строки нет: место не найдётся, пока в places нет строки. */
    private static function filesWithoutRow(string $direction, string $name): void
    {
        echo "Ошибка: каталог {$direction}/{$name} есть, а строки в places нет — место не будет найдено.\n";
        echo "Удалите каталог и создайте место заново.\n";
    }

    private static function dropPlace(int $placeId): void
    {
        $stmt = DbConnector::pdo()->prepare('DELETE FROM places WHERE id = ?');
        $stmt->execute([$placeId]);
    }

    /** Строка места по имени направления и места; null — направления или строки нет. */
    private static function rowId(string $direction, string $name): ?int
    {
        $directionId = self::directionId($direction);

        if ($directionId === null) {
            return null;
        }

        return self::placeId($directionId, $name);
    }

    private static function placeId(int $directionId, string $name): ?int
    {
        $stmt = DbConnector::pdo()->prepare('SELECT id FROM places WHERE direction_id = ? AND name = ?');
        $stmt->execute([$directionId, $name]);
        $row = $stmt->fetch();

        return $row ? (int) $row['id'] : null;
    }

    private static function directionId(string $direction): ?int
    {
        $stmt = DbConnector::pdo()->prepare('SELECT id FROM directions WHERE name = ?');
        $stmt->execute([$direction]);
        $row = $stmt->fetch();

        return $row ? (int) $row['id'] : null;
    }

    private static function insertPlace(string $name, int $directionId): int
    {
        // RETURNING id: номер нужен сразу — им место встаёт в раздел.
        $stmt = DbConnector::pdo()->prepare(
            'INSERT INTO places (name, direction_id) VALUES (?, ?) RETURNING id'
        );
        $stmt->execute([$name, $directionId]);

        return (int) $stmt->fetchColumn();
    }

    private static function ensureWorkspaces(string $direction): void
    {
        $file = WORLD_DIR . '/' . $direction . '/Places/' . $direction . 'Workspaces.php';

        if (is_file($file)) {
            return;
        }

        $template = Support::template('MakeDirection', 'workspaces.tpl.php');
        $content  = Support::render($template, ['Direction' => $direction]);

        Support::put($file, $content);
        echo "Создан файл: {$direction}/Places/{$direction}Workspaces.php\n";
    }

    private static function createWorkspace(string $direction, string $name, string $dir, ?string $tool = null): void
    {
        $vars = ['Direction' => $direction, 'Place' => $name];

        // Имя инструмента известно только сборке каркаса: место обычно заводится раньше
        // своих инструментов, и тогда пространство остаётся с заглушкой и подсказкой.
        if ($tool === null) {
            $template = Support::template('MakePlace', 'workspace.tpl.php');
        } else {
            $template = Support::template('MakePlace', 'workspace-demo.tpl.php');
            $vars    += ['Tool' => $tool];
        }

        Support::put($dir . '/Workspace.php', Support::render($template, $vars));
        echo "Создан файл: Workspace.php\n";
    }

    private static function createSettings(string $dir): void
    {
        // render, а не file_get_contents: нечитаемый шаблон — исключение, иначе
        // settings.json записался бы пустым.
        Support::put($dir . '/settings.json', Support::render(Support::template('MakePlace', 'settings.tpl.json')));

        echo "Создан файл: settings.json\n";
    }
}
