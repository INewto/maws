<?php

namespace Maws\System\Helpers\Hash;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Hash\Exception\HashMisuse;
use ValueError;

#[Subject(Hash::class, 'new')]
#[Purpose('Три разные вещи, которые нельзя путать: хеш пароля медленный, с солью, при каждом вызове другой и проверяется только сверкой; контрольная сумма быстрая и повторяемая — годится лишь для «данные не изменились»; подпись с ключом доказывает, что данные пришли от того, у кого ключ. Отсюда три двери, и общего «просто хешируй» нет.')]
final class Hash
{
    private const ALGO = 'sha256';

    private function __construct()
    {
    }

    #[Purpose('Хеширует пароль для хранения: медленно и с солью, поэтому одинаковые пароли дают разные строки. Проверять только через verify — сравнивать строки бессмысленно. Bcrypt смотрит на первые 72 байта: это его предел, а не наш; нулевой байт он не принимает вовсе.')]
    #[Param('password', 'string', 'пароль как его ввели')]
    #[Returns('string', 'строка для хранения в базе')]
    #[Throws(HashMisuse::class, 'в пароле нулевой байт — такой пароль хешировать нечем: отсекай его при проверке ввода')]
    #[Effect(Effect::NONE)]
    public static function password(string $password): string
    {
        try {
            return password_hash($password, PASSWORD_DEFAULT);
        } catch (ValueError $e) {
            throw new HashMisuse('пароль не годится для хранения: ' . $e->getMessage());
        }
    }

    #[Purpose('Отвечает, тот ли это пароль, за постоянное время. Испорченное или пустое значение в базе — это «не тот», а не сбой: вход должен отказать, а не упасть. Пароль с нулевым байтом не совпадает ни с чем: такой нечем было сохранить, а bcrypt молча обрезал бы его по нулю и принял чужой хвост.')]
    #[Param('password', 'string', 'пароль как его ввели')]
    #[Param('hash', 'string', 'сохранённая строка')]
    #[Returns('bool', 'true, только если пароль подходит к сохранённой строке')]
    #[Effect(Effect::NONE)]
    #[Gives(['секрет', '$2y$10$VnNbMM4lfyYMt8xcfYB2UOjgeMdCMwDJJK0KbEbgfkjGFF8nUPRcO'], true)]
    #[Gives(['Секрет', '$2y$10$VnNbMM4lfyYMt8xcfYB2UOjgeMdCMwDJJK0KbEbgfkjGFF8nUPRcO'], false)]
    #[Gives(['секрет', ''], false)]
    #[Gives(['секрет', 'не хеш'], false)]
    public static function verify(string $password, string $hash): bool
    {
        if (str_contains($password, "\0")) {
            return false;
        }

        return password_verify($password, $hash);
    }

    #[Purpose('Отвечает, устарела ли сохранённая строка: сменился алгоритм или его стоимость — значит при следующем входе пароль надо перехешировать, иначе защита останется на уровне, выбранном годы назад.')]
    #[Param('hash', 'string', 'сохранённая строка')]
    #[Returns('bool', 'true, если строку пора пересчитать текущим способом')]
    #[Effect(Effect::NONE)]
    #[Gives(['$2y$10$VnNbMM4lfyYMt8xcfYB2UOjgeMdCMwDJJK0KbEbgfkjGFF8nUPRcO'], false)]
    #[Gives(['не хеш'], true)]
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    #[Purpose('Считает контрольную сумму: быстро и повторяемо, чтобы сравнить «до» и «после». Для паролей не годится — там password; и секретов она не скрывает, а только опознаёт содержимое.')]
    #[Param('value', 'string', 'что опознаём')]
    #[Param('algo', 'string', 'имя алгоритма из hash_algos()')]
    #[Returns('string', 'шестнадцатеричная запись суммы')]
    #[Throws(HashMisuse::class, 'алгоритм не существует')]
    #[Effect(Effect::NONE)]
    #[Gives(['abc'], 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad')]
    #[Gives([''], 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855')]
    #[Gives(['привет', 'md5'], '608333adc72f545078ede3aad71bfe74')]
    #[Fails(['abc', 'nope'], HashMisuse::class)]
    public static function digest(string $value, string $algo = self::ALGO): string
    {
        return hash(self::checked($algo), $value);
    }

    #[Purpose('Подписывает данные ключом: подпись может повторить только тот, у кого тот же ключ, поэтому она отличает «пришло от своего» от «пришло от кого угодно». Пустой ключ не ключ — это ошибка, а не настройка.')]
    #[Param('value', 'string', 'что подписываем')]
    #[Param('key', 'string', 'общий секрет; пустой — ошибка разработчика')]
    #[Param('algo', 'string', 'имя алгоритма из hash_algos()')]
    #[Returns('string', 'шестнадцатеричная запись подписи')]
    #[Throws(HashMisuse::class, 'ключ пуст или алгоритм не существует')]
    #[Effect(Effect::NONE)]
    #[Gives(['данные', 'ключ'], '735e97ea09bca778813114ffb283c41115d47dabf70d31e857b29f089cd7a3b2')]
    #[Gives(['данные', 'ключ', 'md5'], '6585d1de097be89b632ecb5ac4274560')]
    #[Fails(['данные', ''], HashMisuse::class)]
    #[Fails(['данные', 'ключ', 'nope'], HashMisuse::class)]
    public static function sign(string $value, string $key, string $algo = self::ALGO): string
    {
        if ($key === '') {
            throw new HashMisuse('подпись без ключа ничего не доказывает: ключ не может быть пустым');
        }

        return hash_hmac(self::checked($algo), $value, $key);
    }

    #[Purpose('Сверяет сумму или подпись за постоянное время, чтобы по времени ответа нельзя было подбирать знак за знаком. Пустая строка не совпадает ни с чем, включая пустую: непосчитанное не должно сходить за совпавшее.')]
    #[Param('expected', 'string', 'сохранённая сумма или подпись')]
    #[Param('given', 'string', 'предъявленная сумма или подпись')]
    #[Returns('bool', 'true, только если обе строки непусты и совпадают целиком')]
    #[Effect(Effect::NONE)]
    #[Gives(['abc', 'abc'], true)]
    #[Gives(['abc', 'abd'], false)]
    #[Gives(['', ''], false)]
    public static function same(string $expected, string $given): bool
    {
        if ($expected === '' || $given === '') {
            return false;
        }

        return hash_equals($expected, $given);
    }

    private static function checked(string $algo): string
    {
        if (!in_array($algo, hash_algos(), true)) {
            throw new HashMisuse("алгоритма \"{$algo}\" нет: возьми имя из hash_algos()");
        }

        return $algo;
    }
}
