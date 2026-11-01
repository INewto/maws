<?php

namespace Maws\System\Crabic;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Crabic\Exception\CrabicBroken;

/**
 * Часть ядра, а не хелпер. Проверка поведения — это механизм, а не удобство: состояние живёт в
 * сессии, склад отпечатков создан ядровой миграцией, а половина протокола лежит в public/js.
 * Хелпер обязан удаляться целиком, не оставляя сирот; здесь после удаления остались бы и таблица,
 * и скрипт. Плюс ядро не может позвать хелпер — значит проверку нельзя было бы встроить в руки.
 *
 * Здесь только арифметика: ни сессии, ни базы, ни $_POST. Поэтому её и можно проверить прогоном.
 */
#[Subject(Motion::class, 'new')]
#[Purpose('Движение как данные: квантование траектории, отпечаток и проверка последовательности пачек.')]
final class Motion
{
    /** Сторона клетки мелкой сетки, пиксели. Два разных жеста почти никогда не совпадают в ней. */
    public const CELL = 20;

    /** Шаг мелкой сетки по времени, миллисекунды. */
    public const STEP = 100;

    /** Во сколько раз грубая сетка крупнее мелкой: она ловит тот же путь с дрожанием. */
    public const COARSE = 2;

    /** Сколько точек принимается в пачке. Больше — либо чужой формат, либо подделка. */
    public const MAX_POINTS = 240;

    /** Меньше этого числа клеток траектория ничего не опознаёт: у двух людей совпадёт. */
    public const MIN_CELLS = 4;

    /** Доля заявленного времени, которую сервер готов простить сверх прожитого: запасные часы браузера — Date.now(), а он может прыгнуть. */
    public const SLACK = 1.25;

    /** Кривизна, ниже которой путь считается нарисованным по линейке (промилле). */
    public const STRAIGHT = 1001;

    #[Purpose('Разбирает пачку трекера в плоскую запись: числа, два отпечатка и кривизна. Всё, что дальше, работает с этой записью, а не с точками.')]
    #[Param('batch', 'array', 'пачка из трекера: id, t0, dt, w, h, events, keys, distinct, pts')]
    #[Returns('array', 'запись пачки: те же числа, плюс points, cells, curve, hash, coarse')]
    #[Throws(CrabicBroken::class, 'пачка не того вида: нет поля, поле не того типа, точки вне окна, время назад, точек больше MAX_POINTS, событий меньше точек')]
    #[Effect(Effect::NONE)]
    #[Fails([['id' => 'x']], CrabicBroken::class)]
    #[Fails([['id' => 'abcdefgh', 't0' => 0, 'dt' => 3000, 'w' => 800, 'h' => 600, 'events' => 1, 'keys' => 0, 'distinct' => 0, 'pts' => [[0, 900, 10]]]], CrabicBroken::class)]
    public static function describe(array $batch): array
    {
        $id = self::field($batch, 'id', 'string');
        if (preg_match('/^[a-z0-9]{8,32}$/', $id) !== 1) {
            throw new CrabicBroken('id пачки — 8–32 знака [a-z0-9]');
        }

        $t0  = self::number($batch, 't0', 0, PHP_INT_MAX);
        $dt  = self::number($batch, 'dt', 1, 600000);
        $w   = self::number($batch, 'w', 100, 20000);
        $h   = self::number($batch, 'h', 100, 20000);
        $ev  = self::number($batch, 'events', 0, 1000000);
        $key = self::number($batch, 'keys', 0, 1000000);
        $dis = self::number($batch, 'distinct', 0, 1000000);

        if ($dis > $key) {
            throw new CrabicBroken('различных клавиш больше, чем нажатий');
        }

        $pts = $batch['pts'] ?? null;

        if (!is_array($pts) || count($pts) > self::MAX_POINTS) {
            throw new CrabicBroken('pts — список не длиннее ' . self::MAX_POINTS . ' точек');
        }

        $points = [];

        foreach ($pts as $point) {
            if (!is_array($point) || count($point) !== 3) {
                throw new CrabicBroken('точка — тройка [время, x, y]');
            }

            [$t, $x, $y] = array_values($point);

            if (!is_int($t) || !is_int($x) || !is_int($y)) {
                throw new CrabicBroken('точка — три целых числа');
            }

            if ($t < 0 || $t > $dt) {
                throw new CrabicBroken('время точки вне пачки');
            }

            if ($x < 0 || $x > $w || $y < 0 || $y > $h) {
                throw new CrabicBroken('точка вне окна, о котором заявила пачка');
            }

            if ($points !== [] && $t < $points[count($points) - 1][0]) {
                throw new CrabicBroken('время точки идёт назад');
            }

            $points[] = [$t, $x, $y];
        }

        // Точки — выборка из событий. Выборка не может быть длиннее того, из чего её взяли:
        // у настоящего браузера это выполняется всегда, у подделки — только если автор догадался.
        if (count($points) > $ev) {
            throw new CrabicBroken('точек больше, чем событий указателя');
        }

        return [
            'id'       => $id,
            't0'       => $t0,
            'dt'       => $dt,
            'w'        => $w,
            'h'        => $h,
            'events'   => $ev,
            'keys'     => $key,
            'distinct' => $dis,
            'points'   => count($points),
            'cells'    => self::cells($points),
            'curve'    => self::curve($points),
            'hash'     => self::fingerprint($points, self::CELL, self::STEP),
            'coarse'   => self::fingerprint($points, self::CELL * self::COARSE, self::STEP * self::COARSE),
        ];
    }

