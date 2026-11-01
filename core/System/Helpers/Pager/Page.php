<?php

namespace Maws\System\Helpers\Pager;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Pager\Exception\PagerMisuse;

#[Subject(Page::class, 'of', [[], 12, 5, 2])]
#[Purpose('Одна страница выборки: её строки и арифметика — всего, номер, соседи.')]
final class Page
{
    private array $rows;

    private int $total;

    private int $perPage;

    private int $number;

    #[Purpose('Собирает страницу из готовых строк и чисел.')]
    #[Param('rows', 'array', 'строки этой страницы')]
    #[Param('total', 'int', 'всего строк при тех же условиях')]
    #[Param('perPage', 'int', 'размер страницы')]
    #[Param('number', 'int', 'номер страницы, с единицы')]
    #[Throws(PagerMisuse::class, 'размер страницы меньше единицы, номер меньше единицы или всего строк отрицательно')]
    #[Effect(Effect::NONE)]
    #[Fails([[], 12, 0, 1], PagerMisuse::class)]
    public static function of(array $rows, int $total, int $perPage, int $number): self
    {
        // Числа проверяются до сборки: страница без размера или с нулевым номером невыразима.
        if ($perPage < 1 || $number < 1 || $total < 0) {
            throw new PagerMisuse("страница не может быть такой: строк {$total}, по {$perPage}, номер {$number}");
        }

        $page = new self();

        $page->rows    = $rows;
        $page->total   = $total;
        $page->perPage = $perPage;
        $page->number  = $number;

        return $page;
    }

    #[Purpose('Собирается только фабрикой: страница без чисел не имеет смысла.')]
    #[Effect(Effect::NONE)]
    private function __construct()
    {
    }

    #[Purpose('Отдаёт строки этой страницы.')]
    #[Returns('array', 'строки страницы')]
    #[Effect(Effect::NONE)]
    #[Gives([], [])]
    public function rows(): array
    {
        return $this->rows;
    }

    #[Purpose('Отдаёт общее число строк при тех же условиях.')]
    #[Returns('int', 'всего строк')]
    #[Effect(Effect::NONE)]
    #[Gives([], 12)]
    public function total(): int
    {
        return $this->total;
    }

    #[Purpose('Отдаёт размер страницы.')]
    #[Returns('int', 'сколько строк на странице')]
    #[Effect(Effect::NONE)]
    #[Gives([], 5)]
    public function perPage(): int
    {
        return $this->perPage;
    }

    #[Purpose('Отдаёт номер страницы, зажатый в диапазон при выборке.')]
    #[Returns('int', 'номер страницы')]
    #[Effect(Effect::NONE)]
    #[Gives([], 2)]
    public function number(): int
    {
        return $this->number;
    }

    #[Purpose('Сколько страниц всего; у пустой выборки — ноль.')]
    #[Returns('int', 'число страниц')]
    #[Effect(Effect::NONE)]
    #[Gives([], 3)]
    public function pages(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }

    #[Purpose('Сколько строк пропущено до этой страницы.')]
    #[Returns('int', 'смещение для LIMIT … OFFSET')]
    #[Effect(Effect::NONE)]
    #[Gives([], 5)]
    public function offset(): int
    {
        return ($this->number - 1) * $this->perPage;
    }

    #[Purpose('Есть ли такая страница: номер в диапазоне от первой до последней.')]
    #[Param('number', 'int', 'номер страницы')]
    #[Returns('bool', 'true, если страница существует')]
    #[Effect(Effect::NONE)]
    #[Gives([3], true)]
    #[Gives([4], false)]
    public function has(int $number): bool
    {
        return $number >= 1 && $number <= $this->pages();
    }

    #[Purpose('Следующая страница или null, если это последняя.')]
    #[Returns('int|null', 'номер следующей страницы')]
    #[Effect(Effect::NONE)]
    #[Gives([], 3)]
    public function next(): ?int
    {
        return $this->number < $this->pages() ? $this->number + 1 : null;
    }

    #[Purpose('Предыдущая страница или null, если это первая.')]
    #[Returns('int|null', 'номер предыдущей страницы')]
    #[Effect(Effect::NONE)]
    #[Gives([], 1)]
    public function previous(): ?int
    {
        return $this->number > 1 ? $this->number - 1 : null;
    }
}
