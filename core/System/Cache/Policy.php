<?php

namespace Maws\System\Cache;

use JsonException;
use Maws\System\Cache\Exception\CacheMisuse;
use Maws\System\Contract\{Effect, Fails, Fixture, Gives, Param, Purpose, Returns, Subject, Throws};

/**
 * Часть ядра, а не хелпер: политику читают голова и экран, а хелперы — необязательный слой,
 * который разработчик вправе удалить целиком, ничего не сломав.
 */
#[Subject(Policy::class, 'Scaffold::policy', ['plain/settings.json'])]
#[Fixture(files: [
    'plain/settings.json'     => '{"tools": {"list": []}}',
    'bad-ttl/settings.json'   => '{"tools": {"list": []}, "cache": {"ttl": "60"}}',
    'bad-scope/settings.json' => '{"tools": {"list": []}, "cache": {"ttl": 60, "scope": "everyone"}}',
    'extra/settings.json'     => '{"tools": {"list": []}, "cache": {"ttl": 60, "vary": ["Accept-Language"]}}',
])]
#[Purpose('Политика кэша места из settings.json: сколько секунд ответ свежий и кто вправе его хранить.')]
final class Policy
{
    private function __construct(private readonly ?int $ttl, private readonly bool $public)
    {
    }

    #[Purpose('Политика «не хранить»: у страниц ошибок и у мест, которые ничего не объявили.')]
    #[Returns('Policy', 'политика no-store')]
    #[Effect(Effect::NONE)]
    public static function none(): self
    {
        return new self(null, false);
    }

    #[Purpose('Читает settings.json места. Нет файла или нет ключа "cache" — политика «не хранить»: молча кэшировать личные страницы нельзя.')]
    #[Param('dir', 'string', 'каталог места; settings.json лежит в нём')]
    #[Returns('Policy', 'объявленная политика или no-store')]
    #[Throws(CacheMisuse::class, 'ключ "cache" не объект, неизвестная настройка, ttl не целое или отрицательный, scope не из двух значений')]
    #[Effect(Effect::FS_READ)]
    #[Fails(['@sandbox/bad-ttl'], CacheMisuse::class)]
    #[Fails(['@sandbox/bad-scope'], CacheMisuse::class)]
    #[Fails(['@sandbox/extra'], CacheMisuse::class)]
    public static function fromDir(string $dir): self
    {
        $file = $dir . '/settings.json';

        if (!is_readable($file)) {
            return self::none();
        }

        $raw = file_get_contents($file);

        if ($raw === false) {
            return self::none();
        }

        try {
            $settings = \json5_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CacheMisuse('settings.json не разбирается: ' . $e->getMessage());
        }

        if (!is_object($settings) || !property_exists($settings, 'cache')) {
            return self::none();
        }

        $cache = $settings->cache;

        if (!is_object($cache)) {
            throw new CacheMisuse('"cache" должен быть объектом');
        }

        // Неизвестный ключ — отказ: опечатка в "scope" иначе молча оставила бы private,
        // а опечатка в "ttl" — молча no-store, и оба случая выглядели бы как «работает».
        foreach (array_keys(get_object_vars($cache)) as $key) {
            if (!in_array($key, ['ttl', 'scope'], true)) {
                throw new CacheMisuse("неизвестная настройка кэша \"{$key}\": есть только ttl и scope");
            }
        }

        $ttl = $cache->ttl ?? null;

        if (!is_int($ttl) || $ttl < 0) {
            throw new CacheMisuse('"ttl" — целое число секунд, ноль или больше');
        }

        $scope = $cache->scope ?? 'private';

        if (!in_array($scope, ['private', 'public'], true)) {
            throw new CacheMisuse('"scope" — "private" или "public"');
        }

        return new self($ttl, $scope === 'public');
    }

    #[Purpose('Сколько секунд ответ свежий; null — не хранить вовсе.')]
    #[Returns('int|null', 'секунды или null')]
    #[Effect(Effect::NONE)]
    #[Gives([], null)]
    public function ttl(): ?int
    {
        return $this->ttl;
    }

    #[Purpose('Вправе ли общий кэш отдать копию другому посетителю.')]
    #[Returns('bool', 'true только при явном "scope": "public"')]
    #[Effect(Effect::NONE)]
    #[Gives([], false)]
    public function isPublic(): bool
    {
        return $this->public;
    }
}
