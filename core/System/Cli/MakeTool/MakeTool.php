<?php

namespace Maws\System\Cli\MakeTool;

use Maws\System\Cli\Support\Support;
use Maws\System\Contract\Effect;
use Maws\System\Contract\Purpose;
use Maws\System\Contract\Returns;
use Maws\System\Language;
use Maws\World\System\DbConnector;
use Maws\World\World;

class MakeTool
{
    #[Effect(Effect::INPUT)]
    #[Effect(Effect::FS_READ)]
    #[Effect(Effect::FS_WRITE)]
    public static function run(): void
    {
        echo "=== Создание нового инструмента ===\n\n";

        $directions = Support::directions();

        if (empty($directions)) {
            echo "Ошибка: нет ни одного направления. Создайте направление командой make direction.\n";
            return;
        }

        $direction = Support::menu('Выберите направление:', $directions);

        $places = Support::places($direction);

        if (empty($places)) {
            echo "Ошибка: в направлении {$direction} нет мест. Создайте место командой make place.\n";
            return;
        }

        $place = Support::menu('Выберите место:', $places);
        $tool  = Support::askName('название инструмента');
        $lang  = Support::askLanguage('инструмента');

        $useDbase = Support::menu('Будет ли использоваться база данных?', ['да', 'нет']) === 'да';
        $tables   = $useDbase ? self::askTables() : [];

        if (self::create($direction, $place, $tool, $tables, $lang)) {
            echo "Готово.\n";
        }
    }

    /**
     * Заводит инструмент в готовом месте: файлы, dbase.json и запись в settings.json
     * места. Место берётся из базы — инструмент привязывается к строке places, а не
     * к имени каталога.
     */
    #[Purpose('Создаёт инструмент в месте и регистрирует его в настройках места.')]
    #[Param('direction', 'string', 'направление места')]
    #[Param('place', 'string', 'место')]
    #[Param('tool', 'string', 'имя инструмента')]
    #[Param('tables', 'array', 'таблицы, объявленные в dbase.json')]
    #[Param('lang', 'string|null', 'язык шаблона; null — язык из конфига (так собирает make build)')]
    #[Param('demo', 'bool', 'собрать показной инструмент: чтение и разметка отдельными методами, шаблон печатает строки')]
    #[Returns('bool', 'true — инструмент создан; false — место не найдено или инструмент уже есть')]
    #[Effect(Effect::FS_WRITE)]
    #[Effect(Effect::DB_READ)]
    public static function create(
        string $direction,
        string $place,
        string $tool,
        array $tables = [],
        ?string $lang = null,
        bool $demo = false
    ): bool {
        $lang = $lang ?? Language::current();
        $stmt = DbConnector::pdo()->prepare(
            'SELECT p.id FROM places p JOIN directions d ON d.id = p.direction_id'
            . ' WHERE d.name = ? AND p.name = ?'
        );
        $stmt->execute([$direction, $place]);
        $placeId = $stmt->fetchColumn();

        if ($placeId === false) {
            echo "Ошибка: места {$direction}/{$place} нет в базе.\n";
            return false;
        }

        $placeObject = (new World())->place((int) $placeId);

        if (!is_dir($placeObject->dir())) {
            echo "Ошибка: каталог места не найден ({$placeObject->dir()}).\n";
            return false;
        }

        $toolDir = $placeObject->dir() . '/Tools/' . $tool;

        if (is_dir($toolDir)) {
            echo "Ошибка: инструмент {$tool} уже существует ({$toolDir}).\n";
            return false;
        }

        $settings = self::readSettings($placeObject->dir());

        if ($settings === null) {
            return false;
        }

        // Показному инструменту нужна таблица: getTestData читает её через Repository.
        // Без таблицы остаётся обычная заготовка — показывать нечего.
        $demo = $demo && $tables !== [];

        try {
            self::write($toolDir, $direction, $place, $tool, $tables, $lang, $demo);
            self::register($placeObject->dir(), $settings, $tool);
        } catch (\RuntimeException $e) {
            Support::removeDir($toolDir);

            echo "Инструмент {$tool} не создан: {$e->getMessage()}\n";
            return false;
        }

        return true;
    }

    private static function askTables(): array
    {
        $schema = Support::askSqlName('имя схемы');
        $tables = [];

        while (true) {
            $table = Support::askTableName();

            if ($table === 'exit') {
                break;
            }

            $tables[] = "{$schema}.{$table}";
        }

        return $tables;
    }

