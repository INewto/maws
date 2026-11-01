<?php

namespace Maws\System\Helpers\Token;

use Exception;
use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Hash\Hash;
use Maws\System\Helpers\Token\Exception\TokenFailure;
use Maws\System\Helpers\Token\Exception\TokenMisuse;

#[Subject(Token::class, 'new')]
#[Purpose('Непредсказуемая строка, которую предъявляют вместо пароля: метка формы против подделки запроса, идентификатор сессии, ссылка сброса пароля, ключ доступа. Случайность только криптографическая — угадываемый токен перестаёт быть токеном, поэтому rand, mt_rand, uniqid и время по назначению не годятся. Три вида записи: для адреса, для хранения, для человека.')]
final class Token
{
    private const MIN_BYTES  = 16;
    private const MIN_DIGITS = 6;

    private function __construct()
    {
    }

    #[Purpose('Токен для адреса: годится в ссылку и в имя параметра без кодирования — ни плюсов, ни слэшей, ни знаков выравнивания.')]
    #[Param('bytes', 'int', 'сколько байт случайности; меньше 16 — ошибка разработчика')]
    #[Returns('string', 'знаки A-Z a-z 0-9 - _ ; на каждые 16 байт — 22 знака')]
    #[Throws(TokenMisuse::class, 'случайности меньше 16 байт: такой токен подбирается')]
    #[Throws(TokenFailure::class, 'источник случайности недоступен')]
    #[Effect(Effect::NONE)]
    #[Fails([15], TokenMisuse::class)]
    #[Fails([0], TokenMisuse::class)]
    #[Fails([-1], TokenMisuse::class)]
    public static function url(int $bytes): string
    {
        self::demand($bytes, self::MIN_BYTES, 'байт случайности');

        return rtrim(strtr(base64_encode(self::bytes($bytes)), '+/', '-_'), '=');
    }

    #[Purpose('Токен для хранения и сравнения: только 0-9 и a-f, ровно два знака на байт — длина сразу говорит, сколько в нём случайности.')]
    #[Param('bytes', 'int', 'сколько байт случайности; меньше 16 — ошибка разработчика')]
    #[Returns('string', 'шестнадцатеричная запись; на каждые 16 байт — 32 знака')]
    #[Throws(TokenMisuse::class, 'случайности меньше 16 байт: такой токен подбирается')]
    #[Throws(TokenFailure::class, 'источник случайности недоступен')]
    #[Effect(Effect::NONE)]
    #[Fails([15], TokenMisuse::class)]
    #[Fails([0], TokenMisuse::class)]
    public static function hex(int $bytes): string
    {
        self::demand($bytes, self::MIN_BYTES, 'байт случайности');

        return bin2hex(self::bytes($bytes));
    }

    #[Purpose('Код для человека: только цифры, чтобы его можно было продиктовать голосом и перепечатать с бумаги. Первая цифра не ноль — иначе код, сохранённый числом, теряет знак и принимает более короткий ввод.')]
    #[Param('digits', 'int', 'сколько цифр; меньше 6 — ошибка разработчика')]
    #[Returns('string', 'код ровно из стольких цифр, первая — от 1 до 9')]
    #[Throws(TokenMisuse::class, 'цифр меньше шести: такой код подбирается')]
    #[Throws(TokenFailure::class, 'источник случайности недоступен')]
    #[Effect(Effect::NONE)]
    #[Fails([5], TokenMisuse::class)]
    #[Fails([0], TokenMisuse::class)]
    #[Fails([-1], TokenMisuse::class)]
    public static function digits(int $digits): string
    {
        self::demand($digits, self::MIN_DIGITS, 'цифр в коде');

        $code = (string) self::draw(1, 9);

        for ($i = 1; $i < $digits; $i++) {
            $code .= (string) self::draw(0, 9);
        }

        return $code;
    }

    #[Purpose('Сверяет предъявленный токен с сохранённым за постоянное время, чтобы по времени ответа нельзя было подбирать знак за знаком. Пустая строка не совпадает ни с чем, включая пустую: незаполненная метка не должна проходить проверку.')]
    #[Param('expected', 'string', 'сохранённый токен')]
    #[Param('given', 'string', 'предъявленный токен')]
    #[Returns('bool', 'true, только если обе строки непусты и совпадают целиком')]
    #[Effect(Effect::NONE)]
    #[Gives(['abc', 'abc'], true)]
    #[Gives(['abc', 'abd'], false)]
    #[Gives(['abc', 'ab'], false)]
    #[Gives(['', ''], false)]
    #[Gives(['abc', ''], false)]
    public static function same(string $expected, string $given): bool
    {
        return Hash::same($expected, $given);
    }

    private static function bytes(int $bytes): string
    {
        try {
            return random_bytes($bytes);
        } catch (Exception $e) {
            // Наружу исключение чужой библиотеки не пускаем: оно не нашего рода.
            throw new TokenFailure('нет источника случайности: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function draw(int $lowest, int $highest): int
    {
        try {
            return random_int($lowest, $highest);
        } catch (Exception $e) {
            throw new TokenFailure('нет источника случайности: ' . $e->getMessage(), 0, $e);
        }
    }

    private static function demand(int $value, int $lowest, string $what): void
    {
        if ($value < $lowest) {
            throw new TokenMisuse("{$what} не может быть меньше {$lowest}: {$value} — короче подбирается");
        }
    }
}
