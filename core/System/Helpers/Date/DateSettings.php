<?php

namespace Maws\System\Helpers\Date;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Date\Exception\DateMisuse;

#[Subject(DateSettings::class, 'fromArray', [self::CONFIG])]
#[Purpose('Политика дат, которую задаёт разработчик: вид записи, принимаемые форматы, пороги и слова. Пояса в политике нет — его определяет сервер, а не сайт. Умолчаний нет намеренно — сколько длится «только что», когда переходить на часы и какими словами это называть, решает сайт, а не фреймворк.')]
final class DateSettings
{
    private const CONFIG = [
        'format' => 'd.m.Y',
        'parseFormats' => ['d.m.Y', 'Y-m-d', 'Y-m-d H:i:s'],
        'thresholds' => ['justNow' => 60, 'minutes' => 3600, 'hours' => 86400, 'days' => 604800],
        'now' => 'только что',
        'ago' => '{n} {unit} назад',
        'in' => 'через {n} {unit}',
        'units' => [
            'minute' => ['минуту', 'минуты', 'минут'],
            'hour' => ['час', 'часа', 'часов'],
            'day' => ['день', 'дня', 'дней'],
        ],
    ];

    private const KEYS = ['format', 'parseFormats', 'thresholds', 'now', 'ago', 'in', 'units'];

    private const BOUNDS = ['justNow', 'minutes', 'hours', 'days'];

    private const UNITS = ['minute', 'hour', 'day'];

    private const BAD_THRESHOLDS = ['thresholds' => ['justNow' => 60, 'minutes' => 30, 'hours' => 86400, 'days' => 604800]] + self::CONFIG;

    private const BAD_EXTRA_KEY = ['timeZone' => 'UTC'] + self::CONFIG;

    private const BAD_UNIT_FORMS = ['units' => ['minute' => ['минуту', 'минут'], 'hour' => ['час', 'часа', 'часов'], 'day' => ['день', 'дня', 'дней']]] + self::CONFIG;

    private const BAD_TEMPLATE = ['ago' => '{n} назад'] + self::CONFIG;

    private function __construct(
        public readonly string $format,
        public readonly array $parseFormats,
        public readonly array $thresholds,
        public readonly string $now,
        public readonly string $ago,
        public readonly string $in,
        public readonly array $units,
    ) {
    }

