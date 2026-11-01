<?php

namespace App\Ai\Multi\Agent2;

// Agent2 — соседний агент: свой контракт, своя подсказка, свой каталог объявлений.
// Agent1 зовёт его классом и передаёт задачу в work() — как инструмент места получает
// своё имя и права: аргументом, а не через консоль.
//
// Запускается и сам по себе: php app/Ai/Multi/Agent2/Agent2.php "любая задача".

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
    public function perform(array $arguments): string
    {
        return 'Agent2 выполнил задачу: ' . $arguments['task'];
    }

    // Задача пришла, модель выбрала инструмент, ответ ушёл тому, кто позвал.
    public function work(string $task): string
    {
        $call   = $this->callLLM($task);
        $result = $this->callTool($call['name'], $call['arguments']);

        return $result['content']['text'];
    }

    // Зашитая модель: один вызов — выполняет то, что передали.
    public function callLLM($task = null)
    {
        return ['name' => 'perform', 'arguments' => ['task' => (string) $task]];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $done = 0;

    // ДЕМО: задача одна, а процесс должен жить — поэтому цикл холостой намеренно.
    // В рабочем коде на его месте блокирующее ожидание задачи у брокера (Redis, RabbitMQ —
    // что выберет разработчик): процесс спит, пока задачи нет, и просыпается на новую.
    while (1) {
        if ($done == 0) {
            echo (new Agent2())->work($argv[1] ?? 'демо-задача') . "\n";

            $done = 1;
        }
    }
}
