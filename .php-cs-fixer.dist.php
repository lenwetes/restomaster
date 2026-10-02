<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude(['bootstrap', 'storage', 'vendor', 'node_modules', 'resources/views'])
    ->name('*.php');

return (new Config())
    ->setRules([
        '@PSR12' => true,
        'array_indentation' => true,
        'concat_space' => ['spacing' => 'none'],
        'no_unused_imports' => true,
        'ordered_imports' => true,
        'php_unit_test_class_requires_covers' => false,
    ])
    ->setFinder($finder);
