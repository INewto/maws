<?php

namespace Maws\Man\Head\System;

use Maws\System\Contract\{Changes, Effect, Gives, Param, Purpose, Returns, Subject};

#[Subject(Memory::class, 'up', [])]
#[Purpose('Память головы: одно общее хранилище на весь процесс.')]
class Memory
{
    public static $instance = null;
    public $data = [];

    #[Purpose('Отдаёт общую память, создавая её при первом обращении.')]
    #[Returns('self', 'единственный экземпляр памяти')]
    #[Effect(Effect::NONE)]
    public static function up(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            return self::$instance;
        }

        return self::$instance;
    }

    #[Purpose('Запоминает значение по ключу.')]
    #[Param('key', 'string', 'ключ')]
    #[Param('value', 'string', 'значение')]
    #[Effect(Effect::NONE)]
    #[Changes(['probe', 'value'], [['data', [], ['probe' => 'value']]])]
    public function set($key, $value): void
    {
        $this->data[$key] = $value;
    }

    #[Purpose('Отдаёт запомненное значение по ключу.')]
    #[Param('key', 'string', 'ключ')]
    #[Returns('mixed', 'значение, запомненное ранее')]
    #[Effect(Effect::NONE)]
    #[Gives(['probe'], 'value', before: [['set', ['probe', 'value']]])]
    public function get($key)
    {
        return $this->data[$key];
    }

    #[Purpose('Спрашивает, есть ли запомненное значение по ключу, — без исключения в ответ на «нет».')]
    #[Param('key', 'string', 'ключ')]
    #[Returns('bool', 'true — значение запомнено')]
    #[Effect(Effect::NONE)]
    #[Gives(['probe'], true, before: [['set', ['probe', 'value']]])]
    #[Gives(['nope'], false)]
    public function has($key): bool
    {
        return array_key_exists($key, $this->data);
    }
}
