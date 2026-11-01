<?php

namespace Maws\System\Helpers\Csv;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Csv\Exception\CsvMisuse;
use ValueError;

#[Subject(Csv::class, 'new')]
#[Purpose('Обмен таблицами с Excel и подобными: строки и клетки, где всё — текст. Три вещи, на которых CSV обычно ломается, решены явно: разделитель объявляется (в русском Excel это точка с запятой), метка BOM при чтении снимается и ставится при записи по просьбе, а конец строки — CRLF, как в RFC 4180. Значения не угадываются: «007» остаётся «007», «1,50» — текстом.')]
final class Csv
{
    private const BOM = "\xEF\xBB\xBF";

    private function __construct()
    {
    }

    #[Purpose('Пишет таблицу строкой. Клетка с разделителем, кавычкой или переводом строки берётся в кавычки, внутренние кавычки удваиваются. Строки — списки клеток; если строки-словари, первая задаёт заголовок, а набор колонок в остальных обязан совпасть — иначе клетка потерялась бы молча.')]
    #[Param('rows', 'array', 'строки: список клеток или словарь колонок → значение')]
    #[Param('delimiter', 'string', 'один знак: запятая, точка с запятой, табуляция')]
    #[Param('bom', 'bool', 'true — поставить метку UTF-8, без неё Excel показывает кириллицу мусором')]
    #[Returns('string', 'таблица строкой; конец строки — CRLF')]
    #[Throws(CsvMisuse::class, 'разделитель не один знак, строки разной формы, другой набор колонок')]
    #[Effect(Effect::NONE)]
    #[Gives([[['a', 'b'], ['1', '2']]], "a,b\r\n1,2\r\n")]
    #[Gives([[['а, б', 'с']]], "\"а, б\",с\r\n")]
    #[Gives([[['он сказал "да"']]], "\"он сказал \"\"да\"\"\"\r\n")]
    #[Gives([[['первая', 'вторая']], ';'], "первая;вторая\r\n")]
    #[Gives([[['id', 'имя'], ['1', 'Аня']], ',', true], "\xEF\xBB\xBFid,имя\r\n1,Аня\r\n")]
    #[Gives([[['a' => '1', 'b' => '2'], ['a' => '3', 'b' => '4']]], "a,b\r\n1,2\r\n3,4\r\n")]
    #[Gives([[]], '')]
    #[Fails([[['a' => '1'], ['b' => '2']]], CsvMisuse::class)]
    #[Fails([[['a', 'b'], ['a' => '1', 'b' => '2']]], CsvMisuse::class)]
    #[Fails([[['a']], ''], CsvMisuse::class)]
    #[Fails([[['a']], ',, '], CsvMisuse::class)]
    #[Fails([[['a']], '"'], CsvMisuse::class)]
    public static function encode(array $rows, string $delimiter = ',', bool $bom = false): string
    {
        self::demandDelimiter($delimiter);

        $stream = fopen('php://temp', 'r+');

        foreach (self::shaped($rows) as $row) {
            fputcsv($stream, $row, $delimiter, '"', '', "\r\n");
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $bom ? self::BOM . $csv : $csv;
    }

    #[Purpose('Читает таблицу как есть: список строк, все клетки — строки. Пустые строки пропускаются — это не данные; BOM снимается; кодировка объявляется, а не угадывается, потому что угадывание портит кириллицу молча.')]
    #[Param('csv', 'string', 'таблица строкой')]
    #[Param('delimiter', 'string', 'один знак: запятая, точка с запятой, табуляция')]
    #[Param('encoding', 'string', 'в какой кодировке пришла таблица; наружу — всегда UTF-8')]
    #[Returns('array', 'строки, каждая — список клеток-строк')]
    #[Throws(CsvMisuse::class, 'разделитель не один знак, строка не в объявленной кодировке')]
    #[Effect(Effect::NONE)]
    #[Gives(["a,b\r\n1,2\r\n"], [['a', 'b'], ['1', '2']])]
    #[Gives(["a;b\r\n1;2\r\n", ';'], [['a', 'b'], ['1', '2']])]
    #[Gives(["\xEF\xBB\xBFid,имя\r\n1,Аня\r\n"], [['id', 'имя'], ['1', 'Аня']])]
    #[Gives(["a,b\r\n\r\n1,2\r\n"], [['a', 'b'], ['1', '2']])]
    #[Gives(["\"а, б\",с\r\n"], [['а, б', 'с']])]
    #[Gives(["\"первая\r\nвторая\",x\r\n"], [["первая\r\nвторая", 'x']])]
    #[Gives([''], [])]
    #[Gives(["\xE8\xEC\xFF\r\n\xC0\xED\xFF\r\n", ',', 'Windows-1251'], [['имя'], ['Аня']])]
    #[Fails(["\xB1"], CsvMisuse::class)]
    #[Fails(['a,b', ',, '], CsvMisuse::class)]
    public static function decode(string $csv, string $delimiter = ',', string $encoding = 'UTF-8'): array
    {
        return self::rows($csv, $delimiter, $encoding);
    }

    #[Purpose('Читает таблицу с заголовком: первая строка — имена колонок, дальше строки-словари. Заголовок с повтором или без имени — отказ, как и строка другой ширины: иначе значение привязалось бы не к той колонке, а заметить это было бы нечем.')]
    #[Param('csv', 'string', 'таблица строкой')]
    #[Param('delimiter', 'string', 'один знак: запятая, точка с запятой, табуляция')]
    #[Param('encoding', 'string', 'в какой кодировке пришла таблица; наружу — всегда UTF-8')]
    #[Returns('array', 'строки, каждая — словарь «колонка → значение»')]
    #[Throws(CsvMisuse::class, 'колонка без имени, две колонки с одним именем, строка другой ширины')]
    #[Effect(Effect::NONE)]
    #[Gives(["id,имя\r\n1,Аня\r\n2,Ёж\r\n"], [['id' => '1', 'имя' => 'Аня'], ['id' => '2', 'имя' => 'Ёж']])]
    #[Gives(["id;имя\r\n1;Аня\r\n", ';'], [['id' => '1', 'имя' => 'Аня']])]
    #[Gives([''], [])]
    #[Fails(["id,id\r\n1,2\r\n"], CsvMisuse::class)]
    #[Fails(["id,\r\n1,2\r\n"], CsvMisuse::class)]
    #[Fails(["id,имя\r\n1\r\n"], CsvMisuse::class)]
    #[Fails(["id,имя\r\n1,Аня,лишнее\r\n"], CsvMisuse::class)]
    public static function table(string $csv, string $delimiter = ',', string $encoding = 'UTF-8'): array
    {
        $rows = self::rows($csv, $delimiter, $encoding);

        if ($rows === []) {
            return [];
        }

        $header = array_shift($rows);
        self::demandHeader($header);

        $width = count($header);
        $table = [];

        foreach ($rows as $index => $row) {
            if (count($row) !== $width) {
                throw new CsvMisuse(
                    'строка ' . ($index + 2) . ': клеток ' . count($row) . ', а в заголовке ' . $width
                );
            }

            $table[] = array_combine($header, $row);
        }

        return $table;
    }

    /** @return array<int, array<int, string|null>> */
    private static function rows(string $csv, string $delimiter, string $encoding): array
    {
        self::demandDelimiter($delimiter);

        $csv = self::utf8($csv, $encoding);

        if (str_starts_with($csv, self::BOM)) {
            $csv = substr($csv, strlen(self::BOM));
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);

        $rows = [];

        while (($row = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            // Пустая строка — это [null], и она не данные; строка из пустых клеток — ['', ''] — данные.
            if ($row === [null]) {
                continue;
            }

            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    private static function shaped(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $first = reset($rows);

        if (!is_array($first)) {
            throw new CsvMisuse('строка таблицы — это массив клеток');
        }

        $keys = array_is_list($first) ? null : array_keys($first);
        $list = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new CsvMisuse('строка таблицы — это массив клеток');
            }

            if ($keys === null) {
                if (!array_is_list($row)) {
                    throw new CsvMisuse('первая строка — список, а эта — словарь: мешать формы нельзя');
                }

                $list[] = $row;

                continue;
            }

            $list[] = self::byKeys($row, $keys);
        }

        if ($keys !== null) {
            array_unshift($list, $keys);
        }

        return $list;
    }

    /** @param array<int, string> $keys @return array<int, mixed> */
    private static function byKeys(array $row, array $keys): array
    {
        $cells = [];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                throw new CsvMisuse("в строке нет колонки \"{$key}\": клетка потерялась бы молча");
            }

            $cells[] = $row[$key];
        }

        if (count($row) !== count($keys)) {
            $extra = array_diff(array_keys($row), $keys);

            throw new CsvMisuse('в строке лишние колонки: ' . implode(', ', $extra));
        }

        return $cells;
    }

