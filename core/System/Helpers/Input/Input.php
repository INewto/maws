<?php

namespace Maws\System\Helpers\Input;

use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Helpers\Input\Exception\InputMisuse;

#[Subject(Input::class, 'from', [['product' => 'first', 'count' => '3', 'price' => '1.50', 'tags' => ['a', 'b'], 'blank' => '', 'zero' => '012', 'whole' => 5]])]
#[Purpose('Читает присланные пользователем данные и отдаёт их приведёнными к нужному виду. Это читатель, а не проверяющий: нет данных или не тот вид — false, отказ решает вызвавший.')]
final class Input
{
    #[Param('source', 'array', 'набор данных — обычно $_POST или $_GET')]
    private function __construct(private readonly array $source)
    {
    }

    #[Purpose('Собирает читателя над присланным набором данных.')]
    #[Param('source', 'array', 'набор данных — обычно $_POST или $_GET')]
    #[Returns('self', 'читатель над этим набором')]
    #[Effect(Effect::NONE)]
    public static function from(array $source): self
    {
        return new self($source);
    }

    #[Purpose('Отдаёт значение как строку; не-строка и пустая строка — false.')]
    #[Param('key', 'string', 'имя поля в наборе')]
    #[Returns('string|false', 'строка или false, если поля нет, оно не строка или пусто')]
    #[Effect(Effect::NONE)]
    #[Gives(['product'], 'first')]
    #[Gives(['count'], '3')]
    #[Gives(['blank'], false)]
    #[Gives(['tags'], false)]
    #[Gives(['missing'], false)]
    public function string(string $key): string|false
    {
        $value = $this->source[$key] ?? null;

        if (!is_string($value) || $value === '') {
            return false;
        }

        return $value;
    }

    #[Purpose('Отдаёт значение как целое; неразбираемое — false.')]
    #[Param('key', 'string', 'имя поля в наборе')]
    #[Returns('int|false', 'целое или false, если поля нет или оно не целое')]
    #[Effect(Effect::NONE)]
    #[Gives(['count'], 3)]
    #[Gives(['whole'], 5)]
    #[Gives(['zero'], false)]
    #[Gives(['price'], false)]
    #[Gives(['product'], false)]
    #[Gives(['missing'], false)]
    public function int(string $key): int|false
    {
        $value = $this->source[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return false;
        }

        return filter_var(trim($value), FILTER_VALIDATE_INT);
    }

    #[Purpose('Отдаёт значение как дробное; неразбираемое — false.')]
    #[Param('key', 'string', 'имя поля в наборе')]
    #[Returns('float|false', 'дробное или false, если поля нет или оно не число')]
    #[Effect(Effect::NONE)]
    #[Gives(['price'], 1.5)]
    #[Gives(['count'], 3.0)]
    #[Gives(['whole'], 5.0)]
    #[Gives(['product'], false)]
    #[Gives(['missing'], false)]
    public function float(string $key): float|false
    {
        $value = $this->source[$key] ?? null;

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return false;
        }

        return filter_var(trim($value), FILTER_VALIDATE_FLOAT);
    }

    #[Purpose('Отдаёт значение, если оно входит в перечень допустимых; иначе — false.')]
    #[Param('key', 'string', 'имя поля в наборе')]
    #[Param('allowed', 'array', 'перечень допустимых значений')]
    #[Returns('string|false', 'значение или false, если его нет или оно не из перечня')]
    #[Throws(InputMisuse::class, 'перечень допустимых значений пуст — сверять не с чем')]
    #[Effect(Effect::NONE)]
    #[Gives(['product', ['second', 'first']], 'first')]
    #[Gives(['product', ['second']], false)]
    #[Gives(['missing', ['second', 'first']], false)]
    #[Fails(['product', []], InputMisuse::class)]
    public function oneOf(string $key, array $allowed): string|false
    {
        if ($allowed === []) {
            throw new InputMisuse("перечень допустимых значений для поля «{$key}» пуст — сверять не с чем");
        }

        $value = $this->string($key);

        if ($value === false) {
            return false;
        }

        return in_array($value, $allowed, true) ? $value : false;
    }

    #[Purpose('Отдаёт значение как набор; не-набор — false.')]
    #[Param('key', 'string', 'имя поля в наборе')]
    #[Returns('array|false', 'набор или false, если поля нет или оно не набор')]
    #[Effect(Effect::NONE)]
    #[Gives(['tags'], ['a', 'b'])]
    #[Gives(['product'], false)]
    #[Gives(['missing'], false)]
    public function array(string $key): array|false
    {
        $value = $this->source[$key] ?? null;

        return is_array($value) ? $value : false;
    }

    #[Purpose('Говорит, есть ли поле в наборе, — независимо от того, годится ли его значение.')]
    #[Param('key', 'string', 'имя поля в наборе')]
    #[Returns('bool', 'true, если поле есть')]
    #[Effect(Effect::NONE)]
    #[Gives(['product'], true)]
    #[Gives(['blank'], true)]
    #[Gives(['missing'], false)]
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->source);
    }

    #[Purpose('Отдаёт весь набор как он пришёл.')]
    #[Returns('array', 'присланный набор')]
    #[Effect(Effect::NONE)]
    #[Gives([], ['product' => 'first', 'count' => '3', 'price' => '1.50', 'tags' => ['a', 'b'], 'blank' => '', 'zero' => '012', 'whole' => 5])]
    public function all(): array
    {
        return $this->source;
    }
}
