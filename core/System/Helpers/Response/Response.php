<?php

namespace Maws\System\Helpers\Response;

use Maws\System\Contract\{Effect, Fails, Param, Purpose, Responds, Subject, Throws};
use Maws\System\Exception\ResponseSent;
use Maws\System\Helpers\Json\Exception\JsonMisuse;
use Maws\System\Helpers\Json\Json;
use Maws\System\Helpers\Response\Exception\ResponseMisuse;

#[Subject(Response::class, 'new')]
#[Purpose('Исходящий ответ места: код, тип и тело одной строкой, после которой сцена не рисуется. Не путать с Http\Response — тот описывает ответ, полученный от чужого сервиса, а этот отправляет свой.')]
final class Response
{
    private const HTML = 'text/html; charset=utf-8';
    private const JSON = 'application/json; charset=utf-8';

    private function __construct()
    {
    }

    #[Purpose('Отправляет ответ и завершает работу места: выставляет код и тип, печатает тело. Строка уходит как есть, массив или объект — в JSON.')]
    #[Param('data', 'mixed', 'тело ответа: строка без изменений, остальное — Json::encode')]
    #[Param('status', 'int', 'код ответа, допустимо 100–599')]
    #[Param('type', 'string|null', 'Content-Type; null — text/html для строки и application/json для остального')]
    #[Throws(ResponseSent::class, 'всегда: ответ уже отправлен, сцена не нужна')]
    #[Throws(ResponseMisuse::class, 'код вне 100–599 или вывод начался раньше ответа')]
    #[Throws(JsonMisuse::class, 'данные не кодируются в JSON: битая кодировка, ресурс, рекурсия')]
    #[Effect(Effect::SCREEN)]
    #[Fails(['тело', 700], ResponseMisuse::class)]
    #[Fails(['тело', 99], ResponseMisuse::class)]
    #[Responds(['<h1>Ответ места</h1>'], '<h1>Ответ места</h1>')]
    public static function send(mixed $data, int $status = 200, ?string $type = null): never
    {
        if ($status < 100 || $status > 599) {
            throw new ResponseMisuse("код {$status} — не код ответа: допустимо 100–599");
        }

        // В CLI заголовков нет вовсе, и headers_sent() становится true после первой
        // же строки отчёта — проверка там ложно срабатывает на любом прогоне.
        if (PHP_SAPI !== 'cli' && headers_sent($file, $line)) {
            throw new ResponseMisuse(
                "вывод уже начался в {$file}:{$line} — заголовки не поставить; "
                . 'уберите echo/print до вызова Response::send()'
            );
        }

        $body = is_string($data) ? $data : Json::encode($data);

        http_response_code($status);
        header('Content-Type: ' . ($type ?? (is_string($data) ? self::HTML : self::JSON)));

        // Ответ места собирается под конкретный запрос и политики места не спрашивает:
        // без явного запрета браузер вправе сохранить его эвристически.
        header('Cache-Control: no-store');

        echo $body;

        throw new ResponseSent();
    }

    #[Purpose('Отправляет JSON и завершает работу места: то же, что send с типом application/json, но короче на вызове.')]
    #[Param('data', 'mixed', 'что кодировать: массив, объект или строка с готовым JSON')]
    #[Param('status', 'int', 'код ответа, допустимо 100–599')]
    #[Throws(ResponseSent::class, 'всегда: ответ уже отправлен, сцена не нужна')]
    #[Throws(ResponseMisuse::class, 'код вне 100–599 или вывод начался раньше ответа')]
    #[Throws(JsonMisuse::class, 'данные не кодируются в JSON: битая кодировка, ресурс, рекурсия')]
    #[Effect(Effect::SCREEN)]
    #[Responds([['ok' => true]], '{"ok":true}')]
    public static function json(mixed $data, int $status = 200): never
    {
        self::send($data, $status, self::JSON);
    }
}
