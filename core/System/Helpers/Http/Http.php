<?php

namespace Maws\System\Helpers\Http;

use Maws\System\Contract\{Effect, Fails, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Http\Exception\HttpMisuse;
use Maws\System\Helpers\Http\Exception\HttpUnreachable;

#[Subject(Http::class, 'new')]
#[Purpose('Точка входа в запросы к чужим сервисам: сразу выполнить или набрать настройки цепочкой. Сам состояния не держит — настраивается конкретный запрос, а не «Http вообще». Сбои транспорта переводятся в HttpUnreachable, потому что исключения чужой библиотеки не нашего рода и проскочили бы мимо слоя исключений MAWS; ответы 4xx и 5xx — это ответ, а не исключение.')]
final class Http
{
    private function __construct()
    {
    }

    #[Purpose('Выполняет GET сразу, без настроек.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('query', 'array', 'параметры адреса')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой: адрес недоступен, соединение оборвалось')]
    #[Effect(Effect::HTTP)]
    public static function get(string $url, array $query = []): Response
    {
        return (new PendingRequest())->get($url, $query);
    }

    #[Purpose('Выполняет HEAD сразу: только заголовки, без тела.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('query', 'array', 'параметры адреса')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public static function head(string $url, array $query = []): Response
    {
        return (new PendingRequest())->head($url, $query);
    }

    #[Purpose('Выполняет POST сразу, с данными в теле.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('data', 'array', 'полезная нагрузка: по умолчанию уйдёт как JSON')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public static function post(string $url, array $data = []): Response
    {
        return (new PendingRequest())->post($url, $data);
    }

    #[Purpose('Выполняет PUT сразу, с данными в теле.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('data', 'array', 'полезная нагрузка')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public static function put(string $url, array $data = []): Response
    {
        return (new PendingRequest())->put($url, $data);
    }

    #[Purpose('Выполняет PATCH сразу, с данными в теле.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('data', 'array', 'полезная нагрузка')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public static function patch(string $url, array $data = []): Response
    {
        return (new PendingRequest())->patch($url, $data);
    }

    #[Purpose('Выполняет DELETE сразу.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('data', 'array', 'полезная нагрузка')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public static function delete(string $url, array $data = []): Response
    {
        return (new PendingRequest())->delete($url, $data);
    }

    #[Purpose('Начинает запрос с заголовками; выполнение — следующим шагом цепочки.')]
    #[Param('headers', 'array', 'заголовки: имя → значение')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function withHeaders(array $headers): PendingRequest
    {
        return (new PendingRequest())->withHeaders($headers);
    }

    #[Purpose('Начинает запрос с токеном в заголовке Authorization.')]
    #[Param('token', 'string', 'токен')]
    #[Param('type', 'string', 'вид токена: Bearer, Basic и подобные')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function withToken(string $token, string $type = 'Bearer'): PendingRequest
    {
        return (new PendingRequest())->withToken($token, $type);
    }

    #[Purpose('Начинает запрос с парой логин-пароль.')]
    #[Param('user', 'string', 'имя пользователя')]
    #[Param('password', 'string', 'пароль')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function withBasicAuth(string $user, string $password): PendingRequest
    {
        return (new PendingRequest())->withBasicAuth($user, $password);
    }

    #[Purpose('Начинает запрос с постоянными параметрами адреса.')]
    #[Param('query', 'array', 'параметры адреса')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function withQueryParameters(array $query): PendingRequest
    {
        return (new PendingRequest())->withQueryParameters($query);
    }

    #[Purpose('Начинает запрос с указанием клиента.')]
    #[Param('agent', 'string', 'строка User-Agent')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function withUserAgent(string $agent): PendingRequest
    {
        return (new PendingRequest())->withUserAgent($agent);
    }

    #[Purpose('Начинает запрос с заранее заданными печеньями для домена.')]
    #[Param('cookies', 'array', 'печенья: имя → значение')]
    #[Param('domain', 'string', 'домен, которому они принадлежат')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function withCookies(array $cookies, string $domain): PendingRequest
    {
        return (new PendingRequest())->withCookies($cookies, $domain);
    }

    #[Purpose('Начинает запрос с телом-строкой как есть.')]
    #[Param('body', 'string', 'тело запроса')]
    #[Param('contentType', 'string', 'вид содержимого')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function withBody(string $body, string $contentType = 'application/octet-stream'): PendingRequest
    {
        return (new PendingRequest())->withBody($body, $contentType);
    }

    #[Purpose('Начинает запрос, который ждёт ответа в JSON.')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function acceptJson(): PendingRequest
    {
        return (new PendingRequest())->acceptJson();
    }

    #[Purpose('Начинает запрос, который отправляет данные как JSON.')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function asJson(): PendingRequest
    {
        return (new PendingRequest())->asJson();
    }

    #[Purpose('Начинает запрос, который отправляет данные как форму.')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function asForm(): PendingRequest
    {
        return (new PendingRequest())->asForm();
    }

    #[Purpose('Начинает запрос с таймаутом в секундах.')]
    #[Param('seconds', 'int', 'сколько ждать; ноль — без таймаута')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Throws(HttpMisuse::class, 'таймаут отрицательный')]
    #[Effect(Effect::NONE)]
    #[Fails([-1], HttpMisuse::class)]
    public static function timeout(int $seconds): PendingRequest
    {
        return (new PendingRequest())->timeout($seconds);
    }

    #[Purpose('Начинает запрос с таймаутом на установку соединения.')]
    #[Param('seconds', 'int', 'сколько ждать; ноль — без таймаута')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Throws(HttpMisuse::class, 'таймаут отрицательный')]
    #[Effect(Effect::NONE)]
    #[Fails([-1], HttpMisuse::class)]
    public static function connectTimeout(int $seconds): PendingRequest
    {
        return (new PendingRequest())->connectTimeout($seconds);
    }

    #[Purpose('Начинает запрос, который не ходит по перенаправлениям.')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function withoutRedirecting(): PendingRequest
    {
        return (new PendingRequest())->withoutRedirecting();
    }

    #[Purpose('Начинает запрос с ограничением числа перенаправлений.')]
    #[Param('redirects', 'int', 'сколько перенаправлений разрешено')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Throws(HttpMisuse::class, 'число перенаправлений отрицательное')]
    #[Effect(Effect::NONE)]
    #[Fails([-1], HttpMisuse::class)]
    public static function maxRedirects(int $redirects): PendingRequest
    {
        return (new PendingRequest())->maxRedirects($redirects);
    }

    #[Purpose('Начинает запрос с повторами при сбое.')]
    #[Param('times', 'int', 'сколько раз повторять')]
    #[Param('sleepMs', 'int', 'пауза между повторами в миллисекундах')]
    #[Param('when', 'callable|null', 'решение о повторе; null — повторять сбой транспорта и ответы 5xx')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Throws(HttpMisuse::class, 'число повторов или пауза отрицательные')]
    #[Effect(Effect::NONE)]
    #[Fails([-1], HttpMisuse::class)]
    public static function retry(int $times, int $sleepMs = 0, ?callable $when = null): PendingRequest
    {
        return (new PendingRequest())->retry($times, $sleepMs, $when);
    }

    #[Purpose('Начинает запрос с сырыми настройками Guzzle поверх набранных.')]
    #[Param('options', 'array', 'настройки Guzzle')]
    #[Returns('PendingRequest', 'запрос, который ещё не отправлен')]
    #[Effect(Effect::NONE)]
    public static function withOptions(array $options): PendingRequest
    {
        return (new PendingRequest())->withOptions($options);
    }
}
