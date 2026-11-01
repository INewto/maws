<?php

namespace Maws\World\System;

use JsonException;
use Maws\System\Contract\{Effect, Fails, Fixture, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\World\System\Exception\ContractBroken;
use Maws\World\System\Exception\ContractUnreadable;

#[Subject(DbaseRights::class, 'Scaffold::dbaseRights', ['dbase.json'])]
#[Fixture(files: ['dbase.json' => '{"tables":["public.test_table"]}'])]
#[Purpose('Права инструмента на таблицы базы: список "схема.таблица" из dbase.json.')]
final class DbaseRights
{
    private function __construct(private array $tables)
    {
    }




    #[Purpose('Читает права инструмента из dbase.json в каталоге инструмента.')]
    #[Param('toolDir', 'string', 'каталог инструмента, в котором лежит dbase.json')]
    #[Returns('self', 'права с разобранным списком "схема.таблица"')]
    #[Throws(ContractUnreadable::class, 'dbase.json нет или он не читается')]
    #[Throws(ContractBroken::class, 'битый JSON, нет списка "tables" или таблица записана недопустимо')]
    #[Effect(Effect::FS_READ)]
    #[Fails(['/нет-такого-каталога'], ContractUnreadable::class)]
    public static function fromDir(string $toolDir): self
    {
        $file = $toolDir . '/dbase.json';

        if (!is_readable($file)) {
            throw new ContractUnreadable($file);
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new ContractUnreadable($file);
        }

        try {
            $contract = \json5_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ContractBroken($file, $e->getMessage());
        }

        if (!is_object($contract) || !isset($contract->tables) || !is_array($contract->tables)) {
            throw new ContractBroken($file, 'нет списка "tables"');
        }

        $tables = [];

        foreach ($contract->tables as $table) {
            if (!is_string($table) || $table === '') {
                throw new ContractBroken($file, 'в "tables" должны быть непустые строки');
            }




            $tables[] = DbaseSpelling::fromDeclaration($table, $file)->pair();
        }

        return new self($tables);
    }


    #[Purpose('Отдаёт список разрешённых таблиц.')]
    #[Returns('array', 'пары [схема, таблица]')]
    #[Effect(Effect::NONE)]
    #[Gives([], [['public', 'test_table']])]
    public function tables(): array
    {
        return $this->tables;
    }
}