    private static function demandDelimiter(string $delimiter): void
    {
        if (strlen($delimiter) !== 1) {
            throw new CsvMisuse('разделитель — ровно один знак, получено ' . strlen($delimiter) . ' байт');
        }

        if ($delimiter === '"' || $delimiter === "\r" || $delimiter === "\n") {
            throw new CsvMisuse('кавычка и перевод строки разделителем быть не могут: они разметка');
        }
    }

    /** @param array<int, string|null> $header */
    private static function demandHeader(array $header): void
    {
        $seen = [];

        foreach ($header as $name) {
            if ($name === null || $name === '') {
                throw new CsvMisuse('в заголовке есть колонка без имени: к ней нечего привязать');
            }

            if (isset($seen[$name])) {
                throw new CsvMisuse("в заголовке две колонки \"{$name}\": вторая затёрла бы первую");
            }

            $seen[$name] = true;
        }
    }

    private static function utf8(string $csv, string $encoding): string
    {
        try {
            if (!mb_check_encoding($csv, $encoding)) {
                throw new CsvMisuse("таблица не в кодировке {$encoding}");
            }

            return $encoding === 'UTF-8' ? $csv : mb_convert_encoding($csv, 'UTF-8', $encoding);
        } catch (ValueError $e) {
            throw new CsvMisuse("кодировки \"{$encoding}\" нет: " . $e->getMessage());
        }
    }
}
