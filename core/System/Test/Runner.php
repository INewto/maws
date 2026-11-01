<?php

namespace Maws\System\Test;

use Maws\System\Contract\Param;
use Maws\System\Contract\Purpose;
use Maws\System\Contract\Token;
use Maws\System\Exception\ResponseSent;
use Maws\System\Test\Exception\TestMisuse;
use PDO;
use PDOException;
use ReflectionMethod;
use ReflectionProperty;

#[Purpose('Прогон контракта: собирает получателя через Scaffold, выполняет объявленные случаи и печатает отчёт.')]
final class Runner
{
    private string $source;
    private string $sandboxRoot;
    private string $subjectClass = '';
    private string $factory = '';
    private array $factoryArgs = [];
    private string $path = '';
    private array $fixture = [];
    private array $bindings = [];
    private mixed $root = null;
    private int $case = 0;
    private int $passed = 0;
    private int $failed = 0;

    // Пояс, в котором считается оракул. Примеры #[Gives] записаны в UTC, а пояс машины задаётся
    // php.ini: без фиксации прогон отвечал бы на настройку сервера, а не на код, и краснел бы
    // на другой машине, ничего не проверив по существу.
    private const ZONE = 'UTC';

    #[Purpose('Начинает прогон и требует живую базу.')]
    #[Param('source', 'string', 'путь источника контракта относительно корня проекта')]
    public function __construct(string $source)
    {
        $this->source      = $source;
        $this->sandboxRoot = sys_get_temp_dir() . '/maws-tests-' . getmypid();

        // Настройка прогона, а не фреймворка: сайт живёт в поясе сервера, оракул — в ZONE.
        date_default_timezone_set(self::ZONE);

        // Отчёт копится в буфере и уходит в поток на выходе. Иначе первая же строка
        // считается отправленной, и header() в проверяемом месте падает с
        // «headers already sent» ещё до того, как место что-то напечатало.
        ob_start();

        echo "MAWS — прогон контракта\n";
        echo "Источник: {$source}\n";

        $this->demandDbase();
    }

    #[Purpose('Объявляет получателя: класс, фабрика корня, её аргументы и путь от корня до получателя.')]
    #[Param('class', 'string', 'класс получателя')]
    #[Param('factory', 'string', 'фабрика корня: "new", "метод" или "Класс::метод"')]
    #[Param('args', 'array', 'аргументы фабрики')]
    #[Param('path', 'string', 'цепочка свойств через "->" от корня до получателя')]
    public function subject(string $class, string $factory, array $args = [], string $path = ''): void
    {
        $this->subjectClass = $class;
        $this->factory      = $factory;
        $this->factoryArgs  = $args;
        $this->path         = $path;

        echo "Получатель: {$class}\n\n";
    }

    #[Purpose('Задаёт файлы песочницы для следующих случаев.')]
    #[Param('files', 'array', 'пары «путь в песочнице → содержимое»')]
    public function fixture(array $files): void
    {
        $this->fixture = $files;
    }

    #[Purpose('Случай «успешный вызов»: аргументы дают ожидаемое значение.')]
    #[Param('method', 'string', 'имя метода')]
    #[Param('args', 'array', 'аргументы вызова')]
    #[Param('expect', 'mixed', 'ожидаемое значение')]
    #[Param('before', 'array', 'шаги подготовки до вызова')]
    public function gives(string $method, array $args, mixed $expect, array $before = []): void
    {
        $this->run(
            $this->label($method, $args) . ' → ' . $this->want($expect),
            $method,
            $args,
            $before,
            function (callable $call) use ($expect): ?string {
                $got = $call();

                return $this->fits($got, $expect)
                    ? null
                    : 'получено ' . $this->show($got) . ', ожидалось ' . $this->want($expect);
            }
        );
    }

