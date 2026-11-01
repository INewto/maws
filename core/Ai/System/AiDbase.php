<?php

namespace Maws\Ai\System;

use JsonException;
use Maws\Ai\System\Exception\AiDbaseDenied;
use Maws\Ai\System\Exception\AiDbaseFailed;
use Maws\Ai\System\Exception\AiDbaseMisuse;
use Maws\Ai\System\Exception\AiDbasePlanless;
use Maws\Ai\System\Exception\AiDbaseUnreachable;
use Maws\Ai\System\Exception\AiDbaseUnsupported;
use Maws\System\Contract\{Changes, Effect, Fails, Fixture, Gives, Param, Purpose, Returns, Subject, Throws, Token};
use PDO;
use PDOException;

#[Subject(AiDbase::class, 'Scaffold::aiDbase', ['dbaccess.json'])]
#[Fixture(files: ['dbaccess.json' => '{"tables":["public.test_table","public.scenes"]}'])]
#[Purpose('База под правами агента: сверяет таблицы из плана запроса с dbaccess.json и отказывает там, где плана нет.')]
final class AiDbase
{
    private const EXPLAIN_PREFIX = 'EXPLAIN (VERBOSE, FORMAT JSON) ';

    private const PLANNED = ['select', 'insert', 'update', 'delete', 'values', 'merge', 'with', 'table'];

    // Функции, которые читают файлы, чужие таблицы или исполняют SQL из строки. Внутри у них
    // плана нет: EXPLAIN показывает только имя вызова, поэтому список ведётся руками. Список —
    // не защита, а ранний отказ: то, что он пропустит, поймает сверка по факту (touched).
    private const FORBIDDEN = [
        // файлы
        'pg_read_file', 'pg_read_binary_file', 'pg_ls_dir', 'pg_stat_file',
        'pg_ls_waldir', 'pg_ls_logdir', 'pg_ls_archive_statusdir', 'pg_ls_tmpdir',
        // SQL из строки и таблица по имени
        'query_to_xml', 'cursor_to_xml',
        'table_to_xml', 'table_to_xmlschema', 'table_to_xml_and_xmlschema',
        'schema_to_xml', 'schema_to_xmlschema', 'schema_to_xml_and_xmlschema',
        'database_to_xml', 'database_to_xmlschema', 'database_to_xml_and_xmlschema',
        // большие объекты
        'lo_import', 'lo_export', 'lo_create', 'lo_unlink', 'lo_from_bytea', 'lo_put',
        // чужая база
        'dblink', 'dblink_exec', 'dblink_connect', 'dblink_connect_u',
        // настройки сессии, включая role
        'set_config',
    ];

    private ?PDO $pdo = null;

    #[Purpose('Собирает обёртку над базой с правами агента.')]
    #[Param('access', 'AiAccess', 'права агента: какие таблицы ему разрешены')]
    #[Param('dsn', 'string', 'строка подключения; движок должен быть pgsql')]
    #[Param('user', 'string', 'пользователь базы')]
    #[Param('password', 'string', 'пароль базы')]
    #[Effect(Effect::NONE)]
    public function __construct(
        private AiAccess $access,
        private string $dsn,
        private string $user,
        private string $password,
    ) {
    }

