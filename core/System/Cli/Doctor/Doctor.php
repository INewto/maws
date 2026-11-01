<?php

namespace Maws\System\Cli\Doctor;

use Maws\System\Contract\Effect;
use Maws\System\Contract\Purpose;
use Maws\World\System\DbConnector;
use Throwable;

/**
 * Диагностика окружения: одна команда вместо переписки «почему не работает».
 *
 * Проверки делятся на две части. Ядро знает про окружение и связность — версию PHP,
 * расширения, константы, права на каталоги, доступность базы. Приложение знает про свой
 * предмет — какие таблицы должны быть на месте, отвечает ли внешний сервис; ядро этих имён
 * не знает и знать не должно, поэтому список приходит извне (`DOCTOR_CHECKS` в
 * `app/config.php`).
 *
 * Проверка отвечает словами: `null` — в порядке, строка — что именно сломалось. «Что-то
 * пошло не так» здесь бесполезно: команду запускают тогда, когда уже не работает.
 *
 * Три состояния, а не два. Провал — то, без чего сайт не работает. Предупреждение — то,
 * без чего не работает часть хелперов: сайт без картинок живёт и без `gd`, и красный
 * doctor на здоровом сайте приучил бы не смотреть на его цвет.
 */
class Doctor
{
    // Без этих сайт не работает вовсе, а не «работает медленно».
    private const EXTENSIONS = ['pdo_pgsql', 'mbstring', 'json'];

    // Эти нужны не ядру, а хелперам. Ключ — имя расширения, значение — что именно встанет.
    private const OPTIONAL = ['gd' => 'хелперы Image и Upload не заработают'];

    // Что именно перестанет работать: «не загружено» без этого ничего не объясняет.
    private const WHY = [
        'pdo_pgsql' => 'без него нет соединения с базой',
        'mbstring'  => 'без него рвётся русский текст в Text и Csv',
        'json'      => 'без него не читаются settings.json, dbase.json и ответы',
    ];

    // DBASE_PASSWORD в списке нет: пустой пароль — законное значение, а не забытое.
    private const CONSTANTS = [
        'CORE_DIR',
        'APP_DIR',
        'WORLD_DIR',
        'SCREEN_DIR',
        'AI_DIR',
        'LOGS_FILE',
        'MIGRATIONS_DIR',
        'DBASE_DSN',
        'DBASE_USER',
        'LANGUAGE',
        'DEV',
    ];

    private static int $passed = 0;
    private static int $failed = 0;
    private static int $warned = 0;

    #[Purpose('Проверяет окружение: PHP, расширения, константы, права на каталоги, базу и проверки приложения.')]
    #[Effect(Effect::DB_CONNECT)]
    #[Effect(Effect::FS_WRITE)]
    public static function run(): void
    {
        self::$passed = 0;
        self::$failed = 0;
        self::$warned = 0;

        echo "=== Проверка окружения MAWS ===\n\n";

        self::php();
        self::extensions();

        self::constants();

        // Каждая проверка сама говорит, что ей нужно: одна незаданная константа не должна
        // прятать сбой в другом месте. Поэтому не общий «конфиг плох», а «этой проверке
        // нечем работать».
        self::writable('каталог журнала', self::dir('LOGS_FILE'), true);
        self::writable('каталог миграций', self::path('MIGRATIONS_DIR'));
        self::writable('временный каталог', sys_get_temp_dir());
        self::dbase();

        self::version();
        self::app();

        self::summary();
    }

    private static function php(): void
    {
        $floor = self::floor();

        if ($floor === null) {
            self::skip('PHP ' . PHP_VERSION, 'порог не объявлен в composer.json');
            return;
        }

        $need = self::number($floor);

        if (PHP_VERSION_ID < $floor) {
            self::bad('PHP ' . PHP_VERSION, "нужен {$need} или новее");
            return;
        }

        self::ok('PHP ' . PHP_VERSION . " — нужен {$need} или новее");
    }

