<?php

namespace Maws\System\Helpers\Html;

use Maws\System\Contract\{Effect, Gives, Param, Purpose, Returns, Subject};

#[Subject(Html::class, 'new')]
#[Purpose('Одно место, где значение становится безопасной разметкой: экранирование на вывод, обратное раскодирование и снятие тегов. Флаги и кодировка заданы здесь, поэтому по вызовам они разойтись не могут. Флаги выбраны осознанно: ENT_QUOTES экранирует обе кавычки, ENT_HTML5 берёт таблицу HTML5, а ENT_SUBSTITUTE не даёт негодной кодировке превратиться в пустую строку — без него htmlspecialchars молча отдаёт "".')]
final class Html
{
    private const FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5;

    private const ENCODING = 'UTF-8';

    private function __construct()
    {
    }

    #[Purpose('Экранирует значение для вывода — и в текст между тегами, и в значение атрибута.')]
    #[Param('value', 'string', 'значение как оно есть')]
    #[Returns('string', 'значение, безопасное для разметки')]
    #[Effect(Effect::NONE)]
    #[Gives(['<b>Привет</b>'], '&lt;b&gt;Привет&lt;/b&gt;')]
    #[Gives(['a & b'], 'a &amp; b')]
    #[Gives(['a "b" \'c\''], 'a &quot;b&quot; &apos;c&apos;')]
    #[Gives([''], '')]
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, self::FLAGS, self::ENCODING);
    }

    #[Purpose('Возвращает экранированное значение обратно — для сравнения и повторной записи.')]
    #[Param('value', 'string', 'экранированное значение')]
    #[Returns('string', 'значение в том виде, в каком оно было до экранирования')]
    #[Effect(Effect::NONE)]
    #[Gives(['&lt;b&gt;Привет&lt;/b&gt;'], '<b>Привет</b>')]
    #[Gives(['&quot;a&quot; &apos;b&apos; &amp;'], '"a" \'b\' &')]
    #[Gives(['просто текст'], 'просто текст')]
    public static function unescape(string $value): string
    {
        return html_entity_decode($value, self::FLAGS, self::ENCODING);
    }

    #[Purpose('Снимает теги, оставляя только их содержимое.')]
    #[Param('value', 'string', 'разметка как она есть')]
    #[Param('allowed', 'string', 'теги, которые надо оставить, записью strip_tags: <b><i>; пусто — снять все')]
    #[Returns('string', 'содержимое без снятых тегов')]
    #[Effect(Effect::NONE)]
    #[Gives(['<b>a</b><i>b</i>'], 'ab')]
    #[Gives(['<b>a</b><i>b</i>', '<b>'], '<b>a</b>b')]
    #[Gives(['a < b'], 'a < b')]
    #[Gives([''], '')]
    public static function strip(string $value, string $allowed = ''): string
    {
        return strip_tags($value, $allowed);
    }
}
