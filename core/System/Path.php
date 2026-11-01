<?php

namespace Maws\System;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Exception\PathFailure;

/**
 * Своя нормализация пути, отдельная от хелпера `Path`: ядру нужен один вид пути для сообщений
 * об ошибке и чтения файлов, а хелпер обязан удаляться, не роняя ядро. Две реализации здесь —
 * не недосмотр: у ядра и у хелпера разные сроки жизни.
 */
#[Subject(Path::class, 'new')]
#[Purpose('Нормализация пути в ядре: свой экземпляр правила, не связанный с одноимённым хелпером.')]
final class Path
{
    private function __construct()
    {
    }

    #[Purpose('Приводит путь к одному виду: обратный слэш становится прямым, «.» и пустые сегменты уходят, «..» схлопывается с предыдущим сегментом. Путь, который выходит за свой корень, — отказ: «..» некуда схлопывать.')]
    #[Param('path', 'string', 'путь в любом виде')]
    #[Returns('string', 'нормализованный путь')]
    #[Throws(PathFailure::class, '«..» в начале пути — выходить некуда')]
    #[Effect(Effect::NONE)]
    #[Gives(['/a/./b/../c//'], '/a/c')]
    #[Gives(['C:\\a\\.\\b'], 'C:/a/b')]
    #[Gives(['\\\\server\\share\\a\\..\\b'], '//server/share/b')]
    #[Gives(['/'], '/')]
    #[Gives(['a/..'], '.')]
    #[Gives([''], '')]
    #[Gives(['C:./a'], 'a')]
    #[Fails(['../123'], PathFailure::class)]
    #[Fails(['a/../../b'], PathFailure::class)]
    public static function normalize(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Исходный вид — для сообщения об отказе: показывать надо то, что пришло, а не то, что
        // осталось после отрезания префикса и замены слэшей.
        $source = $path;

        $path = str_replace('\\', '/', $path);

        // Префикс — всё до первого слэша, и диск («C:») в нём префикс, а не сегмент: приклеенный
        // через слэш, он превращал бы «от текущего каталога на диске» в «от корня диска». Диск без
        // слэша за собой отрезается, остаток считается относительным путём.
        $drive = '';

        if (preg_match('#^[A-Za-z]:#', $path) === 1) {
            $drive = substr($path, 0, 2);
            $path  = substr($path, 2);

            if (!str_starts_with($path, '/')) {
                $drive = '';
            }
        }

        $absolute = str_starts_with($path, '/');
        $unc      = $drive === '' && str_starts_with($path, '//');
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                // Схлопывать некуда — значит путь уходит выше того, от чего отсчитан. Пропустить
                // его значит вернуть «../123» как нормализованный путь и отдать дыру вызывающему.
                if ($segments === []) {
                    throw new PathFailure($source);
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $result = implode('/', $segments);

        if ($absolute) {
            return $drive . ($unc ? '//' : '/') . $result;
        }

        return $result === '' ? '.' : $result;
    }
}
