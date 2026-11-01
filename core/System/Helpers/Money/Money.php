<?php

namespace Maws\System\Helpers\Money;

use JsonException;
use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Money\Exception\MoneyMisuse;
use Maws\System\Helpers\Num\Num;
use Maws\System\Helpers\Text\Text;

#[Subject(Money::class, 'fromArray', [self::CONFIG])]
#[Purpose('Показывает, разбирает и называет денежную сумму по правилам, которые задал разработчик. Сумма всегда целое число МИНИМАЛЬНЫХ единиц (копейки, центы) — деньги не проходят через дробное число, иначе копейки теряются на округлении. Сколько этих единиц в валюте, как она пишется и как называется, берётся из политики.')]
final class Money
{
    private const CONFIG = [
        'RUB' => [
            'symbol' => '₽',
            'minor' => 2,
            'after' => true,
            'space' => true,
            'decimal' => ',',
            'thousands' => ' ',
            'lang' => 'rus',
            'unit' => ['рубль', 'рубля', 'рублей'],
            'subunit' => ['копейка', 'копейки', 'копеек'],
        ],
        'USD' => [
            'symbol' => '$',
            'minor' => 2,
            'after' => false,
            'space' => false,
            'decimal' => '.',
            'thousands' => ',',
            'lang' => 'en',
            'unit' => ['dollar', 'dollars', 'dollars'],
            'subunit' => ['cent', 'cents', 'cents'],
        ],
        'JPY' => [
            'symbol' => '¥',
            'minor' => 0,
            'after' => false,
            'space' => false,
            'decimal' => '.',
            'thousands' => ',',
            'lang' => 'en',
            'unit' => ['yen', 'yen', 'yen'],
            'subunit' => ['sen', 'sen', 'sen'],
        ],
    ];

    #[Param('settings', 'MoneySettings', 'политика денег: валюты и их оформление')]
    private function __construct(private readonly MoneySettings $settings)
    {
    }

    #[Purpose('Собирает читателя сумм по готовой политике.')]
    #[Param('settings', 'MoneySettings', 'политика денег')]
    #[Returns('self', 'читатель сумм с этой политикой')]
    #[Effect(Effect::NONE)]
    public static function with(MoneySettings $settings): self
    {
        return new self($settings);
    }

    #[Purpose('Собирает читателя сумм прямо из конфига, каким его пишут в файле или в коде сайта.')]
    #[Param('config', 'array', 'набор «код валюты → описание»')]
    #[Returns('self', 'читатель сумм с этой политикой')]
    #[Throws(MoneyMisuse::class, 'валюта описана неполно, лишним полем или значением не того вида')]
    #[Effect(Effect::NONE)]
    #[Fails([[]], MoneyMisuse::class)]
    public static function fromArray(array $config): self
    {
        return new self(MoneySettings::fromArray($config));
    }

    #[Purpose('Собирает читателя сумм из JSON-файла — чтобы валюты сайта можно было держать рядом с сайтом, а не в коде.')]
    #[Param('path', 'string', 'путь к файлу конфига')]
    #[Returns('self', 'читатель сумм с политикой из файла')]
    #[Throws(MoneyMisuse::class, 'файл не читается или не разбирается как JSON')]
    #[Effect(Effect::FS_READ)]
    #[Fails(['нет-такого-конфига-денег.json'], MoneyMisuse::class)]
    public static function fromFile(string $path): self
    {
        if (!is_readable($path)) {
            throw new MoneyMisuse("конфиг денег не читается: {$path}");
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new MoneyMisuse("конфиг денег не читается: {$path}");
        }

        try {
            $config = \json5_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MoneyMisuse("конфиг денег не разбирается ({$path}): {$e->getMessage()}");
        }

        if (!is_array($config)) {
            throw new MoneyMisuse("конфиг денег должен быть объектом ({$path})");
        }

        return self::fromArray($config);
    }

    #[Purpose('Показывает сумму: разряды, знак дробной части и знак валюты — так, как описана валюта.')]
    #[Param('amount', 'int', 'сумма в минимальных единицах валюты: копейки, центы')]
    #[Param('currency', 'string', 'код валюты, как он назван в конфиге')]
    #[Returns('string', 'запись суммы со знаком валюты')]
    #[Throws(MoneyMisuse::class, 'такой валюты в конфиге нет')]
    #[Effect(Effect::NONE)]
    #[Gives([123456, 'RUB'], '1 234,56 ₽')]
    #[Gives([0, 'RUB'], '0,00 ₽')]
    #[Gives([5, 'RUB'], '0,05 ₽')]
    #[Gives([-123456, 'RUB'], '-1 234,56 ₽')]
    #[Gives([123456, 'USD'], '$1,234.56')]
    #[Gives([123456, 'JPY'], '¥123,456')]
    #[Gives([0, 'JPY'], '¥0')]
    #[Fails([100, 'EUR'], MoneyMisuse::class)]
    public function format(int $amount, string $currency): string
    {
        $spec = $this->settings->currency($currency);
        $step = 10 ** $spec['minor'];
        $units = intdiv(abs($amount), $step);
        $rest = abs($amount) % $step;
        $body = Num::format($units, 0, $spec['decimal'], $spec['thousands']);

        if ($spec['minor'] > 0) {
            $body .= $spec['decimal'] . str_pad((string) $rest, $spec['minor'], '0', STR_PAD_LEFT);
        }

        $number = ($amount < 0 ? '-' : '') . $body;

        if ($spec['symbol'] === '') {
            return $number;
        }

        $gap = $spec['space'] ? ' ' : '';

        return $spec['after']
            ? $number . $gap . $spec['symbol']
            : $spec['symbol'] . $gap . $number;
    }

