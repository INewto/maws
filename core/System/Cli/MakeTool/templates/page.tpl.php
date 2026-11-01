<?php

namespace App\World\{{Direction}}\Places\{{Place}}\Tools\{{Tool}}\System;

use Maws\Screen\System\View;

class Page extends View
{
    public function index(array $data): string
    {
        return $this->render($this->template(__DIR__ . '/Templates'), $data);
    }
}
