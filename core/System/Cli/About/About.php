<?php

namespace Maws\System\Cli\About;

class About
{
    public static function run(): void
    {
        $template = __DIR__ . '/templates/about.tpl.txt';

        if (is_readable($template)) {
            echo file_get_contents($template);
        } else {
            echo "MAWS v0.1 — Man / Ai / World / Screen\n";
        }
    }
}
