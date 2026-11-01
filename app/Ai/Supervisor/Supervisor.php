<?php

namespace App\Ai\Supervisor;

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

// Агент-надзиратель: своих инструментов у него нет — его инструменты другие агенты.
// Каждый объявлен в tools.json так же, как объявляется инструмент: имя, описание и
// схема входа. Поэтому модель выбирает по описанию, кого позвать и с какой задачей.
//
// Подчинённые — обычные агенты: свой tools.json, своя подсказка, свой каталог
// объявлений и свои права. Надзиратель держит имена классов, а не пути к файлам.
//
// Запускается сам по себе: php app/Ai/Supervisor/Supervisor.php

use App\Ai\Supervisor\Agent1\Agent1;
use App\Ai\Supervisor\Agent2\Agent2;
use Maws\Ai\Ai;

class Supervisor extends Ai
{
    public function __construct()
    {
        $this->agentDir = __DIR__;
        $this->prompt   = $this->getPrompt(['toolsListXml' => $this->getToolsPromptXml()]);
    }

    // ------------------------------------------------------------------
    // 1. Агенты как инструменты
    // ------------------------------------------------------------------
    //
    // Имя метода — имя инструмента из tools.json. Задача уходит в work() агента,
    // ответ возвращается строкой: надзиратель его не пересказывает и не украшает.

    public function sayHelloAgent(array $arguments): string
    {
        return (new Agent1())->work((string) $arguments['task']);
    }

    public function sayByeAgent(array $arguments): string
    {
        return (new Agent2())->work((string) $arguments['task']);
    }

    // ------------------------------------------------------------------
    // 2. Работа надзирателя
    // ------------------------------------------------------------------

    // Модель отдаёт план: кого позвать и с какой задачей.
    public function work(string $task): string
    {
        $calls = $this->callLLM($task);

        $answers = array_map(
            fn (array $call): string => $this->callTool($call['name'], $call['arguments'])['content']['text'],
            $calls,
        );

        return implode("\n", $answers);
    }

    // Зашитая модель: «привет и пока» — два действия, значит два вызова, каждому свой
    // агент и своя задача. Одно действие — один вызов.
    public function callLLM($task = null)
    {
        $task = (string) $task;

        if (str_contains($task, 'привет') && str_contains($task, 'пока')) {
            return [
                ['name' => 'sayHelloAgent', 'arguments' => ['task' => 'скажи привет']],
                ['name' => 'sayByeAgent', 'arguments' => ['task' => 'скажи пока']],
            ];
        }

        if (str_contains($task, 'пока')) {
            return [['name' => 'sayByeAgent', 'arguments' => ['task' => $task]]];
        }

        return [['name' => 'sayHelloAgent', 'arguments' => ['task' => $task]]];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $done = 0;

    // ДЕМО: задача одна, а процесс должен жить — поэтому цикл холостой намеренно.
    // В рабочем коде на его месте блокирующее ожидание задачи у брокера (Redis, RabbitMQ —
    // что выберет разработчик): процесс спит, пока задачи нет, и просыпается на новую.
    while (1) {
        if ($done == 0) {
            echo (new Supervisor())->work($argv[1] ?? 'скажи привет и скажи пока') . "\n";

            $done = 1;
        }
    }
}