    #[Purpose('Читает все строки по SQL, предварительно сверив таблицы запроса с правами агента.')]
    #[Param('sql', 'string', 'SQL-запрос; таблицы из его плана сверяются с dbaccess.json')]
    #[Param('params', 'array', 'значения для подстановки; по умолчанию пусто')]
    #[Returns('array', 'все выбранные строки; пустой массив, если ничего не найдено')]
    #[Throws(AiDbasePlanless::class, 'план не назвал ни одной таблицы — сверять стражу нечего')]
    #[Throws(AiDbaseMisuse::class, 'запросу плана не бывает вовсе (TRUNCATE, DDL, COPY, SET) — такие идут дверью')]
    #[Throws(AiDbaseDenied::class, 'план запроса задел таблицу, не разрешённую агенту')]
    #[Throws(AiDbaseFailed::class, 'сервер не выполнил запрос или не вернул план EXPLAIN')]
    #[Throws(AiDbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(AiDbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_READ)]
    #[Gives(['SELECT * FROM public.test_table LIMIT 0'], [])]
    #[Fails(['SELECT * FROM public.areas'], AiDbaseDenied::class)]
    #[Fails(['SELECT 1'], AiDbasePlanless::class)]
    #[Fails(['CREATE TABLE public.probe_x (id int)'], AiDbaseMisuse::class)]
    #[Changes(['SELECT * FROM public.test_table LIMIT 0'], [['inTransaction', [], false]])]
    public function all(string $sql, array $params = []): array
    {
        return $this->guarded($sql, $params, fn (): array => $this->statement($sql, $params)->fetchAll());
    }

    #[Purpose('Читает одну строку по SQL, предварительно сверив таблицы запроса с правами агента.')]
    #[Param('sql', 'string', 'SQL-запрос; таблицы из его плана сверяются с dbaccess.json')]
    #[Param('params', 'array', 'значения для подстановки; по умолчанию пусто')]
    #[Returns('array|null', 'первая строка или null, если строк нет')]
    #[Throws(AiDbasePlanless::class, 'план не назвал ни одной таблицы — сверять стражу нечего')]
    #[Throws(AiDbaseMisuse::class, 'запросу плана не бывает вовсе (TRUNCATE, DDL, COPY, SET) — такие идут дверью')]
    #[Throws(AiDbaseDenied::class, 'план запроса задел таблицу, не разрешённую агенту')]
    #[Throws(AiDbaseFailed::class, 'сервер не выполнил запрос или не вернул план EXPLAIN')]
    #[Throws(AiDbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(AiDbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_READ)]
    #[Gives(['SELECT * FROM public.test_table LIMIT 0'], null)]
    #[Fails(['SELECT * FROM public.areas'], AiDbaseDenied::class)]
    #[Fails(['SELECT 1'], AiDbasePlanless::class)]
    #[Fails(['TRUNCATE public.test_table'], AiDbaseMisuse::class)]
    #[Changes(['SELECT * FROM public.test_table LIMIT 0'], [['inTransaction', [], false]])]
    public function one(string $sql, array $params = []): ?array
    {
        return $this->guarded($sql, $params, function () use ($sql, $params): ?array {
            $row = $this->statement($sql, $params)->fetch();

            return $row === false ? null : $row;
        });
    }

    #[Purpose('Выполняет запрос и отдаёт число затронутых строк, сверив таблицы с правами агента.')]
    #[Param('sql', 'string', 'SQL-запрос; таблицы из его плана сверяются с dbaccess.json')]
    #[Param('params', 'array', 'значения для подстановки; по умолчанию пусто')]
    #[Returns('int', 'число затронутых строк')]
    #[Throws(AiDbasePlanless::class, 'план не назвал ни одной таблицы — сверять стражу нечего')]
    #[Throws(AiDbaseMisuse::class, 'запросу плана не бывает вовсе (TRUNCATE, DDL, COPY, SET) — такие идут дверью')]
    #[Throws(AiDbaseDenied::class, 'план запроса задел таблицу, не разрешённую агенту')]
    #[Throws(AiDbaseFailed::class, 'сервер не выполнил запрос или не вернул план EXPLAIN')]
    #[Throws(AiDbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(AiDbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_WRITE)]
    #[Gives(['DELETE FROM public.test_table WHERE false'], 0)]
    #[Fails(['DELETE FROM public.areas'], AiDbaseDenied::class)]
    #[Fails(['TRUNCATE public.test_table'], AiDbaseMisuse::class)]
    #[Changes(['DELETE FROM public.test_table WHERE false'], [['inTransaction', [], false]])]
    public function run(string $sql, array $params = []): int
    {
        return $this->guarded($sql, $params, fn (): int => $this->statement($sql, $params)->rowCount());
    }

