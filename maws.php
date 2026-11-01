<?php

require __DIR__ . '/app/config.php';
require __DIR__ . '/vendor/autoload.php';

class Maws
{
    public static function run(): void
    {
        global $argv;

        if (PHP_SAPI !== 'cli') {
            echo "MAWS: эта точка входа предназначена только для CLI.\n";
            exit(1);
        }

        $command = $argv[1] ?? 'list';

        switch ($command) {
            case 'make':
                self::dispatchMake($argv[2] ?? null);
                break;

            case 'test':
                self::call('RunTests\RunTests');
                break;

            case 'style':
                self::call('Style\Style');
                break;

            case 'doctor':
                self::call('Doctor\Doctor');
                break;

            case 'list':
                self::call('ListCommand\ListCommand');
                break;

            case 'about':
                self::call('About\About');
                break;

            default:
                echo "Неизвестная команда: {$command}\n";
                echo "Доступные команды: maws list\n";
                exit(1);
        }
    }

    private static function dispatchMake(?string $sub): void
    {
        if ($sub !== null && str_starts_with($sub, 'test:')) {
            self::call('MakeTest\MakeTest');
            return;
        }

        match ($sub) {
            'direction' => self::make('MakeDirection\MakeDirection'),
            'place'     => self::make('MakePlace\MakePlace'),
            'area'      => self::make('MakeArea\MakeArea'),
            'tool'      => self::make('MakeTool\MakeTool'),
            'agent'     => self::make('MakeAgent\MakeAgent'),
            'migration' => self::make('MakeMigration\MakeMigration'),
            'migrate'   => self::make('Migrate\Migrate'),
            'rollback'  => self::make('Rollback\Rollback'),
            'build'     => self::make('Build\Build'),
            default     => print "Использование: maws make {direction|place|area|tool|agent|migration|migrate|rollback|build|test:<файл>}\n",
        };
    }

    // Отказ команды — не авария, а объяснение: «нет областей», «направления нет в базе».
    // Без этой обёртки такое исключение улетало бы трейсом в кроне, а его ловит index.php,
    // которого в CLI нет.
    private static function make(string $class): void
    {
        $fqcn = 'Maws\System\Cli\\' . $class;

        try {
            $fqcn::run();
        } catch (\Maws\System\Exception\MawsMisuse $e) {
            echo "Ошибка: {$e->getMessage()}\n";
            exit(1);
        }
    }

    private static function call(string $class): void
    {
        $fqcn = 'Maws\System\Cli\\' . $class;
        $fqcn::run();
    }
}

Maws::run();