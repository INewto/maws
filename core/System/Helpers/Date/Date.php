<?php

namespace Maws\System\Helpers\Date;

use DateTimeImmutable;
use JsonException;
use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Date\Exception\DateMisuse;
use Maws\System\Helpers\Text\Text;

#[Subject(Date::class, 'fromArray', [self::CONFIG])]
#[Purpose('Приводит момент времени к записи и обратно по правилам, которые задал разработчик. Своих умолчаний не имеет: вид записи, принимаемые форматы, пороги «человеческого» времени и сами слова берутся из политики, потому что решать это за сайт нельзя. Пояс берётся у сервера, а не из политики.')]
final class Date
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

    #[Param('settings', 'DateSettings', 'политика дат: вид записи, форматы, пороги, слова')]
    private function __construct(private readonly DateSettings $settings)
    {
    }

    #[Purpose('Собирает читателя дат по готовой политике.')]
    #[Param('settings', 'DateSettings', 'политика дат')]
    #[Returns('self', 'читатель дат с этой политикой')]
    #[Effect(Effect::NONE)]
    public static function with(DateSettings $settings): self
    {
        return new self($settings);
    }

    #[Purpose('Собирает читателя дат прямо из конфига, каким его пишут в файле или в коде сайта.')]
    #[Param('config', 'array', 'весь конфиг: format, parseFormats, thresholds, now, ago, in, units')]
    #[Returns('self', 'читатель дат с этой политикой')]
    #[Throws(DateMisuse::class, 'в конфиге нет ключа, есть лишний ключ или значение не того вида')]
    #[Effect(Effect::NONE)]
    #[Fails([[]], DateMisuse::class)]
    public static function fromArray(array $config): self
    {
        return new self(DateSettings::fromArray($config));
    }

    #[Purpose('Собирает читателя дат из JSON-файла — чтобы политику можно было держать рядом с сайтом, а не в коде.')]
    #[Param('path', 'string', 'путь к файлу конфига')]
    #[Returns('self', 'читатель дат с политикой из файла')]
    #[Throws(DateMisuse::class, 'файл не читается или не разбирается как JSON')]
    #[Effect(Effect::FS_READ)]
    #[Fails(['нет-такого-конфига-дат.json'], DateMisuse::class)]
    public static function fromFile(string $path): self
    {
        if (!is_readable($path)) {
            throw new DateMisuse("конфиг дат не читается: {$path}");
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new DateMisuse("конфиг дат не читается: {$path}");
        }

        try {
            $config = \json5_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new DateMisuse("конфиг дат не разбирается ({$path}): {$e->getMessage()}");
        }

        if (!is_array($config)) {
            throw new DateMisuse("конфиг дат должен быть объектом ({$path})");
        }

        return self::fromArray($config);
    }

    #[Purpose('Записывает момент по виду записи из политики или по указанному виду, в поясе сервера.')]
    #[Param('timestamp', 'int', 'момент времени')]
    #[Param('format', 'string|null', 'вид записи; null — вид из политики')]
    #[Returns('string', 'запись момента')]
    #[Effect(Effect::NONE)]
    #[Gives([0], '01.01.1970')]
    #[Gives([0, 'Y-m-d H:i:s'], '1970-01-01 00:00:00')]
    #[Gives([1792108800], '16.10.2026')]
    public function format(int $timestamp, ?string $format = null): string
    {
        return date($format ?? $this->settings->format, $timestamp);
    }

    #[Purpose('Говорит, сколько прошло или сколько осталось, словами и по порогам из политики; за последним порогом отдаёт обычную запись. Считает длительность, а не календарь: «вчера» требует пояса и календаря, а это уже не длительность.')]
    #[Param('timestamp', 'int', 'момент времени')]
    #[Param('now', 'int|null', 'от чего считать; null — текущее время')]
    #[Returns('string', 'человеческая запись')]
    #[Effect(Effect::NONE)]
    #[Gives([1000, 1030], 'только что')]
    #[Gives([1000, 1060], '1 минуту назад')]
    #[Gives([1000, 1120], '2 минуты назад')]
    #[Gives([1000, 1300], '5 минут назад')]
    #[Gives([1000, 4600], '1 час назад')]
    #[Gives([1000, 8200], '2 часа назад')]
    #[Gives([1000, 19000], '5 часов назад')]
    #[Gives([1000, 87400], '1 день назад')]
    #[Gives([1000, 173800], '2 дня назад')]
    #[Gives([1000, 433000], '5 дней назад')]
    #[Gives([1000, 700000], '01.01.1970')]
    #[Gives([1000, 700], 'через 5 минут')]
    public function human(int $timestamp, ?int $now = null): string
    {
        $shift = $timestamp - ($now ?? time());
        $size = abs($shift);
        $bounds = $this->settings->thresholds;

        if ($size < $bounds['justNow']) {
            return $this->settings->now;
        }

        if ($size < $bounds['minutes']) {
            return $this->say($shift, 'minute', 60);
        }

        if ($size < $bounds['hours']) {
            return $this->say($shift, 'hour', 3600);
        }

        if ($size < $bounds['days']) {
            return $this->say($shift, 'day', 86400);
        }

        return $this->format($timestamp);
    }

    #[Purpose('Разбирает запись в момент времени: перебирает форматы из политики и отвергает всё, что разобралось с оговоркой — иначе 32 января молча стало бы 1 февраля. Пробельные знаки по краям не мешают, внутри — значимы.')]
    #[Param('value', 'string', 'запись как её написал человек')]
    #[Returns('int|false', 'метка времени Unix в секундах или false, если запись не подошла ни под один формат')]
    #[Effect(Effect::NONE)]
    #[Gives(['16.10.2026'], 1792108800)]
    #[Gives([' 16.10.2026 '], 1792108800)]
    #[Gives(["\u{00A0}2026-10-16\t"], 1792108800)]
    #[Gives(['2026-10-16'], 1792108800)]
    #[Gives(['2026-10-16 21:14:03'], 1792185243)]
    #[Gives(['32.01.2026'], false)]
    #[Gives(['16.10.2026 xxx'], false)]
    #[Gives(['abc'], false)]
    #[Gives([''], false)]
    public function utimestamp(string $value): int|false
    {
        $value = (string) preg_replace('/(*UCP)^\s+|\s+$/u', '', $value);

        foreach ($this->settings->parseFormats as $format) {
            $moment = DateTimeImmutable::createFromFormat('!' . $format, $value);

            if ($moment === false) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            return $moment->getTimestamp();
        }

        return false;
    }

    private function say(int $shift, string $unit, int $seconds): string
    {
        $count = max(1, intdiv(abs($shift), $seconds));
        $template = $shift > 0 ? $this->settings->in : $this->settings->ago;

        return str_replace(['{n}', '{unit}'], [$count, Text::plural($count, $this->settings->units[$unit])], $template);
    }
}
