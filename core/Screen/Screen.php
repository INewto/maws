<?php

namespace Maws\Screen;

use JsonException;
use Maws\Screen\System\Exception\CommandNotDeclared;
use Maws\Screen\System\Exception\ConfigBroken;
use Maws\Screen\System\Exception\ConfigUnreadable;
use Maws\Screen\System\Exception\SceneMissing;
use Maws\Screen\System\Exception\TransferBroken;
use Maws\System\Cache\Cache;
use Maws\System\Cache\Policy;
use Maws\System\Exception\ResponseSent;
use Maws\System\Language;
use Maws\System\Contract\{Effect, Fails, Gives, Param, Purpose, Responds, Returns, Subject, Throws};

#[Subject(Screen::class, 'new', [])]
#[Purpose('Экран: выбирает сцену по типу экрана и показывает её. Сцена выбирается по НАПРАВЛЕНИЮ: своего шаблона у места нет, все места направления показываются одной сценой.')]
class Screen
{
    // Коды, которыми кнопка redirect имеет право закончить ответ: постоянный перенос,
    // временный и «после POST» — браузер обязан перейти методом GET.
    private const TRANSFER_CODES = [301, 302, 303];

    #[Purpose('Создаёт экран.')]
    #[Effect(Effect::NONE)]
    public function __construct()
    {
    }



    #[Purpose('Возвращает имя сцены, которой нужно показать экран данного типа. Сцена берётся по части адреса до "/", то есть по НАПРАВЛЕНИЮ, а не по месту: отдельной сцены для места не бывает, и заводить её не нужно. "Default" — основной шаблон сайта: в него встраиваются элементы мест, поэтому новому месту обычно достаётся он.')]
    #[Param('screenType', 'string|null', 'адрес экрана "Направление/Место"; null — сцена по умолчанию')]
    #[Returns('string', 'имя сцены из app/Screen/config.json')]
    #[Throws(ConfigUnreadable::class, 'не читается app/Screen/config.json')]
    #[Throws(ConfigBroken::class, 'битый JSON, конфиг не объект, нет сцены "Default" или для направления нет сцены')]
    #[Effect(Effect::FS_READ)]
    #[Gives([null], 'Default')]
    #[Fails(['Nope/Thing'], ConfigBroken::class)]
    public function getScene($screenType = null): string
    {
        $scenes = $this->getScenes();

        if ($screenType === null) {
            if (!isset($scenes->Default) || !is_string($scenes->Default)) {
                throw new ConfigBroken('нет сцены "Default"');
            }

            return $scenes->Default;
        }

        $key = str_contains($screenType, '/') ? explode('/', $screenType)[0] : $screenType;

        if (!isset($scenes->$key)) {
            throw new ConfigBroken("нет сцены для \"{$key}\"");
        }

        if (!is_string($scenes->$key)) {
            throw new ConfigBroken("сцена для \"{$key}\" должна быть строкой");
        }

        return $scenes->$key;
    }

