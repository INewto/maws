<?php

namespace Maws\System\Helpers\File;

use Maws\System\Contract\{Changes, Effect, Fails, Fixture, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\File\Exception\FileFailure;
use Maws\System\Helpers\File\Exception\FileMisuse;
use Maws\System\Helpers\Path\Exception\PathFailure;
use Maws\System\Helpers\Path\Path;

#[Subject(File::class, 'in', ['@sandbox'])]
#[Fixture(files: ['read.txt' => 'привет'])]
#[Purpose('Работает с файлами внутри одного каталога. Корень задаётся при сборке, а пути — только относительные от него: выход за корень и абсолютный путь отвергаются дважды — по строке и по разрешённому пути, — поэтому ни «..», ни симлинк, ведущий наружу, не уводят чтение или запись куда-то ещё.')]
final class File
{
    #[Param('root', 'string', 'каталог, за пределы которого работа не выходит')]
    #[Param('real', 'string|null', 'тот же каталог, разрешённый файловой системой: с ним сравнивается разрешённый путь')]
    private function __construct(private readonly string $root, private ?string $real = null)
    {
    }

    #[Purpose('Собирает читателя файлов над каталогом.')]
    #[Param('root', 'string', 'каталог, за пределы которого работа не выходит')]
    #[Returns('self', 'читатель файлов внутри этого каталога')]
    #[Throws(FileMisuse::class, 'корень пустой или сам выходит за свои пределы')]
    #[Effect(Effect::NONE)]
    #[Fails([''], FileMisuse::class)]
    public static function in(string $root): self
    {
        try {
            $root = Path::normalize($root);
        } catch (PathFailure) {
            throw new FileMisuse("корень выходит за свои пределы: {$root}");
        }

        if ($root === '') {
            throw new FileMisuse('корень не может быть пустым — нечего ограничивать');
        }

        // Корень разрешается один раз: realpath снимает ссылки в самом корне (на macOS «/var» —
        // это ссылка на «/private/var») и приводит регистр к тому, что на диске. Без этого
        // разрешённый путь ребёнка было бы не с чем сравнивать.
        $real = realpath($root);

        return new self($root, $real === false ? null : Path::normalize($real));
    }

    #[Purpose('Читает файл целиком. Файла нет — false, а не исключение: «нет файла» бывает обычным делом, и решает это вызвавший. Файл есть, но не читается — сбой, а не «нет данных».')]
    #[Param('path', 'string', 'путь относительно корня')]
    #[Returns('string|false', 'содержимое или false, если файла нет')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку')]
    #[Throws(FileFailure::class, 'файл есть, но не читается')]
    #[Effect(Effect::FS_READ)]
    #[Gives(['read.txt'], 'привет')]
    #[Gives(['./read.txt'], 'привет')]
    #[Gives(['sub/../read.txt'], 'привет')]
    #[Gives(['nope.txt'], false)]
    #[Fails(['../read.txt'], FileMisuse::class)]
    #[Fails(['/etc/passwd'], FileMisuse::class)]
    public function read(string $path): string|false
    {
        $full = $this->path($path);

        if (!is_file($full)) {
            return false;
        }

        $content = file_get_contents($full);

        if ($content === false) {
            throw new FileFailure("файл есть, но не читается: {$full}");
        }

        return $content;
    }

    #[Purpose('Записывает файл целиком, создавая его при необходимости. Сбой записи — исключение, а не false: молча не записанные данные вызывающий не заметит, а потеря уже случилась.')]
    #[Param('path', 'string', 'путь относительно корня')]
    #[Param('content', 'string', 'что записать')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку')]
    #[Throws(FileFailure::class, 'каталога нет или файл не записывается')]
    #[Effect(Effect::FS_WRITE)]
    #[Changes(['new.txt', 'привет'], [['read', ['new.txt'], 'привет']])]
    #[Fails(['nope/new.txt', 'привет'], FileFailure::class)]
    public function write(string $path, string $content): void
    {
        $full = $this->path($path);

        if (!is_dir(dirname($full))) {
            throw new FileFailure("каталога нет: " . dirname($full) . ' — создавать его скрыто нельзя, для этого есть ensureDir');
        }

        if (file_put_contents($full, $content, LOCK_EX) === false) {
            throw new FileFailure("не записать файл: {$full}");
        }
    }

    #[Purpose('Дописывает в конец файла, создавая его при необходимости.')]
    #[Param('path', 'string', 'путь относительно корня')]
    #[Param('content', 'string', 'что дописать')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку')]
    #[Throws(FileFailure::class, 'каталога нет или файл не дописывается')]
    #[Effect(Effect::FS_WRITE)]
    #[Changes(['read.txt', ' мир'], [['read', ['read.txt'], 'привет мир']])]
    public function append(string $path, string $content): void
    {
        $full = $this->path($path);

        if (!is_dir(dirname($full))) {
            throw new FileFailure("каталога нет: " . dirname($full) . ' — создавать его скрыто нельзя, для этого есть ensureDir');
        }

        if (file_put_contents($full, $content, FILE_APPEND | LOCK_EX) === false) {
            throw new FileFailure("не дописать файл: {$full}");
        }
    }

    #[Purpose('Отвечает, есть ли такой файл или каталог.')]
    #[Param('path', 'string', 'путь относительно корня')]
    #[Returns('bool', 'true, если файл или каталог существует')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку')]
    #[Effect(Effect::FS_READ)]
    #[Gives(['read.txt'], true)]
    #[Gives(['nope.txt'], false)]
    public function exists(string $path): bool
    {
        return file_exists($this->path($path));
    }

    #[Purpose('Отдаёт размер файла в байтах.')]
    #[Param('path', 'string', 'путь относительно корня')]
    #[Returns('int|false', 'размер в байтах или false, если это не файл')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку')]
    #[Effect(Effect::FS_READ)]
    #[Gives(['read.txt'], 12)]
    #[Gives(['nope.txt'], false)]
    public function size(string $path): int|false
    {
        $full = $this->path($path);

        if (!is_file($full)) {
            return false;
        }

        return filesize($full);
    }

    #[Purpose('Отдаёт время последнего изменения файла.')]
    #[Param('path', 'string', 'путь относительно корня')]
    #[Returns('int|false', 'метка времени Unix или false, если это не файл')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку')]
    #[Effect(Effect::FS_READ)]
    #[Gives(['nope.txt'], false)]
    public function modified(string $path): int|false
    {
        $full = $this->path($path);

        if (!is_file($full)) {
            return false;
        }

        return filemtime($full);
    }

    #[Purpose('Перечисляет, что лежит в каталоге, по алфавиту. Служебные «.» и «..» не попадают в список: вызывающему они не нужны, а их присутствие — источник ошибок.')]
    #[Param('dir', 'string', 'путь каталога относительно корня; пусто — сам корень')]
    #[Returns('array', 'имена файлов и каталогов по алфавиту')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку')]
    #[Throws(FileFailure::class, 'это не каталог или он не читается')]
    #[Effect(Effect::FS_READ)]
    #[Gives([], ['read.txt'])]
    #[Fails(['read.txt'], FileFailure::class)]
    public function names(string $dir = ''): array
    {
        $full = $this->path($dir);

        if (!is_dir($full)) {
            throw new FileFailure("не каталог: {$full}");
        }

        $names = scandir($full);

        if ($names === false) {
            throw new FileFailure("не прочитать каталог: {$full}");
        }

        return array_values(array_diff($names, ['.', '..']));
    }

    #[Purpose('Создаёт каталог со всеми недостающими родителями. Уже существующий каталог — не ошибка: вызов можно повторять.')]
    #[Param('dir', 'string', 'путь каталога относительно корня')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку')]
    #[Throws(FileFailure::class, 'каталог не создаётся')]
    #[Effect(Effect::FS_WRITE)]
    #[Changes(['sub/dir'], [['exists', ['sub/dir'], true]])]
    public function ensureDir(string $dir): void
    {
        $full = $this->path($dir);

        if (is_dir($full)) {
            return;
        }

        if (!mkdir($full, 0777, true) && !is_dir($full)) {
            throw new FileFailure("не создать каталог: {$full}");
        }
    }

    #[Purpose('Удаляет файл. Отсутствие файла — сбой, а не тихий успех: иначе опечатка в пути выглядела бы как выполненное удаление. Каталоги не трогает.')]
    #[Param('path', 'string', 'путь относительно корня')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку, либо это каталог')]
    #[Throws(FileFailure::class, 'файла нет или он не удаляется')]
    #[Effect(Effect::FS_WRITE)]
    #[Changes(['read.txt'], [['exists', ['read.txt'], false]])]
    #[Fails(['nope.txt'], FileFailure::class)]
    public function delete(string $path): void
    {
        $full = $this->path($path);

        if (is_dir($full)) {
            throw new FileMisuse("это каталог, а не файл: {$full} — удаление каталогов не входит в этот метод");
        }

        if (!is_file($full)) {
            throw new FileFailure("файла нет: {$full}");
        }

        if (!unlink($full)) {
            throw new FileFailure("не удалить файл: {$full}");
        }
    }

    #[Purpose('Копирует файл внутри корня.')]
    #[Param('from', 'string', 'откуда, относительно корня')]
    #[Param('to', 'string', 'куда, относительно корня')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку')]
    #[Throws(FileFailure::class, 'исходного файла нет или копия не создаётся')]
    #[Effect(Effect::FS_READ)]
    #[Effect(Effect::FS_WRITE)]
    #[Changes(['read.txt', 'copy.txt'], [['read', ['copy.txt'], 'привет']])]
    public function copy(string $from, string $to): void
    {
        $source = $this->path($from);
        $target = $this->path($to);

        if (!is_file($source)) {
            throw new FileFailure("файла нет: {$source}");
        }

        if (!copy($source, $target)) {
            throw new FileFailure("не скопировать {$source} в {$target}");
        }
    }

    #[Purpose('Переносит файл внутри корня. Если переименование невозможно (разные диски), файл копируется и исходный удаляется — иначе перенос между диском сайта и временным каталогом молча не работал бы.')]
    #[Param('from', 'string', 'откуда, относительно корня')]
    #[Param('to', 'string', 'куда, относительно корня')]
    #[Throws(FileMisuse::class, 'путь абсолютный, выходит за корень или ведёт за него через ссылку')]
    #[Throws(FileFailure::class, 'исходного файла нет или перенос не удался')]
    #[Effect(Effect::FS_READ)]
    #[Effect(Effect::FS_WRITE)]
    #[Changes(['read.txt', 'moved.txt'], [['read', ['moved.txt'], 'привет'], ['exists', ['read.txt'], false]])]
    public function move(string $from, string $to): void
    {
        $source = $this->path($from);
        $target = $this->path($to);

        if (!is_file($source)) {
            throw new FileFailure("файла нет: {$source}");
        }

        if (@rename($source, $target)) {
            return;
        }

        if (!copy($source, $target)) {
            throw new FileFailure("не перенести {$source} в {$target}");
        }

        if (!unlink($source)) {
            throw new FileFailure("скопировано, но исходный файл не удалён: {$source}");
        }
    }

    private function path(string $path): string
    {
        if (Path::isAbsolute($path)) {
            throw new FileMisuse("путь \"{$path}\" абсолютный — он должен быть относительным от корня {$this->root}");
        }

        // Нормализация сама отказывает, если «..» некуда схлопывать; для читателя это тот же
        // выход за корень, поэтому и исключение то же — иначе вызывающему пришлось бы ловить два.
        try {
            $full = Path::normalize(Path::join($this->root, $path));
        } catch (PathFailure) {
            throw new FileMisuse("путь \"{$path}\" выходит за корень {$this->root}");
        }

        if (!Path::inside($full, $this->root)) {
            throw new FileMisuse("путь \"{$path}\" выходит за корень {$this->root}");
        }

        // Строковой проверки мало: симлинк или junction внутри корня уводит за него, а строка
        // остаётся «внутри». Разрешаем путь файловой системой — она же приводит регистр к тому,
        // что на диске, поэтому «C:/ROOT» и «C:/Root» сходятся без ручного сравнения без регистра.
        $real = self::resolve($full);
        $base = $this->realRoot();

        if ($real !== null && $base !== null && !Path::inside($real, $base)) {
            throw new FileMisuse("путь \"{$path}\" ведёт за корень {$this->root} через ссылку");
        }

        return $full;
    }

    // Корень, каким его видит файловая система. Считается при первом обращении: каталог мог не
    // существовать при `in()` и появиться позже — тогда разрешать его насовсем было бы неверно.
    private function realRoot(): ?string
    {
        if ($this->real === null) {
            $real = realpath($this->root);

            $this->real = $real === false ? null : Path::normalize($real);
        }

        return $this->real;
    }

    // Разрешает путь так, как это сделает файловая система: существующий хвост — через realpath,
    // недостающий приписывается к разрешённому ближайшему предку. Поэтому проверяется и запись
    // в файл, которого ещё нет. null — разрешить не удалось ни на одном уровне (недоступный
    // диск); тогда решает строковая проверка, а сама операция всё равно упрётся в отказ.
    private static function resolve(string $path): ?string
    {
        $real = realpath($path);

        if ($real !== false) {
            return Path::normalize($real);
        }

        $tail = [];
        $dir  = $path;

        while (true) {
            $parent = Path::dirname($dir);

            if (preg_match('#^[A-Za-z]:$#', $parent) === 1) {
                $parent .= '/';
            }

            if ($parent === $dir || $parent === '') {
                return null;
            }

            array_unshift($tail, Path::base($dir));
            $dir  = $parent;
            $real = realpath($dir);

            if ($real !== false) {
                return Path::normalize($real . '/' . implode('/', $tail));
            }
        }
    }
}