    #[Purpose('Выполняет select, у которого плана с таблицами нет, — в транзакции только для чтения. План не видит, что функция читает внутри себя, поэтому таблицы сверяются ещё и по факту: тронул необъявленную — результата не будет.')]
    #[Param('sql', 'string', 'один select; таблиц он трогать не должен')]
    #[Returns('PDOStatement', 'выполненное выражение; строки прочитаны на клиент, читать можно после возврата')]
    #[Throws(AiDbaseMisuse::class, 'запрос не того вида, содержит «;», трогает таблицы или зовёт запрещённую функцию')]
    #[Throws(AiDbaseDenied::class, 'запрос тронул таблицу, не разрешённую агенту; видно только по факту, план её не назвал')]
    #[Throws(AiDbaseFailed::class, 'сервер не выполнил запрос или не вернул план EXPLAIN')]
    #[Throws(AiDbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(AiDbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_READ)]
    #[Effect(Effect::DB_UNGUARDED)]
    #[Gives(['SELECT 1'], Token::STATEMENT)]
    #[Gives(["SELECT to_char(now(), 'DD.MM.YY')"], Token::STATEMENT)]
    #[Changes(['SELECT 1'], [['inTransaction', [], false]])]
    #[Fails(['DELETE FROM public.test_table'], AiDbaseMisuse::class)]
    #[Fails(['SELECT 1; SELECT 2'], AiDbaseMisuse::class)]
    #[Fails(['SELECT * FROM public.test_table'], AiDbaseMisuse::class)]
    #[Fails(["SELECT query_to_xml('SELECT 1 FROM public.areas', true, false, '')"], AiDbaseMisuse::class)]
    #[Fails(["SELECT pg_read_file('/etc/passwd')"], AiDbaseMisuse::class)]
    public function selectUnplanned(string $sql): \PDOStatement
    {
        $this->demandPlainSelect($sql);
        $this->demandNoTables($sql);
        $this->demandNoForbidden($sql);

        return $this->inReadOnly($sql);
    }

    #[Purpose('Открывает транзакцию.')]
    #[Throws(AiDbaseMisuse::class, 'транзакция уже открыта')]
    #[Throws(AiDbaseFailed::class, 'сервер не начал транзакцию')]
    #[Throws(AiDbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(AiDbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_WRITE)]
    #[Changes([], [['inTransaction', [], true]])]
    #[Fails([], AiDbaseMisuse::class, before: [['beginTransaction', []]])]
    public function beginTransaction(): void
    {
        if ($this->pdo()->inTransaction()) {
            throw new AiDbaseMisuse('вложенный begin — транзакция уже открыта');
        }

        try {
            $this->pdo()->beginTransaction();
        } catch (PDOException $e) {
            throw new AiDbaseFailed('BEGIN', $e->getMessage());
        }
    }

    #[Purpose('Фиксирует открытую транзакцию.')]
    #[Throws(AiDbaseMisuse::class, 'commit без открытой транзакции')]
    #[Throws(AiDbaseFailed::class, 'сервер не зафиксировал транзакцию')]
    #[Throws(AiDbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(AiDbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_WRITE)]
    #[Changes([], [['inTransaction', [], false]], before: [['beginTransaction', []]])]
    #[Fails([], AiDbaseMisuse::class)]
    public function commit(): void
    {
        if (!$this->pdo()->inTransaction()) {
            throw new AiDbaseMisuse('commit без открытой транзакции');
        }

        try {
            $this->pdo()->commit();
        } catch (PDOException $e) {
            throw new AiDbaseFailed('COMMIT', $e->getMessage());
        }
    }

    #[Purpose('Откатывает открытую транзакцию.')]
    #[Throws(AiDbaseMisuse::class, 'rollBack без открытой транзакции')]
    #[Throws(AiDbaseFailed::class, 'сервер не откатил транзакцию')]
    #[Throws(AiDbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(AiDbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_WRITE)]
    #[Changes([], [['inTransaction', [], false]], before: [['beginTransaction', []]])]
    #[Fails([], AiDbaseMisuse::class)]
    public function rollBack(): void
    {
        if (!$this->pdo()->inTransaction()) {
            throw new AiDbaseMisuse('rollBack без открытой транзакции');
        }

        try {
            $this->pdo()->rollBack();
        } catch (PDOException $e) {
            throw new AiDbaseFailed('ROLLBACK', $e->getMessage());
        }
    }

