<?php

namespace Maws\World\System;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\World\System\Exception\ContractBroken;

#[Subject(DbaseSpelling::class, 'fromDeclaration', ['public.test_table', 'dbase.json'])]
#[Purpose('Единственное место, где живёт написание имени таблицы — пара «схема.таблица». Им пользуется объявление dbase.json, поэтому запись в файле и разбор совпадают по определению.')]
final class DbaseSpelling
{
    private const IDENT = '/^[A-Za-z_][A-Za-z0-9_$]*$/';

    private function __construct(
        private readonly string $schema,
        private readonly string $table,
    ) {
    }

    #[Purpose('Разбирает имя таблицы, пришедшее из объявления dbase.json.')]
    #[Param('name', 'string', 'имя в записи «схема.таблица»')]
    #[Param('file', 'string', 'файл объявления — попадает в текст отказа')]
    #[Returns('self', 'разобранное имя')]
    #[Throws(ContractBroken::class, 'имя записано не как «схема.таблица» или недопустимыми знаками')]
    #[Effect(Effect::NONE)]
    #[Fails(['test_table', 'dbase.json'], ContractBroken::class)]
    #[Fails(['public.my-table', 'dbase.json'], ContractBroken::class)]
    public static function fromDeclaration(string $name, string $file): self
    {
        $pair = self::split($name);

        if ($pair === null) {
            throw new ContractBroken($file, "таблица \"{$name}\" не названа со схемой — нужно \"схема.таблица\"");
        }

        foreach ($pair as $part) {
            if (preg_match(self::IDENT, $part) !== 1) {
                throw new ContractBroken($file, "таблица \"{$name}\" записана недопустимо: «{$part}»");
            }
        }

        return new self($pair[0], $pair[1]);
    }

    #[Purpose('Отдаёт схему.')]
    #[Returns('string', 'схема в нижнем регистре')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'public')]
    public function schema(): string
    {
        return $this->schema;
    }

    #[Purpose('Отдаёт имя таблицы.')]
    #[Returns('string', 'имя таблицы в нижнем регистре')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'test_table')]
    public function table(): string
    {
        return $this->table;
    }

    #[Purpose('Отдаёт имя парой — в том же виде, в каком его сравнивает страж с правами.')]
    #[Returns('array', 'пара [схема, таблица]')]
    #[Effect(Effect::NONE)]
    #[Gives([], ['public', 'test_table'])]
    public function pair(): array
    {
        return [$this->schema, $this->table];
    }

    private static function split(string $name): ?array
    {
        $parts = explode('.', strtolower($name), 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return [$parts[0], $parts[1]];
    }
}
