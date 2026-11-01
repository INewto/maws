<?php

namespace App\Ai\Supervisor\Agent2;

// Agent2 под началом Supervisor: умеет прощаться. От Agent1 отличается контрактом —
// инструмент один и другой, — а каркас тот же: надзиратель решает, кого и в каком
// порядке звать, а чем агент умеет, сказано в его tools.json.
//
// Запускается и сам по себе: php app/Ai/Supervisor/Agent2/Agent2.php

use Maws\Ai\Ai;

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

class Agent2 extends Ai
{
    public function __construct()
    {
        $this->agentDir = __DIR__;
        $this->prompt   = $this->getPrompt(['toolsListXml' => $this->getToolsPromptXml()]);
    }

    // Инструмент из tools.json: имя метода — имя инструмента, ответ — строка.
    public function sayBye(array $arguments): string
    {
        return implode(' ', array_fill(0, (int) $arguments['amount'], 'bye'));
    }

    // Задача пришла, модель выбрала инструмент, ответ ушёл тому, кто позвал.
    public function work(string $task): string
    {
        $call   = $this->callLLM($task);
        $result = $this->callTool($call['name'], $call['arguments']);

        return $result['content']['text'];
    }

    // Зашитая модель: один вызов — прощается.
    public function callLLM($task = null)
    {
        return ['name' => 'sayBye', 'arguments' => ['amount' => 1]];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $done = 0;

    // ДЕМО: задача одна, а процесс должен жить — поэтому цикл холостой намеренно.
    // В рабочем коде на его месте блокирующее ожидание задачи у брокера (Redis, RabbitMQ —
    // что выберет разработчик): процесс спит, пока задачи нет, и просыпается на новую.
    while (1) {
        if ($done == 0) {
            echo (new Agent2())->work($argv[1] ?? 'попрощайся') . "\n";

            $done = 1;
        }
    }
}