    private function getScenes(): object
    {
        $file = SCREEN_DIR . '/config.json';

        if (!is_readable($file)) {
            throw new ConfigUnreadable($file);
        }

        try {
            $scenes = \json5_decode(file_get_contents($file), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ConfigBroken('битый JSON: ' . $e->getMessage());
        }

        if (!is_object($scenes)) {
            throw new ConfigBroken('конфиг должен быть объектом');
        }

        return $scenes;
    }

    #[Purpose('Нажимает кнопку пульта: показывает экран названной командой. Завершает процесс.')]
    #[Param('screenType', 'string|null', 'адрес экрана; для команды redirect не нужен — сцена не выбирается')]
    #[Param('command', 'string', 'кнопка пульта: showPage, show404 или redirect')]
    #[Param('data', 'mixed', 'данные для сцены; для redirect — адрес строкой или пара ["url" => адрес, "status" => код]')]
    #[Param('meta', 'array|null', 'шапка страницы из раздела: плоский список «имя колонки → значение»; нужна только showPage')]
    #[Param('cache', 'Policy|null', 'политика кэша места; null — не хранить')]
    #[Throws(CommandNotDeclared::class, 'пульт не знает такой кнопки')]
    #[Throws(ConfigUnreadable::class, 'не читается app/Screen/config.json')]
    #[Throws(ConfigBroken::class, 'нет сцены для направления')]
    #[Throws(SceneMissing::class, 'нет шаблона сцены')]
    #[Throws(ResponseSent::class, 'ответ ушёл: сцена отдана, перенос выставлен или копия клиента свежа')]
    #[Throws(TransferBroken::class, 'команда redirect: адреса нет или код не из тех, что понимает перенос')]
    #[Effect(Effect::SCREEN)]
    #[Responds([null, 'redirect', '/x'], '', 301)]
    #[Responds([null, 'redirect', ['url' => '/x', 'status' => 303]], '', 303)]
    #[Fails([null, 'redirect', ['url' => '/x', 'status' => 200]], TransferBroken::class)]
    #[Fails(['Error', 'nope'], CommandNotDeclared::class)]
    public function screenInterface($screenType, $command, $data = null, $meta = null, ?Policy $cache = null)
    {
        $commands = ['showPage', 'show404', 'redirect'];

        if (!in_array($command, $commands)) {
            throw new CommandNotDeclared($command);
        }

        // Шапку принимает только страница: у 404 и редиректа печатать её негде.
        if ($command === 'showPage') {
            $this->showPage($screenType, $data, $meta, $cache);
            return;
        }

        $this->$command($screenType, $data);
    }

    #[Purpose('Отправляет на другой адрес вместо сцены. Кнопка пульта: отдельной двери у экрана нет. Ответ объявляется отданным, а не обрывает процесс: редирект — такой же конец ответа, как ответ места, и проверяется прогоном.')]
    #[Param('screenType', 'null', 'не используется: сцена не выбирается')]
    #[Param('url', 'string|array', 'адрес строкой — перенос 301; пара ["url" => адрес, "status" => код] — код 301, 302 или 303')]
    #[Throws(TransferBroken::class, 'адреса нет или код не из тех, что понимает перенос')]
    #[Throws(ResponseSent::class, 'ответ ушёл: заголовок выставлен, тело пустое')]
    #[Effect(Effect::SCREEN)]
    private function redirect($screenType, $url = null): never
    {
        [$location, $status] = $this->transfer($url);

        // Код ставится и явно: header() с кодом работает в вебе, но в прогоне
        // (PHP CLI) заголовки не отправляются, и прочитать код было бы нечем.
        http_response_code($status);
        header('Location: ' . $location, true, $status);

        throw new ResponseSent();
    }

    #[Purpose('Разбирает данные кнопки redirect: строка — адрес с кодом 301, пара — адрес и код.')]
    #[Param('data', 'mixed', 'адрес строкой или пара ["url" => адрес, "status" => код]')]
    #[Returns('array', 'адрес и код ответа')]
    #[Throws(TransferBroken::class, 'адреса нет или код не из тех, что понимает перенос')]
    #[Effect(Effect::NONE)]
    private function transfer($data): array
    {
        if (is_string($data)) {
            $data = ['url' => $data];
        }

        if (!is_array($data) || !is_string($data['url'] ?? null) || $data['url'] === '') {
            throw new TransferBroken('нужен адрес: строка или пара ["url" => адрес, "status" => код]');
        }

        $status = $data['status'] ?? 301;

        if (!in_array($status, self::TRANSFER_CODES, true)) {
            throw new TransferBroken('код ' . var_export($status, true)
                . ' не годится: 301 — постоянный, 302 — временный, 303 — после POST');
        }

        return [$data['url'], $status];
    }

    #[Purpose('Показывает сцену ошибки: при 404 — Error/404, иначе Error. Завершает процесс.')]
    #[Param('status', 'int|string', 'код ответа; "404" и 404 равнозначны')]
    #[Param('message', 'string|null', 'текст сбоя; показывается только в DEV')]
    #[Throws(ConfigUnreadable::class, 'не читается app/Screen/config.json')]
    #[Throws(ConfigBroken::class, 'нет сцены "Error"')]
    #[Throws(SceneMissing::class, 'нет шаблона сцены')]
    #[Effect(Effect::SCREEN)]
    public function screenErrorInterface($status, $message = null)
    {
        // Код приводим к числу: из строки запроса и из вызова в коде он приходит как "404".
        $status = (int) $status;

        // Код вне диапазона — не код ответа, а ошибка вызывающего: показываем 500.
        if ($status < 100 || $status > 599) {
            $status = 500;
        }

        // Код выставляется ЗДЕСЬ, до выбора сцены: иначе страница ошибки уедет с 200.
        http_response_code($status);

        $command = $status === 404 ? 'show404' : 'showPage';

        // Страница ошибки заканчивает ответ так же, как любая другая: сцена отдана,
        // дальше печатать нечего. Ответ ловится здесь, потому что страницу ошибки
        // зовут из обработчика исключений, а его собственный throw уже никто не поймает.
        try {
            $this->screenInterface('Error', $command, $message);
        } catch (ResponseSent) {
        }
    }

    private function show404($screenType, $data = null)
    {
        $scene = $this->getScene($screenType);

        // Код ставится и здесь: show404 достижим напрямую — screenInterface('Error', 'show404').
        http_response_code(404);
        $this->sceneUp($scene, ['subscene' => 404], $data);
    }

    private function showPage($screenType, $data = null, $meta = null, ?Policy $cache = null)
    {
        $scene = $this->getScene($screenType);

        $this->sceneUp($scene, ['meta' => $meta ?? []], $data, $cache);
    }

    private function sceneUp($scene, $params = [], $data = null, ?Policy $cache = null)
    {
        // Шапка страницы: плоский список значений из раздела. Экран её не разбирает
        // и ничего не вычисляет — пустые значения тоже уходят как есть. Печатать
        // пустой тег или пропустить его, решает шаблон сцены, а не ядро.
        $meta = $params['meta'] ?? [];

        $dir = SCREEN_DIR . '/Scenes/' . $scene;

        $name     = $scene;
        $file     = 'index.tpl.php';

        if (!empty($params['subscene'])) {
            $name = $scene . '/' . basename($params['subscene']);
            $file = basename($params['subscene']) . '/index.tpl.php';
        }

        $template = Language::file($dir, $file);

        if ($template === null) {
            throw new SceneMissing($name);
        }

        // Сцена печатает сама, поэтому тело сначала собирается в буфер: без него
        // ETag считать не по чему, а заголовки после первого вывода уже не поставить.
        ob_start();

        try {
            require $template;
        } catch (ResponseSent $e) {
            // Сцена ответила сама: тело ответа уже в буфере, отдаём его как есть.
            ob_end_flush();

            throw $e;
        } catch (\Throwable $e) {
            // Недорисованная страница никому не нужна: её место займёт страница ошибки.
            ob_end_clean();

            throw $e;
        }

        $body = ob_get_clean();

        Cache::send($body === false ? '' : $body, $cache ?? Policy::none());
    }
}
