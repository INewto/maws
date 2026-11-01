<?php

namespace Maws\Ai;

use JsonException;
use Maws\Ai\System\AiAccess;
use Maws\Ai\System\Exception\AccessBroken;
use Maws\Ai\System\Exception\AccessNotDeclared;
use Maws\Ai\System\Exception\AccessUnreadable;
use Maws\Ai\System\Exception\AgentDirMissing;
use Maws\Ai\System\Exception\ContractBroken;
use Maws\Ai\System\Exception\ContractUnreadable;
use Maws\Ai\System\Exception\SchemaBroken;
use Maws\Ai\System\Exception\ToolNotImplemented;
use Maws\System\Contract\{Effect, Fails, Fixture, Gives, Param, Purpose, Returns, Subject, Throws};
use stdClass;

#[Subject(Ai::class, 'Scaffold::ai')]
#[Purpose('Основа агента: объявление инструментов, права на таблицы и вызов инструмента по имени.')]
class Ai
{
    // Каталог объявлений агента: tools.json, instruction.prompt.php и dbaccess.json.
    // Закрыт снаружи намеренно — иначе права перестают быть дверью: любой, кто держит
    // агента, выставил бы ему чужой каталог и читал чужие права через rights(). Ставит
    // его сам агент в своём конструкторе, то есть своё объявление; наследник сузить
    // видимость не может, а расширить до public ему не даст сам PHP.
    protected $agentDir;

    // Подсказка агента: собирается в конструкторе из instruction.prompt.php и описания
    // инструментов. Закрыта по той же причине, что и каталог: снаружи её не подменяют, а
    // объявлена здесь, чтобы агент не заводил её сам как динамическое свойство.
    protected $prompt;

    #[Purpose('Права агента на таблицы — единственная дверь к базе: без dbaccess.json прав нет.')]
    #[Returns('AiAccess', 'права с разобранным списком "схема.таблица"')]
    #[Throws(AgentDirMissing::class, 'у агента не задан каталог объявлений')]
    #[Throws(AccessNotDeclared::class, 'dbaccess.json нет или он не читается')]
    #[Throws(AccessUnreadable::class, 'файл есть, но прочитать не удалось')]
    #[Throws(AccessBroken::class, 'битый JSON, нет списка "tables" или таблица записана недопустимо')]
    #[Effect(Effect::FS_READ)]
    #[Fixture(files: ['tools.json' => '[]'])]
    #[Fails([], AccessNotDeclared::class)]
    final public function rights(): AiAccess
    {
        return AiAccess::fromDir($this->agentDir());
    }




    #[Purpose('Читает объявление инструментов агента из tools.json.')]
    #[Returns('array', 'список инструментов под ключом "tools"')]
    #[Throws(AgentDirMissing::class, 'у агента не задан каталог объявлений')]
    #[Throws(ContractUnreadable::class, 'tools.json нет или он не читается')]
    #[Throws(ContractBroken::class, 'битый JSON, объявление не список или у инструмента нет непустого "name"')]
    #[Effect(Effect::FS_READ)]
    #[Fixture(files: ['dbaccess.json' => '{"tables":["public.test_table"]}'])]
    #[Fails([], ContractUnreadable::class)]
    public function listTools(): array
    {
        $file = $this->contractFile();

        if (!is_readable($file)) {
            throw new ContractUnreadable($file);
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new ContractUnreadable($file);
        }

        try {
            $tools = \json5_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ContractBroken($file, $e->getMessage());
        }

        if (!is_array($tools)) {
            throw new ContractBroken($file, 'объявление должно быть списком инструментов');
        }

        foreach ($tools as $tool) {
            if (!is_object($tool) || !isset($tool->name) || !is_string($tool->name) || $tool->name === '') {
                throw new ContractBroken($file, 'каждый инструмент должен быть объектом с непустой строкой "name"');
            }
        }

        return ['tools' => $tools];
    }




