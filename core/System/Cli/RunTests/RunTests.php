<?php

namespace Maws\System\Cli\RunTests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class RunTests
{
    private const SUMMARY = '~Итог:\s*(\d+)\s+проверок,\s*(\d+)\s+прошло,\s*(\d+)\s+провалено~u';

    private const SUBJECT = '/^#\[Subject\(/m';

    public static function run(): void
    {
        $root    = dirname(APP_DIR);
        $sources = self::sources($root);

        if ($sources === []) {
            echo "В core/ нет файлов с объявленным контрактом (#[Subject]).\n";
            exit(1);
        }

        echo "MAWS — пакетный прогон контрактов\n";
        echo 'Источников: ' . count($sources) . "\n\n";

        $passed = 0;
        $empty  = [];
        $broken = [];

        foreach ($sources as $source) {
            [$code, $output] = self::one($root, $source);
            $summary = self::summary($output);

            if ($code === 0 && $summary !== null) {
                echo "[ok]   {$source} — {$summary}\n";
                $passed++;

                continue;
            }

            // Контракт объявлен, а случаев в нём нет: файл ничего не проверяет. Это не провал
            // прогона, но и не успех — иначе объявление выглядело бы проверенным.
            if (str_contains($output, 'В контракте нет проверок')) {
                echo "[--]   {$source} — объявлен, проверок нет\n";
                $empty[] = $source;

                continue;
            }

            echo '[!!]   ' . $source . ($summary === null ? '' : " — {$summary}") . "\n";
            $broken[$source] = $output;
        }

        foreach ($broken as $source => $output) {
            echo "\n=== {$source}\n" . self::reasons($output);
        }

        $failed = count($broken);

        echo "\nИтог: контрактов " . count($sources) . ", прошло {$passed}, провалено {$failed}"
            . ($empty === [] ? '' : ', без проверок ' . count($empty)) . "\n";

        exit($failed === 0 ? 0 : 1);
    }

    // Источник контракта — файл класса, объявивший #[Subject] с начала строки. Отражением
    // такой файл не найти: контракт объявляется и на том, что грузится только вместе с
    // родителем, и в общий обход по классам оно не попадёт. Шаблоны пропускаются: в них
    // объявление написано для будущего класса, а не для существующего.
    private static function sources(string $root): array
    {
        $found = [];

        $walk = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . '/core', RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($walk as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            if (str_contains($path, '/templates/')) {
                continue;
            }

            if (preg_match(self::SUBJECT, (string) file_get_contents($path)) !== 1) {
                continue;
            }

            $found[] = substr($path, strlen(str_replace('\\', '/', $root)) + 1);
        }

        sort($found);

        return $found;
    }

    // Каждый контракт — отдельный процесс. В общем процессе прогон уносил бы с собой
    // соединение, сессию и файлы песочницы, а фатал в одном контракте обрывал бы остальные.
    // Аргументы экранируются по одному: на Windows escapeshellarg("a b") даёт одну строку,
    // и «make test:…» приехало бы в maws.php единственным аргументом.
    private static function one(string $root, string $source): array
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' ' . escapeshellarg($root . '/maws.php')
            . ' ' . escapeshellarg('make')
            . ' ' . escapeshellarg('test:' . $source)
            . ' 2>&1';

        exec($command, $lines, $code);

        return [$code, implode(PHP_EOL, $lines)];
    }

    private static function summary(string $output): ?string
    {
        if (preg_match(self::SUMMARY, $output, $found) !== 1) {
            return null;
        }

        return "{$found[1]} проверок, {$found[3]} провалено";
    }

    // Провал объясняет себя сам: строка [fail] и подробность следующей строкой. Остальное
    // в сводке — шум; если объяснения не нашлось, показываем вывод целиком, иначе причина
    // осталась бы за кадром.
    private static function reasons(string $output): string
    {
        $lines = preg_split('/\r\n|\n|\r/', $output) ?: [];
        $kept  = [];

        foreach ($lines as $number => $line) {
            if (!str_contains($line, '[fail]') && !str_contains($line, 'Нет живой базы')) {
                continue;
            }

            $kept[] = $line;

            $next = $lines[$number + 1] ?? '';

            if ($next !== '' && !str_starts_with(ltrim($next), '[')) {
                $kept[] = $next;
            }
        }

        return $kept === [] ? $output . "\n" : implode("\n", $kept) . "\n";
    }
}
