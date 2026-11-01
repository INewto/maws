<?php

namespace {{Namespace}};

use Maws\Ai\Ai;

require_once __DIR__ . '{{ConfigPath}}';
require_once __DIR__ . '{{VendorPath}}';

// Агент-надзиратель: своих инструментов у него нет — их место занимают подчинённые.
// Они объявлены в tools.json рядом, как обычные инструменты: имя, описание и схема
// входа с задачей. Модель выбирает по описанию, кого позвать и с какой задачей.
// Вызов подчинённого — метод с именем инструмента: задача уходит в его work(),
// ответ возвращается строкой.
class Supervisor extends Ai
{
    public function __construct()
    {
        $this->agentDir = __DIR__;
        $this->prompt   = $this->getPrompt(['toolsListXml' => $this->getToolsPromptXml()]);
    }

    // Модель отдаёт план: кого позвать и с какой задачей. В плане только те агенты,
    // которых она выбрала, и у каждого своя задача.
    public function work(string $task): string
    {
        $calls = $this->callLLM($task);

        $answers = array_map(
            fn (array $call): string => $this->callTool($call['name'], $call['arguments'])['content']['text'],
            $calls,
        );

        return implode("\n", $answers);
    }

    // Зашитая модель: подчинённых пока нет, план пуст. Здесь место живого вызова.
    public function callLLM($task = null)
    {
        return [];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $done = 0;

    // Задача одна: выполнили — ждём следующую. В бою её принесла бы очередь.
    while (1) {
        if ($done == 0) {
            echo (new Supervisor())->work($argv[1] ?? 'демо-задача') . "\n";

            $done = 1;
        }
    }
}
