<?php

namespace App\Ai\Supervisor\Agent1;

// Agent1 под началом Supervisor: умеет здороваться и отдаёт ответ строкой. Надзиратель
// зовёт его классом, передаёт задачу и печатает ответ под своим именем — поэтому агент
// ничего не нумерует и не украшает.
//
// Запускается и сам по себе: php app/Ai/Supervisor/Agent1/Agent1.php

use Maws\Ai\Ai;

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

class Agent1 extends Ai
{
    public function __construct()
    {
        $this->agentDir = __DIR__;
        $this->prompt   = $this->getPrompt(['toolsListXml' => $this->getToolsPromptXml()]);
    }

    // Инструмент из tools.json: имя метода — имя инструмента, ответ — строка.
    public function sayHello(array $arguments): string
    {
        return implode(' ', array_fill(0, (int) $arguments['amount'], 'hello'));
    }

    // Задача пришла, модель выбрала инструмент, ответ ушёл тому, кто позвал.
    public function work(string $task): string
    {
        $call   = $this->callLLM($task);
        $result = $this->callTool($call['name'], $call['arguments']);

        return $result['content']['text'];
    }

    // Зашитая модель: один вызов — здоровается.
    public function callLLM($task = null)
    {
        return ['name' => 'sayHello', 'arguments' => ['amount' => 1]];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $done = 0;

    // ДЕМО: задача одна, а процесс должен жить — поэтому цикл холостой намеренно.
    // В рабочем коде на его месте блокирующее ожидание задачи у брокера (Redis, RabbitMQ —
    // что выберет разработчик): процесс спит, пока задачи нет, и просыпается на новую.
    while (1) {
        if ($done == 0) {
            echo (new Agent1())->work($argv[1] ?? 'поздоровайся') . "\n";

            $done = 1;
        }
    }
}
