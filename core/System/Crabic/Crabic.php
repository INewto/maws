<?php

namespace Maws\System\Crabic;

use Maws\System\Contract\{Effect, Param, Purpose, Returns, Throws};
use Maws\System\Crabic\Exception\CrabicBroken;
use Maws\System\Exception\ResponseSent;
use Maws\System\Session\Session;
use Maws\World\System\DbConnector;
use Maws\World\System\Exception\DbaseFailed;
use PDOException;

/**
 * Часть ядра. Причина та же, что у Motion: состояние живёт в сессии, склад отпечатков создан
 * ядровой миграцией, половина протокола лежит в public/js. Хелпер обязан удаляться целиком —
 * после него остались бы таблица и скрипт; и ядро не может позвать хелпер, то есть проверку
 * нельзя было бы встроить в руки.
 *
 * Дверь одна — chew. Он сам отличает пачку трекера от отправки формы, сам отвечает на пачку
 * и сам судит отправку; причину отказа он пишет в журнал public.chews, а наружу отдаёт только
 * решение: разжевал или жевать нечего. Инструменту места не нужно писать ни протокол, ни проверку.
 *
 * В базу Крабик ходит сам, через DbConnector: ограничение прав придумано для app/** — ядро ведёт
 * свои таблицы и спрашивать разрешения у чужого dbase.json не должно.
 *
 * Здесь механика, а не арифметика: кольцо пачек в сессии и склад отпечатков в базе. Сами решения
 * принимает Motion — он и покрыт прогоном. У Crabic контракта нет: #[Fixture] умеет писать только
 * файлы в песочницу, а сессию и базу так не подделать, и зелёный контракт был бы зелёным просто
 * потому, что сессии нет. Двери проверяются живой пробой, как у Session::grant.
 */
final class Crabic
{
    /** Имя скрытого поля со сводкой. Оно же поле-ловушка: заполнено неверно — значит не трекер. */
    public const FIELD = 'crabic';

    /** Ключ кольца в сессии. Одно имя на все дорожки: у каждой вкладки своя. */
    public const KEY = '[useractions]';

    /** Ключ времени отрисовки страницы: от него считается, сколько человек на ней пробыл. */
    public const START = '[crabicstart]';

    /** Сколько пачек хранится на дорожку. Три — это девять секунд последнего движения. */
    public const KEEP = 3;

    /** Сколько дорожек держится разом: столько вкладок с формой не мешают друг другу. */
    public const TRACKS = 4;

    /** Сколько секунд живёт отпечаток на складе и запись в журнале. */
    public const TTL = 86400;

    /**
     * Сколько секунд страница обязана прожить до отправки. Секунда — это увидеть страницу, довести
     * указатель до кнопки и нажать; быстрее человек не успевает. Меряется по часам сервера, от
     * отрисовки: браузер получает страницу позже, поэтому у человека запас, а не у бота.
     */
    public const MIN = 1;

    #[Purpose('Одна дверь Крабика: пачку трекера принимает и отвечает 204, отправку формы судит, отрисовку страницы начинает заново.')]
    #[Param('fields', 'array', 'имена полей формы: по ним узнаётся отправка; пусто — отправкой считается любой POST без тела JSON')]
    #[Param('config', 'array', 'min — сколько секунд страница обязана прожить, ttl — сколько секунд живут отпечаток и запись журнала')]
    #[Returns('bool', 'true — Крабик разжевал, это бот; false — жевать нечего: отправки не было или она похожа на человеческую')]
    #[Throws(ResponseSent::class, 'пачка трекера: ответ 204 отправлен здесь, страница не рисуется')]
    #[Throws(DbaseFailed::class, 'сервер не записал отпечаток или запись журнала')]
    #[Effect(Effect::INPUT)]
    #[Effect(Effect::SCREEN)]
    #[Effect(Effect::DB_READ)]
    #[Effect(Effect::DB_WRITE)]
    public static function chew(array $fields = [], array $config = []): bool
    {
        // Пачка — служебный запрос: ответ уходит здесь, страница не рисуется вовсе.
        if (self::isBatch()) {
            self::receive();
        }

        $reason = self::isForm($fields) ? self::check($_POST, $config) : null;

        // Причина отказа живёт в журнале, а не в возврате: наружу отдаётся только решение.
        // Причина нужна тому, кто читает базу, а не тому, кто отправил форму.
        if ($reason !== null) {
            self::record($reason, self::ttl($config));
        }

        // Ответ — новая страница, и дорожка на ней начинается заново: кольцо прошлой страницы
        // к этой не относится, а отметка времени ставится сейчас. Сброс идёт после приговора:
        // приговору нужна отметка прошлой отрисовки, а не эта.
        self::reset();

        return $reason !== null;
    }

