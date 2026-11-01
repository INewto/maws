<?php

namespace Maws\System\Cli\Support;

use Maws\System\Language;
use Maws\World\System\DbConnector;

final class Support
{
    /**
     * Читает строку ответа. Конец ввода — не пустой ответ, а отказ: без этой проверки
     * любой вопрос на закрытом stdin крутился бы вечно, печатая подсказку за подсказкой.
     */
    private static function line(): string
    {
        $input = fgets(STDIN);

        if ($input === false) {
            echo "\nВвод закончен — команда остановлена.\n";
            exit(1);
        }

        return trim($input);
    }

    public static function askName(string $what): string
    {
        while (true) {
            echo "Введите {$what} (латиница, цифры и подчёркивание, начиная с буквы): ";
            $input = self::line();

            if ($input === '') {
                echo "Значение не может быть пустым.\n";
                continue;
            }

            // Подчёркивание разрешено наравне с буквами и цифрами: это имя класса, а PHP
            // его допускает. Без него нельзя повторить руками имя второго инструмента
            // каркаса — ExampleTool_1 (см. Build::TOOL_1).
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $input)) {
                echo "Имя должно начинаться с латинской буквы и состоять из букв, цифр и подчёркивания (это имя класса).\n";
                continue;
            }

            return ucfirst($input);
        }
    }

    public static function askUrl(string $what): string
    {
        while (true) {
            echo "Введите {$what} (строчная латиница, цифры, дефис): ";
            $input = self::line();

            if ($input === '') {
                echo "Значение не может быть пустым.\n";
                continue;
            }

            if (!preg_match('/^[a-z][a-z0-9-]*$/', $input)) {
                echo "url пишется строчными: латинская буква, дальше буквы, цифры и дефис.\n";
                continue;
            }

            return $input;
        }
    }

    /**
     * Спрашивает язык, а не берёт его из константы: в одном направлении языков может быть
     * несколько, и второй язык не должен требовать правки `app/config.php`.
     */
    public static function askLanguage(string $what): string
    {
        while (true) {
            echo "Укажите язык {$what} (например \"ru\" или \"en\"): ";
            $input = self::line();

            if (!Language::isCode($input)) {
                echo "Язык — две строчные латинские буквы, например \"ru\": это имя каталога шаблонов.\n";
                continue;
            }

            return $input;
        }
    }

    public static function menu(string $title, array $items): string
    {
        echo "{$title}\n";

        $menu = [];
        $i = 1;

        foreach ($items as $item) {
            $menu[$i] = $item;
            echo "  {$i}) {$item}\n";
            $i++;
        }

        while (true) {
            echo "Ваш выбор: ";
            $input = self::line();

            if (isset($menu[$input])) {
                return $menu[$input];
            }

            echo "Введите цифру от 1 до " . count($menu) . ".\n";
        }
    }

    /**
     * Вопрос «да/нет». Enter и любой другой ответ — «нет»: разрушительное действие
     * не должно случаться оттого, что человек просто нажал ввод.
     */
    public static function confirm(string $question): bool
    {
        echo "{$question} [y/N]: ";
        $answer = strtolower(self::line());

        return in_array($answer, ['y', 'yes', 'д', 'да'], true);
    }

    public static function askSqlName(string $what): string
    {
        while (true) {
            echo "Введите {$what} (латиница, цифры, подчёркивание): ";
            $input = self::line();

            if ($input === '') {
                echo "Значение не может быть пустым.\n";
                continue;
            }

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $input)) {
                echo "Допустимы латинские буквы, цифры и подчёркивание; начинать с буквы или подчёркивания.\n";
                continue;
            }

            return $input;
        }
    }

    public static function askTableName(): string
    {
        while (true) {
            echo 'Введите название таблицы или "exit" для завершения: ';
            $input = self::line();

            if ($input === 'exit') {
                return 'exit';
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $input)) {
                return $input;
            }

            echo "Допустимы латинские буквы, цифры и подчёркивание.\n";
        }
    }

    public static function directions(): array
    {
        $stmt = DbConnector::pdo()->prepare('SELECT name FROM directions ORDER BY name');
        $stmt->execute();

        return array_column($stmt->fetchAll(), 'name');
    }

    public static function places(string $direction): array
    {
        $stmt = DbConnector::pdo()->prepare(
            'SELECT p.name FROM places p JOIN directions d ON d.id = p.direction_id'
            . ' WHERE d.name = ? ORDER BY p.name'
        );
        $stmt->execute([$direction]);

        return array_column($stmt->fetchAll(), 'name');
    }

    public static function jsonPretty(array $data): string
    {
        return self::encode($data, 0) . "\n";
    }

    private static function encode(mixed $value, int $level): string
    {
        if (!is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $list  = array_is_list($value);
        $open  = $list ? '[' : '{';
        $close = $list ? ']' : '}';

        if ($value === []) {
            return $open . $close;
        }

        if ($list && !array_filter($value, 'is_array')) {
            $items = array_map(
                static fn ($item): string => json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $value
            );

            return '[' . implode(', ', $items) . ']';
        }

        $pad   = str_repeat('  ', $level + 1);
        $items = [];

        foreach ($value as $key => $item) {
            $label = $list
                ? ''
                : json_encode((string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ': ';

            $items[] = $pad . $label . self::encode($item, $level + 1);
        }

        return $open . "\n" . implode(",\n", $items) . "\n" . str_repeat('  ', $level) . $close;
    }

    public static function scenesDir(): string
    {
        return SCREEN_DIR . '/Scenes';
    }

    public static function screenConfigFile(): string
    {
        return SCREEN_DIR . '/config.json';
    }

    public static function readScreenConfig(): array
    {
        $file = self::screenConfigFile();

        if (!is_readable($file)) {
            throw new \RuntimeException("Конфиг экрана не читается: {$file}");
        }

        $config = \json5_decode(file_get_contents($file), true);

        if (!is_array($config)) {
            throw new \RuntimeException("Конфиг экрана имеет неверный формат: {$file}");
        }

        return $config;
    }

    /**
     * Шапки конфигов, которые команды переписывают: файл собирается заново из массива,
     * и без шапки комментарии в нём терялись бы при первом же `make direction` или `make tool`.
     */
    public const SCREEN_HEADER = "// Какая сцена рисует направление: ключ — направление, значение — каталог в Screen/Scenes.\n";

    public const SETTINGS_HEADER = "// Что место объявляет: свои инструменты, нужды в чужих и политику кэша.\n"
        . "// list — инструменты этого места; needs — что нужно из других мест, \"Направление/Место\" → имена.\n"
        . "// cache — необязательно: { \"ttl\": секунды, \"scope\": \"private\"|\"public\" }; без него страница не кэшируется.\n";

    public static function writeScreenConfig(array $config): void
    {
        self::put(self::screenConfigFile(), self::SCREEN_HEADER . self::jsonPretty($config));
    }

    public static function template(string $command, string $name): string
    {
        return dirname(__DIR__) . '/' . $command . '/templates/' . $name;
    }

    public static function render(string $template, array $vars = []): string
    {
        $tpl = @file_get_contents($template);

        if ($tpl === false) {
            throw new \RuntimeException("Шаблон не читается: {$template}");
        }

        foreach ($vars as $key => $value) {
            $tpl = str_replace('{{' . $key . '}}', (string) $value, $tpl);
        }

        return $tpl;
    }

    /**
     * Записывает файл целиком. Сбой — исключение, а не false: `file_put_contents` сообщает
     * о неудаче предупреждением, а предупреждение — не исключение, поэтому без проверки
     * команда сказала бы «Готово» над пустым каталогом.
     *
     * Пишется во временный файл рядом и переносится на место: оборванная запись не оставит
     * наполовину затёртый `config.json` или `settings.json`.
     */
    public static function put(string $path, string $content): void
    {
        $tmp = $path . '.tmp';
        $len = strlen($content);

        $written = @file_put_contents($tmp, $content, LOCK_EX);

        if ($written === false) {
            throw new \RuntimeException("не записать файл: {$path}");
        }

        if ($written !== $len) {
            @unlink($tmp);
            throw new \RuntimeException("файл записан не целиком: {$path} ({$written} из {$len} байт)");
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("не заменить файл: {$path}");
        }
    }

    /** Создаёт каталог со всеми родителями. Уже существующий — не ошибка. */
    public static function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("не создать каталог: {$dir}");
        }
    }

    /**
     * Удаляет каталог со всем содержимым. Зовут только для того, что создала эта же
     * команда: откат не должен уносить чужое.
     */
    public static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
