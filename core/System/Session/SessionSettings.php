<?php

namespace Maws\System\Session;

use JsonException;
use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Session\Exception\SessionMisuse;

#[Subject(SessionSettings::class, 'fromArray', [self::SAMPLE])]
#[Purpose('Политика сессии: имя печенья, срок жизни, SameSite и Secure. Только то, что действительно решает сайт, — путь и домен печенья у сессии постоянны, а httpOnly не выбор никогда. Умолчаний нет намеренно: SameSite, Secure и срок жизни решают, кто и откуда может пользоваться сессией.')]
final class SessionSettings
{
    // Образец набора — для прогона: атрибуты требуют константных выражений, поэтому файл
    // config.json здесь не прочитать. Прогон проверяет разбор набора, а сам файл читается
    // в бою на каждом запросе, и сломанный файл падает на первом же.
    private const SAMPLE = [
        'name' => 'maws_sid',
        'lifetime' => 0,
        'secure' => null,
        'sameSite' => 'Lax',
    ];

    private const KEYS = ['name', 'lifetime', 'secure', 'sameSite'];

    private const SAME_SITE = ['lax' => 'Lax', 'strict' => 'Strict', 'none' => 'None'];

    private const BAD_EXTRA_KEY = ['path' => '/'] + self::SAMPLE;

    private const BAD_MISSING_KEY = ['name' => 'maws_sid', 'lifetime' => 0, 'secure' => null];

    private const BAD_LIFETIME = ['lifetime' => -1] + self::SAMPLE;

    private const BAD_NAME = ['name' => ''] + self::SAMPLE;

    private const BAD_NAME_DIGITS = ['name' => '12345'] + self::SAMPLE;

    private const BAD_SECURE = ['secure' => 'yes'] + self::SAMPLE;

    private const BAD_PREFIX_SECURE = ['name' => '__Host-maws'] + self::SAMPLE;

    private const BAD_SAME_SITE_NONE = ['sameSite' => 'None'] + self::SAMPLE;

    private const BAD_SAME_SITE = ['sameSite' => 'Sometimes'] + self::SAMPLE;

    private function __construct(
        public readonly string $name,
        public readonly int $lifetime,
        public readonly ?bool $secure,
        public readonly string $sameSite,
    ) {
    }