    private static function extensions(): void
    {
        foreach (self::EXTENSIONS as $name) {
            if (extension_loaded($name)) {
                self::ok("расширение {$name}");
                continue;
            }

            self::bad("расширение {$name}", self::WHY[$name]);
        }

        foreach (self::OPTIONAL as $name => $why) {
            if (extension_loaded($name)) {
                self::ok("расширение {$name}");
                continue;
            }

            self::warn("расширение {$name}", $why);
        }
    }

    /**
     * Конфиг уже загружен точкой входа — до неё PHP бы не дошёл. Проверяется не наличие
     * файла, а то, что в нём есть: пустая константа выглядит объявленной и не работает.
     */
    private static function constants(): void
    {
        $missing = [];

        foreach (self::CONSTANTS as $name) {
            if (!defined($name)) {
                $missing[] = $name;
                continue;
            }

            $value = constant($name);

            if (is_string($value) && trim($value) === '') {
                $missing[] = "{$name} (пустая)";
            }
        }

        if ($missing !== []) {
            self::bad('константы конфига', 'не заданы: ' . implode(', ', $missing));
            return;
        }

        self::ok('константы конфига: ' . count(self::CONSTANTS) . ' обязательных');
    }

    /**
     * Значение константы-пути или null, если её нет: тогда проверка, которой она нужна,
     * честно говорит «путь неизвестен», а не падает и не подставляет догадку.
     */
    private static function path(string $name): ?string
    {
        if (!defined($name)) {
            return null;
        }

        $value = constant($name);

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function dir(string $name): ?string
    {
        $path = self::path($name);

        return $path === null ? null : dirname($path);
    }

    /**
     * Права проверяются записью, а не `is_writable()`: бит «на запись» ничего не говорит
     * о разделе, смонтированном только для чтения, и о переполненном диске.
     */
    private static function writable(string $what, ?string $dir, bool $mayCreate = false): void
    {
        if ($dir === null) {
            self::skip($what, 'константа не задана — путь неизвестен');
            return;
        }

        $target = $dir;

        if (!is_dir($target)) {
            if (!$mayCreate) {
                self::bad($what, "каталога нет: {$target}");
                return;
            }

            // Журнал заводит свой каталог сам — тогда проверяется тот, в котором он его заведёт.
            $parent = dirname($target);

            if (!is_dir($parent)) {
                self::bad($what, "каталога нет и создать его негде: {$parent}");
                return;
            }

            $target = $parent;
            $what  .= ' (каталога нет, заведётся сам)';
        }

        $probe = $target . DIRECTORY_SEPARATOR . '.maws-doctor-' . getmypid();

        try {
            $written = @file_put_contents($probe, 'ok');
        } finally {
            @unlink($probe);
        }

        // Показывается разрешённый путь: в константах он собран через `..`, и человек,
        // читающий отчёт, ищет каталог по имени, а не разбирает `app/../logs`.
        $shown = realpath($target) ?: $target;

        if ($written !== 2) {
            self::bad($what, "запись не удалась: {$shown}");
            return;
        }

        self::ok("{$what}: {$shown}");
    }

    private static function dbase(): void
    {
        if (self::path('DBASE_DSN') === null || self::path('DBASE_USER') === null) {
            self::skip('база данных', 'DBASE_DSN или DBASE_USER не заданы');
            return;
        }

        try {
            $row = DbConnector::pdo()
                ->query("SELECT current_setting('server_version') AS version")
                ->fetch();
        } catch (Throwable $e) {
            // Сюда попадают и DbaseUnreachable, и DbaseUnsupported, и DbaseDenied: у всех
            // в тексте уже сказано, что именно не так, и пересказывать его незачем.
            // Переводы строк убираются: текст драйвера многострочный, а строка отчёта — одна.
            self::bad('база данных', preg_replace('/\s+/', ' ', $e->getMessage()));
            return;
        }

        self::ok('база данных отвечает: PostgreSQL ' . ($row['version'] ?? 'неизвестной версии'));
    }

    private static function version(): void
    {
        $json = self::composer();

        if ($json === null) {
            self::bad('composer.json', 'не читается или не разбирается: ' . dirname(CORE_DIR) . '/composer.json');
            return;
        }

        $version = $json['version'] ?? null;

        if (!is_string($version) || trim($version) === '') {
            self::skip('версия фреймворка', 'не объявлена в composer.json (пункт 1.4)');
            return;
        }

        self::ok("версия фреймворка: {$version}");
    }

    /**
     * Проверки приложения: список `имя => callable` в константе `DOCTOR_CHECKS`.
     * Ядро не знает, что там внутри, и не пытается угадать по имени.
     */
    private static function app(): void
    {
        if (!defined('DOCTOR_CHECKS')) {
            self::skip('проверки приложения', 'DOCTOR_CHECKS не объявлена в app/config.php');
            return;
        }

        $checks = constant('DOCTOR_CHECKS');

        if (!is_array($checks)) {
            self::bad('проверки приложения', 'DOCTOR_CHECKS объявлена, но это не массив');
            return;
        }

        if ($checks === []) {
            self::skip('проверки приложения', 'список пуст');
            return;
        }

        foreach ($checks as $name => $check) {
            if (!is_callable($check)) {
                // Объявленная проверка, которая не запускается, хуже отсутствующей: она
                // создаёт видимость, что этот случай кто-то стережёт.
                self::bad((string) $name, 'не callable: ' . get_debug_type($check));
                continue;
            }

            try {
                $answer = $check();
            } catch (Throwable $e) {
                self::bad((string) $name, 'исключение: ' . preg_replace('/\s+/', ' ', $e->getMessage()));
                continue;
            }

            if ($answer === null) {
                self::ok((string) $name);
                continue;
            }

            if (is_string($answer) && trim($answer) !== '') {
                self::bad((string) $name, $answer);
                continue;
            }

            self::bad((string) $name, 'проверка вернула не null и не строку: ' . get_debug_type($answer));
        }
    }

    private static function summary(): void
    {
        echo "\n";

        if (self::$failed === 0) {
            echo 'Всё в порядке: ' . self::$passed . ' проверок';

            if (self::$warned > 0) {
                echo ', предупреждений: ' . self::$warned;
            }

            echo ".\n";
            return;
        }

        echo 'Провалено: ' . self::$failed . ' из ' . (self::$passed + self::$failed);

        if (self::$warned > 0) {
            echo ', предупреждений: ' . self::$warned;
        }

        echo ".\n";
        exit(1);
    }

    private static function ok(string $what): void
    {
        self::$passed++;
        echo "[ok]   {$what}\n";
    }

    private static function bad(string $what, string $why): void
    {
        self::$failed++;
        echo "[!!]   {$what} — {$why}\n";
    }

    private static function warn(string $what, string $why): void
    {
        self::$warned++;
        echo "[??]   {$what} — {$why}\n";
    }

    private static function skip(string $what, string $why): void
    {
        echo "[--]   {$what} — {$why}\n";
    }

    /**
     * Порог PHP берётся из composer.json: он уже записан там, и второе место с тем же
     * числом разошлось бы с первым — вопрос времени, а не вероятности.
     */
    private static function floor(): ?int
    {
        $require = self::composer()['require']['php'] ?? null;

        if (!is_string($require) || !preg_match('/>=\s*(\d+)\.(\d+)/', $require, $m)) {
            return null;
        }

        return (int) $m[1] * 10000 + (int) $m[2] * 100;
    }

    private static function number(int $id): string
    {
        return intdiv($id, 10000) . '.' . intdiv($id % 10000, 100);
    }

    private static function composer(): ?array
    {
        static $json = false;

        if ($json === false) {
            $file = dirname(CORE_DIR) . '/composer.json';
            $raw  = is_readable($file) ? file_get_contents($file) : false;

            $json = $raw === false ? null : json_decode($raw, true);
            $json = is_array($json) ? $json : null;
        }

        return $json;
    }
}
