<?php

namespace Maws\System\Helpers\Cookie;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Cookie\Exception\CookieMisuse;

#[Subject(Cookie::class, 'new')]
#[Purpose('Собирает строку Set-Cookie так, чтобы браузер её принял, а не выбросил молча: имя сверяется с правилами RFC 6265, значение кодируется (как это делает setcookie), SameSite=None без Secure отвергается, а префиксы __Host- и __Secure- получают свои обязательные условия. Само печенье не отправляется: хелпер отдаёт заголовок, потому что отправка зависит от того, как устроен ответ сайта.')]
final class Cookie
{
    private const SAME_SITE = ['lax' => 'Lax', 'strict' => 'Strict', 'none' => 'None'];

    private const NAME = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/';

    private function __construct()
    {
    }

    #[Purpose('Собирает Set-Cookie. Secure по умолчанию включён: без него печенье уходит и по http, то есть его можно перехватить, а заметить это в работе сайта нечем — локальная разработка без TLS снимает его явно.')]
    #[Param('name', 'string', 'имя печенья по RFC 6265: латиница, цифры и !#$%&\'*+-.^_`|~')]
    #[Param('value', 'string', 'значение как есть; кодируется само, поэтому читать его надо как обычное значение из $_COOKIE')]
    #[Param('expires', 'int|null', 'метка времени, когда печенье истечёт; null — до закрытия браузера')]
    #[Param('path', 'string', 'путь, на котором печенье видно; начинается со слэша')]
    #[Param('domain', 'string|null', 'хост, которому печенье принадлежит; null — только текущий')]
    #[Param('secure', 'bool', 'только по https; на боевом сайте обязан быть включён')]
    #[Param('httpOnly', 'bool', 'скрыть от JavaScript — тогда украденный скрипт печенье не прочитает')]
    #[Param('sameSite', 'string|null', 'Lax, Strict или None; null — не указывать вовсе')]
    #[Returns('string', 'значение заголовка Set-Cookie')]
    #[Throws(CookieMisuse::class, 'имя не по RFC, путь без слэша, SameSite=None без Secure, нарушены условия префикса')]
    #[Effect(Effect::NONE)]
    #[Gives(['sid', 'abc'], 'sid=abc; Path=/; Secure; HttpOnly; SameSite=Lax')]
    #[Gives(['sid', 'abc', 1445412480], 'sid=abc; Expires=Wed, 21 Oct 2015 07:28:00 GMT; Path=/; Secure; HttpOnly; SameSite=Lax')]
    #[Gives(['sid', 'Аня'], 'sid=%D0%90%D0%BD%D1%8F; Path=/; Secure; HttpOnly; SameSite=Lax')]
    #[Gives(['sid', 'a b;c'], 'sid=a%20b%3Bc; Path=/; Secure; HttpOnly; SameSite=Lax')]
    #[Gives(['sid', 'abc', null, '/', 'example.test'], 'sid=abc; Path=/; Domain=example.test; Secure; HttpOnly; SameSite=Lax')]
    #[Gives(['sid', 'abc', null, '/', null, false, false, null], 'sid=abc; Path=/')]
    #[Gives(['sid', 'abc', null, '/', null, true, true, 'strict'], 'sid=abc; Path=/; Secure; HttpOnly; SameSite=Strict')]
    #[Gives(['__Host-sid', 'abc'], '__Host-sid=abc; Path=/; Secure; HttpOnly; SameSite=Lax')]
    #[Fails(['__Host-sid', 'abc', null, '/', 'example.test'], CookieMisuse::class)]
    #[Fails(['__Host-sid', 'abc', null, '/app'], CookieMisuse::class)]
    #[Fails(['__Secure-sid', 'abc', null, '/', null, false], CookieMisuse::class)]
    #[Fails(['sid name', 'abc'], CookieMisuse::class)]
    #[Fails(['', 'abc'], CookieMisuse::class)]
    #[Fails(['sid', 'abc', null, 'app'], CookieMisuse::class)]
    #[Fails(['sid', 'abc', null, '/', null, false, true, 'None'], CookieMisuse::class)]
    #[Fails(['sid', 'abc', null, '/', null, true, true, 'Laxa'], CookieMisuse::class)]
    public static function create(
        string $name,
        string $value,
        ?int $expires = null,
        string $path = '/',
        ?string $domain = null,
        bool $secure = true,
        bool $httpOnly = true,
        ?string $sameSite = 'Lax'
    ): string {
        self::demandName($name, $secure, $path, $domain);
        self::demandPath($path);
        self::demandDomain($domain);

        $parts = [$name . '=' . rawurlencode($value)];

        if ($expires !== null) {
            $parts[] = 'Expires=' . gmdate('D, d M Y H:i:s \G\M\T', $expires);
        }

        $parts[] = 'Path=' . $path;

        if ($domain !== null) {
            $parts[] = 'Domain=' . $domain;
        }

        if ($secure) {
            $parts[] = 'Secure';
        }

        if ($httpOnly) {
            $parts[] = 'HttpOnly';
        }

        if ($sameSite !== null) {
            $parts[] = 'SameSite=' . self::sameSite($sameSite, $secure);
        }

        return implode('; ', $parts);
    }