    #[Purpose('Случай «есть ключи»: вызов вернул массив, в котором присутствуют объявленные ключи.')]
    #[Param('method', 'string', 'имя метода')]
    #[Param('args', 'array', 'аргументы вызова')]
    #[Param('keys', 'array', 'ключи, которые обязаны быть в результате; значения и лишние ключи не сверяются')]
    #[Param('before', 'array', 'шаги подготовки до вызова')]
    public function has(string $method, array $args, array $keys, array $before = []): void
    {
        $this->run(
            $this->label($method, $args) . ' → ключи',
            $method,
            $args,
            $before,
            function (callable $call) use ($keys): ?string {
                $got = $call();

                if (!is_array($got)) {
                    return 'получено ' . $this->show($got) . ', ожидался массив';
                }

                // Только наличие: значение выбирает тот, кто наполняет данные, а новые колонки
                // в таблице — его право. Пропажа объявленного ключа — уже сломанная форма.
                $missing = array_values(array_diff($keys, array_keys($got)));

                return $missing === []
                    ? null
                    : 'нет ключей ' . implode(', ', $missing) . ', в результате ' . $this->show(array_keys($got));
            }
        );
    }

    #[Returns('int', 'сколько провалилось')]
    public function failures(): int
    {
        return $this->failed;
    }

    #[Purpose('Случай «отказной вызов»: аргументы дают ожидаемое исключение.')]
    #[Param('method', 'string', 'имя метода')]
    #[Param('args', 'array', 'аргументы вызова')]
    #[Param('exception', 'string', 'класс ожидаемого исключения')]
    #[Param('before', 'array', 'шаги подготовки до вызова')]
    public function fails(string $method, array $args, string $exception, array $before = []): void
    {
        $this->run(
            $this->label($method, $args) . ' → отказ ' . $this->short($exception),
            $method,
            $args,
            $before,
            function (callable $call) use ($exception): ?string {
                try {
                    $call();
                } catch (\Throwable $e) {
                    return get_class($e) === $exception
                        ? null
                        : 'получено ' . get_class($e) . ': ' . $e->getMessage() . ', ожидалось ' . $exception;
                }

                return 'исключения не было, ожидалось ' . $exception;
            }
        );
    }

    #[Purpose('Случай «место ответило само»: метод напечатал ожидаемое тело, выставил ожидаемый код и завершил ответ через ResponseSent.')]
    #[Param('method', 'string', 'имя метода')]
    #[Param('args', 'array', 'аргументы вызова')]
    #[Param('expect', 'string', 'ожидаемое тело ответа, байт в байт')]
    #[Param('status', 'int', 'ожидаемый код ответа; 200 — и «выставлен явно», и «не выставлен вовсе»: в веб-SAPI это одно и то же')]
    #[Param('before', 'array', 'шаги подготовки до вызова')]
    public function responds(string $method, array $args, string $expect, int $status = 200, array $before = []): void
    {
        $this->run(
            $this->label($method, $args) . ' → ответ',
            $method,
            $args,
            $before,
            function (callable $call, ?object $root, callable $output) use ($expect, $status): ?string {
                try {
                    $call();
                } catch (ResponseSent $e) {
                    $got = $output();

                    if ($got !== $expect) {
                        return 'тело ' . $this->show($got) . ', ожидалось ' . $this->show($expect);
                    }

                    $code = http_response_code();

                    return $code === $status
                        ? null
                        : 'код ответа ' . $this->show($code) . ', ожидался ' . $this->show($status);
                }

                return 'метод вернул управление, ожидался ' . $this->short(ResponseSent::class);
            }
        );
    }

    #[Purpose('Случай «мутирующий вызов»: аргументы дают объявленное состояние после.')]
    #[Param('method', 'string', 'имя метода')]
    #[Param('args', 'array', 'аргументы вызова')]
    #[Param('then', 'array', 'проверки после вызова: [путь, аргументы, ожидание]')]
    #[Param('before', 'array', 'шаги подготовки до вызова')]
    public function changes(string $method, array $args, array $then, array $before = []): void
    {
        $this->run(
            $this->label($method, $args) . ' → состояние',
            $method,
            $args,
            $before,
            function (callable $call, ?object $root) use ($then): ?string {
                $call();

                foreach ($then as $step) {
                    $got  = $this->peek($root, $step[0], $step[1] ?? []);
                    $want = $step[2] ?? null;

                    if ($got !== $want) {
                        return $step[0] . ' = ' . $this->show($got) . ', ожидалось ' . $this->show($want);
                    }
                }

                return null;
            }
        );
    }

    #[Purpose('Печатает итог, убирает песочницу и завершает процесс кодом провала.')]
    public function finish(): void
    {
        $total = $this->passed + $this->failed;

        if ($total === 0) {
            echo "Проверок нет.\n";
            $this->cleanup();
            exit(1);
        }

        echo "\nИтог: {$total} проверок, {$this->passed} прошло, {$this->failed} провалено\n";

        $this->cleanup();
        exit($this->failed === 0 ? 0 : 1);
    }

