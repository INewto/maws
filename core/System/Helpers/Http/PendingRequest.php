<?php

namespace Maws\System\Helpers\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws, Token};
use Maws\System\Helpers\Http\Exception\HttpMisuse;
use Maws\System\Helpers\Http\Exception\HttpUnreachable;
use Maws\System\Helpers\Json\Exception\JsonMisuse;
use Maws\System\Helpers\Json\Json;
use Psr\Http\Message\ResponseInterface;

#[Subject(PendingRequest::class, 'new')]
#[Purpose('Запрос, который ещё не отправлен: накопленные настройки плюс отправка. Цепочка живёт здесь, а не в Http, потому что настраивается конкретный запрос, а не «Http вообще»; каждый шаг возвращает тот же объект, поэтому цепочка читается слева направо, а уходит запрос в конце. Три умолчания: тело — JSON, записанный без экранирования кириллицы и слэшей (нужна форма — asForm, нужна строка как есть — withBody); 4xx и 5xx — это ответ, а не исключение (хочешь отказ — Response::throw); исключения Guzzle наружу не выходят — транспортный сбой становится HttpUnreachable.')]
class PendingRequest
{
    /** @var array<string, mixed> сырые настройки Guzzle */
    private array $options = [];

    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<string, mixed> */
    private array $query = [];

    private ?string $body = null;

    /** json | form | raw */
    private string $bodyFormat = 'json';

    private ?int $retryTimes = null;

    private int $retrySleep = 0;

    /** @var callable|null */
    private $retryWhen = null;

    // --- настройки ---

