<?php

namespace Maws\World;

use Maws\System\Contract\{Effect, Fails, Param, Purpose, Returns, Subject, Throws};
use Maws\World\System\DbConnector;
use Maws\World\System\Exception\DbConnectDenied;
use Maws\World\System\Exception\DirectionNotFound;
use Maws\World\System\Exception\PlaceBroken;
use Maws\World\System\Exception\PlaceNotFound;
use Maws\World\System\Exception\WorkspacesMissing;
use Maws\World\System\Place;

#[Subject(World::class, 'new', [])]
#[Purpose('Карта мира: какие есть направления и какие места в них. Обе правды берутся из базы: направления — из directions, места — из places. Найдя место, мир подгружает базовый класс его направления: {Напр}Workspaces объявлен файлом, автозагрузка его не видит.')]
class World
{
    /** Место по id: имя направления приходит соединением, поэтому Place знает, где он лежит. */
    private const PLACE_SQL = 'SELECT p.id, p.name, d.name AS direction'
        . ' FROM places p LEFT JOIN directions d ON d.id = p.direction_id WHERE p.id = ?';

    /** Место по адресу: id берётся из places, направление — из directions по direction_id. */
    private const FIND_SQL = 'SELECT p.id, p.name, d.name AS direction'
        . ' FROM places p LEFT JOIN directions d ON d.id = p.direction_id'
        . ' WHERE p.name = ? AND d.name = ?';

    #[Purpose('Ничего не собирает: направления и места читаются из базы по запросу, карты в памяти нет.')]
    #[Effect(Effect::NONE)]
    public function __construct()
    {
    }

    #[Purpose('Собирает место по адресу «Направление/Место»: имя места и имя его направления сверяются со строками базы.')]
    #[Param('way', 'string', 'адрес вида "Направление/Место"')]
    #[Returns('Place', 'место, привязанное к корню мира')]
    #[Throws(PlaceBroken::class, 'адрес не состоит ровно из двух непустых частей')]
    #[Throws(PlaceNotFound::class, 'такого места нет в базе')]
    #[Throws(DirectionNotFound::class, 'у места нет направления или направления с таким именем нет')]
    #[Throws(WorkspacesMissing::class, 'у направления нет файла {Напр}Workspaces.php')]
    #[Throws(DbConnectDenied::class, 'дверь в базу открыта не голове, не миру и не базе')]
    #[Effect(Effect::DB_READ)]
    #[Effect(Effect::FS_READ)]
    #[Fails(['Nope/Nope'], DirectionNotFound::class)]
    #[Fails(['Public/Nope'], PlaceNotFound::class)]
    #[Fails(['Public'], PlaceBroken::class)]
    public function find(string $way): Place
    {
        $parts = explode('/', $way);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new PlaceBroken($way);
        }

        $stmt = DbConnector::pdo()->prepare(self::FIND_SQL);
        $stmt->execute([$parts[1], $parts[0]]);
        $row = $stmt->fetch();

        if (!$row) {
            // Сначала сверяем направление: «нет такого направления» и «нет такого места
            // в направлении» — разные беды, и лечатся они в разных файлах.
            $stmt = DbConnector::pdo()->prepare('SELECT 1 FROM directions WHERE name = ?');
            $stmt->execute([$parts[0]]);

            if ($stmt->fetchColumn() === false) {
                throw new DirectionNotFound($parts[0]);
            }

            throw new PlaceNotFound($way);
        }

        $place = Place::fromRow($row, WORLD_DIR);

        self::loadDirection($place->direction());

        return $place;
    }

    #[Purpose('Собирает место по id, проверив, что направление существует.')]
    #[Param('id', 'int', 'id строки в places')]
    #[Returns('Place', 'место, привязанное к корню мира')]
    #[Throws(PlaceNotFound::class, 'места с таким id нет в базе')]
    #[Throws(DirectionNotFound::class, 'у места нет направления: direction_id не находит строку в directions')]
    #[Throws(WorkspacesMissing::class, 'у направления нет файла {Напр}Workspaces.php')]
    #[Throws(DbConnectDenied::class, 'дверь в базу открыта не голове и не базе')]
    #[Effect(Effect::DB_READ)]
    #[Effect(Effect::FS_READ)]
    #[Fails([999999], PlaceNotFound::class)]
    public function place(int $id): Place
    {
        $stmt = DbConnector::pdo()->prepare(self::PLACE_SQL);
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new PlaceNotFound("#{$id}");
        }

        // LEFT JOIN ради этой проверки: место без направления не притворяется местом
        // ни в одном направлении — каталога у него нет.
        if ($row['direction'] === null) {
            throw new DirectionNotFound("#{$id}");
        }

        $place = Place::fromRow($row, WORLD_DIR);

        self::loadDirection($place->direction());

        return $place;
    }

    #[Purpose('Подгружает базовый класс направления: файл {Напр}Workspaces рядом с местами объявляет его полным именем.')]
    #[Param('direction', 'string', 'имя направления из базы')]
    #[Throws(WorkspacesMissing::class, 'файла {Напр}Workspaces.php нет по ожидаемому пути или он не объявил класс')]
    #[Effect(Effect::FS_READ)]
    public static function loadDirection(string $direction): void
    {
        $file = WORLD_DIR . '/' . $direction . '/Places/' . $direction . 'Workspaces.php';

        if (!is_file($file)) {
            throw new WorkspacesMissing($direction, $file);
        }

        require_once $file;

        // Автозагрузка не спрашивается: файл уже подключён, а её промах включил бы его
        // второй раз.
        $class = 'App\\World\\' . $direction . '\\Places\\' . $direction . 'Workspaces';

        if (!class_exists($class, false)) {
            throw new WorkspacesMissing($direction, $file);
        }
    }
}
