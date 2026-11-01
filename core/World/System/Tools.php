<?php

namespace Maws\World\System;

use JsonException;
use Maws\System\Contract\{Effect, Fails, Fixture, Gives, Param, Purpose, Returns, Subject, Throws};
use Maws\System\Path;
use Maws\World\System\Exception\AccessNotDeclared;
use Maws\World\System\Exception\ContractBroken;
use Maws\World\System\Exception\ContractUnreadable;

#[Subject(Tools::class, 'Maws\World\System\Tools::of', ['@sandbox/ok', 'Public/Home'])]
#[Fixture(files: [
    'ok/settings.json' => '{"tools":{"list":["ExampleTool","SecondTool"],"needs":{"Public/Home":["SecondTool"]}}}',
    'bad/settings.json' => '{"tools":',
    'shape/settings.json' => '{"tools":{"list":"ExampleTool"}}',
    'alien/settings.json' => '{"tools":{"list":["ExampleTool"],"needs":{"Secret/Home":["ExampleTool"]}}}',
])]
#[Purpose('Инструменты места: что место объявило в settings.json, кому из соседей разрешило свои инструменты и что из этого достанется просящему. Файл читается один раз при сборке: битый settings.json — отказ на входе, а не сюрприз посреди работы.')]
final class Tools
{
    #[Param('file', 'string', 'прочитанный settings.json — нужен для текста отказов')]
    #[Param('place', 'string', 'адрес места "Направление/Место"')]
    #[Param('list', 'array', 'имена инструментов места')]
    #[Param('needs', 'array', 'нужды места: адрес соседа → имена нужных инструментов')]
    #[Param('raw', 'object', 'объект "tools" как он записан в файле')]
    private function __construct(
        private readonly string $file,
        private readonly string $place,
        private readonly array $list,
        private readonly array $needs,
        private readonly object $raw
    ) {
    }

    #[Purpose('Читает settings.json места и проверяет его форму. Место отвечает за файл целиком: и список своих инструментов, и нужды в чужих. Нужда вне своего направления — отказ: направление граница доверия, и файл, лежащий рядом, её не расширяет.')]
    #[Param('dir', 'string', 'каталог места; settings.json лежит в нём')]
    #[Param('place', 'string', 'адрес места "Направление/Место"')]
    #[Returns('self', 'объявленные инструменты и нужды места')]
    #[Throws(ContractUnreadable::class, 'нет settings.json или он не читается')]
    #[Throws(ContractBroken::class, 'битый json, нет списка "tools", нужда вне своего направления')]
    #[Effect(Effect::FS_READ)]
    #[Fails(['@sandbox/missing', 'Public/Home'], ContractUnreadable::class)]
    #[Fails(['@sandbox/bad', 'Public/Home'], ContractBroken::class)]
    #[Fails(['@sandbox/shape', 'Public/Home'], ContractBroken::class)]
    #[Fails(['@sandbox/alien', 'Public/Home'], ContractBroken::class)]
    public static function of(string $dir, string $place): self
    {
        $file = Path::normalize($dir . '/settings.json');

        if (!is_readable($file)) {
            throw new ContractUnreadable($file);
        }

        $raw = file_get_contents($file);

        if ($raw === false) {
            throw new ContractUnreadable($file);
        }

        try {
            $settings = \json5_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ContractBroken($file, $e->getMessage());
        }

        if (!is_object($settings) || !isset($settings->tools->list) || !is_array($settings->tools->list)) {
            throw new ContractBroken($file, 'нет списка "tools"');
        }

        foreach ($settings->tools->list as $tool) {
            if (!is_string($tool) || $tool === '') {
                throw new ContractBroken($file, 'в "tools" должны быть непустые строки');
            }
        }

        return new self(
            $file,
            $place,
            array_values($settings->tools->list),
            self::needsOf($settings->tools, $file, explode('/', $place, 2)[0]),
            $settings->tools
        );
    }

    #[Purpose('Отдаёт имена инструментов, объявленных местом.')]
    #[Returns('array', 'имена инструментов в том порядке, как в settings.json')]
    #[Effect(Effect::NONE)]
    #[Gives([], ['ExampleTool', 'SecondTool'])]
    public function list(): array
    {
        return $this->list;
    }

    #[Purpose('Отдаёт нужды места: какие соседи и какие их инструменты ему нужны.')]
    #[Returns('array', 'адрес места → имена нужных инструментов')]
    #[Effect(Effect::NONE)]
    #[Gives([], ['Public/Home' => ['SecondTool']])]
    public function needs(): array
    {
        return $this->needs;
    }

    #[Purpose('Отдаёт настройки как они записаны в файле — для тех, кто читает settings.json сам и ждёт прежнюю форму.')]
    #[Returns('object', 'объект "tools" из settings.json')]
    #[Effect(Effect::NONE)]
    public function raw(): object
    {
        return $this->raw;
    }

    #[Purpose('Отвечает, что из чужих инструментов достанется просящему месту: только объявленное в нуждах и в том порядке, в каком инструменты просили. Место, которого нет в нуждах, не получает ничего — отказ, а не пустой список: пустой список значил бы «доступа нет, но и беды нет», а это разные вещи.')]
    #[Param('place', 'string', 'адрес места, которое просит инструменты')]
    #[Param('tools', 'array', 'инструменты, которые оно хотело бы получить')]
    #[Returns('array', 'имена инструментов, разрешённых этому месту')]
    #[Throws(AccessNotDeclared::class, 'место не объявлено в нуждах этого места')]
    #[Effect(Effect::NONE)]
    #[Gives(['Public/Home', ['SecondTool', 'ExampleTool']], ['SecondTool'])]
    #[Gives(['Public/Home', ['ExampleTool']], [])]
    #[Fails(['Public/Nope', ['SecondTool']], AccessNotDeclared::class)]
    public function for(string $place, array $tools): array
    {
        if (!isset($this->needs[$place])) {
            throw new AccessNotDeclared($this->place, $place);
        }

        $allowed = $this->needs[$place];

        return array_values(array_filter(
            $tools,
            static fn ($tool): bool => in_array($tool, $allowed, true)
        ));
    }

    private static function needsOf(object $tools, string $file, string $direction): array
    {
        if (!isset($tools->needs)) {
            return [];
        }

        if (!is_object($tools->needs)) {
            throw new ContractBroken($file, '"needs" должен быть объектом');
        }

        $needs = [];

        foreach ($tools->needs as $neededPlace => $neededTools) {
            if (!is_array($neededTools)) {
                throw new ContractBroken($file, "объект \"needs\" {$neededPlace} должен содержать список");
            }

            if (!str_starts_with($neededPlace, $direction . '/')) {
                throw new ContractBroken($file, "нужда \"{$neededPlace}\" не в направлении {$direction}");
            }

            $needs[$neededPlace] = array_values($neededTools);
        }

        return $needs;
    }
}
