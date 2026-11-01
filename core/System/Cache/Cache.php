<?php

namespace Maws\System\Cache;

use Maws\System\Contract\{Effect, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Exception\ResponseSent;
use Maws\System\Language;

/**
 * Часть ядра, а не хелпер: заголовки кэша и ответ 304 печатает экран, а хелперы — необязательный
 * слой, который разработчик вправе удалить целиком, ничего не сломав.
 */
#[Subject(Cache::class, 'new', [])]
#[Purpose('Заголовки кэша и ответ 304: по готовому телу считает ETag, сверяет If-None-Match и отдаёт тело или пустой ответ.')]
final class Cache
{
    private function __construct()
    {
    }

    #[Purpose('Собирает строку Cache-Control из чисел политики.')]
    #[Param('ttl', 'int|null', 'секунды свежести; null — не хранить')]
    #[Param('public', 'bool', 'разрешено ли общему кэшу отдавать копию другим')]
    #[Returns('string', 'значение заголовка Cache-Control')]
    #[Effect(Effect::NONE)]
    #[Gives([null, false], 'no-store')]
    #[Gives([0, false], 'private, no-cache')]
    #[Gives([0, true], 'public, no-cache')]
    #[Gives([60, true], 'public, max-age=60')]
    #[Gives([60, false], 'private, max-age=60')]
    public static function line(?int $ttl, bool $public): string
    {
        if ($ttl === null) {
            return 'no-store';
        }

        $scope = $public ? 'public' : 'private';

        return $ttl === 0 ? $scope . ', no-cache' : $scope . ', max-age=' . $ttl;
    }

    #[Purpose('Совпадает ли ETag ответа с присланным If-None-Match. Сравнение слабое, как требует RFC: W/ снимается.')]
    #[Param('etag', 'string', 'наш ETag вместе с кавычками')]
    #[Param('header', 'string', 'значение If-None-Match; пустая строка — заголовка не было')]
    #[Returns('bool', 'true, если копия у клиента та же')]
    #[Effect(Effect::NONE)]
    #[Gives(['"a"', '"a"'], true)]
    #[Gives(['"a"', '"b", "a"'], true)]
    #[Gives(['"a"', 'W/"a"'], true)]
    #[Gives(['"a"', '*'], true)]
    #[Gives(['"a"', ''], false)]
    #[Gives(['"a"', '"b"'], false)]
    public static function notModified(string $etag, string $header): bool
    {
        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '*') {
                return true;
            }

            if (str_starts_with($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }

            if ($candidate === $etag) {
                return true;
            }
        }

        return false;
    }

    #[Purpose('Отдаёт готовое тело с заголовками кэша: 200 с телом, 304 без тела или 200 без тела для HEAD. Завершает ответ.')]
    #[Param('body', 'string', 'готовая разметка страницы')]
    #[Param('policy', 'Policy', 'политика места')]
    #[Throws(ResponseSent::class, 'ответ ушёл: заголовки выставлены, тело отдано или подавлено')]
    #[Effect(Effect::SCREEN)]
    public static function send(string $body, Policy $policy): never
    {
        $public = $policy->isPublic();

        // Общий кэш не должен получить ответ, который ставит печенье: иначе чужой
        // идентификатор сессии уедет следующему посетителю. Язык из печенья делает ответ
        // личным по той же причине: копия ушла бы человеку с другим языком.
        if ($public && (self::setsCookie() || Language::fromCookie())) {
            $public = false;
        }

        header('Cache-Control: ' . self::line($policy->ttl(), $public));

        if ($policy->ttl() === null) {
            echo $body;

            throw new ResponseSent();
        }

        $etag = '"' . hash('xxh128', $body) . '"';
        header('ETag: ' . $etag);

        if (self::notModified($etag, $_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) {
            http_response_code(304);

            throw new ResponseSent();
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            throw new ResponseSent();
        }

        echo $body;

        throw new ResponseSent();
    }

    #[Purpose('Ставит ли этот ответ печенье: общий кэш такого ответа запрещён.')]
    #[Returns('bool', 'true, если среди готовых заголовков есть Set-Cookie')]
    #[Effect(Effect::NONE)]
    private static function setsCookie(): bool
    {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                return true;
            }
        }

        return false;
    }
}
