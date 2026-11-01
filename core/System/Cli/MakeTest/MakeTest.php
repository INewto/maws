<?php

namespace Maws\System\Cli\MakeTest;

use Maws\System\Cli\Support\Support;
use Maws\System\Contract\Changes;
use Maws\System\Contract\Fails;
use Maws\System\Contract\Fixture;
use Maws\System\Contract\Gives;
use Maws\System\Contract\Has;
use Maws\System\Contract\Responds;
use Maws\System\Contract\Subject;
use Maws\World\World;
use ReflectionClass;
use ReflectionMethod;

class MakeTest
{
    /** Виды случаев: по ним сверяются объявленное и собранное. */
    private const CASES = ['Gives', 'Has', 'Fails', 'Changes', 'Responds'];

    /** Атрибуты, которые читает генератор: без `use` каждый из них теряется молча. */
    private const READ = ['Gives', 'Has', 'Fails', 'Changes', 'Responds', 'Fixture'];

    /** Все атрибуты контракта: по ним ищется подсказка при опечатке в имени. */
    private const KNOWN = [
        'Subject', 'Purpose', 'Param', 'Returns', 'Throws', 'Effect',
        'Gives', 'Has', 'Fails', 'Changes', 'Responds', 'Fixture',
    ];

    private static ?string $loadError = null;

    public static function run(): void
    {
        global $argv;

        $arg = $argv[2] ?? '';

        if (!str_starts_with($arg, 'test:')) {
            echo "Использование: maws make test:<файл>\n";
            exit(1);
        }

        $source = ltrim(str_replace('\\', '/', substr($arg, 5)), '/');

        if ($source === '') {
            echo "Использование: maws make test:<файл>\n";
            echo "Все контракты сразу: maws test\n";
            exit(1);
        }

        while (str_starts_with($source, './')) {
            $source = substr($source, 2);
        }

        $root = dirname(APP_DIR);
        $file = $root . '/' . $source;

        if (!is_file($file)) {
            echo "Нет такого файла: {$source}\n";
            exit(1);
        }

        $contracts = self::contracts($file);

        if ($contracts === [] && self::$loadError !== null) {
            echo "Источник не загрузился: " . self::$loadError . "\n";
            echo "Файл: {$source}\n";
            echo "Скорее всего недоступен родитель класса — контракт объявляется на классе, ";
            echo "который грузится сам.\n";
            exit(1);
        }

        if ($contracts === []) {
            echo "В файле нет получателя контракта (#[Subject]) — тест не создан.\n";
            exit(1);
        }

        $cases = 0;
        $built = [];
        $names = [];

        foreach ($contracts as $contract) {
            $cases  += count($contract['cases']);
            $names[] = $contract['subject']->class;

            foreach ($contract['counts'] as $kind => $number) {
                $built[$kind] = ($built[$kind] ?? 0) + $number;
            }
        }

        self::demandClasses($file, $names);

        // Раньше счёта собранного: если единственный атрибут не разрешился, честный ответ —
        // «атрибут не разрешился», а не «в контракте нет проверок» (они написаны).
        self::demandCases($file, $built);

        if ($cases === 0) {
            echo "В контракте нет проверок (Gives/Has/Fails/Changes/Responds) — тест не создан.\n";
            exit(1);
        }

        $target = self::target($root, $source);
        self::makeDir(dirname($target));

        Support::put($target, Support::render(
            Support::template('MakeTest', 'test.tpl.php'),
            [
                'Source'   => $source,
                'Bootstrap' => self::bootstrap($root, $source),
                'Body'     => self::body($contracts),
            ]
        ));

        echo 'Создан тест: ' . substr($target, strlen($root) + 1) . "\n";
        echo "Проверок в контракте: {$cases}\n\n";

        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($target), $code);

