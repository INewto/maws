<?php

namespace App\Ai\Multi\Agent1;

/**
 * Agent1 — пример агента, который работает с базой и зовёт соседнего агента.
 *
 * В файле три сюжета:
 *   1) инструменты, объявленные в tools.json (sayHello, sayBye);
 *   2) вызов Agent2 — классом, как инструмент места;
 *   3) пример доступа к базе — запросы через AiDbase.
 *
 * Отдельного стража нет: AiDbase проверяет права САМ, на каждом запросе.
 * Обойти это нечем — другого входа в базу у агента нет, и отбросить проверку
 * неоткуда: она не отдельный класс, а начало каждого SQL-метода.
 *
 * Права агент берёт у себя же: $this->rights() читает dbaccess.json из каталога
 * агента. Каталог закрыт снаружи (protected в Ai), поэтому «пойти в чужой
 * dbaccess.json» снаружи нечем: своё объявление ставит сам агент.
 *
 * Агент не видит ни мир, ни его типы. У него свой AiDbase и свои права.
 */

use App\Ai\Multi\Agent2\Agent2;
use Maws\Ai\Ai;
use Maws\Ai\System\AiDbase;
use Maws\Ai\System\Exception\AiDbaseDenied;

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

class Agent1 extends Ai
{
    private $dbDSN;
    private $dbUser;
    private $dbPassword;

    /**
     * Настройки соединения приходят аргументами, а не берутся из глобальных
     * констант: AiDbase принципиально их не читает — соединение знает тот,
     * кто его открывает. Здесь их передаёт запускающий (см. низ файла).
     */
    public function __construct()
    {
        $this->dbDSN      = DBASE_DSN;
        $this->dbUser     = DBASE_USER;
        $this->dbPassword = DBASE_PASSWORD;
        $this->agentDir   = __DIR__;
        $this->prompt     = $this->getPrompt(['toolsListXml' => $this->getToolsPromptXml()]);
    }

    // ------------------------------------------------------------------
    // 1. Инструменты, объявленные в tools.json
    // ------------------------------------------------------------------
    //
    // Имя метода совпадает с "name" инструмента: база зовёт их через
    // $this->$name($arguments). Метод возвращает СТРОКУ — конверт
    // (content/isError) собирает Ai::runTool, инструменту это не дело.

    public function sayHello(array $arguments): string
    {
        return implode(' ', array_fill(0, (int) $arguments['amount'], 'hello'));
    }

    public function sayBye(array $arguments): string
    {
        return implode(' ', array_fill(0, (int) $arguments['amount'], 'bye'));
    }

    // ------------------------------------------------------------------
    // 2. Вызов соседнего агента
    // ------------------------------------------------------------------
    //
    // Agent2 — такой же класс, как этот: свой tools.json, своя подсказка, свой
    // каталог объявлений, свои права

    public function askAgent2(array $arguments): string
    {
        return (new Agent2())->work((string) $arguments['task']);
    }

    // ------------------------------------------------------------------
    // 3. Пример работы с базой
    // ------------------------------------------------------------------

    /**
     * База агента. Два ответа приходят вместе, в один объект:
     *   $this->rights() — ЧТО агенту можно (реестр прав из dbaccess.json);
     *   остальные аргументы — ГДЕ база (своё соединение агента).
     *
     * Права идут именно реестром, а не списком таблиц: список можно выписать
     * на месте, а AiAccess — нет, он читается из файла. Так разрешение нельзя
     * выдать себе в обход объявления.
     *
     * Соединение ленивое: пока не выполнен ни один запрос, PDO не открыт.
     */
    private function dbase(): AiDbase
    {
        return new AiDbase(
            $this->rights(),
            $this->dbDSN,
            $this->dbUser,
            $this->dbPassword,
        );
    }

    /**
     * Чтение. Проверка уже внутри all(): таблицы, названные движком в плане,
     * сверяются с объявленными. public.test_table объявлена в dbaccess.json — проходит.
     */
    public function demoRead(): void
    {
        $rows = $this->dbase()->all('SELECT id, name FROM public.test_table ORDER BY id');

        foreach ($rows as $row) {
            echo "{$row['id']}: {$row['name']}\n";
        }
    }

    /**
     * Запись в транзакции.
     *
     * Пример намеренно идёт по ветке отката: база общая с человеком, и пример
     * не должен оставлять в ней изменений. В рабочем коде на месте rollBack()
     * стоит commit() (а rollBack — в catch).
     */
    public function demoWrite(): void
    {
        $db = $this->dbase();

        $db->beginTransaction();

        $affected = $db->run(
            'UPDATE public.test_table SET name = :name WHERE id = :id',
            ['name' => 'renamed', 'id' => 1],
        );

        $db->rollBack();

        echo "UPDATE прошёл проверку, затронуто строк: {$affected} (откат — база не изменена)\n";
    }

    /**
     * Отказ. public.areas не объявлена в dbaccess.json, поэтому запрос отклоняется
     * ДО выполнения.
     */
    public function demoDenied(): void
    {
        try {
            $this->dbase()->all('SELECT id, name FROM public.areas');
        } catch (AiDbaseDenied $e) {
            echo 'запрос отклонён: ' . $e->getMessage() . "\n";
        }
    }

    // ------------------------------------------------------------------
    // 4. Работа агента
    // ------------------------------------------------------------------

    // Агент получает задачу, модель выбирает один инструмент, ответ уходит тому,
    // кто позвал. Методы demoRead/demoWrite/demoDenied сюда не входят: они пишут
    // в общую базу и зовутся вручную.

    public function work(string $task): string
    {
        $call   = $this->callLLM($task);
        $result = $this->callTool($call['name'], $call['arguments']);

        return $result['content']['text'];
    }

    // Зашитая модель: задачу про соседа отдаёт Agent2, на остальное здоровается.
    public function callLLM($task = null)
    {
        return str_contains((string) $task, 'сосед')
            ? ['name' => 'askAgent2', 'arguments' => ['task' => (string) $task]]
            : ['name' => 'sayHello', 'arguments' => ['amount' => 1]];
    }
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $done = 0;

    // ДЕМО: задача одна, а процесс должен жить — поэтому цикл холостой намеренно.
    // В рабочем коде на его месте блокирующее ожидание задачи у брокера (Redis, RabbitMQ —
    // что выберет разработчик): процесс спит, пока задачи нет, и просыпается на новую.
    while (1) {
        if ($done == 0) {
            echo (new Agent1())->work('передай приветствие соседу') . "\n";

            $done = 1;
        }
    }
}
