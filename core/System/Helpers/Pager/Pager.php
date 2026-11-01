<?php

namespace Maws\System\Helpers\Pager;

use Maws\System\Contract\{Effect, Fails, Fixture, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Pager\Exception\PagerMisuse;
use Maws\System\Test\Scaffold;
use Maws\World\System\Dbase;

#[Subject(Pager::class, 'fixture', ['dbase.json'])]
#[Fixture(files: ['dbase.json' => '{"tables":["public.test_table"]}'])]
#[Purpose('Страница выборки: считает всего строк, зажимает номер и отдаёт строки одной страницы. Запрос остаётся ваш, дверь — Dbase.')]
final class Pager
{
    private ?int $perPage = null;

    private int $number = 1;

    private function __construct(private Dbase $db)
    {
    }

    #[Purpose('Начинает страницу над базой с правами инструмента.')]
    #[Param('db', 'Dbase', 'база с правами инструмента: другой у инструмента нет')]
    #[Returns('Pager', 'пагинатор без размера страницы и номера')]
    #[Effect(Effect::NONE)]
    public static function over(Dbase $db): self
    {
        return new self($db);
    }

    #[Purpose('Собирает пагинатор над песочницей прогона: права берутся из файла песочницы, запрос задаёт случай. Нужен только контракту — в работе пагинатор начинают через over().')]
    #[Param('file', 'string', 'имя файла прав в песочнице')]
    #[Returns('Pager', 'пагинатор с правами песочницы')]
    #[Effect(Effect::NONE)]
    public static function fixture(string $file): self
    {
        return self::over(Scaffold::dbase($file));
    }

    #[Purpose('Задаёт размер страницы. Умолчания нет: это политика инструмента, а не фреймворка.')]
    #[Param('perPage', 'int', 'сколько строк на странице; меньше единицы — отказ')]
    #[Returns('Pager', 'тот же пагинатор')]
    #[Throws(PagerMisuse::class, 'размер страницы меньше единицы')]
    #[Effect(Effect::NONE)]
    #[Fails([0], PagerMisuse::class)]
    public function perPage(int $perPage): self
    {
        if ($perPage < 1) {
            throw new PagerMisuse("размер страницы должен быть больше нуля, получено {$perPage}");
        }

        $this->perPage = $perPage;

        return $this;
    }

    #[Purpose('Задаёт номер страницы. Номер считается с единицы; за последней страницей не выходит — зажимается при выборке.')]
    #[Param('number', 'int', 'номер страницы; меньше единицы — отказ')]
    #[Returns('Pager', 'тот же пагинатор')]
    #[Throws(PagerMisuse::class, 'номер меньше единицы')]
    #[Effect(Effect::NONE)]
    #[Fails([0], PagerMisuse::class)]
    public function at(int $number): self
    {
        if ($number < 1) {
            throw new PagerMisuse("номер страницы считается с единицы, получено {$number}");
        }

        $this->number = $number;

        return $this;
    }

    #[Purpose('Считает всего строк, берёт строки запрошенной страницы и отдаёт страницу целиком.')]
    #[Param('sql', 'string', 'запрос без LIMIT и OFFSET: их добавляет пагинатор')]
    #[Param('params', 'array', 'значения для подстановки; по умолчанию пусто')]
    #[Returns('Page', 'строки страницы, всего строк и арифметика страниц')]
    #[Throws(PagerMisuse::class, 'размер страницы не задан или в запросе уже есть LIMIT/OFFSET')]
    #[Effect(Effect::DB_READ)]
    #[Fails(['SELECT id, name FROM public.test_table'], PagerMisuse::class)]
    #[Fails(['SELECT id, name FROM public.test_table LIMIT 1'], PagerMisuse::class, [['perPage', [20]]])]
    public function fetch(string $sql, array $params = []): Page
    {
        $perPage = $this->perPage;

        if ($perPage === null) {
            throw new PagerMisuse('размер страницы не задан: вызови perPage() до fetch()');
        }

        $this->demandPlain($sql);

        $total = $this->total($sql, $params);

        if ($total === 0) {
            return Page::of([], 0, $perPage, 1);
        }

        $pages  = (int) ceil($total / $perPage);
        $number = min($this->number, $pages);
        $offset = ($number - 1) * $perPage;

        return Page::of(
            $this->db->all($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params),
            $total,
            $perPage,
            $number
        );
    }

    #[Purpose('Считает всего строк при тех же условиях: тот же запрос обёрнут в COUNT.')]
    #[Param('sql', 'string', 'запрос без завершающей точки с запятой или с ней')]
    #[Param('params', 'array', 'значения для подстановки')]
    #[Returns('int', 'всего строк; ноль, если выборка пуста')]
    #[Effect(Effect::DB_READ)]
    private function total(string $sql, array $params): int
    {
        $row = $this->db->one('SELECT COUNT(*) AS total FROM (' . rtrim($sql, "; \n\r\t") . ') AS counted', $params);

        return (int) ($row['total'] ?? 0);
    }

    #[Purpose('Отказывает запросу, в котором уже есть LIMIT или OFFSET: второй такой же — синтаксическая ошибка.')]
    #[Param('sql', 'string', 'запрос разработчика')]
    #[Throws(PagerMisuse::class, 'в запросе уже есть LIMIT или OFFSET')]
    #[Effect(Effect::NONE)]
    private function demandPlain(string $sql): void
    {
        if (preg_match('/\b(limit|offset)\b/i', $sql) === 1) {
            throw new PagerMisuse('в запросе уже есть LIMIT или OFFSET: их добавляет пагинатор — уберите их из SQL');
        }
    }
}