    #[Purpose('Собирает Set-Cookie, который стирает печенье: пустое значение и срок в 1970 году. Имя, путь и домен обязаны совпасть с исходными — браузер ищет печенье по ним, и промах оставляет старое на месте.')]
    #[Param('name', 'string', 'имя стираемого печенья')]
    #[Param('path', 'string', 'путь, с которым печенье ставили')]
    #[Param('domain', 'string|null', 'домен, с которым печенье ставили')]
    #[Param('secure', 'bool', 'должен совпадать с тем, как печенье ставили')]
    #[Returns('string', 'значение заголовка Set-Cookie')]
    #[Throws(CookieMisuse::class, 'имя не по RFC или нарушены условия префикса')]
    #[Effect(Effect::NONE)]
    #[Gives(['sid'], 'sid=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; Secure; HttpOnly; SameSite=Lax')]
    #[Gives(['sid', '/app', 'example.test'], 'sid=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/app; Domain=example.test; Secure; HttpOnly; SameSite=Lax')]
    #[Fails(['sid name'], CookieMisuse::class)]
    public static function forget(
        string $name,
        string $path = '/',
        ?string $domain = null,
        bool $secure = true
    ): string {
        return self::create($name, '', 0, $path, $domain, $secure);
    }

    private static function demandName(string $name, bool $secure, string $path, ?string $domain): void
    {
        if (preg_match(self::NAME, $name) !== 1) {
            throw new CookieMisuse(
                "имя печенья \"{$name}\" не по RFC 6265: только латиница, цифры и !#$%&'*+-.^_`|~"
            );
        }

        if (str_starts_with($name, '__Secure-') && !$secure) {
            throw new CookieMisuse('имя с префиксом __Secure- требует Secure: иначе браузер печенье выбросит');
        }

        if (str_starts_with($name, '__Host-') && (!$secure || $path !== '/' || $domain !== null)) {
            throw new CookieMisuse('имя с префиксом __Host- требует Secure, Path=/ и отсутствие Domain');
        }
    }

    private static function demandPath(string $path): void
    {
        if ($path === '' || !str_starts_with($path, '/')) {
            throw new CookieMisuse("путь печенья начинается со слэша, получено \"{$path}\"");
        }
    }

    private static function demandDomain(?string $domain): void
    {
        if ($domain === null) {
            return;
        }

        if ($domain === '' || preg_match('/[\s;,"]/', $domain) === 1) {
            throw new CookieMisuse("домен печенья — имя хоста без пробелов и разделителей, получено \"{$domain}\"");
        }
    }

    private static function sameSite(string $sameSite, bool $secure): string
    {
        $canonical = self::SAME_SITE[strtolower($sameSite)] ?? null;

        if ($canonical === null) {
            throw new CookieMisuse("SameSite бывает Lax, Strict или None, получено \"{$sameSite}\"");
        }

        if ($canonical === 'None' && !$secure) {
            throw new CookieMisuse(
                'SameSite=None без Secure браузер отвергнет: такое печенье нужно для сторонних запросов, '
                . 'а по http его перехватывают'
            );
        }

        return $canonical;
    }
}
