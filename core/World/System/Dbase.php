<?php

namespace Maws\World\System;

use JsonException;
use Maws\System\Contract\{Changes, Effect, Fails, Fixture, Gives, Param, Purpose, Returns, Subject, Throws, Token};
use Maws\World\System\DbConnector;
use Maws\World\System\Exception\DbaseDenied;
use Maws\World\System\Exception\DbaseFailed;
use Maws\World\System\Exception\DbaseMisuse;
use Maws\World\System\Exception\DbasePlanless;
use Maws\World\System\Exception\DbaseUnreachable;
use Maws\World\System\Exception\DbaseUnsupported;
use PDO;
use PDOException;

#[Subject(Dbase::class, 'Scaffold::dbase', ['dbase.json'])]
#[Fixture(files: ['dbase.json' => '{"tables":["public.test_table","public.scenes"]}'])]
#[Purpose('База под правами инструмента: сверяет таблицы из плана запроса с dbase.json и отказывает там, где плана нет.')]
final class Dbase
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

    private function pdo(): PDO
    {
        return DbConnector::pdo();
    }

    #[Purpose('Собирает обёртку над базой с правами инструмента.')]
    #[Param('rights', 'DbaseRights', 'права инструмента: какие таблицы ему разрешены')]
    #[Effect(Effect::NONE)]
    public function __construct(private DbaseRights $rights)
    {
    }

    #[Purpose('Читает все строки по SQL, предварительно сверив таблицы запроса с правами инструмента.')]
    #[Param('sql', 'string', 'SQL-запрос; таблицы из его плана сверяются с dbase.json')]
    #[Param('params', 'array', 'значения для подстановки; по умолчанию пусто')]
    #[Returns('array', 'все выбранные строки; пустой массив, если ничего не найдено')]
    #[Throws(DbasePlanless::class, 'план не назвал ни одной таблицы — сверять стражу нечего')]
    #[Throws(DbaseMisuse::class, 'запросу плана не бывает вовсе (TRUNCATE, DDL, COPY, SET) — такие идут дверью')]
    #[Throws(DbaseDenied::class, 'план запроса задел таблицу, не разрешённую инструменту')]
    #[Throws(DbaseFailed::class, 'сервер не выполнил запрос или не вернул план EXPLAIN')]
    #[Throws(DbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(DbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_READ)]
    #[Gives(['SELECT * FROM public.test_table LIMIT 0'], [])]
    #[Fails(['SELECT * FROM public.areas'], DbaseDenied::class)]
    #[Fails(['SELECT 1'], DbasePlanless::class)]
    #[Fails(['CREATE TABLE public.probe_x (id int)'], DbaseMisuse::class)]
    #[Changes(['SELECT * FROM public.test_table LIMIT 0'], [['inTransaction', [], false]])]
    public function all(string $sql, array $params = []): array
    {
        return $this->guarded($sql, $params, fn (): array => $this->statement($sql, $params)->fetchAll());
    }

    #[Purpose('Читает одну строку по SQL, предварительно сверив таблицы запроса с правами инструмента.')]
    #[Param('sql', 'string', 'SQL-запрос; таблицы из его плана сверяются с dbase.json')]
    #[Param('params', 'array', 'значения для подстановки; по умолчанию пусто')]
    #[Returns('array|null', 'первая строка или null, если строк нет')]
    #[Throws(DbasePlanless::class, 'план не назвал ни одной таблицы — сверять стражу нечего')]
    #[Throws(DbaseMisuse::class, 'запросу плана не бывает вовсе (TRUNCATE, DDL, COPY, SET) — такие идут дверью')]
    #[Throws(DbaseDenied::class, 'план запроса задел таблицу, не разрешённую инструменту')]
    #[Throws(DbaseFailed::class, 'сервер не выполнил запрос или не вернул план EXPLAIN')]
    #[Throws(DbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(DbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_READ)]
    #[Gives(['SELECT * FROM public.test_table LIMIT 0'], null)]
    #[Fails(['SELECT * FROM public.areas'], DbaseDenied::class)]
    #[Fails(['SELECT 1'], DbasePlanless::class)]
    #[Fails(['TRUNCATE public.test_table'], DbaseMisuse::class)]
    #[Changes(['SELECT * FROM public.test_table LIMIT 0'], [['inTransaction', [], false]])]
    public function one(string $sql, array $params = []): ?array
    {
        return $this->guarded($sql, $params, function () use ($sql, $params): ?array {
            $row = $this->statement($sql, $params)->fetch();

            return $row === false ? null : $row;
        });
    }

    #[Purpose('Выполняет запрос и отдаёт число затронутых строк, сверив таблицы с правами инструмента.')]
    #[Param('sql', 'string', 'SQL-запрос; таблицы из его плана сверяются с dbase.json')]
    #[Param('params', 'array', 'значения для подстановки; по умолчанию пусто')]
    #[Returns('int', 'число затронутых строк')]
    #[Throws(DbasePlanless::class, 'план не назвал ни одной таблицы — сверять стражу нечего')]
    #[Throws(DbaseMisuse::class, 'запросу плана не бывает вовсе (TRUNCATE, DDL, COPY, SET) — такие идут дверью')]
    #[Throws(DbaseDenied::class, 'план запроса задел таблицу, не разрешённую инструменту')]
    #[Throws(DbaseFailed::class, 'сервер не выполнил запрос или не вернул план EXPLAIN')]
    #[Throws(DbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(DbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_WRITE)]
    #[Gives(['DELETE FROM public.test_table WHERE false'], 0)]
    #[Fails(['DELETE FROM public.areas'], DbaseDenied::class)]
    #[Fails(['TRUNCATE public.test_table'], DbaseMisuse::class)]
    #[Changes(['DELETE FROM public.test_table WHERE false'], [['inTransaction', [], false]])]
    public function run(string $sql, array $params = []): int
    {
        return $this->guarded($sql, $params, fn (): int => $this->statement($sql, $params)->rowCount());
    }

    #[Purpose('Отдаёт значение последней последовательности, тронутой в этом соединении. На PostgreSQL это lastval(), а не «id той строки, которую вставил ты»: у таблицы без последовательности значение остаётся от прошлой вставки, поэтому надёжный способ узнать id — INSERT … RETURNING id.')]
    #[Returns('string|null', 'значение или null, если в соединении ещё не было вставки в таблицу с последовательностью')]
    #[Throws(DbaseFailed::class, 'сервер не отдал значение')]
    #[Throws(DbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(DbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_CONNECT)]
    #[Gives([], null)]
    public function lastInsertId(): ?string
    {
        try {
            $id = $this->pdo()->lastInsertId();
        } catch (PDOException $e) {
            // PostgreSQL отвечает 55000, пока в соединении не тронута ни одна последовательность:
            // это «значения ещё нет», а не сбой сервера — молчание здесь честнее исключения.
            if ($e->getCode() === '55000') {
                return null;
            }

            throw new DbaseFailed('lastInsertId', $e->getMessage());
        }

        return $id === false ? null : $id;
    }

    #[Purpose('Выполняет select, у которого плана с таблицами нет, — в транзакции только для чтения. План не видит, что функция читает внутри себя, поэтому таблицы сверяются ещё и по факту: тронул необъявленную — результата не будет.')]
    #[Param('sql', 'string', 'один select; таблиц он трогать не должен')]
    #[Returns('PDOStatement', 'выполненное выражение; строки прочитаны на клиент, читать можно после возврата')]
    #[Throws(DbaseMisuse::class, 'запрос не того вида, содержит «;», трогает таблицы или зовёт запрещённую функцию')]
    #[Throws(DbaseDenied::class, 'запрос тронул таблицу, не разрешённую инструменту; видно только по факту, план её не назвал')]
    #[Throws(DbaseFailed::class, 'сервер не выполнил запрос или не вернул план EXPLAIN')]
    #[Throws(DbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(DbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_READ)]
    #[Effect(Effect::DB_UNGUARDED)]
    #[Gives(['SELECT 1'], Token::STATEMENT)]
    #[Gives(["SELECT to_char(now(), 'DD.MM.YY')"], Token::STATEMENT)]
    #[Changes(['SELECT 1'], [['inTransaction', [], false]])]
    #[Fails(['DELETE FROM public.test_table'], DbaseMisuse::class)]
    #[Fails(['SELECT 1; SELECT 2'], DbaseMisuse::class)]
    #[Fails(['SELECT * FROM public.test_table'], DbaseMisuse::class)]
    #[Fails(["SELECT query_to_xml('SELECT 1 FROM public.areas', true, false, '')"], DbaseMisuse::class)]
    #[Fails(["SELECT pg_read_file('/etc/passwd')"], DbaseMisuse::class)]
    public function selectUnplanned(string $sql): \PDOStatement
    {
        $this->demandPlainSelect($sql);
        $this->demandNoTables($sql);
        $this->demandNoForbidden($sql);

        return $this->inReadOnly($sql);
    }

    #[Purpose('Открывает транзакцию.')]
    #[Throws(DbaseMisuse::class, 'транзакция уже открыта')]
    #[Throws(DbaseFailed::class, 'сервер не начал транзакцию')]
    #[Throws(DbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(DbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_WRITE)]
    #[Changes([], [['inTransaction', [], true]])]
    #[Fails([], DbaseMisuse::class, before: [['beginTransaction', []]])]
    public function beginTransaction(): void
    {
        if ($this->pdo()->inTransaction()) {
            throw new DbaseMisuse('вложенный begin — транзакция уже открыта');
        }

        try {
            $this->pdo()->beginTransaction();
        } catch (PDOException $e) {
            throw new DbaseFailed('BEGIN', $e->getMessage());
        }
    }

    #[Purpose('Фиксирует открытую транзакцию.')]
    #[Throws(DbaseMisuse::class, 'commit без открытой транзакции')]
    #[Throws(DbaseFailed::class, 'сервер не зафиксировал транзакцию')]
    #[Throws(DbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(DbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_WRITE)]
    #[Changes([], [['inTransaction', [], false]], before: [['beginTransaction', []]])]
    #[Fails([], DbaseMisuse::class)]
    public function commit(): void
    {
        if (!$this->pdo()->inTransaction()) {
            throw new DbaseMisuse('commit без открытой транзакции');
        }

        try {
            $this->pdo()->commit();
        } catch (PDOException $e) {
            throw new DbaseFailed('COMMIT', $e->getMessage());
        }
    }

    #[Purpose('Откатывает открытую транзакцию.')]
    #[Throws(DbaseMisuse::class, 'rollBack без открытой транзакции')]
    #[Throws(DbaseFailed::class, 'сервер не откатил транзакцию')]
    #[Throws(DbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(DbaseUnsupported::class, 'движок в DSN — не postgres')]
    #[Effect(Effect::DB_WRITE)]
    #[Changes([], [['inTransaction', [], false]], before: [['beginTransaction', []]])]
    #[Fails([], DbaseMisuse::class)]
    public function rollBack(): void
    {
        if (!$this->pdo()->inTransaction()) {
            throw new DbaseMisuse('rollBack без открытой транзакции');
        }

        try {
            $this->pdo()->rollBack();
        } catch (PDOException $e) {
            throw new DbaseFailed('ROLLBACK', $e->getMessage());
        }
    }

    #[Purpose('Сообщает, открыта ли транзакция.')]
    #[Returns('bool', 'true — транзакция открыта')]
    #[Throws(DbaseUnreachable::class, 'соединение с базой не установлено')]
    #[Throws(DbaseUnsupported::class, 'движок в DSN — не postgres')]
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
            throw new DbasePlanless($sql);
        }

        $allowed = $this->rights->tables();

        foreach ($tables as $table) {
            if (!in_array($table, $allowed, true)) {
                throw new DbaseDenied($table, $allowed);
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

        throw new DbaseMisuse(
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
            throw new DbaseMisuse("дверь принимает одну инструкцию, а в запросе есть «;»: {$sql}");
        }

        if ($this->head($sql) !== 'select') {
            throw new DbaseMisuse("дверь принимает только select, получено: {$sql}");
        }
    }

    private function demandNoTables(string $sql): void
    {
        $tables = $this->tablesOf($sql);

        if ($tables !== []) {
            $shown = implode(', ', array_map(static fn (array $t): string => "{$t[0]}.{$t[1]}", $tables));

            throw new DbaseMisuse("дверь принимает только запросы без таблиц, а этот трогает: {$shown}");
        }
    }

    private function inReadOnly(string $sql): \PDOStatement
    {
        if ($this->pdo()->inTransaction()) {
            throw new DbaseMisuse('дверь selectUnplanned не работает внутри открытой транзакции');
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

            throw new DbaseMisuse(
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
            throw new DbaseMisuse('сверка по факту невозможна: pg_stat_xact_user_tables пуст, счётчики выключены');
        }

        $allowed = [];

        foreach ($this->rights->tables() as [$schema, $name]) {
            $allowed["{$schema}.{$name}"] = true;
        }

        foreach ($after as $table => $hits) {
            if (($before[$table] ?? 0) === $hits || isset($allowed[$table])) {
                continue;
            }

            throw new DbaseDenied(explode('.', $table, 2), $this->rights->tables());
        }
    }

    private function tablesOf(string $sql, array $params = []): array
    {
        $json = $this->statement(self::EXPLAIN_PREFIX . $sql, $params)->fetchColumn();

        if (!is_string($json)) {
            throw new DbaseFailed($sql, 'движок не вернул план');
        }

        try {
            $plan = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DbaseFailed($sql, 'план не разбирается: ' . $e->getMessage());
        }

        if (!is_array($plan)) {
            throw new DbaseFailed($sql, 'план не является структурой');
        }

        $nodes = [];
        $this->collect($plan, $nodes);

        $seen   = [];
        $tables = [];

        foreach ($nodes as [$schema, $name]) {
            if ($schema === null) {
                throw new DbaseFailed($sql, "план не назвал схему таблицы {$name}");
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
            $stmt = DbConnector::pdo()->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            throw new DbaseFailed($sql, $e->getMessage());
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
}
