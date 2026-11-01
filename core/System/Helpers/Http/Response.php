<?php

namespace Maws\System\Helpers\Http;

use GuzzleHttp\Psr7\Response as PsrResponse;
use GuzzleHttp\TransferStats;
use JsonException;
use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws, Token};
use Maws\System\Helpers\Http\Exception\HttpFailed;
use Maws\System\Helpers\Http\Exception\HttpMisuse;
use Psr\Http\Message\ResponseInterface;

#[Subject(Response::class, 'fixture', [200, '{"user":{"name":"Аня"},"tags":["a","b"]}', ['X-Trace' => 'abc']])]
#[Purpose('Ответ чужой стороны: код, заголовки, тело и суждение о нём. Обёртка вокруг PSR-7, а не вокруг Guzzle, поэтому движок можно заменить, не трогая этот класс. Строгость в одну сторону — нельзя промолчать: тело, которое не разбирается как JSON, и отсутствующий ключ — отказ, а не тихий null, иначе «сервер отдал HTML вместо JSON» выглядело бы как «поле пустое». Пустое тело отказом не считается: у 204 и HEAD тела нет законно.')]
class Response
{
    #[Param('response', 'ResponseInterface', 'ответ по стандарту PSR-7')]
    #[Param('method', 'string', 'метод запроса, на который это ответ')]
    #[Param('url', 'string', 'адрес запроса, на который это ответ')]
    #[Param('stats', 'TransferStats|null', 'данные передачи: время, адрес, повторы')]
    #[Effect(Effect::NONE)]
    public function __construct(
        private ResponseInterface $response,
        private string $method = '',
        private string $url = '',
        private ?TransferStats $stats = null
    ) {
    }

    #[Purpose('Собирает ответ из частей, без сети: тот же объект, что приходит от запроса. Нужен контракту и ручной сборке — в работе ответ приносит Http.')]
    #[Param('status', 'int', 'код ответа')]
    #[Param('body', 'string', 'тело как есть')]
    #[Param('headers', 'array', 'заголовки «имя → значение»')]
    #[Returns('Response', 'ответ, как будто он пришёл по сети')]
    #[Effect(Effect::NONE)]
    public static function fixture(int $status = 200, string $body = '', array $headers = []): self
    {
        return new self(new PsrResponse($status, $headers, $body));
    }

    #[Purpose('Отдаёт код ответа.')]
    #[Returns('int', 'код ответа')]
    #[Effect(Effect::NONE)]
    #[Gives([], 200)]
    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    #[Purpose('Отдаёт пояснение к коду словами сервера.')]
    #[Returns('string', 'пояснение к коду')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'OK')]
    public function reason(): string
    {
        return $this->response->getReasonPhrase();
    }

    #[Purpose('Отдаёт тело ответа строкой.')]
    #[Returns('string', 'тело ответа')]
    #[Effect(Effect::NONE)]
    #[Gives([], '{"user":{"name":"Аня"},"tags":["a","b"]}')]
    public function body(): string
    {
        return (string) $this->response->getBody();
    }

    #[Purpose('Отдаёт значение заголовка одной строкой.')]
    #[Param('name', 'string', 'имя заголовка')]
    #[Returns('string', 'значение; пустая строка, если заголовка нет')]
    #[Effect(Effect::NONE)]
    #[Gives(['X-Trace'], 'abc')]
    #[Gives(['nope'], '')]
    public function header(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }

    #[Purpose('Отдаёт все заголовки с их значениями.')]
    #[Returns('array', 'заголовки: имя → список значений')]
    #[Effect(Effect::NONE)]
    #[Gives([], ['X-Trace' => ['abc']])]
    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    #[Purpose('Отдаёт метод запроса, на который пришёл этот ответ.')]
    #[Returns('string', 'метод; пусто, если ответ собран не запросом')]
    #[Effect(Effect::NONE)]
    #[Gives([], '')]
    public function method(): string
    {
        return $this->method;
    }

    #[Purpose('Отдаёт адрес запроса, на который пришёл этот ответ.')]
    #[Returns('string', 'адрес; пусто, если ответ собран не запросом')]
    #[Effect(Effect::NONE)]
    #[Gives([], '')]
    public function url(): string
    {
        return $this->url;
    }