    #[Purpose('Сколько различных клеток сетки прошла траектория. Соседние попадания в одну клетку считаются одним: дрожание указателя не должно раздувать число.')]
    #[Param('points', 'array', 'точки [время, x, y]')]
    #[Returns('int', 'число различных клеток по ходу движения')]
    #[Effect(Effect::NONE)]
    #[Gives([[[0, 10, 10], [100, 30, 10], [200, 60, 30], [300, 90, 30], [400, 120, 50]]], 5)]
    #[Gives([[[0, 10, 10], [40, 12, 11], [80, 14, 12], [120, 16, 13]]], 2)]
    #[Gives([[]], 0)]
    public static function cells(array $points): int
    {
        $seen  = [];
        $count = 0;
        $last  = null;

        foreach ($points as $point) {
            $cell = [intdiv($point[0], self::STEP), intdiv($point[1], self::CELL), intdiv($point[2], self::CELL)];

            if ($cell === $last) {
                continue;
            }

            $last = $cell;

            if (isset($seen[$cell[0] . ':' . $cell[1] . ':' . $cell[2]])) {
                continue;
            }

            $seen[$cell[0] . ':' . $cell[1] . ':' . $cell[2]] = true;
            $count++;
        }

        return $count;
    }

    #[Purpose('Кривизна пути в промилле: длина пройденного к длине по прямой. Ровно тысяча — путь по линейке, чего рука не делает.')]
    #[Param('points', 'array', 'точки [время, x, y]')]
    #[Returns('int', 'промилле; ноль, если прямая нулевая (стояли на месте)')]
    #[Effect(Effect::NONE)]
    #[Gives([[[0, 10, 10], [100, 30, 10], [200, 60, 30], [300, 90, 30], [400, 120, 50]]], 1043)]
    #[Gives([[[0, 10, 10], [100, 110, 10]]], 1000)]
    #[Gives([[[0, 10, 10]]], 0)]
    public static function curve(array $points): int
    {
        $length = 0.0;
        $prev   = null;

        foreach ($points as $point) {
            if ($prev !== null) {
                $length += hypot($point[1] - $prev[1], $point[2] - $prev[2]);
            }

            $prev = $point;
        }

        if (count($points) < 2) {
            return 0;
        }

        $first = $points[0];
        $last  = $points[count($points) - 1];
        $span  = hypot($last[1] - $first[1], $last[2] - $first[2]);

        return $span > 0 ? (int) round(1000 * $length / $span) : 0;
    }

    #[Purpose('Отпечаток траектории: хеш последовательности клеток сетки. Два одинаковых жеста дают одинаковый отпечаток, два разных — нет.')]
    #[Param('points', 'array', 'точки [время, x, y]')]
    #[Param('cell', 'int', 'сторона клетки, пиксели')]
    #[Param('step', 'int', 'шаг по времени, миллисекунды')]
    #[Returns('string', '32 шестнадцатеричных знака (xxh128)')]
    #[Effect(Effect::NONE)]
    #[Gives([[], 20, 100], '99aa06d3014798d86001c324468d497f')]
    public static function fingerprint(array $points, int $cell, int $step): string
    {
        $cells = [];
        $last  = null;

        foreach ($points as $point) {
            $current = intdiv($point[0], $step) . ':' . intdiv($point[1], $cell) . ':' . intdiv($point[2], $cell);

            if ($current === $last) {
                continue;
            }

            $last    = $current;
            $cells[] = $current;
        }

        return hash('xxh128', implode(';', $cells));
    }

