<?php

namespace Maws\System\Migrations;

use Maws\System\Contract\Effect;
use Maws\System\Contract\Purpose;
use Maws\System\Contract\Returns;
use Maws\System\Contract\Throws;
use Maws\System\Migrations\Exception\MigrationBroken;
use Maws\World\System\DbConnector;

/**
 * Журнал миграций и снимки схемы.
 *
 * Журнал — таблица migrations: имя файла, когда применилась, когда откатилась, состояние
 * и два снимка структуры. Откат выполняет операторы down(), написанные автором миграции,
 * и сверяет результат со снимком «до»: не сошлось — транзакция отменяет откат.
 *
 * Почему down(), а не откат по снимку: отследить изменения миграции полностью нельзя.
 * Снимок описывает структуру, но не описывает данные, последовательности, представления
 * и триггеры; UPDATE, не изменивший число строк, в нём не виден вовсе. Механизм, который
 * умеет откатывать только часть изменений, заставляет автора подстраивать миграцию под
 * себя. Поэтому откат делегирован автору, а снимок остался проверкой результата.
 */
final class Migrator
{
    /** Таблица журнала. Она не попадает в снимки: её рост — не изменение схемы сайта. */
    private const LEDGER = 'migrations';

    /** Имя файла миграции: четыре цифры, подчёркивание, строчное имя. */
    private const PATTERN = '/^(\d{4})_([a-z][a-z0-9_]*)\.php$/';

    private const COLUMNS_SQL = 'SELECT c.relname AS "table", a.attname AS "name",'
        . ' format_type(a.atttypid, a.atttypmod) AS "type", a.attnotnull::int AS "notnull",'
        . ' pg_get_expr(d.adbin, d.adrelid) AS "default", nullif(a.attidentity, \'\') AS "identity"'
        . ' FROM pg_attribute a'
        . ' JOIN pg_class c ON c.oid = a.attrelid'
        . ' JOIN pg_namespace n ON n.oid = c.relnamespace'
        . ' LEFT JOIN pg_attrdef d ON d.adrelid = c.oid AND d.adnum = a.attnum'
        . ' WHERE n.nspname = \'public\' AND c.relkind = \'r\' AND a.attnum > 0 AND NOT a.attisdropped'
        . ' AND c.relname <> \'' . self::LEDGER . '\''
        . ' ORDER BY c.relname, a.attnum';

    private const CONSTRAINTS_SQL = 'SELECT c.relname AS "table", con.conname AS "name",'
        . ' con.contype AS "type", pg_get_constraintdef(con.oid) AS "def"'
        . ' FROM pg_constraint con'
        . ' JOIN pg_class c ON c.oid = con.conrelid'
        . ' JOIN pg_namespace n ON n.oid = c.relnamespace'
        . ' WHERE n.nspname = \'public\' AND c.relkind = \'r\' AND c.relname <> \'' . self::LEDGER . '\''
        . ' ORDER BY c.relname, con.conname';

    private const INDEXES_SQL = 'SELECT c.relname AS "table", ic.relname AS "name",'
        . ' pg_get_indexdef(i.indexrelid) AS "def"'
        . ' FROM pg_index i'
        . ' JOIN pg_class c ON c.oid = i.indrelid'
        . ' JOIN pg_class ic ON ic.oid = i.indexrelid'
        . ' JOIN pg_namespace n ON n.oid = c.relnamespace'
        . ' WHERE n.nspname = \'public\' AND c.relkind = \'r\' AND c.relname <> \'' . self::LEDGER . '\''
        . ' AND NOT EXISTS (SELECT 1 FROM pg_constraint con WHERE con.conindid = i.indexrelid)'
        . ' ORDER BY c.relname, ic.relname';

