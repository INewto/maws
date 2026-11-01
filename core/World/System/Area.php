<?php

namespace Maws\World\System;

use Maws\System\Contract\{Effect, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\World\System\Exception\AreaBroken;

// Строка-образец: значения здесь не про сайт, а метки — по одной на колонку. Совпадут
// две колонки — и проверка «метод читает свою» перестанет ловить подмену.
#[Subject(Area::class, 'Maws\World\System\Area::fromRow', [[
    'id' => 1,
    'name' => 'имя',
    'place_id' => 2,
    'main' => true,
    'parent_id' => 0,
    'title' => 'заголовок',
    'url' => 'адрес',
    'description' => 'описание',
    'keywords' => 'ключевые слова',
    'og_title' => 'og-заголовок',
    'og_description' => 'og-описание',
    'og_url' => 'og-адрес',
    'og_type' => 'og-тип',
    'og_site_name' => 'og-имя сайта',
    'og_image' => 'og-картинка',
    'og_image_width' => 640,
    'og_image_height' => 480,
    'og_image_alt' => 'og-подпись',
]])]
#[Purpose('Область (раздел сайта): строка areas как она есть плюс типизированный доступ к служебным полям. Собирается только из строки базы.')]
final class Area
{
    /** Строка раздела как она пришла из базы: имя колонки — ключ. */
    private array $row = [];

    private function __construct(
        private int $id,
        private string $name,
        private int $placeId,
        private bool $main,
        private int $parent,
        private string $url
    ) {
    }

    #[Purpose('Собирает раздел из строки базы целиком. Какие в строке колонки — не дело раздела: он их не перечисляет и не делит на группы.')]
    #[Param('row', 'array', 'строка areas любой полноты: служебные поля обязательны, остальные сохраняются как есть')]
    #[Returns('self', 'раздел со всей строкой')]
    #[Throws(AreaBroken::class, 'в строке нет служебной колонки, колонка не того типа или parent_id указывает на себя')]
    #[Effect(Effect::NONE)]
    public static function fromRow(array $row): self
    {
        $area = new self(
            self::whole($row, 'id'),
            self::text($row, 'name'),
            self::whole($row, 'place_id'),
            self::flag($row, 'main'),
            self::whole($row, 'parent_id'),
            self::text($row, 'url')
        );

        if ($area->parent === $area->id) {
            throw new AreaBroken($area->id, 'раздел объявлен родителем самому себе');
        }

        $area->row = $row;

        return $area;
    }

    #[Purpose('Отдаёт все колонки раздела плоским списком «имя колонки → значение». Единственный метод для передачи данных сцене: чем является поле, решает не движок, а тот, кто его читает.')]
    #[Returns('array', 'все колонки строки areas, имя колонки — ключ')]
    #[Effect(Effect::NONE)]
    #[Gives([], [
        'id' => 1,
        'name' => 'имя',
        'place_id' => 2,
        'main' => true,
        'parent_id' => 0,
        'title' => 'заголовок',
        'url' => 'адрес',
        'description' => 'описание',
        'keywords' => 'ключевые слова',
        'og_title' => 'og-заголовок',
        'og_description' => 'og-описание',
        'og_url' => 'og-адрес',
        'og_type' => 'og-тип',
        'og_site_name' => 'og-имя сайта',
        'og_image' => 'og-картинка',
        'og_image_width' => 640,
        'og_image_height' => 480,
        'og_image_alt' => 'og-подпись',
    ])]
    public function fields(): array
    {
        return $this->row;
    }

    #[Purpose('Отдаёт id раздела.')]
    #[Returns('int', 'id строки в areas')]
    #[Effect(Effect::NONE)]
    #[Gives([], 1)]
    public function id(): int
    {
        return $this->id;
    }

    #[Purpose('Отдаёт имя раздела — то, что печатается на странице: «Контакты», «О нас», «Доставка».')]
    #[Returns('string', 'название раздела для посетителя')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'имя')]
    public function name(): string
    {
        return $this->name;
    }

    #[Purpose('Отдаёт id места, в которое ведёт раздел.')]
    #[Returns('int', 'id строки в places')]
    #[Effect(Effect::NONE)]
    #[Gives([], 2)]
    public function placeId(): int
    {
        return $this->placeId;
    }

    #[Purpose('Говорит, главная ли это страница сайта. Главная во всей базе ровно одна.')]
    #[Returns('bool', 'true — раздел отмечен main = 1')]
    #[Effect(Effect::NONE)]
    #[Gives([], true)]
    public function isMain(): bool
    {
        return $this->main;
    }

    #[Purpose('Отдаёт id родительского раздела; 0 — раздел верхнего уровня.')]
    #[Returns('int', 'id строки в areas или 0')]
    #[Effect(Effect::NONE)]
    #[Gives([], 0)]
    public function parent(): int
    {
        return $this->parent;
    }

    #[Purpose('Отдаёт сегмент адреса раздела; у главной страницы он пустой.')]
    #[Returns('string', 'url как он записан в базе')]
    #[Effect(Effect::NONE)]
    #[Gives([], 'адрес')]
    public function url(): string
    {
        return $this->url;
    }

    private static function whole(array $row, string $column): int
    {
        $value = self::value($row, $column);

        // bigint приходит int только благодаря ATTR_EMULATE_PREPARES => false; строка из цифр
        // принимается тоже, а "1abc" — нет: (int) превратил бы её в 1 молча.
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $value = (int) $value;
        }

        if (!is_int($value)) {
            throw new AreaBroken(0, "колонка {$column} должна быть целым числом");
        }

        return $value;
    }

    private static function text(array $row, string $column): string
    {
        $value = self::value($row, $column);

        if ($value === null) {
            return '';
        }

        if (!is_string($value)) {
            throw new AreaBroken(0, "колонка {$column} должна быть строкой");
        }

        return $value;
    }

    private static function flag(array $row, string $column): bool
    {
        $value = self::value($row, $column);

        // true/false — нативный bool из PDO; 1/0 и 't'/'f' тоже, потому что разработчик
        // вправе привести колонку в запросе (main::int).
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === '1' || $value === 't') {
            return true;
        }

        if ($value === 0 || $value === '0' || $value === 'f') {
            return false;
        }

        throw new AreaBroken(0, "колонка {$column} должна быть признаком true/false");
    }

    private static function value(array $row, string $column): mixed
    {
        if (!array_key_exists($column, $row)) {
            throw new AreaBroken(0, "в строке areas нет колонки {$column}");
        }

        return $row[$column];
    }

    private function __clone()
    {
    }
}
