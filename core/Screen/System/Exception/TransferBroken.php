<?php

namespace Maws\Screen\System\Exception;

class TransferBroken extends ScreenMisuse
{
    public function __construct(string $reason)
    {
        parent::__construct("Кнопка redirect не отправит ответ: {$reason}");
    }

}
