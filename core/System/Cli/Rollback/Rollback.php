<?php

namespace Maws\System\Cli\Rollback;

use Maws\System\Cli\Support\Support;
use Maws\System\Contract\Effect;
use Maws\System\Contract\Purpose;
use Maws\System\Contract\Throws;
use Maws\System\Migrations\Exception\MigrationBroken;
use Maws\System\Migrations\Migrator;

class Rollback
{
    /**
     * Имя миграции спрашивается явно. Откатывать «последнюю» молча — значит менять
     * базу догадкой: порядок откатов не обязан совпадать с порядком применения.
     *
     * Данные не сверяются — их состояние знает только down(). Поэтому если созданные
     * миграцией таблицы уже чем-то наполнены, команда спрашивает до того, как они исчезнут.
     */
    #[Purpose('Откатывает названную миграцию: выполняет down() и сверяет структуру со снимком.')]
    #[Throws(MigrationBroken::class, 'миграция не применена, база разошлась со снимком или down() вернул не то')]
    #[Effect(Effect::DB_WRITE)]
    public static function run(): void
    {
        global $argv;

        Migrator::ensure();

        $name = $argv[3] ?? '';

        if ($name === '') {
            self::hint();
            exit(1);
        }

        $risky = Migrator::dataAtRisk($name);

        if ($risky !== []) {
            $list = implode(', ', $risky);

            if (!Support::confirm("Таблицы {$list} содержат данные. Всё равно удалить таблицы?")) {
                echo "Откат отменён: данные остались на месте.\n";
                exit(1);
            }
        }

        Migrator::rollback($name);

        echo "Миграция {$name} откачена: выполнены операторы down().\n";
    }

    private static function hint(): void
    {
        echo "Использование: maws make rollback <миграция>\n";

        $applied = Migrator::applied();

        if ($applied === []) {
            echo "\nПрименённых миграций нет — откатывать нечего.\n";
            return;
        }

        echo "\nПрименены:\n";

        foreach ($applied as $name) {
            echo "  {$name}\n";
        }
    }
}