    #[Purpose('Сообщает, открыта ли транзакция.')]
    #[Returns('bool', 'true — транзакция открыта')]
    #[Throws(AiDbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(AiDbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_CONNECT)]
    #[Gives([], false)]
    #[Gives([], true, before: [['beginTransaction', []]])]
    public function inTransaction(): bool
    {
        return $this->pdo()->inTransaction();
    }

    private function guard(string $sql, array $params): void
    {
        $this->demandPlanned($sql);

        $tables = $this->tablesOf($sql, $params);

        if ($tables === []) {
            throw new AiDbasePlanless($sql);
        }

        $allowed = $this->access->tables();

        foreach ($tables as $table) {
            if (!in_array($table, $allowed, true)) {
                throw new AiDbaseDenied($table, $allowed);
            }
        }
    }

    private function guarded(string $sql, array $params, callable $work): mixed
    {
        // Транзакция здесь не ради атомарности. EXPLAIN берёт ACCESS SHARE на таблицы плана и держит
        // его до COMMIT: пока держит, ALTER и DROP этих таблиц ждут, поэтому разбор запроса на
        // выполнении видит ту же схему, что и проверка, и подменить таблицу между ними нечем.
        // Внутри чужой транзакции блокировки уже живут до её конца — вторая не открывается.
        $own = !$this->pdo()->inTransaction();

        if ($own) {
            $this->beginTransaction();
        }

        try {
            $this->guard($sql, $params);

            $result = $work();
        } catch (\Throwable $e) {
            if ($own) {
                $this->rollBack();
            }

            throw $e;
        }

        if ($own) {
            $this->commit();
        }

        return $result;
    }

    private function demandPlanned(string $sql): void
    {
        $head = $this->head($sql);

        if (in_array($head, self::PLANNED, true)) {
            return;
        }

        throw new AiDbaseMisuse(
            'all/one/run принимают только запросы, у которых бывает план ('
            . implode(', ', self::PLANNED) . "), получено «{$head}». Инструкции без плана — удел миграций; "
            . 'бестабличный select — selectUnplanned'
        );
    }