    #[Purpose('Собирает политику из набора, каким её пишут в конфиге сайта, и проверяет каждое поле.')]
    #[Param('config', 'array', 'набор с ключами format, parseFormats, thresholds, now, ago, in, units')]
    #[Returns('self', 'политика дат')]
    #[Throws(DateMisuse::class, 'ключа нет, ключ лишний или значение не того вида')]
    #[Effect(Effect::NONE)]
    #[Fails([self::BAD_THRESHOLDS], DateMisuse::class)]
    #[Fails([self::BAD_EXTRA_KEY], DateMisuse::class)]
    #[Fails([self::BAD_UNIT_FORMS], DateMisuse::class)]
    #[Fails([self::BAD_TEMPLATE], DateMisuse::class)]
    public static function fromArray(array $config): self
    {
        $unknown = array_diff(array_keys($config), self::KEYS);

        if ($unknown !== []) {
            throw new DateMisuse('в конфиге дат лишние ключи: ' . implode(', ', $unknown));
        }

        $missing = array_diff(self::KEYS, array_keys($config));

        if ($missing !== []) {
            throw new DateMisuse('в конфиге дат нет ключей: ' . implode(', ', $missing) . ' — политику задаёт разработчик, умолчаний нет');
        }

        $format = $config['format'];

        if (!is_string($format) || $format === '') {
            throw new DateMisuse('"format" должен быть непустой строкой вида "d.m.Y"');
        }

        $parseFormats = $config['parseFormats'];

        if (!is_array($parseFormats) || !array_is_list($parseFormats) || $parseFormats === []) {
            throw new DateMisuse('"parseFormats" должен быть непустым списком строк вида ["d.m.Y", "Y-m-d"]');
        }

        foreach ($parseFormats as $one) {
            if (!is_string($one) || $one === '') {
                throw new DateMisuse('в "parseFormats" должны быть непустые строки');
            }
        }

        $thresholds = $config['thresholds'];

        if (!is_array($thresholds)) {
            throw new DateMisuse('"thresholds" должен быть набором порогов justNow, minutes, hours, days');
        }

        $unknown = array_diff(array_keys($thresholds), self::BOUNDS);

        if ($unknown !== []) {
            throw new DateMisuse('в "thresholds" лишние пороги: ' . implode(', ', $unknown));
        }

        $missing = array_diff(self::BOUNDS, array_keys($thresholds));

        if ($missing !== []) {
            throw new DateMisuse('в "thresholds" нет порогов: ' . implode(', ', $missing));
        }

        $previous = 0;
        $ordered = [];

        foreach (self::BOUNDS as $bound) {
            $value = $thresholds[$bound];

            if (!is_int($value) || $value <= 0) {
                throw new DateMisuse("порог \"{$bound}\" должен быть целым числом секунд больше нуля");
            }

            if ($value <= $previous) {
                throw new DateMisuse("пороги должны расти: justNow < minutes < hours < days, а \"{$bound}\" равен {$value} и не больше предыдущего");
            }

            $ordered[$bound] = $value;
            $previous = $value;
        }

        $now = $config['now'];

        if (!is_string($now) || $now === '') {
            throw new DateMisuse('"now" должен быть непустой строкой — что говорить о только что случившемся');
        }

        $ago = $config['ago'];
        $in = $config['in'];

        foreach (['ago' => $ago, 'in' => $in] as $name => $template) {
            if (!is_string($template) || !str_contains($template, '{n}') || !str_contains($template, '{unit}')) {
                throw new DateMisuse("\"{$name}\" должен быть строкой с {n} и {unit}, например \"{n} {unit} назад\"");
            }
        }

        $units = $config['units'];

        if (!is_array($units)) {
            throw new DateMisuse('"units" должен быть набором единиц minute, hour, day');
        }

        $unknown = array_diff(array_keys($units), self::UNITS);

        if ($unknown !== []) {
            throw new DateMisuse('в "units" лишние единицы: ' . implode(', ', $unknown));
        }

        $missing = array_diff(self::UNITS, array_keys($units));

        if ($missing !== []) {
            throw new DateMisuse('в "units" нет единиц: ' . implode(', ', $missing));
        }

        $orderedUnits = [];

        foreach (self::UNITS as $unit) {
            $forms = $units[$unit];

            if (!is_array($forms) || !array_is_list($forms) || count($forms) !== 3) {
                throw new DateMisuse("у \"{$unit}\" должно быть ровно три формы: одна, две, много");
            }

            foreach ($forms as $form) {
                if (!is_string($form) || $form === '') {
                    throw new DateMisuse("в формах \"{$unit}\" должны быть непустые строки");
                }
            }

            $orderedUnits[$unit] = $forms;
        }

        return new self($format, $parseFormats, $ordered, $now, $ago, $in, $orderedUnits);
    }

    #[Purpose('Отдаёт политику тем же набором, каким её задали, — чтобы её можно было записать, сравнить или передать дальше.')]
    #[Returns('array', 'набор с ключами format, parseFormats, thresholds, now, ago, in, units')]
    #[Effect(Effect::NONE)]
    #[Gives([], self::CONFIG)]
    public function toArray(): array
    {
        return [
            'format' => $this->format,
            'parseFormats' => $this->parseFormats,
            'thresholds' => $this->thresholds,
            'now' => $this->now,
            'ago' => $this->ago,
            'in' => $this->in,
            'units' => $this->units,
        ];
    }
}