    #[Purpose('Отвечает, успешный ли ответ: код от 200 до 299.')]
    #[Returns('bool', 'true, если код успешный')]
    #[Effect(Effect::NONE)]
    #[Gives([], true)]
    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    #[Purpose('Отвечает, ответил ли сервер ровно 200.')]
    #[Returns('bool', 'true, если код 200')]
    #[Effect(Effect::NONE)]
    #[Gives([], true)]
    public function ok(): bool
    {
        return $this->status() === 200;
    }

    #[Purpose('Отвечает, перенаправление ли это: код от 300 до 399.')]
    #[Returns('bool', 'true, если код перенаправления')]
    #[Effect(Effect::NONE)]
    #[Gives([], false)]
    public function redirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }

    #[Purpose('Отвечает, ошибка ли на стороне запроса: код от 400 до 499.')]
    #[Returns('bool', 'true, если код 4xx')]
    #[Effect(Effect::NONE)]
    #[Gives([], false)]
    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    #[Purpose('Отвечает, ошибка ли на стороне сервера: код от 500.')]
    #[Returns('bool', 'true, если код 5xx')]
    #[Effect(Effect::NONE)]
    #[Gives([], false)]
    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    #[Purpose('Отвечает, отказ ли это: 4xx или 5xx.')]
    #[Returns('bool', 'true, если это отказ')]
    #[Effect(Effect::NONE)]
    #[Gives([], false)]
    public function failed(): bool
    {
        return $this->clientError() || $this->serverError();
    }

    #[Purpose('Превращает отказ в исключение — единственный способ узнать об отказе, не проверяя код: по умолчанию отказ исключением не становится.')]
    #[Returns('static', 'этот же ответ, если отказа нет')]
    #[Throws(HttpFailed::class, 'код ответа 4xx или 5xx')]
    #[Effect(Effect::NONE)]
    #[Gives([], Token::SUBJECT)]
    public function throw(): static
    {
        if ($this->failed()) {
            throw new HttpFailed($this);
        }

        return $this;
    }

    #[Purpose('Отдаёт разобранное тело. Без ключа — всё целиком; ключ — точечный путь вида "user.name". Ключа нет и запасное значение не задано — отказ: молчаливый null в ответ на «дай мне данные» неотличим от «данные есть, но пустые».')]
    #[Param('key', 'string|null', 'точечный путь до значения; null — тело целиком')]
    #[Param('default', 'mixed', 'что вернуть, если ключа нет; заданное null отличается от незаданного')]
    #[Returns('mixed', 'разобранное тело или значение по пути')]
    #[Throws(HttpMisuse::class, 'тело не JSON или ключа нет, а запасное значение не задано')]
    #[Effect(Effect::NONE)]
    #[Gives(['user.name'], 'Аня')]
    #[Gives(['tags'], ['a', 'b'])]
    #[Gives(['nope', 'запас'], 'запас')]
    #[Gives(['nope', null], null)]
    #[Fails(['nope'], HttpMisuse::class)]
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $decoded = $this->decode(true);

        if ($key === null) {
            return $decoded;
        }

        $value = $decoded;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                if (func_num_args() > 1) {
                    return $default;
                }

                throw new HttpMisuse("в ответе нет ключа \"{$key}\"");
            }

            $value = $value[$segment];
        }

        return $value;
    }

    #[Purpose('Отдаёт тело объектом: удобно, когда JSON — структура, а не список.')]
    #[Returns('object', 'разобранное тело объектом')]
    #[Throws(HttpMisuse::class, 'тело не JSON или это не объект')]
    #[Effect(Effect::NONE)]
    public function object(): object
    {
        $decoded = $this->decode(false);

        if (!is_object($decoded)) {
            throw new HttpMisuse('тело ответа — не JSON-объект');
        }

        return $decoded;
    }

    #[Purpose('Отдаёт исходный ответ по стандарту PSR-7.')]
    #[Returns('ResponseInterface', 'ответ как есть')]
    #[Effect(Effect::NONE)]
    public function toPsrResponse(): ResponseInterface
    {
        return $this->response;
    }

    #[Purpose('Отдаёт данные передачи: время, адрес, повторы.')]
    #[Returns('array', 'данные передачи; пусто, если ответ собран не запросом')]
    #[Effect(Effect::NONE)]
    #[Gives([], [])]
    public function handlerStats(): array
    {
        return $this->stats === null ? [] : $this->stats->getHandlerStats();
    }

    private function decode(bool $assoc): mixed
    {
        $body = $this->body();

        if (trim($body) === '') {
            return null;
        }

        try {
            return json_decode($body, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpMisuse('тело ответа не разбирается как JSON: ' . $e->getMessage());
        }
    }
}
