<?php

namespace Maws\System\Helpers\Upload;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Path\Path;
use Maws\System\Helpers\Upload\Exception\UploadFailure;
use Maws\System\Helpers\Upload\Exception\UploadMisuse;

#[Subject(Upload::class, 'accepts', ['photo.JPG', ['jpg']])]
#[Purpose('Принимает файл, присланный формой, и кладёт его в каталог под новым именем: имя собирается из случайных байтов, расширение берётся из исходного и только из разрешённых. Исходное имя не используется никогда, поэтому «shell.php.jpg» становится безобидным «a1b2….jpg», а «../» в имени ничего не значит. Содержимое сверяется с расширением: имя файла — это заявление посетителя, а не факт. Отказ по вине файла — ответ с причиной, а не исключение: сайт покажет его рядом с формой.')]
final class Upload
{
    public const OK    = 'ok';
    public const ERROR = 'error';

    private const NAME_BYTES = 16;

    // Отказы посетителю: файла нет, он больше предела, отправку оборвали. Виноват файл, а не
    // сервер, поэтому это ответ, который сайт покажет человеку, — а не 500.
    private const REFUSALS = [
        UPLOAD_ERR_NO_FILE => 'файл не выбран',
        UPLOAD_ERR_INI_SIZE => 'файл больше допустимого размера',
        UPLOAD_ERR_FORM_SIZE => 'файл больше допустимого размера',
        UPLOAD_ERR_PARTIAL => 'файл пришёл не полностью — отправку оборвали',
    ];

    // Сбои окружения: PHP не смог принять файл. Здесь исключение, а не ответ: чинить это сайту
    // нечем, а молча потерянный файл вызывающий не заметит.
    private const FAILURES = [
        UPLOAD_ERR_NO_TMP_DIR => 'на сервере нет каталога для временных файлов',
        UPLOAD_ERR_CANT_WRITE => 'сервер не записал временный файл',
        UPLOAD_ERR_EXTENSION => 'загрузку остановило расширение PHP',
    ];

    // Стандартный список: расширение → типы содержимого, которые за ним стоят. Список закрыт
    // намеренно: чего в нём нет, того загрузка не примет, даже если разработчик попросил, —
    // иначе «разрешил doc» означало бы «разрешил что угодно под именем doc». SVG здесь нет:
    // это XML со скриптами внутри, и открытый напрямую файл исполнит их на нашем же домене.
    // Типы записаны строчными, потому что сравнение в fits() тоже строчное, а libmagic отдаёт
    // их как попало (application/CDFV2). cdfv2 рядом с родными типами doc/xls — потому что
    // старый формат это OLE2-контейнер, и часть сборок видит в нём только контейнер. У OOXML
    // рядом с родным типом стоит application/zip по той же причине: отказывать живому файлу
    // из-за версии библиотеки не за что.
    private const TYPES = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv'],
        'zip'  => ['application/zip'],
        'doc'  => ['application/msword', 'application/cdfv2'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'xls'  => ['application/vnd.ms-excel', 'application/cdfv2'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
        ],
        'pptx' => [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
        ],
    ];

    private function __construct()
    {
    }

    #[Purpose('Отвечает, годится ли расширение присланного имени. Содержимое здесь не смотрится: имя о нём ничего не говорит, для этого есть fits и detect. Проверяется прогоном, потому что это единственное решение о форме, которое можно принять без самого файла.')]
    #[Param('name', 'string', 'имя файла из формы, как его прислали')]
    #[Param('types', 'array', 'разрешённые расширения без точки: jpg, png, pdf')]
    #[Returns('bool', 'true, если расширение в списке разрешённых')]
    #[Throws(UploadMisuse::class, 'список пуст, содержит не строку или расширение вне стандартного списка')]
    #[Effect(Effect::NONE)]
    #[Gives(['shell.php.png', ['png']], true)]
    #[Gives(['photo.JPG', ['.jpg']], true)]
    #[Gives(['note.txt', ['jpg', 'png']], false)]
    #[Gives(['noext', ['jpg']], false)]
    #[Gives(['.htaccess', ['jpg']], false)]
    #[Fails(['a.png', []], UploadMisuse::class)]
    #[Fails(['a.svg', ['svg']], UploadMisuse::class)]
    public static function accepts(string $name, array $types): bool
    {
        $extension = strtolower(Path::extension($name));

        return $extension !== '' && in_array($extension, self::allowed($types), true);
    }

