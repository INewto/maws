<?php

namespace Maws\System\Session;

use Maws\System\Contract\Purpose;
use Maws\System\Session\Exception\SessionMisuse;

#[Purpose('Сессия посетителя: одна дверь на процесс к хранилищу, которое живёт дольше страницы. Открывается во входе до первого вывода — после вывода браузер печенье не примет, и сессия окажется новой на каждом запросе.')]
final class Session
{
    private static ?self $instance = null;

    // Слот вошедшего. Точка впереди — чтобы ключ не мог совпасть с ключом, который придумал сайт,
    // и по имени было видно, что это не данные приложения.
    private const AUTH = '.auth';

    // Имя ключа токена формы. Токен — дело хелпера Csrf, но гасит его вход, поэтому имя ключа живёт
    // здесь: иначе токен, известный до входа, переехал бы в новую сессию вместе с остальными данными.
    public const TOKEN = '.csrf';

    private function __construct()
    {
    }

    #[Purpose('Открывает сессию по политике из config.json, который лежит рядом с классом: назначает имя и условия печенья, поднимает сессию. Вызывается один раз, во входе, до первого вывода. Путь к файлу — только для прогона и пробника: обычный вызов open() читает config.json рядом с классом.')]
    public static function open(?string $file = null): self
    {
        if (self::$instance !== null || session_status() === PHP_SESSION_ACTIVE) {
            throw new SessionMisuse('сессия уже открыта: open() вызывается один раз, во входе');
        }

        $settings = SessionSettings::fromFile($file ?? __DIR__ . '/config.json');

        if (headers_sent($sentFile, $sentLine)) {
            throw new SessionMisuse(
                "сессия открывается до первого вывода, а вывод уже был: {$sentFile}:{$sentLine} — "
                . 'иначе браузер не примет печенье, и сессия будет новой на каждом запросе'
            );
        }

        // Идентификатор принимается только из печенья и только свой: иначе навязанный из адреса
        // или подобранный чужой идентификатор подхватывается как свой, и вход ничего не защищает.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        // Файл сессии живёт по своему сроку, и он не связан со сроком печенья: политика в тридцать
        // дней с умолчанием php.ini (24 минуты) потеряла бы сессию на сервере, оставив печенье.
        if ($settings->lifetime > 0) {
            ini_set('session.gc_maxlifetime', (string) $settings->lifetime);
        }

        if (session_name($settings->name) === false) {
            throw new SessionMisuse("PHP не принял имя сессии \"{$settings->name}\"");
        }

        // Secure обязан совпадать со схемой запроса: по http печенье с Secure браузер выбросит сам,
        // а по https без него печенье уйдёт открытым. null в политике значит «решить по схеме».
        $secure = $settings->secure ?? self::overHttps();

        // Путь и httpOnly — не политика, а условие работоспособности: сессия на подпути рвётся
        // на любой ссылке мимо него, а идентификатор JavaScript не нужен никогда.
        $params = [
            'lifetime' => $settings->lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => $settings->sameSite,
        ];

        session_set_cookie_params($params);

        // Заголовки кэша — дело экрана, а не сессии: с умолчанием php.ini PHP сам печатает
        // no-store, Pragma и Expires, и политика места их уже не перебьёт.
        session_cache_limiter('');

        session_start();

        self::$instance = new self();

        return self::$instance;
    }

