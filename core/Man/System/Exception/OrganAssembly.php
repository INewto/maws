<?php

namespace Maws\Man\System\Exception;

class OrganAssembly extends ManMisuse
{
    public function __construct(string $organ, string $owner)
    {
        parent::__construct("Орган {$organ} можно создавать только из {$owner}");
    }
}
