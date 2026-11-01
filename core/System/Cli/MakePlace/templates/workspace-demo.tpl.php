<?php

namespace App\World\{{Direction}}\Places\{{Place}};

use App\World\{{Direction}}\Places\{{Direction}}Workspaces;
use Maws\System\Contract\{Effect, Param, Purpose, Returns, Subject};

#[Subject(Workspace::class, 'Scaffold::workspace', ['{{Direction}}/{{Place}}'])]
#[Purpose('Рабочее пространство {{Direction}}/{{Place}}.')]
class Workspace extends {{Direction}}Workspaces
{
    #[Purpose('Собирает пространство из места, человека и раздела.')]
    #[Param('place', 'Place', 'место, к которому привязано пространство')]
    #[Param('man', 'Man', 'человек, от имени которого работает пространство')]
    #[Param('area', 'Area', 'раздел, по которому пришли в это место')]
    #[Effect(Effect::NONE)]
    public function __construct($place, $man, $area)
    {
        parent::__construct($place, $man, $area);
    }

    // Случая в контракте нет намеренно: startWork отдаёт страницу целиком, а сверять её байт
    // в байт — значит заморозить шаблон в проверке. Страницу смотрит живая проба; в контракт
    // идут методы с проверяемым исходом — #[Gives], #[Fails], #[Changes], #[Responds].
    #[Purpose('Проводит человека по месту, берёт инструменты места и отдаёт разметку.')]
    #[Returns('string', 'готовая разметка места')]
    #[Effect(Effect::FS_READ)]
    #[Effect(Effect::DB_READ)]
    public function startWork()
    {
        $way = $this->place->way();

        $this->man->body->legs->walk($way);
        $pass  = $this->man->enterPlace($way);
        $tools = $this->man->body->hands->takeTools($pass);

        // В данном примере инструмент собирает данные и свою собственную html разметку
        // Однако, разметка для каждого инструмента не является обязательной — её может не быть вовсе
        // А результат возвращаемый Workspace формироваться из совместной работы нескольких инструментов
        return $tools->{{Tool}}->serve();
    }
}