    #[Purpose('Начинает дорожку заново: страница с формой отрисована, старое кольцо относится к прошлой странице и не жалко. Заодно запоминается время отрисовки — единственное, чем сервер меряет, сколько страница прожила.')]
    #[Effect(Effect::NONE)]
    private static function reset(): void
    {
        $session = Session::instance();

        $session->forget(self::KEY);
        $session->set(self::START, self::now());
    }

    #[Purpose('Принимает пачку из тела запроса и отвечает пустым 204: трекеру нечего читать, а страницу ему отдавать незачем.')]
    #[Returns('never', 'ответ уходит здесь, страница не рисуется')]
    #[Throws(ResponseSent::class, 'всегда: ответ 204 отправлен')]
    #[Effect(Effect::INPUT)]
    #[Effect(Effect::SCREEN)]
    private static function receive(): never
    {
        $raw   = file_get_contents('php://input');
        $batch = is_string($raw) ? json_decode($raw, true) : null;

        if (is_array($batch)) {
            try {
                self::accept($batch);
            } catch (CrabicBroken) {
                // Вместо пачки пришёл мусор — обычное дело, и отвечать на него нечем:
                // кольцо просто не пополнится, а время, которое человек прожил, считает сервер.
            }
        }

        // Заголовки ставит само ядро: хелпер Response ядру недоступен, а ответ места — такой же
        // конец запроса, как у кнопки пульта. Код ставится и явно: в прогоне заголовки не
        // отправляются, и прочитать код было бы нечем.
        http_response_code(204);
        header('Cache-Control: no-store');

        throw new ResponseSent();
    }

    #[Purpose('Отличает пачку трекера от отправки формы: у пачки тело JSON, у формы — обычные поля.')]
    #[Returns('bool', 'true, если запрос пришёл с телом JSON')]
    #[Effect(Effect::NONE)]
    private static function isBatch(): bool
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';