    #[Purpose('Решает, может ли такая последовательность пачек прийти от живого человека: было ли на странице хоть одно действие, не приписано ли себе время, которого не было, не нарисован ли путь по линейке. Отпечаток сверяется отдельно, в базе.')]
    #[Param('ring', 'array', 'сохранённые пачки этой страницы, по порядку; у каждой есть at — время прихода на сервер в миллисекундах')]
    #[Param('summary', 'array', 'запись сводки из скрытого поля, с at — временем проверки в миллисекундах')]
    #[Param('started', 'int', 'время отрисовки страницы по часам сервера, миллисекунды')]
    #[Returns('string|null', 'причина отказа (empty, time, motion) или null, если последовательность правдоподобна')]
    #[Effect(Effect::NONE)]
    #[Gives([[], ['at' => 4000, 'dt' => 3000, 't0' => 0, 'points' => 0, 'curve' => 0, 'keys' => 0], 0], 'empty')]
    #[Gives([[], ['at' => 1000, 'dt' => 3000, 't0' => 0, 'points' => 20, 'curve' => 1043, 'keys' => 0], 0], 'time')]
    #[Gives([[], ['at' => 1500, 'dt' => 1400, 't0' => 0, 'points' => 20, 'curve' => 1043, 'keys' => 0], 0], null)]
    #[Gives([[['at' => 3000, 'dt' => 3000, 't0' => 0, 'points' => 20, 'curve' => 1043]], ['at' => 3500, 'dt' => 3000, 't0' => 3000, 'points' => 20, 'curve' => 1043], 0], 'time')]
    #[Gives([[['at' => 3000, 'dt' => 3000, 't0' => 0, 'points' => 20, 'curve' => 1000]], ['at' => 3100, 'dt' => 100, 't0' => 3000, 'points' => 20, 'curve' => 1043], 0], 'motion')]
    #[Gives([[['at' => 3000, 'dt' => 3000, 't0' => 0, 'points' => 20, 'curve' => 1043]], ['at' => 3100, 'dt' => 100, 't0' => 3000, 'points' => 20, 'curve' => 1043], 0], null)]
    #[Gives([[['at' => 3000, 'dt' => 3000, 't0' => 0, 'points' => 20, 'curve' => 1043]], ['at' => 9000, 'dt' => 3000, 't0' => 3000, 'points' => 20, 'curve' => 1043], 0], null)]
    public static function verdict(array $ring, array $summary, int $started): ?string
    {
        // Ни одного действия — страницы не касались. Клик, тап, колесо и клавиша оставляют след
        // всегда, а форму без них не отправить: пустая сводка значит, что клиента на странице
        // не было, а не что человек оказался стеснительным.
        if (($summary['points'] ?? 0) === 0 && ($summary['keys'] ?? 0) === 0) {
            return 'empty';
        }

        // Сколько времени заявила последовательность целиком: длительности всех звеньев и сводки.
        // Это не число звеньев, а их сумма, поэтому одно звено, три и ни одного считаются одинаково.
        $claimed = $summary['dt'];

        foreach ($ring as $batch) {
            $claimed += $batch['dt'];
        }

        // Честный клиент меряет от запуска скрипта, сервер — от отрисовки страницы, поэтому
        // заявленное всегда меньше прожитого. Это тождество, а не порог: оно выполняется само при
        // любом кольце, включая пустое, а лжёт только тот, кто приписал себе небывшее время.
        if ($claimed > ($summary['at'] - $started) * self::SLACK) {
            return 'time';
        }

        // Геометрия судится по каждому звену, какое есть: нарисованное по линейке — приговор,
        // всё равно, сколько звеньев пришло и какое из них последнее.
        foreach ([...$ring, $summary] as $batch) {
            if (($batch['points'] ?? 0) >= 10 && ($batch['curve'] ?? 0) > 0 && $batch['curve'] < self::STRAIGHT) {
                return 'motion';
            }
        }

        return null;
    }

    private static function field(array $batch, string $name, string $type): mixed
    {
        $value = $batch[$name] ?? null;

        if (gettype($value) !== $type) {
            throw new CrabicBroken("поле {$name} — {$type}");
        }

        return $value;
    }

    private static function number(array $batch, string $name, int $min, int $max): int
    {
        $value = $batch[$name] ?? null;

        if (!is_int($value) || $value < $min || $value > $max) {
            throw new CrabicBroken("поле {$name} — целое от {$min} до {$max}");
        }

        return $value;
    }
}
