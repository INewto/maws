<?php

namespace Maws\System\Contract;

#[Purpose('Не атрибут, а держатель имён: подставляется в аргументы и в ожидания там, где объект записать нельзя.')]
final class Token
{
    public const SUBJECT   = '@subject';
    public const SANDBOX   = '@sandbox';
    public const WORLD     = '@world';
    public const SCREEN    = '@screen';
    public const STATEMENT = '@statement';
}