    /**
     * Заводит журнал. Вызывается каждой командой: журнала может не быть ни в свежей базе,
     * ни в той, что жила до миграций, — и это не ошибка, а начало.
     */
    #[Purpose('Создаёт таблицу журнала, если её ещё нет.')]
    #[Effect(Effect::DB_WRITE)]
    public static function ensure(): void
    {
        DbConnector::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::LEDGER . ' ('
            . ' id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,'
            . ' migration varchar(190) NOT NULL,'
            . ' up_date timestamptz,'
            . ' down_date timestamptz,'
            . ' status varchar(4),'
            . ' scheme_before jsonb,'
            . ' scheme_after jsonb,'
            . ' CONSTRAINT uq_migrations_migration UNIQUE (migration),'
            . ' CONSTRAINT ck_migrations_status CHECK (status IN (\'up\', \'down\'))'
            . ')'
        );
    }

    /** Имена миграций по порядку — это имена файлов без расширения. */
    #[Purpose('Перечисляет файлы миграций в db/migrations по порядку номеров.')]
    #[Returns('array', 'список имён без расширения')]
    #[Effect(Effect::FS_READ)]
    public static function files(): array
    {
        $names = [];

        foreach (glob(MIGRATIONS_DIR . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            if (preg_match(self::PATTERN, $name . '.php')) {
                $names[] = $name;
            }
        }

        sort($names);

        return $names;
    }

    #[Purpose('Возвращает путь к файлу миграции по её имени.')]
    #[Returns('string', 'полный путь к файлу')]
    #[Effect(Effect::FS_READ)]
    public static function path(string $name): string
    {
        return MIGRATIONS_DIR . '/' . $name . '.php';
    }

    /** Следующий номер — на единицу больше самого большого в каталоге. */
    #[Purpose('Строит имя новой миграции: следующий номер плюс основа.')]
    #[Returns('string', 'имя вида 0002_add_orders')]
    #[Effect(Effect::FS_READ)]
    public static function next(string $base): string
    {
        $max = 0;

        foreach (self::files() as $name) {
            if (preg_match('/^(\d{4})_/', $name, $match)) {
                $max = max($max, (int) $match[1]);
            }
        }

        return sprintf('%04d_%s', $max + 1, $base);
    }

    /** Строка журнала появляется вместе с файлом: миграция существует, но ещё не применена. */
    #[Purpose('Заводит строку журнала для новой миграции — состояние пустое, снимков нет.')]
    #[Throws(MigrationBroken::class, 'миграция с таким именем уже есть в журнале')]
    #[Effect(Effect::DB_WRITE)]
    public static function register(string $name): void
    {
        $stmt = DbConnector::pdo()->prepare(
            'INSERT INTO ' . self::LEDGER . ' (migration) VALUES (?) ON CONFLICT (migration) DO NOTHING'
        );
        $stmt->execute([$name]);

        if ($stmt->rowCount() === 0) {
            throw new MigrationBroken("миграция {$name} уже есть в журнале");
        }
    }

    /** Применённые миграции — те, чей снимок «после» стал текущим состоянием. */
    #[Purpose('Перечисляет применённые миграции по порядку.')]
    #[Returns('array', 'список имён со status = up')]
    #[Effect(Effect::DB_READ)]
    public static function applied(): array
    {
        $stmt = DbConnector::pdo()->prepare(
            'SELECT migration FROM ' . self::LEDGER . ' WHERE status = \'up\' ORDER BY migration'
        );
        $stmt->execute();

        return array_column($stmt->fetchAll(), 'migration');
    }

    /** Ожидающие — всё, что лежит в каталоге и не помечено применённым. */
    #[Purpose('Перечисляет миграции, которые ещё не применены.')]
    #[Returns('array', 'список имён по порядку')]
    #[Effect(Effect::DB_READ)]
    public static function waiting(): array
    {
        return array_values(array_diff(self::files(), self::applied()));
    }

    /**
     * Применяет ожидающие миграции по одной, в порядке номеров. Каждая — в своей
     * транзакции: упавшая миграция не оставляет за собой половину таблиц.
     *
     * С именем применяется очередь до него включительно. Перескочить предшественников
     * нельзя: они меняют ту же схему, и снимок «до» у следующей снят уже с их результата.
     */
    #[Purpose('Применяет ожидающие миграции по порядку — все или до названной включительно.')]
    #[Returns('array', 'имена применённых миграций; пусто — применять нечего')]
    #[Throws(MigrationBroken::class, 'up() пуст или оператор не строка, имени нет в каталоге, очередь не в порядке')]
    #[Effect(Effect::DB_WRITE)]
    public static function apply(?string $upto = null): array
    {
        self::ensure();

        $applied = [];

        foreach (self::queue($upto) as $name) {
            self::one($name);
            $applied[] = $name;
        }

        return $applied;
    }

    /**
     * Очередь к применению: ожидающие по порядку, обрезанные по имени включительно.
     *
     * Ожидающая миграция старше применённой означает, что журнал не в порядке: её снимок
     * «до» будет снят с уже изменённой схемы, и сверка при откате разойдётся. Поэтому
     * отказ, а не тихое дописывание в конец.
     */
    private static function queue(?string $upto): array
    {
        $waiting = self::waiting();
        $applied = self::applied();

        if ($upto !== null) {
            $index = array_search($upto, $waiting, true);

            if ($index === false && !in_array($upto, $applied, true)) {
                throw new MigrationBroken("миграции {$upto} нет в db/migrations");
            }

            if ($index === false) {
                return [];
            }

            $waiting = array_slice($waiting, 0, $index + 1);
        }

        if ($waiting === []) {
            return [];
        }

        $last = $applied === [] ? '' : (string) end($applied);

        foreach ($waiting as $name) {
            if ($last !== '' && strcmp($name, $last) < 0) {
                throw new MigrationBroken(
                    "очередь не в порядке: {$name} ждёт, а {$last} уже применена — "
                    . 'номер миграции должен быть больше применённых'
                );
            }
        }

        return $waiting;
    }

    /**
     * Откат: выполняются операторы down(), написанные автором, и результат сверяется
     * со снимком «до» — в той же транзакции. Не сошлось — база остаётся как была.
     * Данные не сверяются: их состояние знает только автор миграции.
     */
    #[Purpose('Откатывает применённую миграцию: выполняет down() и сверяет структуру со снимком «до».')]
    #[Throws(MigrationBroken::class, 'миграция не применена, база разошлась со снимком или down() вернул не то')]
    #[Effect(Effect::DB_WRITE)]
    public static function rollback(string $name): void
    {
        self::ensure();

        $row = self::row($name);

        if ($row === null) {
            throw new MigrationBroken("миграции {$name} нет в журнале");
        }

        if ($row['status'] !== 'up') {
            throw new MigrationBroken("миграция {$name} не применена — откатывать нечего");
        }

        $before  = self::decode($row['scheme_before']);
        $after   = self::decode($row['scheme_after']);
        $touched = self::touched($before, $after);

        if ($touched === []) {
            throw new MigrationBroken(
                "миграция {$name} не изменила ни структуру, ни число строк — откатывать нечего"
            );
        }

        $statements = self::statements($name, self::load($name)->down(), 'down()');

        if ($statements === []) {
            throw MigrationBroken::wrongDown($name, 'down() пуст, откат ничего не сделал');
        }

        self::demandMatches($name, $touched, $after, 'до отката');

        $pdo = DbConnector::pdo();
        $pdo->beginTransaction();

        try {
            self::execute($pdo, $name, 'down()', $statements);

            self::demandMatches($name, $touched, $before, 'down() не вернул базу к снимку', true);

            $stmt = $pdo->prepare(
                'UPDATE ' . self::LEDGER . ' SET status = \'down\', down_date = now() WHERE migration = ?'
            );
            $stmt->execute([$name]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Созданные миграцией таблицы, в которых сейчас есть строки. Откат данные не сверяет,
     * поэтому заметить потерю может только человек: команда спрашивает по этому списку
     * до того, как таблицы исчезнут.
     */
    #[Purpose('Перечисляет созданные миграцией таблицы, в которых есть данные.')]
    #[Returns('array', 'имена таблиц с непустым содержимым')]
    #[Effect(Effect::DB_READ)]
    public static function dataAtRisk(string $name): array
    {
        self::ensure();

        $row = self::row($name);

        if ($row === null || $row['status'] !== 'up') {
            return [];
        }

        $before  = self::decode($row['scheme_before']);
        $after   = self::decode($row['scheme_after']);
        $current = self::structure();
        $risky   = [];

        foreach (array_keys(array_diff_key($after['tables'], $before['tables'])) as $table) {
            if (isset($current[$table]) && $current[$table]['count'] > 0) {
                $risky[] = $table;
            }
        }

        return $risky;
    }

    /** Одна миграция: снимок до, операторы, снимок после, запись в журнал — всё в одной транзакции. */
    private static function one(string $name): void
    {
        $statements = self::statements($name, self::load($name)->up(), 'up()');

        if ($statements === []) {
            throw new MigrationBroken("миграция {$name}: up() не вернул ни одного оператора");
        }

        $pdo = DbConnector::pdo();
        $pdo->beginTransaction();

        try {
            $before = self::snapshot();

            self::execute($pdo, $name, 'up()', $statements);

            $after = self::snapshot();

            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::LEDGER
                . ' (migration, status, up_date, scheme_before, scheme_after) VALUES (?, \'up\', now(), ?, ?)'
                . ' ON CONFLICT (migration) DO UPDATE SET status = \'up\', up_date = now(),'
                . ' scheme_before = excluded.scheme_before, scheme_after = excluded.scheme_after'
            );
            $stmt->execute([
                $name,
                json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Таблицы, которых миграция коснулась: структура или число строк изменились. */
    private static function touched(array $before, array $after): array
    {
        $touched = [];

        foreach ($after['tables'] as $table => $new) {
            $old = $before['tables'][$table] ?? null;

            if ($old === null || !self::sameStructure($new, $old) || $new['count'] !== $old['count']) {
                $touched[] = $table;
            }
        }

        foreach ($before['tables'] as $table => $old) {
            if (!isset($after['tables'][$table])) {
                $touched[] = $table; // таблица удалена миграцией — её тоже надо вернуть
            }
        }

        return $touched;
    }

    /**
     * Сверка живых таблиц со снимком: колонки, ограничения, индексы. Данные не сверяются —
     * их состояние описывает down(), а не снимок.
     */
    private static function demandMatches(string $name, array $tables, array $snapshot, string $why, bool $afterDown = false): void
    {
        $current = self::structure();

        foreach ($tables as $table) {
            $want = $snapshot['tables'][$table] ?? null;
            $now  = $current[$table] ?? null;

            if ($want === null && $now === null) {
                continue;
            }

            $reason = match (true) {
                $want === null                    => "таблица {$table} осталась",
                $now === null                     => "таблицы {$table} нет",
                !self::sameStructure($now, $want) => "структура таблицы {$table} другая",
                default                           => null,
            };

            if ($reason === null) {
                continue;
            }

            throw $afterDown
                ? MigrationBroken::wrongDown($name, "{$why}: {$reason}")
                : MigrationBroken::mismatch($name, "{$why}: {$reason}");
        }
    }

    /** Операторы миграции: непустые строки. Пустой список проверяет вызывающий — у дверей разные правила. */
    private static function statements(string $name, array $statements, string $door): array
    {
        foreach ($statements as $sql) {
            if (!is_string($sql) || trim($sql) === '') {
                throw new MigrationBroken("миграция {$name}: в {$door} пустой или не строковый оператор");
            }
        }

        return $statements;
    }

    /**
     * Выполняет операторы по порядку. Отказ движка превращается в отказ миграции: автору нужно
     * знать, какой оператор не прошёл и что ответила база, а не читать трейс PDO. Переводы строк
     * в ответе сжаты — maws печатает отказ одной строкой.
     */
    private static function execute(\PDO $pdo, string $name, string $door, array $statements): void
    {
        $total = count($statements);

        foreach ($statements as $index => $sql) {
            try {
                $pdo->exec($sql);
            } catch (\PDOException $e) {
                $reason = preg_replace('/\s+/', ' ', (string) $e->getMessage());

                throw new MigrationBroken(
                    "миграция {$name}: оператор " . ($index + 1) . " из {$total} не выполнен в {$door} ({$reason})"
                );
            }
        }
    }

    private static function load(string $name): Migration
    {
        $file = self::path($name);

        if (!is_file($file)) {
            throw new MigrationBroken("файла миграции {$name} нет: {$file}");
        }

        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new MigrationBroken("файл миграции {$name} не вернул наследника Migration");
        }

        return $migration;
    }

    private static function row(string $name): ?array
    {
        $stmt = DbConnector::pdo()->prepare('SELECT * FROM ' . self::LEDGER . ' WHERE migration = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    private static function decode(?string $json): array
    {
        $data = json_decode((string) $json, true);

        return is_array($data) && isset($data['tables']) ? $data : ['tables' => []];
    }

    /** Снимок схемы: структура всех таблиц public. Данные в снимок не входят — их знает только down(). */
    private static function snapshot(): array
    {
        return ['tables' => self::structure()];
    }

    /** Структура всех таблиц public плюс число строк в каждой: число ловит запись в чужую таблицу. */
    private static function structure(): array
    {
        $pdo = DbConnector::pdo();
        $tables = [];

        foreach ($pdo->query(self::COLUMNS_SQL)->fetchAll() as $row) {
            $table = $row['table'];

            $tables[$table]['name'] = $table;
            $tables[$table]['columns'][] = [
                'name'     => $row['name'],
                'type'     => $row['type'],
                'notnull'  => (int) $row['notnull'] === 1,
                'default'  => $row['default'],
                'identity' => $row['identity'],
            ];
        }

        foreach ($pdo->query(self::CONSTRAINTS_SQL)->fetchAll() as $row) {
            $tables[$row['table']]['constraints'][] = [
                'name' => $row['name'],
                'type' => $row['type'],
                'def'  => $row['def'],
            ];
        }

        foreach ($pdo->query(self::INDEXES_SQL)->fetchAll() as $row) {
            $tables[$row['table']]['indexes'][] = [
                'name' => $row['name'],
                'def'  => $row['def'],
            ];
        }

        foreach ($tables as $table => $entry) {
            $tables[$table]['constraints'] = $entry['constraints'] ?? [];
            $tables[$table]['indexes']     = $entry['indexes'] ?? [];
            $tables[$table]['count']       = self::count($table);
        }

        return $tables;
    }

    private static function count(string $table): int
    {
        return (int) DbConnector::pdo()->query('SELECT count(*) FROM ' . self::quoted($table))->fetchColumn();
    }

    /** Сверяются только колонки, ограничения и индексы: число строк — не структура. */
    private static function sameStructure(array $now, array $then): bool
    {
        foreach (['columns', 'constraints', 'indexes'] as $part) {
            if (($now[$part] ?? []) != ($then[$part] ?? [])) {
                return false;
            }
        }

        return true;
    }

    private static function quoted(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}
