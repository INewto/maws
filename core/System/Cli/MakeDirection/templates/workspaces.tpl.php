<?php

namespace App\World\{{Direction}}\Places;

class {{Direction}}Workspaces
{
    protected $place;
    protected $toolsList;
    protected $man;
    protected $area;

    public function __construct($place, $man = null, $area = null)
    {
        $this->place = $place;
        $this->man   = $man;
        $this->area  = $area;
    }
}
