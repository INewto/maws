<?php

namespace Maws\System\Exception;

/**
 * Место ответило само: разметку или JSON оно уже напечатало, сцена не нужна.
 *
 * Это не сбой и не ошибка в коде, а конец ответа. Поэтому класс не наследует
 * ни MawsException («показать»), ни MawsMisuse («исправить»): ловить его
 * в ветке ошибок нельзя, иначе поверх готового ответа уедет страница 500.
 */
class ResponseSent extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ответ уже отдан местом: сцена не нужна');
    }
}
