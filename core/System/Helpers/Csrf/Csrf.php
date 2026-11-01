<?php

namespace Maws\System\Helpers\Csrf;

use Maws\System\Contract\{Effect, Gives, Param, Purpose, Returns, Subject};
use Maws\System\Session\Session;

#[Subject(Csrf::class, 'new')]
#[Purpose('Токен форм: держит его в сессии и сверяет с присланным. Имя ключа задаёт ядро (Session::TOKEN): гасит токен вход, а не сам токен. Больше ядро о токенах ничего не знает — ни как он устроен, ни как сверяется.')]
final class Csrf
{
    // Ключ, под которым токен лежит в сессии. Имя — из ядра: иначе токен, известный до входа,
    // переехал бы в новую сессию вместе с остальными данными.
    private const KEY = Session::TOKEN;

    private const TOKEN_BYTES = 32;

    // Имя поля в форме. Одно на весь фреймворк: шаблон печатает его через field(), проверка
    // берёт через verify(), и разойтись в написании им нечем. Подчёркивание впереди — чтобы
    // не спутать с чужим полем `token`: платёжный, пригласительный, API-токен — не этот.
    private const FIELD = '_csrf';

    private function __construct()
    {
    }

    #[Purpose('Имя поля формы, в котором едет токен. Одно на весь фреймворк: шаблон и проверка не могут разойтись в написании.')]
    #[Returns('string', 'имя поля формы')]
    #[Effect(Effect::NONE)]
    public static function field(): string
    {
        return self::FIELD;
    }

    #[Purpose('Отдаёт токен форм, заводя его при первом обращении и сохраняя на всю сессию: две открытые вкладки не мешают друг другу.')]
    public static function token(): string
    {
        $token = Session::instance()->get(self::KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        Session::instance()->set(self::KEY, $token);

        return $token;
    }

    #[Purpose('Забывает прежний токен и заводит новый. Нужен там, где меняются права: иначе токен, украденный до входа, остаётся годным после него.')]
    public static function rotate(): void
    {
        Session::instance()->forget(self::KEY);
        self::token();
    }

    #[Purpose('Отвечает, годится ли токен из формы. Пустое с пустым не совпадает: иначе форма без токена и сессия без токена «сходились» бы, то есть защита пропускала бы ровно тот случай, ради которого стоит.')]
    #[Param('given', 'string|null', 'токен из отправленной формы')]
    #[Param('stored', 'string|null', 'токен, запомненный в сессии')]
    #[Returns('bool', 'true — оба непусты и совпали')]
    #[Effect(Effect::NONE)]
    #[Gives(['abc', 'abc'], true)]
    #[Gives(['', ''], false)]
    #[Gives([null, null], false)]
    #[Gives(['abc', null], false)]
    #[Gives(['abc', 'abd'], false)]
    public static function check(?string $given, ?string $stored): bool
    {
        if ($given === null || $stored === null || $given === '' || $stored === '') {
            return false;
        }

        return hash_equals($stored, $given);
    }

    #[Purpose('Проверяет отправленные поля целиком: сам берёт токен из формы и сравнивает с тем, что лежит в сессии. Один вызов вместо трёх строк, в которых забывают половину. Токен не заводит: проверка ничего не меняет, и сессия без токена даёт отказ, а не новый токен.')]
    #[Param('source', 'array', 'присланные поля формы, обычно $_POST')]
    #[Returns('bool', 'true — токен в форме есть и совпал с сессионным')]
    #[Effect(Effect::NONE)]
    public static function verify(array $source): bool
    {
        $given  = $source[self::FIELD] ?? null;
        $stored = Session::instance()->get(self::KEY);

        return self::check(is_string($given) ? $given : null, is_string($stored) ? $stored : null);
    }
}