    #[Purpose('Собирает политику из набора, каким её пишут в config.json, и проверяет каждое поле.')]
    #[Param('config', 'array', 'набор с ключами name, lifetime, secure, sameSite')]
    #[Returns('self', 'политика сессии')]
    #[Throws(SessionMisuse::class, 'ключа нет, ключ лишний или значение не того вида')]
    #[Effect(Effect::NONE)]
    #[Fails([self::BAD_EXTRA_KEY], SessionMisuse::class)]
    #[Fails([self::BAD_MISSING_KEY], SessionMisuse::class)]
    #[Fails([self::BAD_LIFETIME], SessionMisuse::class)]
    #[Fails([self::BAD_NAME], SessionMisuse::class)]
    #[Fails([self::BAD_NAME_DIGITS], SessionMisuse::class)]
    #[Fails([self::BAD_SECURE], SessionMisuse::class)]
    #[Fails([self::BAD_PREFIX_SECURE], SessionMisuse::class)]
    #[Fails([self::BAD_SAME_SITE_NONE], SessionMisuse::class)]
    #[Fails([self::BAD_SAME_SITE], SessionMisuse::class)]
    public static function fromArray(array $config): self
    {
        $unknown = array_diff(array_keys($config), self::KEYS);

        if ($unknown !== []) {
            throw new SessionMisuse('в конфиге сессии лишние ключи: ' . implode(', ', $unknown));
        }

        $missing = array_diff(self::KEYS, array_keys($config));

        if ($missing !== []) {
            throw new SessionMisuse('в конфиге сессии нет ключей: ' . implode(', ', $missing) . ' — политику задаёт разработчик, умолчаний нет');
        }

        $name = $config['name'];

        if (!is_string($name) || $name === '') {
            throw new SessionMisuse('"name" должен быть непустой строкой — именем печенья сессии');
        }

        // PHP не примет имя из одних цифр: он считает такое имя пустым и оставляет PHPSESSID,
        // то есть сессия завелась бы под чужим именем и молча.
        if (preg_match('/^\d+$/', $name) === 1) {
            throw new SessionMisuse("имя сессии \"{$name}\" из одних цифр — PHP такое имя не примет");
        }

        $lifetime = $config['lifetime'];

        if (!is_int($lifetime) || $lifetime < 0) {
            throw new SessionMisuse('"lifetime" — целое число секунд: 0 — печенье до закрытия браузера');
        }

        $secure = $config['secure'];

        if ($secure !== null && !is_bool($secure)) {
            throw new SessionMisuse('"secure" — true, false или null: null значит «брать из схемы запроса»');
        }

        $sameSite = $config['sameSite'];

        if (!is_string($sameSite)) {
            throw new SessionMisuse('"sameSite" — строка Lax, Strict или None');
        }

        $canonical = self::SAME_SITE[strtolower($sameSite)] ?? null;

        if ($canonical === null) {
            throw new SessionMisuse("SameSite бывает Lax, Strict или None, получено \"{$sameSite}\"");
        }

        // Имя печенья — токен по RFC 6265: латиница, цифры и !#$%&'*+-.^_`|~. Правило своё:
        // ядро не берёт его у хелпера Cookie — хелпер обязан удаляться, не роняя ядро.
        if (preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name) !== 1) {
            throw new SessionMisuse(
                "имя печенья \"{$name}\" не по RFC 6265: только латиница, цифры и !#$%&'*+-.^_`|~"
            );
        }

        // null значит «Secure по схеме запроса»: включится он или нет, заранее неизвестно. Всё, что
        // браузер требует вместе с Secure, с null не гарантировано.
        if ($secure !== true && (str_starts_with($name, '__Host-') || str_starts_with($name, '__Secure-'))) {
            throw new SessionMisuse("имя \"{$name}\" требует \"secure\": true — с null схема запроса может оказаться http");
        }

        if ($secure !== true && $canonical === 'None') {
            throw new SessionMisuse('SameSite=None требует "secure": true — с null схема запроса может оказаться http, и браузер печенье выбросит');
        }

        return new self($name, $lifetime, $secure, $canonical);
    }

    #[Purpose('Читает политику из config.json — того, что лежит рядом с классом сессии.')]
    #[Param('file', 'string', 'путь к config.json')]
    #[Returns('self', 'политика сессии')]
    #[Throws(SessionMisuse::class, 'файл не читается, JSON битый или в нём не тот набор ключей')]
    #[Effect(Effect::FS_READ)]
    #[Fails(['/нет-такого-конфига.json'], SessionMisuse::class)]
    public static function fromFile(string $file): self
    {
        if (!is_readable($file)) {
            throw new SessionMisuse("конфиг сессии не читается: {$file}");
        }

        try {
            $config = \json5_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SessionMisuse("конфиг сессии — битый JSON: {$file} ({$e->getMessage()})");
        }

        if (!is_array($config)) {
            throw new SessionMisuse("конфиг сессии должен быть набором ключей, а не списком: {$file}");
        }

        return self::fromArray($config);
    }

    #[Purpose('Отдаёт политику тем же набором, каким её задали, — чтобы её можно было записать, сравнить или передать дальше.')]
    #[Returns('array', 'набор с ключами name, lifetime, secure, sameSite')]
    #[Effect(Effect::NONE)]
    #[Gives([], self::SAMPLE)]
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'lifetime' => $this->lifetime,
            'secure' => $this->secure,
            'sameSite' => $this->sameSite,
        ];
    }
}
