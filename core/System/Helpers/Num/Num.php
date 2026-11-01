<?php

namespace Maws\System\Helpers\Num;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Num\Exception\NumMisuse;
use Maws\System\Helpers\Text\Text;

#[Subject(Num::class, 'new')]
#[Purpose('Одно место, где число становится человекочитаемым и разбирается обратно. Разделители заданы русской записью — пробел между разрядами и запятая в дробной части, — но их можно назвать и явно, поэтому сайту на другом языке конфиг не нужен. Вывод и разбор обратны друг другу: parse(format($value)) возвращает то же число.')]
final class Num
{
    private const LANGS = ['rus', 'en'];

    private const SMALL = [
        'rus' => [
            'units' => ['ноль', 'один', 'два', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'],
            'unitsF' => ['ноль', 'одна', 'две', 'три', 'четыре', 'пять', 'шесть', 'семь', 'восемь', 'девять'],
            'teens' => ['десять', 'одиннадцать', 'двенадцать', 'тринадцать', 'четырнадцать', 'пятнадцать', 'шестнадцать', 'семнадцать', 'восемнадцать', 'девятнадцать'],
            'tens' => ['', '', 'двадцать', 'тридцать', 'сорок', 'пятьдесят', 'шестьдесят', 'семьдесят', 'восемьдесят', 'девяносто'],
            'hundreds' => ['', 'сто', 'двести', 'триста', 'четыреста', 'пятьсот', 'шестьсот', 'семьсот', 'восемьсот', 'девятьсот'],
        ],
        'en' => [
            'units' => ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'],
            'unitsF' => ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'],
            'teens' => ['ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'],
            'tens' => ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'],
            'hundreds' => [],
        ],
    ];

    private const SCALES = [
        'rus' => [
            ['', '', ''],
            ['тысяча', 'тысячи', 'тысяч'],
            ['миллион', 'миллиона', 'миллионов'],
            ['миллиард', 'миллиарда', 'миллиардов'],
            ['триллион', 'триллиона', 'триллионов'],
            ['квадриллион', 'квадриллиона', 'квадриллионов'],
        ],
        'en' => [
            ['', '', ''],
            ['thousand', 'thousand', 'thousand'],
            ['million', 'million', 'million'],
            ['billion', 'billion', 'billion'],
            ['trillion', 'trillion', 'trillion'],
            ['quadrillion', 'quadrillion', 'quadrillion'],
        ],
    ];

    private const FEMININE_SCALES = ['rus' => [1], 'en' => []];

    private const MAX_DIGITS = 18;

    private function __construct()
    {
    }

    #[Purpose('Записывает число с разделителями разрядов и дробной части.')]
    #[Param('value', 'float|int', 'число')]
    #[Param('decimals', 'int', 'сколько знаков после запятой оставить; отрицательное — ошибка разработчика')]
    #[Param('decimal', 'string', 'знак дробной части')]
    #[Param('thousands', 'string', 'знак разрядов; пусто — без разделения')]
    #[Returns('string', 'число в человекочитаемой записи')]
    #[Throws(NumMisuse::class, 'знаков меньше нуля, или знак дробной части совпал со знаком разрядов — такая запись неразбираема')]
    #[Effect(Effect::NONE)]
    #[Gives([1234.5, 2], '1 234,50')]
    #[Gives([1234.5, 0], '1 235')]
    #[Gives([-1234.5, 2], '-1 234,50')]
    #[Gives([0, 2], '0,00')]
    #[Gives([1234567.891, 2], '1 234 567,89')]
    #[Gives([1234, 0, ',', ''], '1234')]
    #[Gives([1234.5, 2, '.', ','], '1,234.50')]
    #[Fails([1234.5, -1], NumMisuse::class)]
    #[Fails([1234.5, 2, ',', ','], NumMisuse::class)]
    public static function format(float|int $value, int $decimals = 0, string $decimal = ',', string $thousands = ' '): string
    {
        if ($decimals < 0) {
            throw new NumMisuse("знаков после запятой не может быть меньше нуля, получено {$decimals}");
        }

        if ($decimal === $thousands) {
            throw new NumMisuse("знак дробной части и знак разрядов совпали («{$decimal}») — такую запись нельзя разобрать обратно");
        }

        return number_format($value, $decimals, $decimal, $thousands);
    }

    #[Purpose('Разбирает число из русской записи: любые пробельные знаки между разрядами отпадают (в том числе неразрывный и узкий неразрывный пробел), запятая становится точкой.')]
    #[Param('value', 'string', 'число как его написал пользователь')]
    #[Returns('float|false', 'число или false, если запись не число')]
    #[Effect(Effect::NONE)]
    #[Gives(['1 234,56'], 1234.56)]
    #[Gives(["1\u{00A0}234,56"], 1234.56)]
    #[Gives(["1\u{202F}234,5"], 1234.5)]
    #[Gives(['1234'], 1234.0)]
    #[Gives(['-0,5'], -0.5)]
    #[Gives([' 12,5 '], 12.5)]
    #[Gives(['1,234'], 1.234)]
    #[Gives([''], false)]
    #[Gives(['abc'], false)]
    #[Gives(['1e3'], false)]
    #[Gives(['12,'], false)]
    #[Gives(['1.234,5'], false)]
    public static function parse(string $value): float|false
    {
        $clean = str_replace(',', '.', (string) preg_replace('/(*UCP)\s+/u', '', $value));

        if (preg_match('/^[+-]?(\d+(\.\d+)?|\.\d+)$/', $clean) !== 1) {
            return false;
        }

        return (float) $clean;
    }

    #[Purpose('Держит число в границах: меньше нижней — отдаёт нижнюю, больше верхней — верхнюю.')]
    #[Param('value', 'float|int', 'число')]
    #[Param('min', 'float|int', 'нижняя граница')]
    #[Param('max', 'float|int', 'верхняя граница')]
    #[Returns('float|int', 'число, приведённое к границам')]
    #[Throws(NumMisuse::class, 'нижняя граница больше верхней — приводить не к чему')]
    #[Effect(Effect::NONE)]
    #[Gives([5, 0, 10], 5)]
    #[Gives([15, 0, 10], 10)]
    #[Gives([-5, 0, 10], 0)]
    #[Gives([1.5, 0, 1], 1)]
    #[Fails([5, 10, 0], NumMisuse::class)]
    public static function clamp(float|int $value, float|int $min, float|int $max): float|int
    {
        if ($min > $max) {
            throw new NumMisuse("нижняя граница ({$min}) больше верхней ({$max}) — приводить не к чему");
        }

        return max($min, min($value, $max));
    }

    #[Purpose('Записывает целое число словами. Язык выбирает вызывающий, а не настройка: в одном ответе могут встретиться оба, и решать это за сайт нельзя. Разряды разного рода («одна тысяча», но «один миллион») и склонение названий разрядов учтены внутри.')]
    #[Param('value', 'string', 'целое число цифрами, без разделителей разрядов и без дробной части')]
    #[Param('lang', 'string', 'язык записи: "rus" или "en"')]
    #[Returns('string', 'число словами')]
    #[Throws(NumMisuse::class, 'язык не из списка, запись не целое число или в ней больше 18 цифр')]
    #[Effect(Effect::NONE)]
    #[Gives(['3221', 'rus'], 'три тысячи двести двадцать один')]
    #[Gives(['1234325', 'rus'], 'один миллион двести тридцать четыре тысячи триста двадцать пять')]
    #[Gives(['999', 'rus'], 'девятьсот девяносто девять')]
    #[Gives(['1000', 'rus'], 'одна тысяча')]
    #[Gives(['2000', 'rus'], 'две тысячи')]
    #[Gives(['5000', 'rus'], 'пять тысяч')]
    #[Gives(['21000', 'rus'], 'двадцать одна тысяча')]
    #[Gives(['11000', 'rus'], 'одиннадцать тысяч')]
    #[Gives(['0', 'rus'], 'ноль')]
    #[Gives(['-5', 'rus'], 'минус пять')]
    #[Gives(['3221', 'en'], 'three thousand two hundred twenty-one')]
    #[Gives(['1234325', 'en'], 'one million two hundred thirty-four thousand three hundred twenty-five')]
    #[Gives(['21', 'en'], 'twenty-one')]
    #[Gives(['0', 'en'], 'zero')]
    #[Gives(['-5', 'en'], 'minus five')]
    #[Fails(['3221', 'de'], NumMisuse::class)]
    #[Fails(['12,5', 'rus'], NumMisuse::class)]
    #[Fails(['abc', 'rus'], NumMisuse::class)]
    #[Fails(['', 'rus'], NumMisuse::class)]
    #[Fails(['1234567890123456789', 'rus'], NumMisuse::class)]
    public static function toWords(string $value, string $lang): string
    {
        if (!in_array($lang, self::LANGS, true)) {
            throw new NumMisuse("язык \"{$lang}\" не поддерживается — нужен один из: " . implode(', ', self::LANGS));
        }

        if (preg_match('/^[+-]?\d+$/', $value) !== 1) {
            throw new NumMisuse("записать словами можно только целое число цифрами, получено \"{$value}\"");
        }

        $negative = $value[0] === '-';
        $digits = ltrim(ltrim($value, '+-'), '0');

        if ($digits === '') {
            return $lang === 'rus' ? 'ноль' : 'zero';
        }

        if (strlen($digits) > self::MAX_DIGITS) {
            throw new NumMisuse('в числе больше ' . self::MAX_DIGITS . ' цифр — названий таких разрядов нет');
        }

        $groups = [];

        while ($digits !== '') {
            $groups[] = (int) substr($digits, -3);
            $digits = substr($digits, 0, -3);
        }

        $words = [];

        for ($scale = count($groups) - 1; $scale >= 0; $scale--) {
            $group = $groups[$scale];

            if ($group === 0) {
                continue;
            }

            $feminine = in_array($scale, self::FEMININE_SCALES[$lang], true);
            $part = self::triad($group, $lang, $feminine);

            if ($scale > 0) {
                $part .= ' ' . Text::plural($group, self::SCALES[$lang][$scale]);
            }

            $words[] = $part;
        }

        $result = implode(' ', $words);

        if (!$negative) {
            return $result;
        }

        return ($lang === 'rus' ? 'минус ' : 'minus ') . $result;
    }

    private static function triad(int $group, string $lang, bool $feminine): string
    {
        $table = self::SMALL[$lang];
        $parts = [];
        $hundreds = intdiv($group, 100);
        $rest = $group % 100;

        if ($hundreds > 0) {
            $parts[] = $lang === 'rus'
                ? $table['hundreds'][$hundreds]
                : $table['units'][$hundreds] . ' hundred';
        }

        if ($rest >= 10 && $rest < 20) {
            $parts[] = $table['teens'][$rest - 10];

            return implode(' ', $parts);
        }

        $tens = intdiv($rest, 10);
        $units = $rest % 10;
        $tail = '';

        if ($tens > 0) {
            $tail = $table['tens'][$tens];
        }

        if ($units > 0) {
            $unit = $feminine ? $table['unitsF'][$units] : $table['units'][$units];
            $tail = $tail === '' ? $unit : $tail . ($lang === 'en' ? '-' : ' ') . $unit;
        }

        if ($tail !== '') {
            $parts[] = $tail;
        }

        return implode(' ', $parts);
    }
}
