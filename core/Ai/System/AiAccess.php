<?php

namespace Maws\Ai\System;

use JsonException;
use Maws\Ai\System\Exception\AccessBroken;
use Maws\Ai\System\Exception\AccessNotDeclared;
use Maws\Ai\System\Exception\AccessUnreadable;
use Maws\System\Contract\{Effect, Fails, Fixture, Gives, Param, Purpose, Returns, Subject, Throws};

#[Subject(AiAccess::class, 'Scaffold::aiAccess', ['dbaccess.json'])]
#[Fixture(files: ['dbaccess.json' => '{"tables":["public.test_table"]}'])]
#[Purpose('Права агента на таблицы базы: список "схема.таблица" из dbaccess.json.')]
final class AiAccess
{
    private function __construct(private array $tables)
    {
    }



    #[Purpose('Читает права агента из dbaccess.json в каталоге агента.')]
    #[Param('agentDir', 'string', 'каталог агента, в котором лежит dbaccess.json')]
    #[Returns('self', 'права с разобранным списком "схема.таблица"')]
    #[Throws(AccessNotDeclared::class, 'dbaccess.json нет или он не читается')]
    #[Throws(AccessUnreadable::class, 'файл есть, но прочитать не удалось')]
    #[Throws(AccessBroken::class, 'битый JSON, нет списка "tables" или таблица записана недопустимо')]
    #[Effect(Effect::FS_READ)]
    #[Fails(['/нет-такого-каталога'], AccessNotDeclared::class)]
    public static function fromDir(string $agentDir): self
    {
        $file = $agentDir . '/dbaccess.json';

        if (!is_readable($file)) {
            throw new AccessNotDeclared($file);
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new AccessUnreadable($file);
        }

        try {
            $contract = \json5_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new AccessBroken($file, $e->getMessage());
        }

        if (!is_object($contract) || !isset($contract->tables) || !is_array($contract->tables)) {
            throw new AccessBroken($file, 'нет списка "tables"');
        }

        $tables = [];

        foreach ($contract->tables as $table) {
            if (!is_string($table) || $table === '') {
                throw new AccessBroken($file, 'в "tables" должны быть непустые строки');
            }

            $tables[] = AiDbaseSpelling::fromDeclaration($table, $file)->pair();
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
