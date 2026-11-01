<?php

namespace Maws\World\System;

use Maws\System\Contract\{Effect, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\World\System\Exception\PlaceNotFound;

#[Subject(Place::class, 'Maws\World\System\Place::fromRow', [['id' => 1, 'direction' => 'Public', 'name' => 'Home'], '/world'])]
#[Purpose('Место: id, направление и имя, собранные из строки таблицы places.')]
final class Place
{
    private function __construct(
        private int $id,
        private string $direction,
        private string $name,
        private string $root
    ) {
    }

    #[Purpose('Собирает место из строки базы: имя направления приходит из directions по direction_id.')]
    #[Param('row', 'array', 'строка places: id, name и name направления в колонке direction')]
    #[Param('root', 'string', 'корень мира, к которому привязывается место')]
    #[Returns('self', 'место с id, направлением и именем')]
    #[Throws(PlaceNotFound::class, 'в строке нет id, name или direction, или они не того типа')]
    #[Effect(Effect::NONE)]
    public static function fromRow(array $row, string $root): self
    {
        return new self(
            self::whole($row, 'id'),
            self::text($row, 'direction'),
            self::text($row, 'name'),
            $root
        );
    }

    #[Purpose('Отдаёт id места.')]
    #[Returns('int', 'id строки в places')]
    #[Effect(Effect::NONE)]
    #[Gives([], 1)]
    public function id(): int
    {
        return $this->id;
    }

    #[Purpose('Отдаёт направление места.')]
    #[Returns('string', 'имя направления')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'Public')]
    public function direction(): string
    {
        return $this->direction;
    }

    #[Purpose('Отдаёт имя места.')]
    #[Returns('string', 'имя места')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'Home')]
    public function name(): string
    {
        return $this->name;
    }

    #[Purpose('Отдаёт канонический адрес места.')]
    #[Returns('string', 'адрес вида "Направление/Место"')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'Public/Home')]
    public function way(): string
    {
        return $this->direction . '/' . $this->name;
    }

    #[Purpose('Отдаёт каталог места в мире.')]
    #[Returns('string', 'путь {корень}/{Направление}/Places/{Место}')]
    #[Effect(Effect::NONE)]
    #[Gives([], '/world/Public/Places/Home')]
    public function dir(): string
    {
        return $this->root . '/' . $this->direction . '/Places/' . $this->name;
    }

    #[Purpose('Отдаёт полное имя класса рабочего пространства места.')]
    #[Returns('string', 'App\World\{Направление}\Places\{Место}\Workspace')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'App\World\Public\Places\Home\Workspace')]
    public function workspaceClass(): string
    {
        return 'App\\World\\' . $this->direction . '\\Places\\' . $this->name . '\\Workspace';
    }

    #[Purpose('Отдаёт полное имя класса инструмента места.')]
    #[Param('tool', 'string', 'имя инструмента в каталоге Tools')]
    #[Returns('string', 'App\World\{Направление}\Places\{Место}\Tools\{Инструмент}\{Инструмент}')]
    #[Effect(Effect::NONE)]
    #[Gives(['ExampleTool'], 'App\World\Public\Places\Home\Tools\ExampleTool\ExampleTool')]
    public function toolClass(string $tool): string
    {
        return 'App\\World\\' . $this->direction . '\\Places\\' . $this->name . '\\Tools\\' . $tool . '\\' . $tool;
    }

    private static function whole(array $row, string $column): int
    {
        $value = $row[$column] ?? null;

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $value = (int) $value;
        }

        if (!is_int($value)) {
            throw new PlaceNotFound("(в строке places нет целого {$column})");
        }

        return $value;
    }

    private static function text(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        if (!is_string($value) || $value === '') {
            throw new PlaceNotFound("(в строке places нет непустой колонки {$column})");
        }

        return $value;
    }

    private function __clone()
    {
    }
}
