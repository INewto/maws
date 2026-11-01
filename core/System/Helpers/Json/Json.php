<?php

namespace Maws\System\Helpers\Json;

use JsonException;
use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Json\Exception\JsonMisuse;

#[Subject(Json::class, 'new')]
#[Purpose('JSON — единственный формат, где PHP молчит об ошибке: разбор отдаёт null, будто данные есть, а NaN и битая кодировка превращаются в 0 или в null. Здесь оба умолчания закрыты: не разобралось — отказ, не закодировалось — отказ. Кириллица и слэши пишутся как есть, чтобы тело можно было читать глазами; дробь с нулевой дробной частью остаётся дробью, иначе тип числа зависел бы от настройки serialize_precision хоста.')]
final class Json
{
    private function __construct()
    {
    }

    #[Purpose('Записывает значение строкой JSON: кириллица и слэши как есть, а неразрешимое значение — отказ, а не null.')]
    #[Param('value', 'string|int|float|bool|array|null', 'что записываем')]
    #[Param('pretty', 'bool', 'true — с отступами и переводами строк, для чтения человеком')]
    #[Returns('string', 'строка JSON')]
    #[Throws(JsonMisuse::class, 'значение не кодируется: NaN, бесконечность, не UTF-8, рекурсия')]
    #[Effect(Effect::NONE)]
    #[Gives([['a' => 'Аня']], '{"a":"Аня"}')]
    #[Gives([['url' => 'http://example.test/a/b']], '{"url":"http://example.test/a/b"}')]
    #[Gives([[]], '[]')]
    #[Gives([[1, 2]], '[1,2]')]
    #[Gives([null], 'null')]
    #[Gives([1.0], '1.0')]
    #[Gives([[1.0, 2]], '[1.0,2]')]
    #[Gives([['a' => 1], true], "{\n    \"a\": 1\n}")]
    #[Fails([NAN], JsonMisuse::class)]
    public static function encode(mixed $value, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

        try {
            return json_encode($value, $pretty ? $flags | JSON_PRETTY_PRINT : $flags);
        } catch (JsonException $e) {
            throw new JsonMisuse('значение не кодируется в JSON: ' . $e->getMessage());
        }
    }

    #[Purpose('Разбирает строку JSON. Пустая строка и мусор — отказ, а не null: «данных нет» и «данные не разобрались» обязаны различаться.')]
    #[Param('json', 'string', 'строка как она пришла')]
    #[Param('assoc', 'bool', 'true — объекты массивами; false — объектами, тогда пустой объект отличим от пустого списка')]
    #[Returns('mixed', 'разобранное значение')]
    #[Throws(JsonMisuse::class, 'строка не разбирается как JSON')]
    #[Effect(Effect::NONE)]
    #[Gives(['{"a":1}'], ['a' => 1])]
    #[Gives(['{"a":{"b":[1,2]}}'], ['a' => ['b' => [1, 2]]])]
    #[Gives(['[]'], [])]
    #[Gives(['"текст"'], 'текст')]
    #[Gives(['123'], 123)]
    #[Gives(['true'], true)]
    #[Gives(['null'], null)]
    #[Fails(['{'], JsonMisuse::class)]
    #[Fails([''], JsonMisuse::class)]
    #[Fails(['{"a":}'], JsonMisuse::class)]
    public static function decode(string $json, bool $assoc = true): mixed
    {
        try {
            return json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonMisuse('строка не разбирается как JSON: ' . $e->getMessage());
        }
    }
}