    #[Purpose('Вызывает инструмент по имени: находит объявление, проверяет аргументы по схеме и собирает конверт. Что делает сам инструмент — его дело, здесь только объявления.')]
    #[Param('name', 'string', 'имя инструмента из tools.json')]
    #[Param('arguments', 'array', 'аргументы вызова')]
    #[Returns('array', 'конверт: content — часть ответа (type/text), isError — был ли отказ')]
    #[Throws(AgentDirMissing::class, 'у агента не задан каталог объявлений')]
    #[Throws(ContractUnreadable::class, 'tools.json нет или он не читается')]
    #[Throws(ContractBroken::class, 'битый JSON, объявление не список или у инструмента нет непустого "name"')]
    #[Throws(SchemaBroken::class, 'inputSchema объявлена недопустимо')]
    #[Throws(ToolNotImplemented::class, 'инструмент объявлен, но метода с таким именем у агента нет')]
    #[Effect(Effect::FS_READ)]
    #[Fixture(files: [
        'tools.json' => '[{"name":"sayHello","description":"Здоровается","inputSchema":{"type":"object","properties":{"amount":{"type":"integer"}},"required":["amount"]}},{"name":"sayBye","description":"Прощается","inputSchema":{"type":"object","properties":{},"required":[]}}]',
        'dbaccess.json' => '{"tables":["public.test_table"]}',
        'instruction.prompt.php' => '<?php $prompt = ($name ?? "нет") . "!";',
    ])]
    #[Gives(['sayHello', ['amount' => 2]], ['content' => ['type' => 'text', 'text' => 'hello 2'], 'isError' => false])]
    #[Gives(['nope', []], ['content' => ['type' => 'text', 'text' => 'Unknown tool: nope'], 'isError' => true])]
    #[Gives(['sayHello', []], ['content' => ['type' => 'text', 'text' => 'Missing required argument: amount'], 'isError' => true])]
    #[Gives(['sayHello', ['amount' => 'два']], ['content' => ['type' => 'text', 'text' => 'Argument amount must be of type integer'], 'isError' => true])]
    #[Fails(['sayBye', []], ToolNotImplemented::class)]
    public function callTool(string $name, array $arguments = []): array
    {
        $tools = $this->listTools()['tools'];
        $tool  = $this->findTool($tools, $name);

        if ($tool === null) {
            return $this->errorEnvelope("Unknown tool: {$name}");
        }

        $schema = $this->readSchema($tool);

        $error = $this->validateArguments($schema, $arguments);
        if ($error !== null) {
            return $this->errorEnvelope($error);
        }

        if (!method_exists($this, $name)) {


            throw new ToolNotImplemented($name, static::class);
        }

        return $this->runTool($name, $arguments);
    }




    private function runTool(string $name, array $arguments): array
    {
        $output = $this->$name($arguments);

        return [
            'content' => ['type' => 'text', 'text' => (string) $output],
            'isError' => false,
        ];
    }




    private function errorEnvelope(string $text): array
    {
        return [
            'content' => ['type' => 'text', 'text' => $text],
            'isError' => true,
        ];
    }

    #[Purpose('Собирает XML-описание инструментов для подсказки модели: объявления и примеры вызовов.')]
    #[Returns('string', 'XML: <available_tools> с объявлениями и <tool_calling_examples> с примерами')]
    #[Throws(AgentDirMissing::class, 'у агента не задан каталог объявлений')]
    #[Throws(ContractUnreadable::class, 'tools.json нет или он не читается')]
    #[Throws(ContractBroken::class, 'битый JSON, объявление не список или у инструмента нет непустого "name"')]
    #[Effect(Effect::FS_READ)]
    #[Gives([], "<available_tools>\n"
        . "  <tool>\n"
        . "    <name>sayHello</name>\n"
        . "    <description>Здоровается</description>\n"
        . "    <input_schema>\n"
        . "      {&quot;type&quot;:&quot;object&quot;,&quot;properties&quot;:{&quot;amount&quot;:{&quot;type&quot;:&quot;integer&quot;}},&quot;required&quot;:[&quot;amount&quot;]}\n"
        . "    </input_schema>\n"
        . "  </tool>\n"
        . "  <tool>\n"
        . "    <name>sayBye</name>\n"
        . "    <description>Прощается</description>\n"
        . "    <input_schema>\n"
        . "      {&quot;type&quot;:&quot;object&quot;,&quot;properties&quot;:{},&quot;required&quot;:[]}\n"
        . "    </input_schema>\n"
        . "  </tool>\n"
        . "</available_tools>\n"
        . "<tool_calling_examples>\n"
        . "  <tool_call>\n"
        . "    {&quot;name&quot;:&quot;sayHello&quot;,&quot;arguments&quot;:{&quot;amount&quot;:1}}\n"
        . "  </tool_call>\n"
        . "  <tool_call>\n"
        . "    {&quot;name&quot;:&quot;sayBye&quot;,&quot;arguments&quot;:[]}\n"
        . "  </tool_call>\n"
        . "</tool_calling_examples>\n")]
    public function getToolsPromptXml(): string
    {
        $tools = $this->listTools()['tools'];

        return $this->toolsToXml($tools);
    }

    #[Purpose('Собирает подсказку агента: выполняет instruction.prompt.php с переданными данными и отдаёт строку $prompt.')]
    #[Param('data', 'array', 'переменные, доступные шаблону подсказки')]
    #[Returns('string', 'готовая подсказка')]
    #[Throws(AgentDirMissing::class, 'у агента не задан каталог объявлений')]
    #[Throws(ContractUnreadable::class, 'instruction.prompt.php нет или он не читается')]
    #[Throws(ContractBroken::class, 'шаблон не оставил строку $prompt')]
    #[Effect(Effect::FS_READ)]
    #[Gives([['name' => 'Аня']], 'Аня!')]
    public function getPrompt($data = []): string
    {
        $file = $this->agentDir() . '/instruction.prompt.php';

        if (!is_readable($file)) {
            throw new ContractUnreadable($file);
        }

        foreach ($data as $key => $value) {
            $$key = $value;
        }

        require $file;

        if (!isset($prompt) || !is_string($prompt)) {
            throw new ContractBroken($file, 'файл не оставил строку $prompt');
        }

        return $prompt;
    }


    private function agentDir(): string
    {
        if (!is_string($this->agentDir) || $this->agentDir === '') {
            throw new AgentDirMissing(static::class);
        }

        return $this->agentDir;
    }

    private function contractFile(): string
    {
        return $this->agentDir() . '/tools.json';
    }

    private function findTool(array $tools, string $name): ?object
    {
        foreach ($tools as $tool) {
            if (($tool->name ?? null) === $name) {
                return $tool;
            }
        }

        return null;
    }




    private function readSchema(object $tool): array
    {
        $name = $tool->name;
        $schema = (array) ($tool->inputSchema ?? []);

        if (isset($schema['type']) && $schema['type'] !== 'object') {
            throw new SchemaBroken($name, 'inputSchema типа "object" обязателен');
        }

        $properties = $schema['properties'] ?? [];

        if (!is_array($properties) && !is_object($properties)) {
            throw new SchemaBroken($name, '"properties" должен быть объектом');
        }

        $required = $schema['required'] ?? [];

        if (!is_array($required)) {
            throw new SchemaBroken($name, '"required" должен быть списком');
        }

        foreach ($required as $field) {
            if (!is_string($field) || $field === '') {
                throw new SchemaBroken($name, 'в "required" должны быть непустые строки');
            }
        }

        $schema['properties'] = (array) $properties;

        return $schema;
    }




    private function validateArguments(array $schema, array $args): ?string
    {
        $properties = $schema['properties'] ?? [];
        $required   = $schema['required'] ?? [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $args)) {
                return "Missing required argument: {$field}";
            }
        }

        foreach ($args as $key => $value) {
            if (!isset($properties[$key])) {
                return "Unknown argument: {$key}";
            }


            $rule = (array) $properties[$key];

            $expected = $rule['type'] ?? null;
            if ($expected !== null && !$this->matchesType($value, $expected)) {
                return "Argument {$key} must be of type {$expected}";
            }

            if (isset($rule['enum']) && is_array($rule['enum']) && !in_array($value, $rule['enum'], true)) {
                $allowed = implode(', ', array_map(static fn ($v): string => json_encode($v), $rule['enum']));

                return "Argument {$key} must be one of: {$allowed}";
            }

            if (isset($rule['minimum']) && is_numeric($value) && $value < $rule['minimum']) {
                return "Argument {$key} must be >= {$rule['minimum']}";
            }

            if (isset($rule['maximum']) && is_numeric($value) && $value > $rule['maximum']) {
                return "Argument {$key} must be <= {$rule['maximum']}";
            }
        }

        return null;
    }


    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'number'  => is_int($value) || is_float($value),
            'integer' => is_int($value),
            'string'  => is_string($value),
            'boolean' => is_bool($value),
            'array'   => is_array($value) && array_is_list($value),
            'object'  => is_array($value) && !array_is_list($value),
            'null'    => $value === null,
            default   => true,
        };
    }




    private function toolsToXml(array $tools): string
    {
        $toolsXml = [];

        foreach ($tools as $tool) {
            $name        = $tool->name;
            $description = $tool->description ?? '';
            $inputSchema = (array) ($tool->inputSchema ?? ['type' => 'object', 'properties' => []]);

            $schemaJson = json_encode(
                $inputSchema,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            $toolXml  = "  <tool>\n";
            $toolXml .= "    <name>" . $this->xmlEscape($name) . "</name>\n";
            $toolXml .= "    <description>" . $this->xmlEscape($description) . "</description>\n";
            $toolXml .= "    <input_schema>\n";
            $toolXml .= "      " . $this->xmlEscape($schemaJson) . "\n";
            $toolXml .= "    </input_schema>\n";
            $toolXml .= "  </tool>\n";

            $exampleArgs = $this->generateExampleArguments($inputSchema);
            $exampleJson = json_encode(
                ['name' => $name, 'arguments' => $exampleArgs],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );

            $exampleXml  = "  <tool_call>\n";
            $exampleXml .= "    " . $this->xmlEscape($exampleJson) . "\n";
            $exampleXml .= "  </tool_call>";

            $toolsXml[$name] = [
                'tool'    => $toolXml,
                'example' => $exampleXml,
            ];
        }

        $result = "<available_tools>\n";
        foreach ($toolsXml as $xml) {
            $result .= $xml['tool'];
        }
        $result .= "</available_tools>\n";

        $result .= "<tool_calling_examples>\n";
        foreach ($toolsXml as $xml) {
            $result .= $xml['example'] . "\n";
        }
        $result .= "</tool_calling_examples>\n";

        return $result;
    }


    private function xmlEscape(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }




    private function generateExampleArguments(array $schema): array
    {
        $properties = $schema['properties'] ?? [];
        $required   = $schema['required'] ?? [];
        $args       = [];

        foreach ($properties as $propName => $propSchema) {
            if (!in_array($propName, $required, true)) {
                continue;
            }

            $rule = (array) $propSchema;

            $args[$propName] = $this->exampleValueForType($rule['type'] ?? 'string');
        }

        return $args;
    }


    private function exampleValueForType(string $type): mixed
    {
        return match ($type) {
            'number', 'integer' => 1,
            'boolean'           => true,
            'string'            => 'string',
            'array'             => [],
            'object'            => new stdClass(),
            'null'              => null,
            default             => 'string',
        };
    }
}