        exit($code);
    }

    private static function bootstrap(string $root, string $source): string
    {
        if (!str_starts_with($source, 'app/')) {
            return '';
        }

        $code = '';

        // Класс места наследует базу направления, а тест подключает файл сам — значит и
        // базу он подгружает тем же путём, что и мир.
        if (preg_match('#^app/World/([^/]+)/#', $source, $found) === 1) {
            $code = "\n\\Maws\\World\\World::loadDirection('{$found[1]}');\n";
        }

        return $code . "\nrequire_once \$root . '/" . $source . "';\n";
    }

    private static function demandClasses(string $file, array $names): void
    {
        self::load($file, $names);

        foreach ($names as $name) {
            if (!class_exists($name)) {
                echo "Класс источника не объявлен: {$name}\n";
                echo "Источник: {$file}\n";
                echo "Проверь имя класса и то, что его родитель доступен: контракт должен объявляться ";
                echo "на классе, который грузится сам.\n";
                exit(1);
            }
        }
    }

    /**
     * Сверяет два счёта случаев: что объявлено в тексте и что собрано отражением.
     *
     * Отражение видит только разрешившееся имя, поэтому `#[Fails]` без `use` для него не
     * существует: случай исчезает молча, а прогон остаётся зелёным. Здесь расхождение
     * становится отказом — тест не создаётся, автор видит строку и готовый `use`.
     *
     * @param array<string, int> $built вид → сколько случаев собрано
     */
    private static function demandCases(string $file, array $built): void
    {
        $declared = self::declared($file);

        // Сначала — имена: атрибут, чей класс не существует, не случай и не разметка, а
        // опечатка. `#[Fail]` вместо `#[Fails]` отражением не виден вовсе, поэтому раньше
        // такой файл проходил молча: случай исчезал, счёт сходился на нуле.
        foreach ($declared['all'] as $one) {
            if ($one['exists']) {
                continue;
            }

            $hint = self::closest($one['short']);

            echo "Атрибут {$one['short']} (строка {$one['line']}) не разрешился: {$one['name']} — такого класса нет.\n";

            if ($hint === $one['short']) {
                echo "Добавь use Maws\\System\\Contract\\{$hint}; — или исправь имя.\n";
            } elseif ($hint !== null) {
                echo "Похоже на {$hint} — добавь use Maws\\System\\Contract\\{$hint}; или исправь имя.\n";
            } else {
                echo "Атрибут не из Maws\\System\\Contract и имя не опознано: исправь имя или убери атрибут.\n";
            }

            echo "Тест не создан.\n";
            exit(1);
        }

        foreach (self::CASES as $short) {
            $written = count($declared['cases'][$short] ?? []);
            $taken   = $built[$short] ?? 0;

            if ($written <= $taken) {
                continue;
            }

            echo "Объявлено случаев {$short}: {$written} (строки "
                . implode(', ', array_column($declared['cases'][$short], 'line'))
                . "), собрано {$taken}.\n";
            echo "Причины две: атрибут стоит не на методе (конструктор, свойство, класс) или метод\n";
            echo "лежит в классе без #[Subject]. Тест не создан.\n";
            exit(1);
        }
    }

    /** Ближайшее имя среди атрибутов контракта: подсказка при опечатке. */
    private static function closest(string $short): ?string
    {
        $best  = null;
        $least = 3;

        foreach (self::KNOWN as $known) {
            $distance = levenshtein(strtolower($short), strtolower($known));

            if ($distance < $least) {
                $least = $distance;
                $best  = $known;
            }
        }

        return $least <= 2 ? $best : null;
    }

    /**
     * Все атрибуты файла — по тексту, а не отражением.
     *
     * Отражение видит только разрешившееся имя: `#[Fails]` без `use` для него не существует,
     * а `#[Fail]` с опечаткой — не существует вовсе. Здесь атрибут виден таким, каким его
     * написали, вместе с тем, во что разрешилось имя: по этому списку генератор и судит.
     *
     * @return array{cases: array<string, list<array{line: int, name: string, exists: bool}>>,
     *               all: list<array{line: int, short: string, name: string, exists: bool}>}
     */
    private static function declared(string $file): array
    {
        $tokens    = token_get_all((string) file_get_contents($file));
        $count     = count($tokens);
        $namespace = '';
        $imports   = [];
        $grouped   = false;
        $found     = [];
        $all       = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                [$namespace, $i] = self::qualified($tokens, $i + 1);

                continue;
            }

            if ($token[0] === T_USE) {
                [$i, $imports, $grouped] = self::import($tokens, $i, $imports, $grouped);

                continue;
            }

            if ($token[0] !== T_ATTRIBUTE) {
                continue;
            }

            [$name, $i] = self::qualified($tokens, $i + 1);

            if ($name === '') {
                continue;
            }

            $short    = substr((string) strrchr('\\' . ltrim($name, '\\'), '\\'), 1);
            $resolved = self::resolve($name, $namespace, $imports);
            $exists   = $grouped || class_exists($resolved);

            $all[] = [
                'line'   => $token[2],
                'short'  => $short,
                'name'   => $resolved,
                'exists' => $exists,
            ];

            if (in_array($short, self::READ, true)) {
                $found[$short][] = [
                    'line'   => $token[2],
                    'name'   => $resolved,
                    'exists' => $exists,
                ];
            }
        }

        return ['cases' => $found, 'all' => $all];
    }

    /**
     * Разбирает `use`: имя, псевдоним, групповую форму и `use` замыкания.
     *
     * @param array<string, string> $imports псевдоним → полное имя
     * @return array{0: int, 1: array<string, string>, 2: bool} позиция, импорты, была ли группа
     */
    private static function import(array $tokens, int $at, array $imports, bool $grouped): array
    {
        [$name, $end] = self::qualified($tokens, $at + 1);

        if ($name === '') {
            return [$at, $imports, $grouped];
        }

        $next = $tokens[$end] ?? null;

        if ($next === '(') {
            return [$end, $imports, $grouped];
        }

        if ($next === '{') {
            return self::group($tokens, $end, rtrim($name, '\\'), $imports, $grouped);
        }

        $alias = substr((string) strrchr('\\' . $name, '\\'), 1);

        if (is_array($next) && $next[0] === T_AS) {
            [$alias, $end] = self::qualified($tokens, $end + 1);
        }

        $imports[$alias] = ltrim($name, '\\');

        return [$end, $imports, $grouped];
    }

    /**
     * Групповая форма: `use A\B\{C, D as E};` → импорты `C` и `E`.
     *
     * Разбирается, а не пропускается: групповая форма в контрактах самая частая, и без
     * разбора опечатка в атрибуте такого файла не судится вовсе. Встретилось `function`
     * или `const` — файл помечается неразобранным, и имена в нём не судятся, как раньше:
     * отказать верному контракту хуже, чем не заметить опечатку.
     *
     * @param array<string, string> $imports псевдоним → полное имя
     * @return array{0: int, 1: array<string, string>, 2: bool} позиция, импорты, была ли группа
     */
    private static function group(array $tokens, int $at, string $prefix, array $imports, bool $grouped): array
    {
        $count = count($tokens);
        $j     = $at + 1;

        while ($j < $count && $tokens[$j] !== '}') {
            $token = $tokens[$j];

            if (is_array($token) && ($token[0] === T_FUNCTION || $token[0] === T_CONST)) {
                $grouped = true;
            }

            [$member, $j] = self::qualified($tokens, $j);

            if ($member === '') {
                $j++;

                continue;
            }

            $alias = substr((string) strrchr('\\' . $member, '\\'), 1);
            $after = $tokens[$j] ?? null;

            if (is_array($after) && $after[0] === T_AS) {
                [$alias, $j] = self::qualified($tokens, $j + 1);
            }

            $imports[$alias] = $prefix . '\\' . $member;
        }

        return [$j, $imports, $grouped];
    }

    /**
     * Имя по правилам PHP: полное с ведущей чертой, импортированное по первому отрезку,
     * иначе — от текущего пространства имён.
     *
     * @param array<string, string> $imports псевдоним → полное имя
     */
    private static function resolve(string $name, string $namespace, array $imports): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $first = explode('\\', $name)[0];

        if (isset($imports[$first])) {
            return $imports[$first] . substr($name, strlen($first));
        }

        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /**
     * Имя из токенов подряд: `T_NAME_QUALIFIED` целиком, старая разметка — склейкой.
     *
     * @return array{0: string, 1: int} имя и позиция следующего значимого токена
     */
    private static function qualified(array $tokens, int $from): array
    {
        $count = count($tokens);
        $name  = '';
        $j     = $from;

        for (; $j < $count; $j++) {
            $token = $tokens[$j];

            if (is_array($token) && $token[0] === T_WHITESPACE) {
                continue;
            }

            if (is_array($token) && in_array(
                $token[0],
                [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR],
                true
            )) {
                $name .= $token[1];

                continue;
            }

            break;
        }

        return [$name, $j];
    }

    private static function target(string $root, string $source): string
    {
        if (str_starts_with($source, 'core/')) {
            $source = 'Core/' . substr($source, 5);
        } elseif (str_starts_with($source, 'app/')) {
            $source = 'App/' . substr($source, 4);
        }

        return $root . '/app/Tests/' . preg_replace('/\.php$/', 'Test.php', $source);
    }

    private static function contracts(string $file): array
    {
        $classes = self::classesOf($file);
        self::load($file, $classes);

        $contracts = [];

        foreach ($classes as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $subjects   = $reflection->getAttributes(Subject::class);

            if ($subjects === []) {
                continue;
            }

            $counts = [];

            $contracts[] = [
                'subject' => $subjects[0]->newInstance(),
                'fixture' => self::fixturesOf($reflection),
                'cases'   => self::casesOf($reflection, $counts),
                'counts'  => $counts,
            ];
        }

        return $contracts;
    }

    private static function classesOf(string $file): array
    {
        $tokens    = token_get_all((string) file_get_contents($file));
        $count     = count($tokens);
        $namespace = '';
        $classes   = [];

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';

                for ($j = $i + 1; $j < $count; $j++) {
                    $next = $tokens[$j];

                    if (is_array($next) && $next[0] === T_WHITESPACE) {
                        continue;
                    }

                    if (is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR], true)) {
                        $namespace .= $next[1];
                        continue;
                    }

                    break;
                }
            }

            if ($token[0] === T_CLASS) {
                $previous = $tokens[$i - 1] ?? null;

                // Перед объявлением класса пробел не значим, а вот "::class" — это имя класса,
                // а не объявление: отличаем их, пропустив whitespace назад.
                $before = $i - 1;

                while ($before >= 0 && is_array($tokens[$before]) && $tokens[$before][0] === T_WHITESPACE) {
                    $before--;
                }

                if ($before >= 0 && is_array($tokens[$before]) && $tokens[$before][0] === T_DOUBLE_COLON) {
                    continue;
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    $next = $tokens[$j];

                    if (is_array($next) && $next[0] === T_STRING) {
                        $classes[] = ($namespace === '' ? '' : $namespace . '\\') . $next[1];
                        break;
                    }

                    if (!is_array($next) && $next === '{') {
                        break;
                    }
                }
            }
        }

        return $classes;
    }

    private static function load(string $file, array $classes): void
    {
        self::loadBase($file);

        foreach ($classes as $class) {
            if (class_exists($class)) {
                return;
            }
        }

        try {
            require_once $file;
        } catch (\Throwable $e) {
            self::$loadError = $e->getMessage();
        }
    }

    /**
     * Подгружает базу направления перед файлом места: класс места наследует
     * {Напр}Workspaces, а файл читается напрямую — мира, который её грузит, здесь нет.
     */
    private static function loadBase(string $file): void
    {
        $path  = str_replace('\\', '/', $file);
        $parts = explode('/app/World/', $path, 2);

        if (count($parts) !== 2) {
            return;
        }

        try {
            World::loadDirection(explode('/', $parts[1])[0]);
        } catch (\Throwable $e) {
            self::$loadError = $e->getMessage();
        }
    }

    private static function fixturesOf(ReflectionClass $reflection): array
    {
        $attributes = $reflection->getAttributes(Fixture::class);

        return $attributes === [] ? [] : $attributes[0]->newInstance()->files;
    }

    /**
     * @param array<string, int> $counts вид → сколько случаев этого вида собрано
     */
    private static function casesOf(ReflectionClass $reflection, array &$counts): array
    {
        $class   = self::fixturesOf($reflection);
        $current = $class;
        $cases   = [];
        $counts  = [];

        foreach ($reflection->getMethods() as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }

            $files = self::methodFixtures($method);

            if ($files !== [] && $files !== $current) {
                $cases[] = '$run->fixture(' . self::literal($files) . ');';
                $current = $files;
            }

            foreach ($method->getAttributes(Gives::class) as $attribute) {
                $gives   = $attribute->newInstance();
                $cases[] = self::line('gives', $method->getName(), $gives->args, [$gives->expect], $gives->before);
                $counts['Gives'] = ($counts['Gives'] ?? 0) + 1;
            }

            foreach ($method->getAttributes(Has::class) as $attribute) {
                $has     = $attribute->newInstance();
                $cases[] = self::line('has', $method->getName(), $has->args, [$has->keys], $has->before);
                $counts['Has'] = ($counts['Has'] ?? 0) + 1;
            }

            foreach ($method->getAttributes(Fails::class) as $attribute) {
                $fails   = $attribute->newInstance();
                $cases[] = self::line('fails', $method->getName(), $fails->args, [$fails->exception], $fails->before);
                $counts['Fails'] = ($counts['Fails'] ?? 0) + 1;
            }

            foreach ($method->getAttributes(Changes::class) as $attribute) {
                $changes = $attribute->newInstance();
                $cases[] = self::line('changes', $method->getName(), $changes->args, [$changes->then], $changes->before);
                $counts['Changes'] = ($counts['Changes'] ?? 0) + 1;
            }

            foreach ($method->getAttributes(Responds::class) as $attribute) {
                $responds = $attribute->newInstance();
                $cases[]  = self::line(
                    'responds',
                    $method->getName(),
                    $responds->args,
                    [$responds->body, $responds->status],
                    $responds->before
                );
                $counts['Responds'] = ($counts['Responds'] ?? 0) + 1;
            }
        }

        return $cases;
    }

    private static function methodFixtures(ReflectionMethod $method): array
    {
        $attributes = $method->getAttributes(Fixture::class);

        return $attributes === [] ? [] : $attributes[0]->newInstance()->files;
    }

    private static function line(string $name, string $method, array $args, array $extra, array $before): string
    {
        $parts = [self::literal($method), self::literal($args)];

        foreach ($extra as $item) {
            $parts[] = self::literal($item);
        }

        if ($before !== []) {
            $parts[] = self::literal($before);
        }

        return '$run->' . $name . '(' . implode(', ', $parts) . ');';
    }

    private static function body(array $contracts): string
    {
        $blocks = [];

        foreach ($contracts as $contract) {
            $subject = $contract['subject'];
            $args    = [$subject->class, $subject->factory];

            if ($subject->args !== [] || $subject->path !== '') {
                $args[] = $subject->args;
            }

            if ($subject->path !== '') {
                $args[] = $subject->path;
            }

            $rendered = [];

            foreach ($args as $arg) {
                $rendered[] = self::literal($arg);
            }

            $lines = ['$run->subject(' . implode(', ', $rendered) . ');'];

            if ($contract['fixture'] !== []) {
                $lines[] = '';
                $lines[] = '$run->fixture(' . self::literal($contract['fixture']) . ');';
            }

            if ($contract['cases'] !== []) {
                $lines[] = '';
                $lines   = array_merge($lines, $contract['cases']);
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks) . "\n";
    }

    private static function literal(mixed $value, int $level = 0): string
    {
        if (!is_array($value)) {
            return self::scalar($value);
        }

        $inline = self::inline($value);

        if ($value === [] || strlen($inline) + $level * 4 <= 88) {
            return $inline;
        }

        $list  = array_is_list($value);
        $pad   = str_repeat('    ', $level + 1);
        $items = [];

        foreach ($value as $key => $item) {
            $items[] = $pad
                . ($list ? '' : self::scalar($key) . ' => ')
                . self::literal($item, $level + 1);
        }

        return "[\n" . implode(",\n", $items) . ",\n" . str_repeat('    ', $level) . ']';
    }

    private static function inline(mixed $value): string
    {
        if (!is_array($value)) {
            return self::scalar($value);
        }

        $list  = array_is_list($value);
        $items = [];

        foreach ($value as $key => $item) {
            $items[] = ($list ? '' : self::scalar($key) . ' => ') . self::inline($item);
        }

        return '[' . implode(', ', $items) . ']';
    }

    private static function scalar(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
        }

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return var_export($value, true);
        }

        return 'null';
    }

    private static function makeDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Не создать каталог: {$dir}");
        }
    }
}