    #[Purpose('Отдаёт открытую сессию. Сам не открывает: открытие — дело входа, а открытие из середины страницы означает, что заголовки уже уехали.')]
    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new SessionMisuse('сессия не открыта: open() вызывается во входе, до первого вывода');
        }

        return self::$instance;
    }

    #[Purpose('Объявляет посетителя вошедшим: меняет идентификатор сессии и кладёт субъект в зарезервированный слот. Одно действие, потому что порознь эти два шага забывают делать вместе — и тогда идентификатор, навязанный до входа, становится идентификатором вошедшего. Пароль, роли и хранилище — дело сайта: сюда приходит уже проверенный субъект.')]
    public static function grant(int|string $subject, array $claims = []): void
    {
        self::instance();

        if (headers_sent($sentFile, $sentLine)) {
            throw new SessionMisuse(
                "вход объявляется до первого вывода, а вывод уже был: {$sentFile}:{$sentLine} — "
                . 'новый идентификатор уехал бы в уже отправленные заголовки'
            );
        }

        // Сначала смена идентификатора, потом запись: если PHP откажет, в сессии не останется
        // полупризнака «вошёл, но со старым идентификатором». true — старый файл удаляется сразу,
        // иначе он живёт до сборки мусора и остаётся годным для того, кто его знает.
        if (!session_regenerate_id(true)) {
            throw new SessionMisuse('PHP не сменил идентификатор сессии');
        }

        $_SESSION[self::AUTH] = ['id' => $subject, 'claims' => $claims];

        // Токен формы гасится: он был известен до входа — например, тому, кто навязал идентификатор,
        // — а после входа форма с этим токеном прошла бы проверку.
        unset($_SESSION[self::TOKEN]);
    }

    #[Purpose('Забывает, что посетитель вошёл: гасит слот, токен формы и меняет идентификатор сессии. Это не close(): тот уничтожает сессию целиком, а корзина гостя должна пережить выход. Если сессия не открыта, делать нечего: выход требуется и гостю.')]
    public static function revoke(): void
    {
        if (self::$instance === null) {
            return;
        }

        if (headers_sent($sentFile, $sentLine)) {
            throw new SessionMisuse(
                "выход объявляется до первого вывода, а вывод уже был: {$sentFile}:{$sentLine} — "
                . 'идентификатор остался бы прежним, то есть годным'
            );
        }

        unset($_SESSION[self::AUTH], $_SESSION[self::TOKEN]);

        if (!session_regenerate_id(true)) {
            throw new SessionMisuse('PHP не сменил идентификатор сессии');
        }
    }

    #[Purpose('Кто вошёл: идентификатор субъекта или null, если посетитель гость.')]
    public function subject(): int|string|null
    {
        return $_SESSION[self::AUTH]['id'] ?? null;
    }

    #[Purpose('Что о вошедшем помнит сессия — роли, имя, что положил сайт. Содержимое ядру безразлично: оно его хранит и не толкует. Пустой массив — гость.')]
    public function claims(): array
    {
        return $_SESSION[self::AUTH]['claims'] ?? [];
    }

    #[Purpose('Закрывает сессию посетителя: стирает данные, уничтожает файл сессии и печенье, забывает дверь. Нужен там, где сессия не нужна целиком; выход из аккаунта — revoke(), он сохраняет остальные данные. Если сессия не открыта, делать нечего: закрывать нечего.')]
    public static function close(): void
    {
        if (self::$instance === null && session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $name   = session_name();
        $params = session_get_cookie_params();

        self::reset();

        // Печенье стирается с тем же путём, доменом и условиями, с какими его ставили: браузер
        // ищет печенье по ним, и промах оставил бы старое на месте.
        $cookie = [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
        ];

        if ($params['domain'] !== '') {
            $cookie['domain'] = $params['domain'];
        }

        if (($params['samesite'] ?? '') !== '') {
            $cookie['samesite'] = $params['samesite'];
        }

        // Заголовки уже уехали — печенье не стереть. Сессия всё равно уничтожена, а старый
        // идентификатор ничего не открывает: строгий режим не примет его как свой.
        if (!headers_sent()) {
            setcookie($name, '', $cookie);
        }
    }

    #[Purpose('Закрывает сессию и забывает дверь. Нужен прогону: иначе файл сессии и её содержимое переживут случай.')]
    public static function reset(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        self::$instance = null;
    }

    #[Purpose('Читает значение по ключу. null — ключа нет; спрашивать «есть ли» отдельно — has().')]
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    #[Purpose('Запоминает значение до конца сессии. Слот вошедшего закрыт: его ставит только grant(), иначе вход обошёл бы смену идентификатора.')]
    public function set(string $key, mixed $value): void
    {
        if ($key === self::AUTH) {
            throw new SessionMisuse('слот вошедшего ставится только grant(): иначе вход обошёл бы смену идентификатора');
        }

        $_SESSION[$key] = $value;
    }

    #[Purpose('Спрашивает, есть ли значение по ключу, — без исключения в ответ на «нет».')]
    public function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    #[Purpose('Забывает значение по ключу. Слот вошедшего закрыт: его гасит только revoke(), иначе остался бы годный идентификатор.')]
    public function forget(string $key): void
    {
        if ($key === self::AUTH) {
            throw new SessionMisuse('слот вошедшего гасится только revoke(): иначе остался бы годный идентификатор');
        }

        unset($_SESSION[$key]);
    }

    #[Purpose('Читает значение и сразу забывает его — из этого собирается сообщение, которое показывают один раз. Слот вошедшего сюда не относится: он не одноразовый, для него есть subject() и claims().')]
    public function pull(string $key, mixed $default = null): mixed
    {
        if ($key === self::AUTH) {
            throw new SessionMisuse('слот вошедшего читается через subject() и claims()');
        }

        $value = $this->get($key, $default);
        $this->forget($key);

        return $value;
    }

    // Схема запроса. Заголовкам вида X-Forwarded-Proto не верим: их присылает кто угодно, а ошибка
    // здесь тихая. За прокси, который обрывает TLS, сайт ставит "secure": true сам.
    // Public, потому что по этой же схеме решается HSTS в public/index.php: правило должно быть одно,
    // иначе печенье и заголовок разойдутся в том, что считать https.
    public static function overHttps(): bool
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }
}
