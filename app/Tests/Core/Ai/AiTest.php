<?php

// Тест для core/Ai/Ai.php.
// Создан командой: maws make test:core/Ai/Ai.php
// Перезаписывается при каждом запуске команды — правьте контракт в источнике.

$root = __DIR__;

for ($i = 0; $i < 20 && !is_file($root . '/maws.php'); $i++) {
    $root = dirname($root);
}

if (!is_file($root . '/maws.php')) {
    fwrite(STDERR, 'Не найден корень проекта выше ' . __DIR__ . "\n");
    exit(1);
}

require_once $root . '/app/config.php';
require_once $root . '/vendor/autoload.php';

$run = new Maws\System\Test\Runner('core/Ai/Ai.php');

$run->subject('Maws\\Ai\\Ai', 'Scaffold::ai');

$run->fixture(['tools.json' => '[]']);
$run->fails('rights', [], 'Maws\\Ai\\System\\Exception\\AccessNotDeclared');
$run->fixture(['dbaccess.json' => '{"tables":["public.test_table"]}']);
$run->fails('listTools', [], 'Maws\\Ai\\System\\Exception\\ContractUnreadable');
$run->fixture([
    'tools.json' => '[{"name":"sayHello","description":"Здоровается","inputSchema":{"type":"object","properties":{"amount":{"type":"integer"}},"required":["amount"]}},{"name":"sayBye","description":"Прощается","inputSchema":{"type":"object","properties":{},"required":[]}}]',
    'dbaccess.json' => '{"tables":["public.test_table"]}',
    'instruction.prompt.php' => '<?php $prompt = ($name ?? "нет") . "!";',
]);
$run->gives('callTool', ['sayHello', ['amount' => 2]], ['content' => ['type' => 'text', 'text' => 'hello 2'], 'isError' => false]);
$run->gives('callTool', ['nope', []], ['content' => ['type' => 'text', 'text' => 'Unknown tool: nope'], 'isError' => true]);
$run->gives('callTool', ['sayHello', []], [
    'content' => ['type' => 'text', 'text' => 'Missing required argument: amount'],
    'isError' => true,
]);
$run->gives('callTool', ['sayHello', ['amount' => 'два']], [
    'content' => ['type' => 'text', 'text' => 'Argument amount must be of type integer'],
    'isError' => true,
]);
$run->fails('callTool', ['sayBye', []], 'Maws\\Ai\\System\\Exception\\ToolNotImplemented');
$run->gives('getToolsPromptXml', [], '<available_tools>
  <tool>
    <name>sayHello</name>
    <description>Здоровается</description>
    <input_schema>
      {&quot;type&quot;:&quot;object&quot;,&quot;properties&quot;:{&quot;amount&quot;:{&quot;type&quot;:&quot;integer&quot;}},&quot;required&quot;:[&quot;amount&quot;]}
    </input_schema>
  </tool>
  <tool>
    <name>sayBye</name>
    <description>Прощается</description>
    <input_schema>
      {&quot;type&quot;:&quot;object&quot;,&quot;properties&quot;:{},&quot;required&quot;:[]}
    </input_schema>
  </tool>
</available_tools>
<tool_calling_examples>
  <tool_call>
    {&quot;name&quot;:&quot;sayHello&quot;,&quot;arguments&quot;:{&quot;amount&quot;:1}}
  </tool_call>
  <tool_call>
    {&quot;name&quot;:&quot;sayBye&quot;,&quot;arguments&quot;:[]}
  </tool_call>
</tool_calling_examples>
');
$run->gives('getPrompt', [['name' => 'Аня']], 'Аня!');

$run->finish();
