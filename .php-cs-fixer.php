<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/core',
        __DIR__ . '/app',
        __DIR__ . '/public',
        __DIR__ . '/db',
    ])
    ->name('*.php')
    ->notName('*.tpl.php');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder);
