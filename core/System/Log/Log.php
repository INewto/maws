<?php

namespace Maws\System\Log;

use Maws\System\Contract\{Effect, Param, Purpose};
use Throwable;

#[Purpose('Журнал сбоев: файл на сутки и один короткий id на запрос. Один растущий файл за год не открыть, а без id строки соседних запросов смешиваются — trace одного сбоя перемежается с чужим.')]
final class Log
{
    private const ID_BYTES = 4;

    private static ?string $requestId = null;

    private function __construct()
    {
    }

    #[Purpose('Короткий id текущего запроса: один на процесс, поэтому все строки одного сбоя собираются по нему.')]
    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(self::ID_BYTES));
    }

    #[Purpose('Пишет сбой в журнал текущих суток: каждая строка помечена временем и id запроса. Имя файла берётся из базового пути входа — maws.log становится maws-2026-10-20.log.')]
    #[Param('error', 'Throwable', 'сбой вместе с trace')]
    #[Param('base', 'string', 'базовый путь журнала из конфига сайта: logs/maws.log')]
    #[Effect(Effect::LOG)]
    #[Effect(Effect::FS_WRITE)]
    public static function write(Throwable $error, string $base): void
    {
        $file = self::file($base);

        // Каталога нет — журнал заводит его сам: писать в никуда хуже, чем создать одну папку.
        $dir = dirname($file);

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $prefix = date('Y-m-d H:i:s') . ' [' . self::requestId() . '] ';
        $body   = '';

        // Каждая строка trace получает тот же префикс: иначе по журналу не понять, где кончается
        // один сбой и начинается следующий. Переводы строк перечислены явно: \R в PCRE считает
        // новой строкой и байт 0x85, а это второй байт буквы «х» в UTF-8 — текст рвался бы посреди
        // слова, да ещё и с префиксом внутри.
        foreach (preg_split('/\r\n|\n|\r/', rtrim((string) $error)) ?: [] as $line) {
            $body .= $prefix . $line . PHP_EOL;
        }

        error_log($body, 3, $file);
    }

    // Сутки — в имени, рядом с базовым: настройка остаётся одной строкой в конфиге сайта, а ротация
    // не требует ни cron, ни чистки — вчерашний файл просто больше не открывается на запись.
    private static function file(string $base): string
    {
        $name = pathinfo($base, PATHINFO_FILENAME);
        $ext  = pathinfo($base, PATHINFO_EXTENSION);

        return dirname($base) . DIRECTORY_SEPARATOR . $name . '-' . date('Y-m-d')
            . ($ext === '' ? '' : '.' . $ext);
    }
}