    private static function readSettings(string $placeDir): ?array
    {
        $file = $placeDir . '/settings.json';

        if (!is_readable($file)) {
            echo "Ошибка: настройки места не читаются ({$file}).\n";
            return null;
        }

        $settings = \json5_decode(file_get_contents($file), true);

        if (!is_array($settings) || !isset($settings['tools']['list']) || !is_array($settings['tools']['list'])) {
            echo "Ошибка: в настройках места нет списка \"tools\" ({$file}).\n";
            return null;
        }

        return $settings;
    }

    private static function write(string $toolDir, string $direction, string $place, string $tool, array $tables, string $lang, bool $demo): void
    {
        $vars = ['Direction' => $direction, 'Place' => $place, 'Tool' => $tool];

        Support::ensureDir($toolDir . '/System/Templates/' . $lang);
        echo "Создан каталог: Tools/{$tool}/\n";

        Support::put(
            $toolDir . "/{$tool}.php",
            Support::render(
                Support::template('MakeTool', 'tool.tpl.php'),
                $vars + [
                    'ServeBody' => self::serveBody($tables, $demo),
                    'Extra'     => self::extraMethods($tables, $demo),
                ]
            )
        );
        echo "Создан файл: {$tool}.php\n";

        Support::put(
            $toolDir . '/System/Repository.php',
            Support::render(
                Support::template('MakeTool', 'repository.tpl.php'),
                $vars + ['Methods' => self::dbaseMethods($tables)]
            )
        );
        echo "Создан файл: System/Repository.php\n";

        Support::put(
            $toolDir . '/System/Page.php',
            Support::render(Support::template('MakeTool', 'page.tpl.php'), $vars)
        );
        echo "Создан файл: System/Page.php\n";

        Support::put(
            $toolDir . '/System/Templates/' . $lang . '/index.tpl.php',
            Support::render(
                Support::template('MakeTool', $demo ? 'template-demo.tpl.php' : 'template.tpl.php'),
                ['Tool' => $tool]
            )
        );
        echo "Создан файл: System/Templates/{$lang}/index.tpl.php\n";

        Support::put(
            $toolDir . '/dbase.json',
            Support::render(
                Support::template('MakeTool', 'dbase.tpl.json'),
                ['Tables' => self::tablesJson($tables)]
            )
        );
        echo "Создан файл: dbase.json\n";
    }

    private static function serveBody(array $tables, bool $demo): string
    {
        if ($demo) {
            $template = Support::template('MakeTool', 'serve-demo.tpl.txt');
            $vars     = [];
        } elseif ($tables === []) {
            $template = Support::template('MakeTool', 'serve-empty.tpl.txt');
            $vars     = [];
        } else {
            $template = Support::template('MakeTool', 'serve-db.tpl.txt');
            $vars     = ['Method' => self::methodName($tables[0])];
        }

        return rtrim(Support::render($template, $vars), "\n");
    }

    /**
     * Методы показного инструмента: чтение данных и разметка — врозь, а serve() их сводит.
     * Так видно, что разметка — один из выходов инструмента, а не обязанность: getTestData
     * возвращает записи и о шаблоне не знает.
     */
    private static function extraMethods(array $tables, bool $demo): string
    {
        if (!$demo) {
            return '';
        }

        return Support::render(
            Support::template('MakeTool', 'methods-demo.tpl.txt'),
            ['Method' => self::methodName($tables[0])]
        );
    }

    private static function dbaseMethods(array $tables): string
    {
        $blocks = [];

        foreach ($tables as $table) {
            [$schema, $name] = explode('.', $table, 2);

            $blocks[] = rtrim(
                Support::render(
                    Support::template('MakeTool', 'dbase-method.tpl.php'),
                    ['Method' => self::methodName($table), 'Schema' => $schema, 'Table' => $name]
                ),
                "\n"
            );
        }

        return $blocks === [] ? '' : "\n" . implode("\n\n", $blocks) . "\n";
    }

    private static function tablesJson(array $tables): string
    {
        $items = array_map(
            static fn (string $table): string => json_encode($table, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $tables
        );

        return '[' . implode(', ', $items) . ']';
    }

    private static function tableName(string $table): string
    {
        return explode('.', $table, 2)[1];
    }

    /**
     * Имя метода репозитория — таблица в camelCase. В базе имена змеиные, а метод живёт по
     * PSR-12: `test_table` → `testTable`. Схема и таблица в SQL остаются как в базе.
     */
    private static function methodName(string $table): string
    {
        $name = self::tableName($table);

        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    }

    private static function register(string $placeDir, array $settings, string $tool): void
    {
        if (in_array($tool, $settings['tools']['list'], true)) {
            return;
        }

        $settings['tools']['list'][] = $tool;

        Support::put($placeDir . '/settings.json', Support::SETTINGS_HEADER . Support::jsonPretty($settings));
        echo "Инструмент {$tool} добавлен в settings.json места.\n";
    }
}
