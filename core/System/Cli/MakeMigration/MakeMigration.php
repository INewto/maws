<?php

namespace Maws\System\Cli\MakeMigration;

use Maws\System\Cli\Support\Support;
use Maws\System\Contract\Effect;
use Maws\System\Contract\Purpose;
use Maws\System\Contract\Throws;
use Maws\System\Migrations\Exception\MigrationBroken;
use Maws\System\Migrations\Migrator;

class MakeMigration
{
    /**
     * Заводит файл миграции и строку журнала. Строка появляется сразу, с пустым
     * состоянием: миграция уже существует, но ещё не применена — это разные вещи,
     * и путать их нельзя, иначе «применить» пришлось бы угадывать.
     */
    #[Purpose('Создаёт файл миграции в db/migrations и заводит под неё строку журнала.')]
    #[Throws(MigrationBroken::class, 'имя занято или такой файл уже есть')]
    #[Effect(Effect::FS_WRITE)]
    public static function run(): void
    {
        global $argv;

        $base = $argv[3] ?? '';

        if ($base === '') {
            echo "Использование: maws make migration <название>\n";
            echo "Название пишется строчной латиницей: add_orders, seed_scenes.\n";
            exit(1);
        }

        if (!preg_match('/^[a-z][a-z0-9_]*$/', $base)) {
            echo "Ошибка: название пишется строчной латиницей, цифрами и подчёркиванием.\n";
            exit(1);
        }

        Migrator::ensure();

        $name = Migrator::next($base);
        $path = Migrator::path($name);

        if (is_file($path)) {
            echo "Ошибка: файл {$name}.php уже есть в db/migrations.\n";
            exit(1);
        }

        $content = Support::render(
            Support::template('MakeMigration', 'migration.tpl.php'),
            ['name' => $name, 'base' => $base]
        );

        Support::put($path, $content);
        Migrator::register($name);

        echo "Создана миграция {$name}: db/migrations/{$name}.php\n";
        echo "В журнале migrations появилась строка: состояние пустое, снимков нет.\n";
        echo "Наполните up() и примените: maws make migrate\n";
    }
}
