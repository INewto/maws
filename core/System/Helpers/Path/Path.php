<?php

namespace Maws\System\Helpers\Path;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Path\Exception\PathFailure;

#[Subject(Path::class, 'new')]
#[Purpose('Собирает, разбирает и проверяет пути так, чтобы результат не зависел от того, на какой машине идёт код. Разделитель всегда прямой слэш: PHP понимает его и на Windows, а обратный слэш остаётся только на входе.')]
final class Path
{
    private function __construct()
    {
    }

    #[Purpose('Склеивает части пути, не оставляя двойных разделителей на стыках. Части не перекрывают друг друга: склейка — это склейка, а не разрешение пути.')]
    #[Param('parts', 'string', 'части пути по порядку')]
    #[Returns('string', 'склеенный путь')]
    #[Effect(Effect::NONE)]
    #[Gives(['a', 'b', 'c'], 'a/b/c')]
    #[Gives(['/a/', '/b'], '/a/b')]
    #[Gives(['/', 'a'], '/a')]
    #[Gives(['', 'a'], 'a')]
    #[Gives(['a', ''], 'a')]
    public static function join(string ...$parts): string
    {
        $result = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if ($result === '') {
                $result = $part;

                continue;
            }

            $result = rtrim($result, '/\\') . '/' . ltrim($part, '/\\');
        }

        return $result;
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

    #[Purpose('Отвечает, начинается ли путь от корня, а не от текущего каталога.')]
    #[Param('path', 'string', 'путь в любом виде')]
    #[Returns('bool', 'true, если путь абсолютный')]
    #[Effect(Effect::NONE)]
    #[Gives(['/a'], true)]
    #[Gives(['C:\\a'], true)]
    #[Gives(['C:/a'], true)]
    #[Gives(['C:a'], false)]
    #[Gives(['a/b'], false)]
    public static function isAbsolute(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        if ($path !== '' && $path[0] === '/') {
            return true;
        }

        return preg_match('#^[A-Za-z]:/#', $path) === 1;
    }

    #[Purpose('Отдаёт последний сегмент пути — имя файла или каталога вместе с расширением.')]
    #[Param('path', 'string', 'путь в любом виде')]
    #[Returns('string', 'последний сегмент; пусто, если путь кончается корнем')]
    #[Effect(Effect::NONE)]
    #[Gives(['/a/b.txt'], 'b.txt')]
    #[Gives(['a/b/'], 'b')]
    #[Gives(['/'], '')]
    public static function base(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $position = strrpos($path, '/');

        return $position === false ? $path : substr($path, $position + 1);
    }

    #[Purpose('Отдаёт каталог, в котором лежит путь, — без последнего сегмента.')]
    #[Param('path', 'string', 'путь в любом виде')]
    #[Returns('string', 'каталог; пусто, если каталога в пути нет')]
    #[Effect(Effect::NONE)]
    #[Gives(['/a/b.txt'], '/a')]
    #[Gives(['/a'], '/')]
    #[Gives(['b.txt'], '')]
    public static function dirname(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $position = strrpos($path, '/');

        if ($position === false) {
            return '';
        }

        return $position === 0 ? '/' : substr($path, 0, $position);
    }

    #[Purpose('Отдаёт расширение без точки. Точка в начале имени расширением не считается: «.htaccess» — имя целиком.')]
    #[Param('path', 'string', 'путь в любом виде')]
    #[Returns('string', 'расширение без точки; пусто, если его нет')]
    #[Effect(Effect::NONE)]
    #[Gives(['/a/b.tar.gz'], 'gz')]
    #[Gives(['/a/b'], '')]
    #[Gives(['/a/.htaccess'], '')]
    public static function extension(string $path): string
    {
        $base = self::base($path);
        $position = strrpos($base, '.');

        if ($position === false || $position === 0) {
            return '';
        }

        return substr($base, $position + 1);
    }

    #[Purpose('Отдаёт путь без расширения — остальное имя сохраняется как было, включая разделители.')]
    #[Param('path', 'string', 'путь в любом виде')]
    #[Returns('string', 'путь без расширения')]
    #[Effect(Effect::NONE)]
    #[Gives(['/a/b.tar.gz'], '/a/b.tar')]
    #[Gives(['/a/b'], '/a/b')]
    #[Gives(['C:\\a\\.htaccess'], 'C:\\a\\.htaccess')]
    public static function withoutExtension(string $path): string
    {
        $extension = self::extension($path);

        if ($extension === '') {
            return $path;
        }

        return substr($path, 0, strlen($path) - strlen($extension) - 1);
    }

    #[Purpose('Отвечает, лежит ли путь внутри каталога. Сравниваются сегменты после нормализации, а не начало строки: иначе «/root2» считался бы лежащим внутри «/root», и проверка пропускала бы чужое. Операция строковая — файловую систему она не спрашивает.')]
    #[Param('path', 'string', 'проверяемый путь')]
    #[Param('root', 'string', 'каталог, внутри которого путь должен оказаться')]
    #[Returns('bool', 'true, если путь внутри каталога или совпадает с ним; на Windows регистр не различается, как и в самой файловой системе')]
    #[Throws(PathFailure::class, 'путь выходит за свой корень — отвечать «внутри» не на что')]
    #[Effect(Effect::NONE)]
    #[Gives(['/root/a/b', '/root'], true)]
    #[Gives(['/root', '/root'], true)]
    #[Gives(['/root/../etc', '/root'], false)]
    #[Gives(['/root2/a', '/root'], false)]
    #[Gives(['C:/Root/a', 'C:/Root'], true)]
    #[Gives(['C:/Root2/a', 'C:/Root'], false)]
    #[Gives(['/root/a', ''], false)]
    #[Gives(['a.txt', '.'], true)]
    #[Gives(['a/b', './'], true)]
    #[Gives(['/a', '.'], false)]
    #[Fails(['../a', '/root'], PathFailure::class)]
    public static function inside(string $path, string $root): bool
    {
        $base = self::normalize($root);

        if ($base === '') {
            return false;
        }

        $target = self::normalize($path);

        // «.» — текущий каталог: внутри него лежит любой относительный путь. Без этой ветки
        // inside('a.txt', '.') отвечал бы «нет»: префикса «./» в нормализованном пути нет.
        if ($base === '.') {
            return !self::isAbsolute($target);
        }

        $base   = self::fold($base);
        $target = self::fold($target);

        if ($target === $base) {
            return true;
        }

        return str_starts_with($target, rtrim($base, '/') . '/');
    }

    // Windows не различает регистр в путях, поэтому и сравнение там идёт без него: иначе
    // «C:/ROOT/a» считался бы лежащим вне «C:/Root», хотя это один и тот же каталог. Свёртка
    // ASCII: не-ASCII имена разрешает файловая система — этим занимается `File`, а не строки.
    private static function fold(string $path): string
    {
        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    }
}