    #[Purpose('Разбирает запись суммы в минимальные единицы. Точность не теряется: лишние знаки после запятой — не округление, а отказ, потому что молча потерянная копейка в деньгах недопустима.')]
    #[Param('text', 'string', 'запись суммы, как её написал человек: со знаком валюты или без')]
    #[Param('currency', 'string', 'код валюты, как он назван в конфиге')]
    #[Returns('int|false', 'сумма в минимальных единицах или false, если запись не разобрана')]
    #[Throws(MoneyMisuse::class, 'такой валюты в конфиге нет')]
    #[Effect(Effect::NONE)]
    #[Gives(['1 234,56 ₽', 'RUB'], 123456)]
    #[Gives(['1234,56', 'RUB'], 123456)]
    #[Gives(['1 234,5', 'RUB'], 123450)]
    #[Gives(['-1 234,56', 'RUB'], -123456)]
    #[Gives(['$1,234.56', 'USD'], 123456)]
    #[Gives(['1 234,567', 'RUB'], false)]
    #[Gives(['1 234,56', 'JPY'], false)]
    #[Gives(['abc', 'RUB'], false)]
    #[Gives(['', 'RUB'], false)]
    #[Fails(['1', 'EUR'], MoneyMisuse::class)]
    public function toMinor(string $text, string $currency): int|false
    {
        $spec = $this->settings->currency($currency);
        $clean = (string) preg_replace('/(*UCP)^\s+|\s+$/u', '', $text);

        if ($spec['symbol'] !== '') {
            $clean = (string) preg_replace('/(*UCP)^\s+|\s+$/u', '', str_replace($spec['symbol'], '', $clean));
        }

        if ($spec['thousands'] !== '') {
            $clean = str_replace($spec['thousands'], '', $clean);
        }

        $clean = str_replace($spec['decimal'], '.', $clean);

        if (preg_match('/^[+-]?\d+(\.\d+)?$/', $clean) !== 1) {
            return false;
        }

        $negative = $clean[0] === '-';
        $parts = explode('.', ltrim($clean, '+-'), 2);
        $whole = ltrim($parts[0], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = $parts[1] ?? '';

        if (strlen($fraction) > $spec['minor']) {
            return false;
        }

        $units = filter_var($whole, FILTER_VALIDATE_INT);

        if ($units === false) {
            return false;
        }

        $rest = (int) str_pad($fraction, $spec['minor'], '0');
        $amount = $units * (10 ** $spec['minor']) + $rest;

        if (!is_int($amount)) {
            return false;
        }

        return $negative ? -$amount : $amount;
    }

    #[Purpose('Называет сумму словами: «одна тысяча двести тридцать четыре рубля 56 копеек». Так пишут в документах, поэтому копейки называются всегда, даже нулевые, если валюта их имеет.')]
    #[Param('amount', 'int', 'сумма в минимальных единицах валюты: копейки, центы')]
    #[Param('currency', 'string', 'код валюты, как он назван в конфиге')]
    #[Returns('string', 'сумма словами')]
    #[Throws(MoneyMisuse::class, 'такой валюты в конфиге нет')]
    #[Effect(Effect::NONE)]
    #[Gives([123456, 'RUB'], 'одна тысяча двести тридцать четыре рубля 56 копеек')]
    #[Gives([100, 'RUB'], 'один рубль 00 копеек')]
    #[Gives([0, 'RUB'], 'ноль рублей 00 копеек')]
    #[Gives([-150, 'RUB'], 'минус один рубль 50 копеек')]
    #[Gives([123456, 'USD'], 'one thousand two hundred thirty-four dollars 56 cents')]
    #[Gives([123456, 'JPY'], 'one hundred twenty-three thousand four hundred fifty-six yen')]
    #[Fails([100, 'EUR'], MoneyMisuse::class)]
    public function toWords(int $amount, string $currency): string
    {
        $spec = $this->settings->currency($currency);
        $step = 10 ** $spec['minor'];
        $units = intdiv(abs($amount), $step);
        $rest = abs($amount) % $step;
        $words = Num::toWords((string) $units, $spec['lang']) . ' ' . Text::plural($units, $spec['unit']);

        if ($spec['minor'] > 0) {
            $words .= ' ' . str_pad((string) $rest, $spec['minor'], '0', STR_PAD_LEFT)
                . ' ' . Text::plural($rest, $spec['subunit']);
        }

        if ($amount >= 0) {
            return $words;
        }

        return ($spec['lang'] === 'rus' ? 'минус ' : 'minus ') . $words;
    }
}
