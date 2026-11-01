<?php

namespace Maws\System\Test;

use Maws\Man\Man;
use Maws\System\Contract\{Effect, Param, Purpose, Returns};
use Maws\World\System\Area;

/**
 * Строитель для контрактов раздела.
 *
 * Раздел существует только в базе, и собирает его `Head` — спуском по дереву и дочитыванием og.
 * Снаружи взять раздел больше неоткуда, поэтому контракт `Area` строится тем же путём, каким
 * её достаёт роутинг. Это не дубль чужой логики, а тот же вход: голова — единственная дверь.
 */
final class Areafold
{
    #[Purpose('Собирает раздел по адресу: спускается по дереву разделов и дочитывает og — ровно как при роутинге.')]
    #[Param('url', 'string', 'адрес раздела без ведущего слеша, например "main"; "" — главная страница')]
    #[Param('man', 'Man|null', 'человек, чьей головой разбирается адрес; по умолчанию новый')]
    #[Returns('Area', 'раздел, которого достиг адрес, с прочитанными og')]
    #[Effect(Effect::DB_READ)]
    public static function area(string $url, ?Man $man = null): Area
    {
        $man = $man ?? Scaffold::man();
        $man->body->head->getWorkspace($url);

        return $man->body->head->currentArea;
    }
}
