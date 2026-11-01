<?php

namespace Maws\System\Helpers\Money;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Money\Exception\MoneyMisuse;

#[Subject(MoneySettings::class, 'fromArray', [self::CONFIG])]
#[Purpose('Политика денег, которую задаёт разработчик: какие валюты есть у сайта и как каждая из них пишется и называется. Умолчаний нет — валюту, её знак, число знаков после запятой и формы слов знает только сайт, и угадывать это за него нельзя.')]
final class MoneySettings
{
    private const RUB = [
        'symbol' => '₽',
        'minor' => 2,
        'after' => true,
        'space' => true,
        'decimal' => ',',
        'thousands' => ' ',
        'lang' => 'rus',
        'unit' => ['рубль', 'рубля', 'рублей'],
        'subunit' => ['копейка', 'копейки', 'копеек'],
    ];

    private const USD = [
        'symbol' => '$',
        'minor' => 2,
        'after' => false,
        'space' => false,
        'decimal' => '.',
        'thousands' => ',',
        'lang' => 'en',
        'unit' => ['dollar', 'dollars', 'dollars'],
        'subunit' => ['cent', 'cents', 'cents'],
    ];

    private const JPY = [
        'symbol' => '¥',
        'minor' => 0,
        'after' => false,
        'space' => false,
        'decimal' => '.',
        'thousands' => ',',
        'lang' => 'en',
        'unit' => ['yen', 'yen', 'yen'],
        'subunit' => ['sen', 'sen', 'sen'],
    ];

    private const CONFIG = ['RUB' => self::RUB, 'USD' => self::USD, 'JPY' => self::JPY];

    private const FIELDS = ['symbol', 'minor', 'after', 'space', 'decimal', 'thousands', 'lang', 'unit', 'subunit'];

    private const LANGS = ['rus', 'en'];

    private const MAX_MINOR = 6;

    private const BAD_MINOR = ['RUB' => ['minor' => -1] + self::RUB];

    private const BAD_SIGNS = ['RUB' => ['decimal' => ',', 'thousands' => ','] + self::RUB];

    private const BAD_EXTRA_FIELD = ['RUB' => ['simbol' => '₽'] + self::RUB];

    private const BAD_FORMS = ['RUB' => ['unit' => ['рубль', 'рублей']] + self::RUB];

    private const BAD_LANG = ['RUB' => ['lang' => 'de'] + self::RUB];

    private const BAD_CODE = [self::RUB];

    private function __construct(private readonly array $currencies)
    {
    }