    #[Purpose('Сверяет определённый тип содержимого с тем, что обещает расширение. Решение чистое — строка на входе, да или нет на выходе, — поэтому проверяется прогоном; само определение типа остаётся за libmagic и прогоном не проверяется: его ответ зависит от версии библиотеки на машине.')]
    #[Param('extension', 'string', 'расширение без точки')]
    #[Param('detected', 'string', 'тип, определённый по содержимому; параметры вида "; charset=utf-8" не мешают')]
    #[Returns('bool', 'true, если такой тип допустим для этого расширения')]
    #[Throws(UploadMisuse::class, 'расширения нет в стандартном списке')]
    #[Effect(Effect::NONE)]
    #[Gives(['png', 'IMAGE/PNG'], true)]
    #[Gives(['txt', 'text/plain; charset=utf-8'], true)]
    #[Gives(['docx', 'application/zip'], true)]
    #[Gives(['png', 'text/x-php'], false)]
    #[Gives(['png', ''], false)]
    #[Fails(['svg', 'image/svg+xml'], UploadMisuse::class)]
    public static function fits(string $extension, string $detected): bool
    {
        $mimes    = self::TYPES[self::known($extension)];
        $detected = self::base($detected);

        return $detected !== '' && in_array($detected, $mimes, true);
    }

    #[Purpose('Отвечает, больше ли файл объявленного предела. Ровно предел — годится: «до стольких-то байт» включает само число, а не заканчивается перед ним. Ноль значит «своего предела нет»: размер держит php.ini, и хелпер в это не вмешивается.')]
    #[Param('size', 'int', 'размер файла в байтах')]
    #[Param('maxBytes', 'int', 'предел в байтах; 0 — предела нет')]
    #[Returns('bool', 'true, если файл больше предела')]
    #[Throws(UploadMisuse::class, 'предел отрицательный')]
    #[Effect(Effect::NONE)]
    #[Gives([0, 0], false)]
    #[Gives([1000, 0], false)]
    #[Gives([1000, 1000], false)]
    #[Gives([1001, 1000], true)]
    #[Fails([0, -1], UploadMisuse::class)]
    public static function tooBig(int $size, int $maxBytes): bool
    {
        self::demandLimit($maxBytes);

        return $maxBytes > 0 && $size > $maxBytes;
    }

    #[Purpose('Переводит код отказа загрузки из php.ini в ответ для посетителя. null — «виноват не файл»: либо файл принят, либо сломалось окружение, и тогда вызывающий получает исключение от upload. Разделение важно: ответ показывают человеку, исключение — признак, что чинить надо сервер.')]
    #[Param('error', 'int', 'код из $_FILES[поле][error]')]
    #[Returns('array|null', 'ответ «error» с причиной или null, если отказ не по вине файла')]
    #[Effect(Effect::NONE)]
    #[Gives([UPLOAD_ERR_NO_FILE], ['status' => 'error', 'message' => 'файл не выбран', 'name' => null])]
    #[Gives([UPLOAD_ERR_INI_SIZE], ['status' => 'error', 'message' => 'файл больше допустимого размера', 'name' => null])]
    #[Gives([UPLOAD_ERR_FORM_SIZE], ['status' => 'error', 'message' => 'файл больше допустимого размера', 'name' => null])]
    #[Gives([UPLOAD_ERR_PARTIAL], ['status' => 'error', 'message' => 'файл пришёл не полностью — отправку оборвали', 'name' => null])]
    #[Gives([UPLOAD_ERR_CANT_WRITE], null)]
    #[Gives([UPLOAD_ERR_OK], null)]
    public static function refusal(int $error): ?array
    {
        $message = self::REFUSALS[$error] ?? null;

        return $message === null ? null : self::refused($message);
    }

    #[Purpose('Переносит присланный файл в каталог и отдаёт ответ: имя сохранённого файла или причину отказа словами. Виноват файл — «error» и текст для человека (нет файла, не тот тип, слишком большой); виноват сервер или разработчик — исключение, потому что посетителю тут сказать нечего.')]
    #[Param('field', 'string', 'имя поля формы — ключ в $_FILES')]
    #[Param('dir', 'string', 'каталог, куда положить файл; он должен уже существовать')]
    #[Param('types', 'array', 'разрешённые расширения без точки: jpg, png, pdf')]
    #[Param('maxBytes', 'int', 'предел размера в байтах; 0 — только upload_max_filesize из php.ini')]
    #[Returns('array', 'ответ: status = ok|error, message — что сказать человеку, name — новое имя файла или null')]
    #[Throws(UploadMisuse::class, 'список расширений пуст или вне стандартного списка, имя поля пустое, предел отрицательный, каталога нет или файл пришёл не через форму')]
    #[Throws(UploadFailure::class, 'PHP не принял файл, его не удалось перенести или нечем определить тип')]
    #[Effect(Effect::INPUT)]
    #[Effect(Effect::FS_WRITE)]
    public static function upload(string $field, string $dir, array $types = [], int $maxBytes = 0): array
    {
        if ($field === '') {
            throw new UploadMisuse('имя поля пустое: файл ищется в $_FILES по имени поля формы');
        }

        $allowed = self::allowed($types);
        self::demandLimit($maxBytes);

        $file = $_FILES[$field] ?? null;

        if (!is_array($file)) {
            return self::refused('файл не выбран');
        }

        $error   = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $refusal = self::refusal($error);

        if ($refusal !== null) {
            return $refusal;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadFailure(self::reason($error));
        }

        $tmp  = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? '');