        return is_string($type) && str_contains(strtolower($type), 'application/json');
    }

    #[Purpose('Отвечает, была ли это отправка формы: POST и хотя бы одно объявленное поле на месте. Пустой список — отправкой считается любой POST без тела JSON.')]
    #[Param('fields', 'array', 'имена полей формы')]
    #[Returns('bool', 'true, если пришёл POST с полями этой формы')]
    #[Effect(Effect::NONE)]
    private static function isForm(array $fields): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return false;
        }

        if ($fields === []) {
            return true;
        }

        foreach ($fields as $name) {
            if (is_string($name) && array_key_exists($name, $_POST)) {
                return true;
            }
        }

        return false;
    }

    #[Purpose('Принимает пачку трекера в кольцо своей дорожки. Кольцо скользит: четвёртая пачка вытесняет первую, поэтому сессия не растёт.')]
    #[Param('batch', 'array', 'пачка из трекера')]
    #[Throws(CrabicBroken::class, 'пачка не того вида — см. Motion::describe')]
    #[Effect(Effect::NONE)]
    private static function accept(array $batch): void
    {
        $described = Motion::describe($batch);
        $ring      = self::ring();
        $track     = $ring[$described['id']] ?? [];

        $track[] = $described + ['at' => self::now()];

        $ring[$described['id']] = array_slice($track, -self::KEEP);

        if (count($ring) > self::TRACKS) {
            uasort($ring, static fn (array $a, array $b): int => self::reached($a) <=> self::reached($b));
            $ring = array_slice($ring, -self::TRACKS, null, true);
        }

        Session::instance()->set(self::KEY, $ring);

        // Пачка — служебный запрос, и держать блокировку сессии, пока грузится вся страница,
        // значит поставить остальные вкладки в очередь за трекером. Сессия не уничтожается:
        // запись уже сделана, отпускается только замок.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    #[Purpose('Судит отправку формы: сводка из скрытого поля против того, что сервер наблюдал сам, потом отпечаток против склада. Пустое поле, битая сводка, сводка не с этой страницы и страница моложе секунды — отказ.')]
    #[Param('post', 'array', 'данные формы; сводка берётся из поля crabic')]
    #[Param('config', 'array', 'min — сколько секунд страница обязана прожить, ttl — сколько секунд живёт отпечаток')]
    #[Returns('string|null', 'причина отказа (empty, format, order, young, time, motion, repeat) или null, если человек')]
    #[Effect(Effect::DB_READ)]
    #[Effect(Effect::DB_WRITE)]
    private static function check(array $post, array $config): ?string
    {
        $raw = $post[self::FIELD] ?? null;

        // Пустое поле — не «нет данных, применим политику места», а прямой ответ: скрипта не было.
        // Без JS форма не работает давно, блокировщики наш трекер не трогают, а сломанный скрипт —
        // ошибка разработчика, и молчать о ней нельзя.
        if (!is_string($raw) || trim($raw) === '') {
            return 'empty';
        }

        $batch = json_decode($raw, true);

        if (!is_array($batch)) {
            return 'format';
        }

        try {
            $summary = Motion::describe($batch);
        } catch (CrabicBroken) {
            return 'format';
        }

        // Время прихода — в миллисекундах, как и dt в пачке: сравнивать секунды с миллисекундами
        // значит пропустить всё подряд.
        $summary['at'] = self::now();

        // Время отрисовки записал сам сервер, клиент его не присылает: только по нему видно,
        // сколько страница прожила на самом деле. Отметки нет — сессии не было, значит сводка
        // пришла не с этой страницы; без этого условия снятие печенья отменяло бы проверку времени.
        $started = Session::instance()->get(self::START);

        if (!is_int($started)) {
            return 'order';
        }

        // Порог по часам сервера, а не по числу присланных звеньев: звеньев может не быть вовсе —
        // форму из одной кнопки человек вправе отправить сразу, как дошёл до неё.
        if (($summary['at'] - $started) < max(0, (int) ($config['min'] ?? self::MIN)) * 1000) {
            return 'young';
        }

        $ring  = self::ring();
        $track = $ring[$summary['id']] ?? [];

        $reason = Motion::verdict($track, $summary, $started);

        if ($reason !== null) {
            return $reason;
        }

        $hits = self::remember([...$track, $summary], self::ttl($config));

        // Ноль — отпечаток не записывали: движения было слишком мало, чтобы он что-то опознавал.
        if ($hits > 1) {
            return 'repeat';
        }

        return null;
    }

    #[Purpose('Записывает причину отказа в журнал и заодно убирает из него просроченные строки: отдельной уборки у журнала нет.')]
    #[Param('reason', 'string', 'причина отказа: empty, format, order, young, time, motion, repeat')]
    #[Param('ttl', 'int', 'сколько секунд живёт запись')]
    #[Effect(Effect::DB_WRITE)]
    private static function record(string $reason, int $ttl): void
    {
        self::statement(
            'DELETE FROM public.chews WHERE chewed_at < now() - make_interval(secs => ?)',
            [$ttl]
        );

        self::statement('INSERT INTO public.chews (reason) VALUES (?)', [$reason]);
    }

    #[Purpose('Записывает отпечаток последовательности на склад и возвращает, сколько раз он там встретился, считая этот. Заодно убирает просроченные строки: отдельной уборки у склада нет.')]
    #[Param('sequence', 'array', 'пачки этой отправки по порядку')]
    #[Param('ttl', 'int', 'сколько секунд живёт отпечаток')]
    #[Returns('int', 'сколько раз отпечаток встретился; ноль — не записывали')]
    #[Effect(Effect::DB_READ)]
    #[Effect(Effect::DB_WRITE)]
    private static function remember(array $sequence, int $ttl): int
    {
        $cells  = 0;
        $fine   = [];
        $coarse = [];

        foreach ($sequence as $batch) {
            $cells += $batch['cells'];
            $fine[]   = $batch['hash'];
            $coarse[] = $batch['coarse'];
        }

        if ($cells < Motion::MIN_CELLS) {
            return 0;
        }

        self::statement(
            'DELETE FROM public.fingerprints WHERE last_at < now() - make_interval(secs => ?)',
            [$ttl]
        );

        $row = self::statement(
            'INSERT INTO public.fingerprints (hash, coarse) VALUES (?, ?)
             ON CONFLICT (hash) DO UPDATE SET hits = fingerprints.hits + 1, last_at = now()
             RETURNING hits',
            [hash('xxh128', implode('|', $fine)), hash('xxh128', implode('|', $coarse))]
        )->fetch();

        return (int) ($row['hits'] ?? 0);
    }

    // Сколько живут отпечаток и запись журнала. Сутки — по умолчанию, минута и год — границы
    // здравого смысла: ноль означал бы «не хранить вовсе», а вечность — склад, который не чистится.
    private static function ttl(array $config): int
    {
        return max(60, min((int) ($config['ttl'] ?? self::TTL), 31536000));
    }

    // Ошибку сервера заворачиваем так же, как Dbase: одна строка с SQL и причиной, без трейса.
    private static function statement(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = DbConnector::pdo()->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            throw new DbaseFailed($sql, $e->getMessage());
        }

        return $stmt;
    }

    #[Purpose('Отдаёт кольцо пачек из сессии. Битое или чужое значение читается как пустое: кольцо — данные снаружи, доверять его виду нельзя.')]
    #[Returns('array', 'дорожки: id страницы → пачки по порядку')]
    #[Effect(Effect::NONE)]
    private static function ring(): array
    {
        $ring = Session::instance()->get(self::KEY, []);

        return is_array($ring) ? $ring : [];
    }

    // Время в миллисекундах — одна мера на всё: в пачке dt тоже в миллисекундах, и сравнивать
    // секунды с миллисекундами значит пропустить всё подряд.
    private static function now(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    // Время последней пачки дорожки — по нему вытесняется самая старая вкладка.
    private static function reached(array $track): float
    {
        $last = $track[count($track) - 1] ?? ['at' => 0.0];

        return (float) ($last['at'] ?? 0.0);
    }
}
