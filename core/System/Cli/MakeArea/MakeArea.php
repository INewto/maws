<?php

namespace Maws\System\Cli\MakeArea;

use Maws\System\Cli\Support\Support;
use Maws\System\Contract\{Purpose, Throws};
use Maws\World\System\DbConnector;
use Maws\World\System\Exception\AreaMissing;
use Maws\World\System\Exception\PlaceMissing;

class MakeArea
{
    #[Purpose('Создаёт раздел под выбранным родителем и ставит в него существующее место.')]
    #[Throws(AreaMissing::class, 'в таблице areas нет ни одного раздела — дереву не на чем расти')]
    #[Throws(PlaceMissing::class, 'в мире нет ни одного места — раздел некуда поставить')]
    public static function run(): void
    {
        echo "=== Создание нового раздела ===\n\n";

        $areas = self::areas();

        if ($areas === []) {
            throw new AreaMissing();
        }

        // Раздел наследует место, а не наоборот: место от раздела не зависит и заводится
        // командой make place. Разделу остаётся выбрать готовое — если мест нет вовсе,
        // это отказ, а не повод завести место здесь: файлов у раздела не бывает.
        $places = self::placeTitles();

        if ($places === []) {
            throw new PlaceMissing();
        }

        $parent = self::askParent($areas);
        $url    = self::askUrl($parent);

        echo "\nМесто, в которое ведёт раздел:\n";

        $placeId = self::leading(Support::menu('Выберите место:', $places));

        // Название спрашивается последним: место уже выбрано, и человек, ответивший на
        // главный вопрос, не должен ждать, пока спросят про название раздела.
        $name = self::askTitle();

        $stmt = DbConnector::pdo()->prepare(
            'INSERT INTO areas (name, url, parent_id, main, place_id) VALUES (?, ?, ?, false, ?) RETURNING id'
        );
        $stmt->execute([$name, $url, $parent, $placeId]);
        $id = (int) $stmt->fetchColumn();

        echo "Раздел #{$id} создан: {$url} → место #{$placeId}.\n";
        echo "Готово.\n";
    }

    /** Разделы с путём от корня, а не последним сегментом адреса. Путь — обход parent_id. */
    private static function areas(): array
    {
        $stmt = DbConnector::pdo()->prepare('SELECT id, name, url, parent_id FROM areas ORDER BY parent_id, id');
        $stmt->execute();

        $byId = [];

        foreach ($stmt->fetchAll() as $row) {
            $byId[(int) $row['id']] = $row;
        }

        $areas = [];

        foreach ($byId as $id => $row) {
            $trail = [];
            $walk  = $row;

            while (true) {
                array_unshift($trail, (string) $walk['name']);

                $parent = (int) $walk['parent_id'];

                if ($parent === 0 || !isset($byId[$parent])) {
                    break;
                }

                $walk = $byId[$parent];
            }

            $areas[$id] = '#' . $id . ' ' . implode(' / ', $trail);
        }

        return $areas;
    }

    /**
     * Верхний уровень разрешён, только если главной страницы ещё нет: вторая запись
     * с main = 1 сломала бы выбор корня, а отдельного «второго дерева» в модели нет.
     */
    private static function askParent(array $areas): int
    {
        $choice = Support::menu('Родительский раздел:', array_merge($areas, ['верхний уровень']));

        if ($choice !== 'верхний уровень') {
            return self::leading($choice);
        }

        $stmt = DbConnector::pdo()->prepare('SELECT count(*) FROM areas WHERE main = true');
        $stmt->execute();

        if ((int) $stmt->fetchColumn() !== 0) {
            echo "Главная страница уже есть: второй раздел верхнего уровня завёл бы второе дерево.\n";
            echo "Выберите родителя из списка.\n\n";

            return self::askParent($areas);
        }

        return 0;
    }

    private static function askUrl(int $parent): string
    {
        $pdo = DbConnector::pdo();

        while (true) {
            $url = Support::askUrl('url раздела');

            $stmt = $pdo->prepare('SELECT name FROM areas WHERE parent_id = ? AND url = ?');
            $stmt->execute([$parent, $url]);
            $taken = $stmt->fetch();

            if ($taken) {
                echo "url {$url} уже занят разделом «{$taken['name']}» у этого же родителя.\n";
                continue;
            }

            // Адрес пишется вровень с корнем, поэтому на верхнем уровне сегмент не должен
            // совпадать с именем направления — иначе «admin» станет и разделом, и направлением.
            $stmt = $pdo->prepare('SELECT 1 FROM directions WHERE lower(name) = ?');
            $stmt->execute([$url]);

            if ($stmt->fetch()) {
                echo "url {$url} совпадает с именем направления — на верхнем уровне адреса не различить.\n";
                continue;
            }

            return $url;
        }
    }

    /** Название раздела свободное: «О нас», «Доставка». В адрес и в имя класса оно не входит. */
    private static function askTitle(): string
    {
        while (true) {
            echo 'Введите название раздела (как его видит посетитель): ';
            $input = trim((string) fgets(STDIN));

            if ($input === '' && feof(STDIN)) {
                echo "\nВвод закончен — команда остановлена.\n";
                exit(1);
            }

            if ($input === '') {
                echo "Название не может быть пустым.\n";
                continue;
            }

            return $input;
        }
    }

    private static function placeTitles(): array
    {
        $stmt = DbConnector::pdo()->prepare(
            'SELECT p.id, p.name, d.name AS direction FROM places p'
            . ' LEFT JOIN directions d ON d.id = p.direction_id ORDER BY d.name, p.name'
        );
        $stmt->execute();

        $titles = [];

        foreach ($stmt->fetchAll() as $row) {
            $titles[(int) $row['id']] = '#' . $row['id'] . ' ' . ($row['direction'] ?? '?') . '/' . $row['name'];
        }

        return $titles;
    }

    /** Номер, которым начинается строка меню: «#12 …». Он и есть id. */
    private static function leading(string $choice): int
    {
        return (int) substr($choice, 1, strpos($choice, ' ') - 1);
    }
}
