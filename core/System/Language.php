<?php

namespace Maws\System;

use Maws\System\Contract\{Effect, Fixture, Gives, Param, Purpose, Returns, Subject, Throws, Token};
use Maws\System\Exception\LanguageMisuse;

/**
 * Язык сайта — один на запрос и на всё сразу: направления и места берут шаблоны из его каталога.
 * Поэтому он живёт в системе, а не в голове: шаблон места рисуется глубоко в инструменте, куда
 * голова не дотягивается, а сцена — в экране, который о голове не знает.
 */
#[Subject(Language::class, 'new')]
#[Fixture(files: ['tpl/en/a.php' => 'en', 'tpl/ru/a.php' => 'ru'])]
#[Purpose('Язык сайта: из печенья site_language, иначе из константы LANGUAGE. Один на запрос — для направлений и мест сразу.')]
final class Language
{
    public const COOKIE = 'site_language';

    // Код языка становится сегментом пути к шаблону, а приходит он от посетителя: поэтому только
    // две строчные латинские буквы. Всё прочее — заявка на чужой каталог, а не язык.
    private const CODE = '/^[a-z]{2}$/';

    private static ?string $current = null;

    private static bool $fromCookie = false;

    private function __construct()
    {
    }

    #[Purpose('Годен ли код как язык: две строчные латинские буквы. Код становится именем каталога шаблонов.')]
    #[Param('code', 'mixed', 'проверяемое значение')]
    #[Returns('bool', 'true, если это код языка')]
    #[Effect(Effect::NONE)]
    #[Gives(['ru'], true)]
    #[Gives(['en'], true)]
    #[Gives(['EN'], false)]
    #[Gives(['english'], false)]
    #[Gives(['../x'], false)]
    #[Gives([42], false)]
    public static function isCode(mixed $code): bool
    {
        return is_string($code) && preg_match(self::CODE, $code) === 1;
    }

    #[Purpose('Выбирает язык: годное значение печенья, иначе язык из конфига.')]
    #[Param('cookies', 'array', 'присланные печенья, обычно $_COOKIE')]
    #[Param('default', 'string', 'язык из конфига — константа LANGUAGE')]
    #[Returns('string', 'код языка')]
    #[Effect(Effect::NONE)]
    #[Gives([[], 'ru'], 'ru')]
    #[Gives([['site_language' => 'en'], 'ru'], 'en')]
    #[Gives([['site_language' => 'EN'], 'ru'], 'ru')]
    #[Gives([['site_language' => 'english'], 'ru'], 'ru')]
    #[Gives([['site_language' => '../x'], 'ru'], 'ru')]
    #[Gives([['site_language' => 42], 'ru'], 'ru')]
    public static function pick(array $cookies, string $default): string
    {
        $code = $cookies[self::COOKIE] ?? null;

        return self::isCode($code) ? $code : $default;
    }

    #[Purpose('Ставит язык запроса и запоминает, пришёл ли он из печенья.')]
    #[Param('cookies', 'array', 'присланные печенья, обычно $_COOKIE')]
    #[Effect(Effect::INPUT)]
    public static function detect(array $cookies): void
    {
        self::$fromCookie = self::isCode($cookies[self::COOKIE] ?? null);
        self::$current    = self::pick($cookies, self::configured());
    }

    #[Purpose('Язык запроса. До detect — язык из конфига: так работают консольные команды.')]
    #[Returns('string', 'код языка')]
    #[Effect(Effect::NONE)]
    public static function current(): string
    {
        return self::$current ??= self::configured();
    }

    #[Purpose('Путь к файлу нужного языка: <каталог>/<язык>/<файл>. Нет перевода — берётся файл языка по умолчанию.')]
    #[Param('dir', 'string', 'каталог, внутри которого лежат каталоги языков')]
    #[Param('file', 'string', 'имя файла внутри каталога языка')]
    #[Param('lang', 'string|null', 'код языка; null — язык запроса')]
    #[Returns('string|null', 'путь к существующему файлу; null — нет ни перевода, ни оригинала')]
    #[Effect(Effect::FS_READ)]
    #[Gives([Token::SANDBOX . '/tpl', 'a.php', 'en'], Token::SANDBOX . '/tpl/en/a.php')]
    #[Gives([Token::SANDBOX . '/tpl', 'a.php', 'de'], Token::SANDBOX . '/tpl/ru/a.php')]
    #[Gives([Token::SANDBOX . '/tpl', 'b.php', 'ru'], null)]
    public static function file(string $dir, string $file, ?string $lang = null): ?string
    {
        $lang = $lang ?? self::current();
        $path = $dir . '/' . $lang . '/' . $file;

        if (is_file($path)) {
            return $path;
        }

        // Неполный перевод не должен ронять сайт: посетитель с чужим печеньем получает
        // файл языка по умолчанию. Смешение языков возможно только там, где перевод
        // объявлен каталогом, но файла в нём нет.
        $default = self::configured();

        if ($lang === $default) {
            return null;
        }

        $path = $dir . '/' . $default . '/' . $file;

        return is_file($path) ? $path : null;
    }

    #[Purpose('Пришёл ли язык из печенья: тогда ответ зависит от посетителя, и общий кэш его не хранит.')]
    #[Returns('bool', 'true, если годное печенье site_language было')]
    #[Effect(Effect::NONE)]
    public static function fromCookie(): bool
    {
        return self::$fromCookie;
    }

    #[Purpose('Язык из конфига — константа LANGUAGE.')]
    #[Returns('string', 'код языка')]
    #[Throws(LanguageMisuse::class, 'константы нет или она не код языка')]
    #[Effect(Effect::NONE)]
    private static function configured(): string
    {
        if (!defined('LANGUAGE') || !is_string(LANGUAGE) || preg_match(self::CODE, LANGUAGE) !== 1) {
            throw new LanguageMisuse(
                'в app/config.php нужна константа LANGUAGE — код языка из двух строчных латинских букв, например "ru"'
            );
        }

        return LANGUAGE;
    }
}
