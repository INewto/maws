<?php

namespace Maws\System\Cli\MakeDirection;

use Maws\System\Cli\Support\Support;
use Maws\System\Contract\Effect;
use Maws\System\Contract\Purpose;
use Maws\System\Contract\Returns;
use Maws\System\Language;
use Maws\World\System\DbConnector;

class MakeDirection
{
    public static function run(): void
    {
        echo "=== Создание нового направления ===\n\n";

        $name = Support::askName('название направления');
        $lang = Support::askLanguage('направления');

        $id = self::create($name, $lang);

        if ($id === null) {
            return;
        }

        echo "Готово. Направление #{$id} занесено в directions.\n";
    }

    /**
     * Заводит направление: строку в directions, каталог с базовым классом пространств
     * и сцену. Строка идёт первой — она точка фиксации: если файлы не создадутся,
     * строка снимается, и направление не остаётся полуготовым.
     */
    #[Purpose('Создаёт строку в directions, каталог направления, базовый класс пространств и сцену.')]
    #[Param('name', 'string', 'имя направления')]
    #[Param('lang', 'string|null', 'язык сцены; null — язык из конфига (так собирает make build)')]
    #[Returns('?int', 'id строки в directions; null — направление уже есть, нет таблицы или файлы не создались')]
    #[Effect(Effect::DB_WRITE)]
    #[Effect(Effect::FS_WRITE)]
    public static function create(string $name, ?string $lang = null): ?int
    {
        $lang = $lang ?? Language::current();
        if (!self::tableExists()) {
            echo "Ошибка: таблицы directions нет — примените миграцию: make migrate\n";
            return null;
        }

        $dir = WORLD_DIR . '/' . $name;

        if (is_dir($dir)) {
            return self::adopt($name);
        }

        if (file_exists($dir)) {
            echo "Ошибка: путь {$name}/ занят файлом — направление не создано.\n";
            return null;
        }

        // name не уникален: без проверки каталог, удалённый вручную, дал бы вторую строку
        // с тем же именем — и направление задвоилось бы в меню.
        $existing = self::rowId($name);

        if ($existing !== null) {
            echo "Ошибка: строка #{$existing} для {$name} уже есть в directions, а каталога нет.\n";
            echo "Уберите строку или верните каталог: иначе направление останется пустым.\n";
            return null;
        }

        $id = self::row($name);
        echo "Направление #{$id} занесено в таблицу directions.\n";

        // Каталоги, созданные этой командой: при сбое снимаются в обратном порядке.
        $made = [];

        try {
            self::createFiles($name, $dir, $made, $lang);
        } catch (\RuntimeException $e) {
            self::dropRow($id);

            foreach (array_reverse($made) as $path) {
                Support::removeDir($path);
            }

            echo "Строка #{$id} снята, созданное убрано: направление не создано.\n";
            echo "Ошибка: {$e->getMessage()}\n";

            return null;
        }

        return $id;
    }

    private static function createFiles(string $name, string $dir, array &$made, string $lang): void
    {
        $placesDir = $dir . '/Places';

        if (file_exists($placesDir) && !is_dir($placesDir)) {
            throw new \RuntimeException("путь {$name}/Places/ занят файлом");
        }

        Support::ensureDir($placesDir);

        $made[] = $dir;
        echo "Создан каталог: {$name}/Places/\n";

        self::createWorkspaces($name, $dir);
        self::createScene($name, $made, $lang);
    }

    /**
     * Каталог уже есть, а строки может не быть: направление, заведённое файлами без
     * строки, не видно ни в меню, ни в make place. Заводим строку, файлы не трогаем.
     */
    private static function adopt(string $name): ?int
    {
        if (self::rowId($name) !== null) {
            echo "Ошибка: направление {$name} уже существует.\n";
            return null;
        }

        $id = self::row($name);

        echo "Каталог {$name}/ уже есть, строки в directions не было.\n";
        echo "Строка #{$id} заведена; файлы и сцена не тронуты.\n";

        $workspaces = WORLD_DIR . '/' . $name . '/Places/' . $name . 'Workspaces.php';

        if (!is_file($workspaces)) {
            echo "Внимание: нет файла {$name}/Places/{$name}Workspaces.php — направление не загрузится.\n";
        }

        return $id;
    }

    /** Таблицы может не быть: её создаёт миграция, а команду зовут и до неё. */
    private static function tableExists(): bool
    {
        $stmt = DbConnector::pdo()->prepare('SELECT to_regclass(?::text) IS NOT NULL');
        $stmt->execute(['public.directions']);

        return (bool) $stmt->fetchColumn();
    }

    private static function rowId(string $name): ?int
    {
        $stmt = DbConnector::pdo()->prepare('SELECT id FROM directions WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();

        return $row ? (int) $row['id'] : null;
    }

    private static function row(string $name): int
    {
        $stmt = DbConnector::pdo()->prepare('INSERT INTO directions (name) VALUES (?) RETURNING id');
        $stmt->execute([$name]);

        return (int) $stmt->fetchColumn();
    }

    private static function dropRow(int $id): void
    {
        $stmt = DbConnector::pdo()->prepare('DELETE FROM directions WHERE id = ?');
        $stmt->execute([$id]);
    }

    private static function createWorkspaces(string $name, string $dir): void
    {
        $file = $dir . '/Places/' . $name . 'Workspaces.php';

        $content = Support::render(
            Support::template('MakeDirection', 'workspaces.tpl.php'),
            ['Direction' => $name]
        );

        Support::put($file, $content);

        echo "Создан файл: {$name}/Places/{$name}Workspaces.php\n";
    }

    private static function createScene(string $name, array &$made, string $lang): void
    {
        $sceneDir = Support::scenesDir() . '/' . $name;

        if (is_dir($sceneDir)) {
            echo "Сцена {$name} уже существует.\n";
            return;
        }

        if (file_exists($sceneDir)) {
            throw new \RuntimeException("путь Scenes/{$name}/ занят файлом");
        }

        Support::ensureDir($sceneDir . '/' . $lang);

        $made[] = $sceneDir;

        $file = $sceneDir . '/' . $lang . '/index.tpl.php';

        $content = Support::render(
            Support::template('MakeDirection', 'scene.tpl.php'),
            ['Direction' => $name]
        );

        Support::put($file, $content);

        echo "Создана сцена: Scenes/{$name}/{$lang}/\n";

        $config = Support::readScreenConfig();

        if (isset($config[$name])) {
            return;
        }

        $config[$name] = $name;
        Support::writeScreenConfig($config);

        echo "Сцена {$name} добавлена в Screen/config.json.\n";
    }
}