    private function head(string $sql): string
    {
        $bare = preg_replace('~^(?:\s+|/\*.*?\*/|--[^\n]*\n)+~s', '', $sql);

        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)/', (string) $bare, $found) !== 1) {
            return '';
        }

        return strtolower($found[1]);
    }

    private function demandPlainSelect(string $sql): void
    {
        if (str_contains($sql, ';')) {
            throw new AiDbaseMisuse("дверь принимает одну инструкцию, а в запросе есть «;»: {$sql}");
        }

        if ($this->head($sql) !== 'select') {
            throw new AiDbaseMisuse("дверь принимает только select, получено: {$sql}");
        }
    }

    private function demandNoTables(string $sql): void
    {
        $tables = $this->tablesOf($sql);

        if ($tables !== []) {
            $shown = implode(', ', array_map(static fn (array $t): string => "{$t[0]}.{$t[1]}", $tables));

            throw new AiDbaseMisuse("дверь принимает только запросы без таблиц, а этот трогает: {$shown}");
        }
    }

    private function inReadOnly(string $sql): \PDOStatement
    {
        if ($this->pdo()->inTransaction()) {
            throw new AiDbaseMisuse('дверь selectUnplanned не работает внутри открытой транзакции');
        }

        $this->beginTransaction();

        try {
            $this->statement('SET TRANSACTION READ ONLY', []);

            // Снимок «до». План не видит, что функция читает у себя внутри, поэтому таблицы
            // сверяются по факту: счётчики обращений этой транзакции знают, что тронуто.
            $before = $this->touched();

            $st = $this->statement($sql, []);

            $this->demandDeclaredOnly($before);
        } catch (\Throwable $e) {
            $this->rollBack();

            throw $e;
        }

        $this->rollBack();

        return $st;
    }

    private function demandNoForbidden(string $sql): void
    {
        foreach (self::FORBIDDEN as $name) {
            if (preg_match('/(?<![\w$])' . preg_quote($name, '/') . '\s*\(/i', $sql) !== 1) {
                continue;
            }

            throw new AiDbaseMisuse(
                "дверь не принимает {$name}(): функция читает файлы, чужие таблицы или исполняет SQL "
                . 'из строки, а план этого не показывает'
            );
        }
    }

    // Что тронуто в этой транзакции: «схема.таблица» => число обращений. Пустая карта означает,
    // что счётчики выключены, — тогда сверка по факту невозможна, и это отказ, а не «чисто».
    private function touched(): array
    {
        $sql = "SELECT schemaname || '.' || relname AS name,"
            . ' coalesce(seq_scan, 0) + coalesce(idx_scan, 0) + coalesce(n_tup_ins, 0)'
            . ' + coalesce(n_tup_upd, 0) + coalesce(n_tup_del, 0) + coalesce(n_tup_hot_upd, 0) AS hits'
            . ' FROM pg_stat_xact_user_tables';

        $touched = [];

        foreach ($this->statement($sql, [])->fetchAll() as $row) {
            $touched[$row['name']] = (int) $row['hits'];
        }

        return $touched;
    }

    private function demandDeclaredOnly(array $before): void
    {
        $after = $this->touched();

        if ($after === []) {
            throw new AiDbaseMisuse('сверка по факту невозможна: pg_stat_xact_user_tables пуст, счётчики выключены');
        }

        $allowed = [];

        foreach ($this->access->tables() as [$schema, $name]) {
            $allowed["{$schema}.{$name}"] = true;
        }

        foreach ($after as $table => $hits) {
            if (($before[$table] ?? 0) === $hits || isset($allowed[$table])) {
                continue;
            }

            throw new AiDbaseDenied(explode('.', $table, 2), $this->access->tables());
        }
    }

    private function tablesOf(string $sql, array $params = []): array
    {
        $json = $this->statement(self::EXPLAIN_PREFIX . $sql, $params)->fetchColumn();

        if (!is_string($json)) {
            throw new AiDbaseFailed($sql, 'движок не вернул план');
        }

        try {
            $plan = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AiDbaseFailed($sql, 'план не разбирается: ' . $e->getMessage());
        }

        if (!is_array($plan)) {
            throw new AiDbaseFailed($sql, 'план не является структурой');
        }

        $nodes = [];
        $this->collect($plan, $nodes);

        $seen   = [];
        $tables = [];

        foreach ($nodes as [$schema, $name]) {
            if ($schema === null) {
                throw new AiDbaseFailed($sql, "план не назвал схему таблицы {$name}");
            }

            $key = json_encode([$schema, $name]);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $tables[]   = [$schema, $name];
        }

        return $tables;
    }

    private function statement(string $sql, array $params): \PDOStatement
    {
        try {
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            throw new AiDbaseFailed($sql, $e->getMessage());
        }

        return $stmt;
    }

    private function collect(array $node, array &$found): void
    {
        $name   = $node['Relation Name'] ?? null;
        $schema = $node['Schema']        ?? null;

        if (is_string($name)) {
            $found[] = [is_string($schema) ? $schema : null, $name];
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                $this->collect($value, $found);
            }
        }
    }

    private function demandPostgres(): void
    {
        $engine = explode(':', $this->dsn, 2)[0];

        if ($engine !== 'pgsql') {
            throw new AiDbaseUnsupported($engine);
        }
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->demandPostgres();

            try {
                $this->pdo = new PDO($this->dsn, $this->user, $this->password, [
                    PDO::ATTR_ERRMODE             => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES    => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                throw new AiDbaseUnreachable($this->dsn, $e->getMessage());
            }
        }

        return $this->pdo;
    }
}