        // Пришёл не тот файл, который принял PHP: значит, upload позвали не на данных формы,
        // а на чужом пути — переносить его нельзя.
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new UploadMisuse("файл «{$field}» пришёл не через форму: переносить нечего");
        }

        $extension = strtolower(Path::extension($name));

        if (!self::accepts($name, $allowed)) {
            return self::refused('такой файл не принимается: разрешены ' . implode(', ', $allowed));
        }

        $size = filesize($tmp);

        if ($size === false) {
            throw new UploadFailure("не узнать размер файла: {$tmp}");
        }

        // Размер — раньше типа: он дешевле и решает судьбу файла, не читая его.
        if (self::tooBig($size, $maxBytes)) {
            return self::refused('файл больше допустимого размера');
        }

        // Имя обещает одно, а внутри может лежать другое: «photo.png» с PHP внутри — обычная
        // попытка. Сверяем до переноса, чтобы в каталоге сайта не оказалось того, чего мы
        // не проверяли.
        if (!self::fits($extension, self::detect($tmp))) {
            return self::refused('содержимое файла не соответствует расширению');
        }

        $dir = Path::normalize($dir);

        if ($dir === '' || !is_dir($dir)) {
            throw new UploadMisuse("каталога загрузки нет: {$dir} — создавать его скрыто нельзя");
        }

        $new  = bin2hex(random_bytes(self::NAME_BYTES)) . '.' . $extension;
        $full = Path::join($dir, $new);

        if (!move_uploaded_file($tmp, $full)) {
            throw new UploadFailure("не перенести файл в {$full}");
        }

        return ['status' => self::OK, 'message' => '', 'name' => $new];
    }

    // Список приводится к одному виду: строчными и без точки, чтобы «JPG», «.jpg» и «jpg»
    // означали одно и то же. Пустой список — отказ, а не «пропускать всё»: иначе одна опечатка
    // в аргументе открывала бы каталог любому файлу, включая исполняемый.
    private static function allowed(array $types): array
    {
        $allowed = [];

        foreach ($types as $type) {
            if (!is_string($type)) {
                throw new UploadMisuse('расширения перечисляются строками: jpg, png, pdf');
            }

            $type = strtolower(ltrim(trim($type), '.'));

            if ($type === '') {
                continue;
            }

            $allowed[] = self::known($type);
        }

        if ($allowed === []) {
            throw new UploadMisuse('список разрешённых расширений пуст — пропускать что попало нельзя, назови хотя бы одно');
        }

        return $allowed;
    }

    // Расширение вне стандартного списка — опечатка или надежда на проверку, которой нет.
    // И то и другое надо чинить сразу, а не узнавать по молча не принятым файлам.
    private static function known(string $extension): string
    {
        $extension = strtolower(ltrim(trim($extension), '.'));

        if (!isset(self::TYPES[$extension])) {
            throw new UploadMisuse(
                "расширения «{$extension}» нет в стандартном списке: загрузка принимает только то, "
                . 'что умеет проверить — ' . implode(', ', array_keys(self::TYPES))
            );
        }

        return $extension;
    }

    // Отрицательный предел — не «запретить всё», а опечатка: без этой проверки он молча
    // превратился бы в «предела нет», и файлы любого размера прошли бы.
    private static function demandLimit(int $maxBytes): void
    {
        if ($maxBytes < 0) {
            throw new UploadMisuse('предел размера отрицательный — это опечатка, а не запрет загрузок');
        }
    }

    private static function refused(string $message): array
    {
        return ['status' => self::ERROR, 'message' => $message, 'name' => null];
    }

    // Определение типа — единственное место, где загрузка спрашивает библиотеку, а не решает
    // сама. Ответ libmagic зависит от её версии, поэтому прогоном проверяется решение (fits),
    // а не этот ответ.
    private static function detect(string $file): string
    {
        if (!function_exists('finfo_open')) {
            throw new UploadFailure('не определить тип файла: расширение fileinfo не включено');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            throw new UploadFailure('не открыть базу типов fileinfo');
        }

        $mime = finfo_file($finfo, $file);
        finfo_close($finfo);

        if ($mime === false) {
            throw new UploadFailure("не определить тип файла: {$file}");
        }

        return $mime;
    }

    // «text/plain; charset=utf-8» и «TEXT/PLAIN» — один и тот же тип: параметры к делу не
    // относятся, а регистр в ответах библиотек гуляет.
    private static function base(string $mime): string
    {
        return strtolower(trim(explode(';', $mime, 2)[0]));
    }

    private static function reason(int $error): string
    {
        $text = self::FAILURES[$error] ?? "неизвестная ошибка загрузки ({$error})";

        return "PHP не принял файл: {$text}";
    }
}
