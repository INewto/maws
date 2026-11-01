<?php

namespace Maws\System\Test;

use FilesystemIterator;
use Maws\System\Contract\{Effect, Gives, Purpose, Returns, Subject};
use Maws\System\Path;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Проверка границы, а не обещание: ядро не импортирует хелперы, поэтому любой каталог
 * core/System/Helpers/<Имя>/ удаляется целиком, а ядро продолжает работать.
 */
#[Subject(Layers::class, 'new')]
#[Purpose('Граница ядра и хелперов: ядро не обращается к хелперам — иначе хелпер нельзя было бы удалить, не сломав ядро.')]
final class Layers
{
    // Двойные слэши нарочно: так проверяющий файл не находит сам себя.
    private const HELPER = 'Maws\\System\\Helpers';

    private function __construct()
    {
    }

    #[Purpose('Перечисляет строки ядра, которые импортируют хелперы. Пусто — граница держится.')]
    #[Returns('array', 'список «файл:строка — текст»; пустой список — нарушений нет')]
    #[Effect(Effect::FS_READ)]
    #[Gives([], [])]
    public static function helperCalls(): array
    {
        $root = Path::normalize(dirname(CORE_DIR));
        $found = [];

        foreach (self::phpFiles(Path::normalize(CORE_DIR)) as $file) {
            $relative = substr($file, strlen($root) + 1);

            // Сами хелперы друг друга звать вправе: они и есть этот слой.
            if (str_starts_with($relative, 'core/System/Helpers/')) {
                continue;
            }

            foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($file)) as $number => $line) {
                if (str_contains($line, self::HELPER)) {
                    $found[] = $relative . ':' . ($number + 1) . ' — ' . trim($line);
                }
            }
        }

        return $found;
    }

    #[Purpose('Собирает php-файлы каталога рекурсивно, по порядку.')]
    #[Returns('array', 'полные пути файлов')]
    #[Effect(Effect::FS_READ)]
    private static function phpFiles(string $dir): array
    {
        $files = [];
        $walk  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

        foreach ($walk as $item) {
            if ($item->isFile() && $item->getExtension() === 'php') {
                $files[] = Path::normalize($item->getPathname());
            }
        }

        sort($files);

        return $files;
    }
}
