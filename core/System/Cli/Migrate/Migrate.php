<?php

namespace Maws\System\Cli\Migrate;

use Maws\System\Contract\Effect;
use Maws\System\Contract\Purpose;
use Maws\System\Contract\Throws;
use Maws\System\Migrations\Exception\MigrationBroken;
use Maws\System\Migrations\Migrator;

class Migrate
{
    /**
     * Применение — отдельный шаг, а не побочное действие make migration: файл можно
     * написать и переписать сколько угодно, а база меняется только здесь.
     *
     * Имя необязательно: с ним применяется очередь до него включительно — парная
     * команда к rollback <миграция>.
     */
    #[Purpose('Применяет ожидающие миграции по порядку — все или до названной включительно.')]
    #[Throws(MigrationBroken::class, 'up() пуст или оператор не строка, имени нет в каталоге, очередь не в порядке, SQL не выполнен')]
    #[Effect(Effect::DB_WRITE)]
    public static function run(): void
    {
        global $argv;

        $upto = $argv[3] ?? '';

        echo "=== Применение миграций ===\n\n";

        $applied = Migrator::apply($upto === '' ? null : $upto);

        if ($applied === []) {
            echo $upto === ''
                ? "Применять нечего: все миграции уже применены.\n"
                : "Применять нечего: {$upto} уже применена.\n";

            return;
        }

        foreach ($applied as $name) {
            echo "  {$name} — применена\n";
        }

        echo "\nГотово: " . count($applied) . ".\n";
    }
}
