<?php

namespace Maws\System\Cli\MakeAgent;

use Maws\System\Cli\Support\Support;

class MakeAgent
{
    private const TYPES = ['single', 'multi', 'supervisor'];

    public static function run(): void
    {
        echo "=== Создание нового агента ===\n\n";

        $type = Support::menu('Выберите тип агента:', self::TYPES);

        if ($type === 'single') {
            self::createSingle();
            return;
        }

        if ($type === 'supervisor') {
            self::ensureSupervisor();
        }

        self::createAgent($type);
    }

    private static function createSingle(): void
    {
        $dir     = AI_DIR . '/Single';
        $created = !is_dir($dir);

        Support::ensureDir($dir);

        if (self::anyExists($dir, ['Agent.php', 'tools.json', 'instruction.prompt.php'])) {
            echo "Ошибка: агент single уже создан ({$dir}).\n";
            return;
        }

        try {
            self::fill($dir, 'Agent', 'single');
        } catch (\RuntimeException $e) {
            if ($created) {
                Support::removeDir($dir);
            }

            echo "Агент single не создан: {$e->getMessage()}\n";
            return;
        }

        echo "Готово.\n";
    }

    private static function createAgent(string $type): void
    {
        $name = Support::askName('имя агента');
        $base = self::baseDir($type);
        $dir  = $base . '/' . $name;

        if (is_dir($dir)) {
            echo "Ошибка: агент {$name} уже создан ({$dir}).\n";
            return;
        }

        Support::ensureDir($dir);
        echo "Создан каталог: Ai/" . basename($base) . "/{$name}/\n";

        try {
            self::fill($dir, $name, $type);
        } catch (\RuntimeException $e) {
            Support::removeDir($dir);

            echo "Агент {$name} не создан: {$e->getMessage()}\n";
            return;
        }

        echo "Готово.\n";
    }

    private static function fill(string $dir, string $name, string $type): void
    {
        self::createClass($dir, $name, $type);
        self::createTools($dir);
        self::createPrompt($dir);
    }

    private static function createClass(string $dir, string $name, string $type): void
    {
        $paths = self::agentPaths($type);

        $content = Support::render(
            Support::template('MakeAgent', 'agent.tpl.php'),
            [
                'Namespace'  => self::classNamespace($type, $name),
                'AgentName'  => $name,
                'ConfigPath' => $paths['config'],
                'VendorPath' => $paths['vendor'],
            ]
        );

        Support::put($dir . '/' . $name . '.php', $content);
        echo "Создан файл: {$name}.php\n";
    }

    private static function createTools(string $dir): void
    {
        Support::put($dir . '/tools.json', Support::render(Support::template('MakeAgent', 'tools.tpl.json')));

        echo "Создан файл: tools.json\n";
    }

    private static function createPrompt(string $dir): void
    {
        Support::put(
            $dir . '/instruction.prompt.php',
            Support::render(Support::template('MakeAgent', 'prompt.tpl.php'))
        );

        echo "Создан файл: instruction.prompt.php\n";
    }

    private static function ensureSupervisor(): void
    {
        $dir = AI_DIR . '/Supervisor';

        Support::ensureDir($dir);

        $paths = self::paths();

        $files = [
            'Supervisor.php' => Support::render(
                Support::template('MakeAgent', 'supervisor.tpl.php'),
                [
                    'Namespace'  => 'App\\Ai\\Supervisor',
                    'ConfigPath' => $paths['config'],
                    'VendorPath' => $paths['vendor'],
                ]
            ),
            'tools.json'             => Support::render(Support::template('MakeAgent', 'supervisor.tools.tpl.json')),
            'instruction.prompt.php' => Support::render(Support::template('MakeAgent', 'supervisor.prompt.tpl.php')),
        ];

        foreach ($files as $name => $content) {
            if (is_file($dir . '/' . $name)) {
                continue;
            }

            Support::put($dir . '/' . $name, $content);
            echo "Создан файл: Supervisor/{$name}\n";
        }
    }

    private static function baseDir(string $type): string
    {
        return match ($type) {
            'multi'      => AI_DIR . '/Multi',
            'supervisor' => AI_DIR . '/Supervisor',
        };
    }

    /** Пространство имён класса агента: каталог, в котором он лежит. */
    private static function classNamespace(string $type, string $name): string
    {
        return match ($type) {
            'single'     => 'App\\Ai\\Single',
            'multi'      => 'App\\Ai\\Multi\\' . $name,
            'supervisor' => 'App\\Ai\\Supervisor\\' . $name,
        };
    }

    private static function agentPaths(string $type): array
    {
        return match ($type) {
            'single' => self::paths(),
            default  => [
                'config' => '/../../../config.php',
                'vendor' => '/../../../../vendor/autoload.php',
            ],
        };
    }

    private static function paths(): array
    {
        return [
            'config' => '/../../config.php',
            'vendor' => '/../../../vendor/autoload.php',
        ];
    }

    private static function anyExists(string $dir, array $files): bool
    {
        foreach ($files as $file) {
            if (is_file($dir . '/' . $file)) {
                return true;
            }
        }

        return false;
    }
}