    #[Purpose('Добавляет заголовки к уже набранным; одноимённые заменяются.')]
    #[Param('headers', 'array', 'заголовки: имя → значение')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives([['X-A' => 'b']], Token::SUBJECT)]
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    #[Purpose('Ставит заголовок Authorization с токеном.')]
    #[Param('token', 'string', 'токен')]
    #[Param('type', 'string', 'вид токена: Bearer, Basic и подобные')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives(['секрет'], Token::SUBJECT)]
    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeaders(['Authorization' => trim($type . ' ' . $token)]);
    }

    #[Purpose('Ставит пару логин-пароль для проверки подлинности.')]
    #[Param('user', 'string', 'имя пользователя')]
    #[Param('password', 'string', 'пароль')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives(['Аня', 'секрет'], Token::SUBJECT)]
    public function withBasicAuth(string $user, string $password): static
    {
        $this->options[RequestOptions::AUTH] = [$user, $password];

        return $this;
    }

    #[Purpose('Добавляет постоянные параметры адреса; одноимённые заменяются.')]
    #[Param('query', 'array', 'параметры адреса')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives([['page' => 2]], Token::SUBJECT)]
    public function withQueryParameters(array $query): static
    {
        $this->query = array_merge($this->query, $query);

        return $this;
    }

    #[Purpose('Представляется чужим сервисам заданным именем клиента.')]
    #[Param('agent', 'string', 'строка User-Agent')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives(['MAWS'], Token::SUBJECT)]
    public function withUserAgent(string $agent): static
    {
        return $this->withHeaders(['User-Agent' => $agent]);
    }

    #[Purpose('Отправляет заранее заданные печенья указанному домену.')]
    #[Param('cookies', 'array', 'печенья: имя → значение')]
    #[Param('domain', 'string', 'домен, которому они принадлежат')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives([['sid' => 'abc'], 'example.test'], Token::SUBJECT)]
    public function withCookies(array $cookies, string $domain): static
    {
        $jar = [];

        foreach ($cookies as $name => $value) {
            $jar[] = ['Name' => (string) $name, 'Value' => (string) $value, 'Domain' => $domain];
        }

        $this->options[RequestOptions::COOKIES] = new CookieJar(false, $jar);

        return $this;
    }

    #[Purpose('Задаёт тело строкой как есть — для готового JSON, XML или двоичных данных. После этого данные вторым аргументом отправки не принимаются.')]
    #[Param('body', 'string', 'тело запроса')]
    #[Param('contentType', 'string', 'вид содержимого')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives(['{"a":1}', 'application/json'], Token::SUBJECT)]
    public function withBody(string $body, string $contentType = 'application/octet-stream'): static
    {
        $this->body       = $body;
        $this->bodyFormat = 'raw';

        return $this->withHeaders(['Content-Type' => $contentType]);
    }

    #[Purpose('Просит у чужой стороны ответ в JSON.')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives([], Token::SUBJECT)]
    public function acceptJson(): static
    {
        return $this->withHeaders(['Accept' => 'application/json']);
    }

    #[Purpose('Отправляет данные как JSON — умолчание, повторяется для читаемости цепочки.')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives([], Token::SUBJECT)]
    public function asJson(): static
    {
        $this->bodyFormat = 'json';

        return $this;
    }

    #[Purpose('Отправляет данные как форму, а не как JSON.')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives([], Token::SUBJECT)]
    public function asForm(): static
    {
        $this->bodyFormat = 'form';

        return $this;
    }

    #[Purpose('Ограничивает ожидание ответа; ноль — законное «без таймаута».')]
    #[Param('seconds', 'int', 'сколько ждать; ноль — без таймаута')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Throws(HttpMisuse::class, 'таймаут отрицательный')]
    #[Effect(Effect::NONE)]
    #[Gives([5], Token::SUBJECT)]
    #[Gives([0], Token::SUBJECT)]
    #[Fails([-1], HttpMisuse::class)]
    public function timeout(int $seconds): static
    {
        $this->options[RequestOptions::TIMEOUT] = $this->positive($seconds, 'таймаут', true);

        return $this;
    }

    #[Purpose('Ограничивает ожидание установки соединения; ноль — законное «без таймаута».')]
    #[Param('seconds', 'int', 'сколько ждать; ноль — без таймаута')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Throws(HttpMisuse::class, 'таймаут отрицательный')]
    #[Effect(Effect::NONE)]
    #[Gives([3], Token::SUBJECT)]
    #[Fails([-1], HttpMisuse::class)]
    public function connectTimeout(int $seconds): static
    {
        $this->options[RequestOptions::CONNECT_TIMEOUT] = $this->positive($seconds, 'таймаут соединения', true);

        return $this;
    }

    #[Purpose('Запрещает ходить по перенаправлениям: ответ 3xx вернётся как есть.')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives([], Token::SUBJECT)]
    public function withoutRedirecting(): static
    {
        $this->options[RequestOptions::ALLOW_REDIRECTS] = false;

        return $this;
    }

    #[Purpose('Ограничивает число перенаправлений, по которым пойдёт запрос.')]
    #[Param('redirects', 'int', 'сколько перенаправлений разрешено')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Throws(HttpMisuse::class, 'число перенаправлений отрицательное')]
    #[Effect(Effect::NONE)]
    #[Gives([2], Token::SUBJECT)]
    #[Gives([0], Token::SUBJECT)]
    #[Fails([-1], HttpMisuse::class)]
    public function maxRedirects(int $redirects): static
    {
        $this->options[RequestOptions::ALLOW_REDIRECTS] = ['max' => $this->positive($redirects, 'число перенаправлений', true)];

        return $this;
    }

    #[Purpose('Повторяет запрос при сбое. Без своего решения повторяются транспортный сбой и ответы 5xx; своё решение получает номер попытки, ответ и ошибку.')]
    #[Param('times', 'int', 'сколько раз повторять')]
    #[Param('sleepMs', 'int', 'пауза между повторами в миллисекундах')]
    #[Param('when', 'callable|null', 'решение о повторе: function (int $attempts, ?ResponseInterface $response, ?Throwable $error): bool')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Throws(HttpMisuse::class, 'число повторов или пауза отрицательные')]
    #[Effect(Effect::NONE)]
    #[Gives([3], Token::SUBJECT)]
    #[Gives([2, 500], Token::SUBJECT)]
    #[Fails([-1], HttpMisuse::class)]
    #[Fails([2, -1], HttpMisuse::class)]
    public function retry(int $times, int $sleepMs = 0, ?callable $when = null): static
    {
        $this->retryTimes = $this->positive($times, 'число повторов', true);
        $this->retrySleep = $this->positive($sleepMs, 'задержка между повторами', true);
        $this->retryWhen  = $when;

        return $this;
    }

    #[Purpose('Добавляет сырые настройки Guzzle поверх набранных — выход для того, что хелпер не выражает.')]
    #[Param('options', 'array', 'настройки Guzzle')]
    #[Returns('static', 'этот же запрос — цепочка продолжается')]
    #[Effect(Effect::NONE)]
    #[Gives([['verify' => false]], Token::SUBJECT)]
    public function withOptions(array $options): static
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    // --- отправка ---

    #[Purpose('Выполняет GET набранным запросом.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('query', 'array', 'параметры адреса — добавляются к набранным')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public function get(string $url, array $query = []): Response
    {
        return $this->send('GET', $url, [], $query);
    }

    #[Purpose('Выполняет HEAD набранным запросом: только заголовки.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('query', 'array', 'параметры адреса')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public function head(string $url, array $query = []): Response
    {
        return $this->send('HEAD', $url, [], $query);
    }

    #[Purpose('Выполняет POST набранным запросом.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('data', 'array', 'полезная нагрузка: json, форма или ничего, если тело задано строкой')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpMisuse::class, 'тело задано строкой, а данные переданы вторым аргументом')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public function post(string $url, array $data = []): Response
    {
        return $this->send('POST', $url, $data);
    }

    #[Purpose('Выполняет PUT набранным запросом.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('data', 'array', 'полезная нагрузка')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpMisuse::class, 'тело задано строкой, а данные переданы вторым аргументом')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public function put(string $url, array $data = []): Response
    {
        return $this->send('PUT', $url, $data);
    }

    #[Purpose('Выполняет PATCH набранным запросом.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('data', 'array', 'полезная нагрузка')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpMisuse::class, 'тело задано строкой, а данные переданы вторым аргументом')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public function patch(string $url, array $data = []): Response
    {
        return $this->send('PATCH', $url, $data);
    }

    #[Purpose('Выполняет DELETE набранным запросом.')]
    #[Param('url', 'string', 'адрес')]
    #[Param('data', 'array', 'полезная нагрузка')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpMisuse::class, 'тело задано строкой, а данные переданы вторым аргументом')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой')]
    #[Effect(Effect::HTTP)]
    public function delete(string $url, array $data = []): Response
    {
        return $this->send('DELETE', $url, $data);
    }

    #[Purpose('Отправляет набранный запрос произвольным методом. Отказ 4xx и 5xx — это ответ: исключением он становится только через Response::throw.')]
    #[Param('method', 'string', 'метод HTTP')]
    #[Param('url', 'string', 'адрес')]
    #[Param('data', 'array', 'полезная нагрузка: json, форма или ничего, если тело задано строкой')]
    #[Param('query', 'array', 'параметры адреса — добавляются к набранным')]
    #[Returns('Response', 'ответ чужой стороны')]
    #[Throws(HttpMisuse::class, 'тело задано строкой (withBody), а данные переданы вторым аргументом: молча отбросить их нельзя — запрос ушёл бы пустым')]
    #[Throws(HttpMisuse::class, 'данные не кодируются в JSON: не UTF-8, бесконечность или NaN')]
    #[Throws(HttpUnreachable::class, 'транспортный сбой: адрес недоступен, соединение оборвалось')]
    #[Effect(Effect::HTTP)]
    #[Fails(['POST', 'http://example.test/users', ['name' => 'Аня']], HttpMisuse::class, [['withBody', ['{"name":"Аня"}']]])]
    #[Fails(['POST', 'http://example.test/users', ['sum' => NAN]], HttpMisuse::class)]
    #[Fails(['GET', 'http://127.0.0.1:1/'], HttpUnreachable::class, [['timeout', [2]]])]
    public function send(string $method, string $url, array $data = [], array $query = []): Response
    {
        $options = $this->options;

        // 4xx и 5xx — ответ, а не исключение
        $options[RequestOptions::HTTP_ERRORS] = false;

        $headers = array_merge($options[RequestOptions::HEADERS] ?? [], $this->headers);
        if ($headers !== []) {
            $options[RequestOptions::HEADERS] = $headers;
        }

        $query = array_merge($options[RequestOptions::QUERY] ?? [], $this->query, $query);
        if ($query !== []) {
            $options[RequestOptions::QUERY] = $query;
        }

        if ($this->bodyFormat === 'raw') {
            if ($data !== []) {
                // Молча отбросить данные нельзя: запрос уйдёт пустым, а вызывающий
                // будет считать, что отправил их.
                throw new HttpMisuse(
                    'тело уже задано строкой (withBody), поэтому данные вторым аргументом не принять — '
                    . 'убери withBody или используй asJson/asForm'
                );
            }

            $options[RequestOptions::BODY] = $this->body ?? '';
        } elseif ($data !== []) {
            if ($this->bodyFormat === 'form') {
                $options[RequestOptions::FORM_PARAMS] = $data;
            } else {
                $options[RequestOptions::BODY]    = self::encode($data);
                $options[RequestOptions::HEADERS] = self::withContentType(
                    $options[RequestOptions::HEADERS] ?? [],
                    'application/json'
                );
            }
        }

        $stats = null;
        if (!isset($options[RequestOptions::ON_STATS])) {
            $options[RequestOptions::ON_STATS] = static function (TransferStats $transfer) use (&$stats): void {
                $stats = $transfer;
            };
        }

        $client = new Client($this->retryTimes === null ? [] : ['handler' => $this->handler()]);

        try {
            $psr = $client->request($method, $url, $options);
        } catch (GuzzleException $e) {
            // Наружу исключение чужой библиотеки не пускаем: оно не нашего рода
            // и во входе миновало бы catch (MawsException).
            throw new HttpUnreachable($method, $url, $e);
        }

        return new Response($psr, $method, $url, $stats);
    }

    // --- внутреннее ---

    private function handler(): HandlerStack
    {
        $stack = HandlerStack::create();

        $decider = function (
            int $attempts,
            mixed $request,
            ?ResponseInterface $response = null,
            mixed $error = null
        ): bool {
            if ($attempts >= $this->retryTimes) {
                return false;
            }

            if ($this->retryWhen !== null) {
                return (bool) ($this->retryWhen)($attempts, $response, $error);
            }

            if ($error !== null) {
                return true;
            }

            return $response !== null && $response->getStatusCode() >= 500;
        };

        $stack->push(Middleware::retry($decider, fn (int $attempts): int => $this->retrySleep));

        return $stack;
    }

    private function positive(int $value, string $what, bool $zeroAllowed = false): int
    {
        $lowest = $zeroAllowed ? 0 : 1;

        if ($value < $lowest) {
            throw new HttpMisuse("{$what} не может быть меньше {$lowest}: {$value}");
        }

        return $value;
    }

    private static function encode(array $data): string
    {
        try {
            return Json::encode($data);
        } catch (JsonMisuse $e) {
            throw new HttpMisuse($e->getMessage());
        }
    }

    /** @param array<string, mixed> $headers */
    private static function withContentType(array $headers, string $type): array
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'content-type') {
                return $headers;
            }
        }

        return $headers + ['Content-Type' => $type];
    }
}