    #[Purpose('Собирает политику денег из набора «код валюты → как она пишется и называется» и проверяет каждое поле.')]
    #[Param('config', 'array', 'набор, где ключ — код валюты, а значение — её описание')]
    #[Returns('self', 'политика денег')]
    #[Throws(MoneyMisuse::class, 'валюта описана неполно, лишним полем или значением не того вида')]
    #[Effect(Effect::NONE)]
    #[Fails([[]], MoneyMisuse::class)]
    #[Fails([self::BAD_MINOR], MoneyMisuse::class)]
    #[Fails([self::BAD_SIGNS], MoneyMisuse::class)]
    #[Fails([self::BAD_EXTRA_FIELD], MoneyMisuse::class)]
    #[Fails([self::BAD_FORMS], MoneyMisuse::class)]
    #[Fails([self::BAD_LANG], MoneyMisuse::class)]
    #[Fails([self::BAD_CODE], MoneyMisuse::class)]
    public static function fromArray(array $config): self
    {
        if ($config === []) {
            throw new MoneyMisuse('в конфиге денег нет ни одной валюты — описывать нечего');
        }

        $currencies = [];

        foreach ($config as $code => $entry) {
            if (!is_string($code) || $code === '') {
                throw new MoneyMisuse('код валюты должен быть непустой строкой, например "RUB"');
            }

            if (!is_array($entry)) {
                throw new MoneyMisuse("валюта \"{$code}\" должна быть набором полей, а не " . get_debug_type($entry));
            }

            $unknown = array_diff(array_keys($entry), self::FIELDS);

            if ($unknown !== []) {
                throw new MoneyMisuse("у валюты \"{$code}\" лишние поля: " . implode(', ', $unknown));
            }

            $missing = array_diff(self::FIELDS, array_keys($entry));

            if ($missing !== []) {
                throw new MoneyMisuse("у валюты \"{$code}\" нет полей: " . implode(', ', $missing) . ' — умолчаний нет');
            }

            if (!is_string($entry['symbol'])) {
                throw new MoneyMisuse("у валюты \"{$code}\" поле \"symbol\" должно быть строкой; пустая строка — если знака нет");
            }

            if (!is_int($entry['minor']) || $entry['minor'] < 0 || $entry['minor'] > self::MAX_MINOR) {
                throw new MoneyMisuse("у валюты \"{$code}\" поле \"minor\" должно быть целым числом знаков от 0 до " . self::MAX_MINOR);
            }

            foreach (['after', 'space'] as $flag) {
                if (!is_bool($entry[$flag])) {
                    throw new MoneyMisuse("у валюты \"{$code}\" поле \"{$flag}\" должно быть true или false");
                }
            }

            if (!is_string($entry['decimal']) || $entry['decimal'] === '') {
                throw new MoneyMisuse("у валюты \"{$code}\" поле \"decimal\" должно быть непустой строкой");
            }

            if (!is_string($entry['thousands'])) {
                throw new MoneyMisuse("у валюты \"{$code}\" поле \"thousands\" должно быть строкой; пустая строка — без разделения разрядов");
            }

            if ($entry['decimal'] === $entry['thousands']) {
                throw new MoneyMisuse("у валюты \"{$code}\" знак дробной части и знак разрядов совпали («{$entry['decimal']}») — такую запись нельзя разобрать обратно");
            }

            if (!is_string($entry['lang']) || !in_array($entry['lang'], self::LANGS, true)) {
                throw new MoneyMisuse("у валюты \"{$code}\" поле \"lang\" должно быть одним из: " . implode(', ', self::LANGS));
            }

            foreach (['unit', 'subunit'] as $name) {
                $forms = $entry[$name];

                if (!is_array($forms) || !array_is_list($forms) || count($forms) !== 3) {
                    throw new MoneyMisuse("у валюты \"{$code}\" поле \"{$name}\" должно быть списком из трёх форм: одна, две, много");
                }

                foreach ($forms as $form) {
                    if (!is_string($form) || $form === '') {
                        throw new MoneyMisuse("в формах \"{$name}\" валюты \"{$code}\" должны быть непустые строки");
                    }
                }
            }

            $ordered = [];

            foreach (self::FIELDS as $field) {
                $ordered[$field] = $entry[$field];
            }

            $currencies[$code] = $ordered;
        }

        return new self($currencies);
    }

    #[Purpose('Отдаёт описание валюты по её коду.')]
    #[Param('code', 'string', 'код валюты, как он назван в конфиге')]
    #[Returns('array', 'описание валюты: знак, знаков после запятой, положение знака, разделители, язык, формы слов')]
    #[Throws(MoneyMisuse::class, 'такой валюты в конфиге нет')]
    #[Effect(Effect::NONE)]
    #[Gives(['RUB'], self::RUB)]
    #[Fails(['EUR'], MoneyMisuse::class)]
    public function currency(string $code): array
    {
        if (!isset($this->currencies[$code])) {
            throw new MoneyMisuse("валюта \"{$code}\" не описана в конфиге; описаны: " . implode(', ', array_keys($this->currencies)));
        }

        return $this->currencies[$code];
    }

    #[Purpose('Отдаёт политику тем же набором, каким её задали, — чтобы её можно было записать, сравнить или передать дальше.')]
    #[Returns('array', 'набор «код валюты → описание»')]
    #[Effect(Effect::NONE)]
    #[Gives([], self::CONFIG)]
    public function toArray(): array
    {
        return $this->currencies;
    }
}