    private function run(string $label, string $method, array $args, array $before, callable $check): void
    {
        $this->case++;
        $this->bindings = [];
        $this->root     = null;

        // Код ответа — состояние процесса, а не песочницы: без сброса случай, который его
        // выставил, «протёк» бы в следующий, и проверка отвечала бы на чужой вызов.
        http_response_code(200);

        $root = null;

        try {
            Scaffold::begin($this->freshSandbox());

            $static = (new ReflectionMethod($this->subjectClass, $method))->isStatic();

            if ($static) {
                if ($before !== []) {
                    throw new TestMisuse('подготовка до вызова неприменима к статическому методу');
                }

                $class = $this->subjectClass;
                $call  = fn () => $class::$method(...$this->values($args));
            } else {
                $root   = $this->rootUp();
                $target = $this->subjectUp();

                $this->prepare($root, $before);
                $call = fn () => $target->$method(...$this->values($args));
            }

            // Вывод места не должен попадать в отчёт и не должен ломать header():
            // место, которое отвечает само, печатает ровно так же, как в бою.
            ob_start();

            try {
                $problem = $check($call, $root, static fn (): string => (string) ob_get_contents());
            } finally {
                ob_end_clean();
            }
        } catch (ResponseSent $e) {
            // Место ответило само и не вернуло данные: проверять нечего, но это
            // не «тихий зелёный» — случай назван в отчёте.
            $problem = 'место ответило само (ResponseSent): вывод тестом не проверяется';
        } catch (\Throwable $e) {
            $problem = get_class($e) . ': ' . $e->getMessage();
        }

        if ($problem === null) {
            $this->passed++;
            echo "  [ok]   {$label}\n";

            return;
        }

        $this->failed++;
        echo "  [fail] {$label}\n";
        echo "         {$problem}\n";
    }

    private function rootUp(): object
    {
        if ($this->root === null) {
            $this->root = $this->build();
        }

        return $this->root;
    }

    private function subjectUp(): object
    {
        $subject = $this->rootUp();

        foreach (explode('->', $this->path) as $step) {
            if ($step !== '') {
                $subject = $subject->$step;
            }
        }

        return $subject;
    }

    private function build(): object
    {
        $args = $this->values($this->factoryArgs);

        $this->demandMode();

        if ($this->factory === 'new') {
            $class = $this->subjectClass;

            return new $class(...$args);
        }

        if (str_contains($this->factory, '::')) {
            [$class, $method] = explode('::', $this->factory, 2);
            $class = $this->factoryClass($class);

            return $class::$method(...$args);
        }

        $class  = $this->subjectClass;
        $method = $this->factory;

        return $class::$method(...$args);
    }

    private function demandMode(): void
    {
        if ($this->subjectClass === '') {
            throw new TestMisuse('фабрика не объявлена: сначала вызови $run->subject(...)');
        }

        if (str_contains($this->factory, '::')) {
            return;
        }

        if (!class_exists($this->subjectClass)) {
            throw new TestMisuse(
                "класс {$this->subjectClass} не найден: файл источника не объявил его — "
                . 'проверь имя класса и то, что его родитель загружен'
            );
        }
    }

    private function factoryClass(string $name): string
    {
        if (str_contains($name, '\\')) {
            return $name;
        }

        $class = __NAMESPACE__ . '\\' . $name;

        if (!class_exists($class)) {
            throw new TestMisuse("фабрика {$name} не найдена: нет класса {$class}");
        }

        return $class;
    }

    private function peek(object $root, string $path, array $args): mixed
    {
        $steps = explode('->', $path);
        $last  = array_pop($steps);
        $owner = $root;

        foreach ($steps as $step) {
            if (!$this->hasProperty($owner, $step)) {
                throw new TestMisuse("путь {$path} не разрешается от " . get_class($root) . ": нет свойства {$step}");
            }

            $owner = $owner->$step;
        }

        if ($this->hasProperty($owner, $last)) {
            return $owner->$last;
        }

        if ($this->hasMethod($owner, $last)) {
            return $owner->$last(...$this->values($args));
        }

        throw new TestMisuse("путь {$path} не разрешается от " . get_class($root) . ": нет ни свойства, ни метода {$last}");
    }

