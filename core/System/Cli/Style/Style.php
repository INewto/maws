<?php

namespace Maws\System\Cli\Style;

class Style
{
    public static function run(): void
    {
        echo "=== Стиль кода (PSR-12) ===\n\n";

        $root    = dirname(__DIR__, 4);
        $fixer   = $root . '/.tools/php-cs-fixer.phar';
        $config  = $root . '/.php-cs-fixer.php';

        if (!is_file($fixer)) {
            echo "PHP-CS-Fixer не найден: {$fixer}\n"
                . "Каталог .tools создаётся вручную. Версия, под которую проверен стиль проекта, — 3.95.27:\n"
                . "  mkdir .tools\n"
                . "  curl -L https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/releases/download/v3.95.27/php-cs-fixer.phar"
                . " -o .tools/php-cs-fixer.phar\n"
                . "Последняя версия: https://cs.symfony.com/download/php-cs-fixer-v3.phar\n";

            return;
        }

        if (!is_file($config)) {
            echo "Ошибка: не найден конфиг PHP-CS-Fixer: {$config}\n";
            return;
        }

        $cmd = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg($fixer)
            . ' fix'
            . ' --config=' . escapeshellarg($config)
            . ' --verbose'
            . ' 2>&1';

        passthru($cmd, $exitCode);

        if ($exitCode === 0) {
            echo "\nPHP-CS-Fixer завершён успешно.\n";
        } else {
            echo "\nPHP-CS-Fixer завершился с кодом {$exitCode}.\n";
        }
    }
}
