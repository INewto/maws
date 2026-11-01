<?php

namespace Maws\System\Contract;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Effect
{
    public const NONE         = 'none';
    public const DB_CONNECT   = 'db:connect';
    public const DB_READ      = 'db:read';
    public const DB_WRITE     = 'db:write';
    public const DB_UNGUARDED = 'db:unguarded';
    public const FS_READ      = 'fs:read';
    public const FS_WRITE     = 'fs:write';
    public const HTTP         = 'http';
    public const SCREEN       = 'screen';
    public const LOG          = 'log';
    public const INPUT        = 'input';

    #[Param('kind', 'string', 'вид эффекта; брать из констант Effect::*')]
    public function __construct(public readonly string $kind)
    {
    }
}
