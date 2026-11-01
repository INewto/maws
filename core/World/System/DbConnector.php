<?php

namespace Maws\World\System;

use Maws\Man\Head\Head;
use Maws\System\Cli\Build\Build;
use Maws\System\Cli\Doctor\Doctor;
use Maws\System\Cli\MakeArea\MakeArea;
use Maws\System\Cli\MakeDirection\MakeDirection;
use Maws\System\Cli\MakePlace\MakePlace;
use Maws\System\Cli\MakeTool\MakeTool;
use Maws\System\Cli\Support\Support;
use Maws\System\Crabic\Crabic;
use Maws\System\Migrations\Migrator;
use Maws\System\Test\Scaffold;
use Maws\World\System\Exception\DbConnectDenied;
use Maws\World\System\Exception\DbaseUnreachable;
use Maws\World\System\Exception\DbaseUnsupported;
use Maws\World\World;
use PDO;
use PDOException;

class DbConnector
{
    private static ?PDO $pdo = null;

    /**
     * Двери, которым открыта база. Список поимённый: мир, голова, база и те, кто
     * создаёт мир из командной строки. Строитель прогона в списке потому, что он
     * собирает те же места и из той же таблицы — иначе контракт места не запустить.
     * Журнал миграций в списке потому, что он работает со схемой, а не с таблицами:
     * стража таблиц на CREATE и DROP нечего сверять — этих таблиц ещё нет в правах.
     * Доктор в списке потому, что проверять он должен ту же дверь, в которую ходит сайт:
     * соединение, собранное вторым способом, отвечало бы за другое.
     * Крабик в списке потому, что он ядро: он ведёт свои таблицы — склад отпечатков и журнал
     * отказов, — и ограничение прав, придуманное для app/**, к нему не относится.
     */
    private const OPEN_TO = [
        Head::class,
        World::class,
        Scaffold::class,
        Dbase::class,
        Crabic::class,
        MakePlace::class,
        MakeDirection::class,
        MakeArea::class,
        MakeTool::class,
        Support::class,
        Migrator::class,
        Build::class,
        Doctor::class,
    ];

    public static function pdo()
    {
        $trace  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? [];

        if (!self::openTo($caller['class'] ?? null)) {
            throw new DbConnectDenied();
        }

        if (self::$pdo === null) {
            self::$pdo = self::connect();
            return self::$pdo;
        }
        return self::$pdo;
    }

    // закрывает общее соединение: прав не выдаёт, нужен прогону, чтобы случаи не делили транзакцию
    public static function reset(): void
    {
        self::$pdo = null;
    }

    private static function openTo(?string $class): bool
    {
        return $class !== null && in_array($class, self::OPEN_TO, true);
    }

    private static function connect(): PDO
    {
        self::demandPostgres();

        try {
            $pdo = new PDO(DBASE_DSN, DBASE_USER, DBASE_PASSWORD, [
                PDO::ATTR_ERRMODE             => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES    => false,
                PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new DbaseUnreachable(DBASE_DSN, $e->getMessage());
        }

        return $pdo;
    }

    // MySQL и MariaDB не строят план с именами таблиц — страж молча пропускал бы всё
    private static function demandPostgres(): void
    {
        $engine = explode(':', DBASE_DSN, 2)[0];

        if ($engine !== 'pgsql') {
            throw new DbaseUnsupported($engine);
        }
    }
}
