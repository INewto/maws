<?php

namespace Maws\System\Helpers\Text;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Text\Exception\TextMisuse;
use voku\helper\ASCII;

#[Subject(Text::class, 'new')]
#[Purpose('Одно место, где текст приводится к нужному виду: регистр, длина, пробелы, адресная часть и русское склонение. Работа идёт знаками, а не байтами, поэтому кириллица не рвётся. Локаль не нужна: правила склонения русские и заданы здесь, конфиг не читается.')]
final class Text
{
    private const ENCODING = 'UTF-8';

    private function __construct()
    {
    }

    #[Purpose('Считает знаки, а не байты.')]
    #[Param('value', 'string', 'текст как он есть')]
    #[Returns('int', 'число знаков')]
    #[Effect(Effect::NONE)]
    #[Gives(['Привет'], 6)]
    #[Gives([''], 0)]
    #[Gives(['Ёж'], 2)]
    public static function length(string $value): int
    {
        return mb_strlen($value, self::ENCODING);
    }

    #[Purpose('Обрезает текст по знакам, приписывая хвост только тогда, когда обрезать пришлось.')]
    #[Param('value', 'string', 'текст как он есть')]
    #[Param('length', 'int', 'сколько знаков оставить; меньше одного — ошибка разработчика')]
    #[Param('end', 'string', 'хвост, приписываемый к обрезанному тексту')]
    #[Returns('string', 'текст не длиннее length знаков плюс хвост')]
    #[Throws(TextMisuse::class, 'length меньше единицы — обрезать не до чего')]
    #[Effect(Effect::NONE)]
    #[Gives(['Привет мир', 6], 'Привет…')]
    #[Gives(['Привет', 6], 'Привет')]
    #[Gives(['Привет мир', 6, '...'], 'Привет...')]
    #[Gives(['Привет', 6, '...'], 'Привет')]
    #[Fails(['Привет', 0], TextMisuse::class)]
    #[Fails(['Привет', -1], TextMisuse::class)]
    public static function limit(string $value, int $length, string $end = '…'): string
    {
        if ($length < 1) {
            throw new TextMisuse("обрезать можно до одного знака и больше, получено {$length}");
        }

        if (mb_strlen($value, self::ENCODING) <= $length) {
            return $value;
        }

        return mb_substr($value, 0, $length, self::ENCODING) . $end;
    }

    #[Purpose('Поднимает регистр знаков.')]
    #[Param('value', 'string', 'текст как он есть')]
    #[Returns('string', 'текст в верхнем регистре')]
    #[Effect(Effect::NONE)]
    #[Gives(['Привет'], 'ПРИВЕТ')]
    #[Gives(['hello'], 'HELLO')]
    #[Gives([''], '')]
    public static function upper(string $value): string
    {
        return mb_strtoupper($value, self::ENCODING);
    }

    #[Purpose('Опускает регистр знаков.')]
    #[Param('value', 'string', 'текст как он есть')]
    #[Returns('string', 'текст в нижнем регистре')]
    #[Effect(Effect::NONE)]
    #[Gives(['ПРИВЕТ'], 'привет')]
    #[Gives(['Hello'], 'hello')]
    #[Gives([''], '')]
    public static function lower(string $value): string
    {
        return mb_strtolower($value, self::ENCODING);
    }

    #[Purpose('Делает каждое слово с заглавной, остальные знаки опускает.')]
    #[Param('value', 'string', 'текст как он есть')]
    #[Returns('string', 'текст, где каждое слово начинается с заглавной')]
    #[Effect(Effect::NONE)]
    #[Gives(['иван петров'], 'Иван Петров')]
    #[Gives(['КОРЗИНА ПОКУПОК'], 'Корзина Покупок')]
    #[Gives([''], '')]
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, self::ENCODING);
    }

    #[Purpose('Обрезает края и сжимает повторные пробелы в один.')]
    #[Param('value', 'string', 'текст как он есть')]
    #[Returns('string', 'текст без лишних пробелов')]
    #[Effect(Effect::NONE)]
    #[Gives(['  Привет   мир  '], 'Привет мир')]
    #[Gives(["Привет\n\tмир"], 'Привет мир')]
    #[Gives([''], '')]
    public static function squish(string $value): string
    {
        return trim((string) preg_replace('/(*UCP)\s+/u', ' ', $value));
    }

    #[Purpose('Превращает текст в адресную часть: латиница в нижнем регистре через дефисы. Кириллица транслитерируется, лишние знаки отпадают.')]
    #[Param('value', 'string', 'текст как он есть')]
    #[Returns('string', 'адресная часть')]
    #[Effect(Effect::NONE)]
    #[Gives(['Привет мир'], 'privet-mir')]
    #[Gives(['Ёлка, ёж и мёд'], 'elka-ez-i-med')]
    #[Gives(['  --Уже--слаг--  '], 'uze-slag')]
    #[Gives([''], '')]
    public static function slug(string $value): string
    {
        return ASCII::to_slugify($value);
    }

    #[Purpose('Выбирает русскую форму слова по числу: одна для одного, другая для двух-четырёх, третья для остальных.')]
    #[Param('count', 'int', 'число, к которому относится слово')]
    #[Param('forms', 'array', 'ровно три формы по порядку: для одного, для двух-четырёх, для остальных')]
    #[Returns('string', 'форма, подходящая числу')]
    #[Throws(TextMisuse::class, 'форм не ровно три или они заданы не списком')]
    #[Effect(Effect::NONE)]
    #[Gives([1, ['товар', 'товара', 'товаров']], 'товар')]
    #[Gives([2, ['товар', 'товара', 'товаров']], 'товара')]
    #[Gives([4, ['товар', 'товара', 'товаров']], 'товара')]
    #[Gives([5, ['товар', 'товара', 'товаров']], 'товаров')]
    #[Gives([0, ['товар', 'товара', 'товаров']], 'товаров')]
    #[Gives([11, ['товар', 'товара', 'товаров']], 'товаров')]
    #[Gives([21, ['товар', 'товара', 'товаров']], 'товар')]
    #[Gives([102, ['товар', 'товара', 'товаров']], 'товара')]
    #[Gives([112, ['товар', 'товара', 'товаров']], 'товаров')]
    #[Gives([-1, ['товар', 'товара', 'товаров']], 'товар')]
    #[Fails([1, ['товар', 'товара']], TextMisuse::class)]
    #[Fails([1, ['товар', 'товара', 'товаров', 'лишняя']], TextMisuse::class)]
    #[Fails([1, ['one' => 'товар', 'few' => 'товара', 'many' => 'товаров']], TextMisuse::class)]
    public static function plural(int $count, array $forms): string
    {
        if (!array_is_list($forms) || count($forms) !== 3) {
            throw new TextMisuse('склонение задаётся ровно тремя формами по порядку: для одного, для двух-четырёх, для остальных');
        }

        $hundreds = abs($count) % 100;

        if ($hundreds >= 11 && $hundreds <= 14) {
            return $forms[2];
        }

        return match (abs($count) % 10) {
            1       => $forms[0],
            2, 3, 4 => $forms[1],
            default => $forms[2],
        };
    }
}
