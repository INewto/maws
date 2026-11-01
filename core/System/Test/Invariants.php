<?php

namespace Maws\System\Test;

use Closure;
use Error;
use Maws\Man\Body\Body;
use Maws\Man\Hands\Hands;
use Maws\Man\Head\Head;
use Maws\Man\Legs\Legs;
use Maws\Man\Man;
use Maws\System\Contract\{Effect, Gives, Param, Purpose, Returns, Subject};
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

#[Subject(Invariants::class, 'final', [Man::class])]
#[Purpose('Проверяет обещания сборки человека: классы окончательны, органы типизированы и readonly, пустой объект не отдаёт органы, клон и снятие органа запрещены. Обещания держатся объявлением, а не вызовом, поэтому проверка идёт рефлексией.')]
final class Invariants
{
    // Что именно считается органом и к какому классу он приписан. Таблица, а не «все свойства»:
    // у головы есть состояние (currentPlace и прочее), которое меняется по ходу дела и органом
    // не является.
    private const ORGANS = [
        Man::class   => ['body', 'head', 'hands', 'legs', 'world'],
        Body::class  => ['man', 'head', 'hands', 'legs'],
        Head::class  => ['body', 'world'],
        Hands::class => ['body', 'world', 'screen'],
        Legs::class  => ['body', 'world'],
    ];

    #[Purpose('Отвечает, объявлен ли класс окончательным: наследник не подменит поведение и не принесёт своё состояние в собранного человека.')]
    #[Param('class', 'string', 'проверяемый класс')]
    #[Returns('bool', 'true, если класс объявлен final')]
    #[Effect(Effect::NONE)]
    #[Gives([Man::class], true)]
    #[Gives([Body::class], true)]
    #[Gives([Head::class], true)]
    #[Gives([Hands::class], true)]
    #[Gives([Legs::class], true)]
    public static function final(string $class): bool
    {
        return (new ReflectionClass($class))->isFinal();
    }

    #[Purpose('Отвечает, объявлены ли органы так, как обещает документ: у каждого есть тип и readonly. Тип не даёт пустому объекту отдать null вместо органа, readonly не даёт подменить орган после сборки.')]
    #[Param('class', 'string', 'проверяемый класс')]
    #[Returns('bool', 'true, если все органы класса типизированы и readonly')]
    #[Effect(Effect::NONE)]
    #[Gives([Man::class], true)]
    #[Gives([Body::class], true)]
    #[Gives([Head::class], true)]
    #[Gives([Hands::class], true)]
    #[Gives([Legs::class], true)]
    public static function organs(string $class): bool
    {
        foreach (self::ORGANS[$class] as $name) {
            if (!property_exists($class, $name)) {
                return false;
            }

            $property = new ReflectionProperty($class, $name);

            if (!$property->hasType() || !$property->isReadOnly()) {
                return false;
            }
        }

        return true;
    }

    #[Purpose('Проверяет обещание «человека без тела не бывает» на деле: объект, собранный в обход конструктора, не отдаёт органы — чтение падает, а не возвращает null.')]
    #[Param('class', 'string', 'проверяемый класс')]
    #[Returns('bool', 'true, если чтение каждого органа у пустого объекта даёт Error')]
    #[Effect(Effect::NONE)]
    #[Gives([Man::class], true)]
    #[Gives([Body::class], true)]
    #[Gives([Head::class], true)]
    #[Gives([Hands::class], true)]
    #[Gives([Legs::class], true)]
    public static function bodyless(string $class): bool
    {
        $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        foreach (self::ORGANS[$class] as $name) {
            try {
                (new ReflectionProperty($class, $name))->getValue($object);
            } catch (Error $e) {
                continue;
            }

            // Хоть один орган отдался — тело без человека или человек без тела возможны.
            return false;
        }

        return true;
    }

    #[Purpose('Проверяет, что собранного человека нельзя скопировать: копия жила бы со ссылками на чужие органы, а «человек» получился бы из одного тела дважды.')]
    #[Param('class', 'string', 'проверяемый класс')]
    #[Returns('bool', 'true, если клонирование даёт Error')]
    #[Effect(Effect::NONE)]
    #[Gives([Man::class], true)]
    #[Gives([Body::class], true)]
    #[Gives([Head::class], true)]
    #[Gives([Hands::class], true)]
    #[Gives([Legs::class], true)]
    public static function cloned(string $class): bool
    {
        $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        try {
            clone $object;
        } catch (Error $e) {
            return true;
        }

        return false;
    }

    #[Purpose('Проверяет, что орган нельзя снять с готового объекта. Свойство сначала заполняется изнутри области класса: пустое readonly-свойство unset разрешает и молча ничего не делает, поэтому запрет виден только на заполненном.')]
    #[Param('class', 'string', 'проверяемый класс')]
    #[Returns('bool', 'true, если unset каждого заполненного органа из области класса даёт Error')]
    #[Effect(Effect::NONE)]
    #[Gives([Man::class], true)]
    #[Gives([Body::class], true)]
    #[Gives([Head::class], true)]
    #[Gives([Hands::class], true)]
    #[Gives([Legs::class], true)]
    public static function stripped(string $class): bool
    {
        $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        $fill = Closure::bind(static function (object $object, string $name, object $organ): void {
            $object->$name = $organ;
        }, null, $class);

        $strip = Closure::bind(static function (object $object, string $name): void {
            unset($object->$name);
        }, null, $class);

        if ($fill === null || $strip === null) {
            return false;
        }

        foreach (self::ORGANS[$class] as $name) {
            $type = (new ReflectionProperty($class, $name))->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                return false;
            }

            // Заполняем пустышкой того же типа: проверяется свойство, а не годность органа.
            $organ = (new ReflectionClass($type->getName()))->newInstanceWithoutConstructor();

            try {
                $fill($object, $name, $organ);
            } catch (Error $e) {
                return false;
            }

            try {
                $strip($object, $name);
            } catch (Error $e) {
                continue;
            }

            // Снялось — орган можно потерять с живого человека.
            return false;
        }

        return true;
    }
}