    private function hasProperty(object $owner, string $name): bool
    {
        return property_exists($owner, $name) && (new ReflectionProperty($owner, $name))->isPublic();
    }

    private function hasMethod(object $owner, string $name): bool
    {
        return method_exists($owner, $name) && (new ReflectionMethod($owner, $name))->isPublic();
    }

    private function prepare(object $root, array $before): void
    {
        foreach ($before as $step) {
            $result = $this->peek($root, $step[0], $step[1] ?? []);

            if (isset($step[2])) {
                $this->bindings[$step[2]] = $result;
            }
        }
    }

    private function values(array $args): array
    {
        $values = [];

        foreach ($args as $arg) {
            $values[] = $this->value($arg);
        }

        return $values;
    }

    private function value(mixed $arg): mixed
    {
        if (!is_string($arg) || !str_starts_with($arg, '@')) {
            return $arg;
        }

        if (str_starts_with($arg, Token::SANDBOX . '/')) {
            return Scaffold::path(substr($arg, strlen(Token::SANDBOX) + 1));
        }

        if ($arg === Token::SANDBOX) {
            return Scaffold::sandbox();
        }

        if ($arg === Token::SUBJECT) {
            return $this->subjectUp();
        }

        if ($arg === Token::WORLD) {
            return Scaffold::world();
        }

        if ($arg === Token::SCREEN) {
            return Scaffold::screen();
        }

        if (!array_key_exists($arg, $this->bindings)) {
            throw new TestMisuse("ссылка {$arg} ни на что не указывает");
        }

        return $this->bindings[$arg];
    }

    private function freshSandbox(): string
    {
        $dir = $this->sandboxRoot . '/' . $this->case;

        $this->makeDir($dir);

        foreach ($this->fixture as $file => $content) {
            $this->makeDir(dirname($dir . '/' . $file));

            if (file_put_contents($dir . '/' . $file, $content) === false) {
                throw new TestMisuse("не записать файл песочницы {$file}");
            }
        }

        return $dir;
    }

    private function makeDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new TestMisuse("не создать каталог {$dir}");
        }
    }

    private function demandDbase(): void
    {
        try {
            new PDO(DBASE_DSN, DBASE_USER, DBASE_PASSWORD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            echo "\nНет живой базы: " . DBASE_DSN . "\n" . $e->getMessage() . "\n";
            exit(1);
        }
    }

    private function cleanup(): void
    {
        if (!str_contains($this->sandboxRoot, 'maws-tests-') || !is_dir($this->sandboxRoot)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sandboxRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($this->sandboxRoot);
    }

    private function short(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    private function label(string $method, array $args): string
    {
        return $this->short($this->subjectClass) . '::' . $method
            . '(' . ($args === [] ? '' : $this->show($args)) . ')';
    }

    private function fits(mixed $got, mixed $expect): bool
    {
        if ($expect === Token::STATEMENT) {
            return $got instanceof \PDOStatement;
        }

        if ($expect === Token::SUBJECT) {
            return $this->root !== null && $got === $this->subjectUp();
        }

        // Ожидание может быть путём в песочнице: метод, возвращающий путь, иначе не проверить —
        // в пути стоит временный каталог прогона, и литералом его не записать.
        if (is_string($expect) && ($expect === Token::SANDBOX || str_starts_with($expect, Token::SANDBOX . '/'))) {
            $expect = $this->value($expect);
        }

        return $got === $expect;
    }

    private function want(mixed $expect): string
    {
        if ($expect === Token::SUBJECT) {
            return 'тот же объект';
        }

        if ($expect === Token::STATEMENT) {
            return 'PDOStatement';
        }

        return $this->show($expect);
    }

    private function show(mixed $value): string
    {
        if (is_array($value)) {
            $items = [];

            foreach ($value as $key => $item) {
                $items[] = (is_int($key) ? '' : $this->show($key) . ' => ') . $this->show($item);
            }

            return '[' . implode(', ', $items) . ']';
        }

        if (is_string($value)) {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            return $json === false ? 'строка' : $json;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_object($value)) {
            return 'объект ' . get_class($value);
        }

        if (is_float($value)) {
            return var_export($value, true);
        }

        return (string) $value;
    }
}
