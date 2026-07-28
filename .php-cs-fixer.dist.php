<?php

use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('vendor')
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        // Industry standard but with function bodies opening on the same line
        '@PSR12' => false,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['default' => 'single_space'],
        'concat_space' => ['spacing' => 'one'],
        'no_unused_imports' => true,
        'no_extra_blank_lines' => ['tokens' => ['extra', 'use']],
        'no_whitespace_in_blank_line' => true,
        'braces_position' => [
            'classes_opening_brace' => 'same_line',
            'functions_opening_brace' => 'same_line',
            'control_structures_opening_brace' => 'same_line',
            'anonymous_classes_opening_brace' => 'same_line',
            'anonymous_functions_opening_brace' => 'same_line',
            'allow_single_line_empty_anonymous_classes' => true,
            'allow_single_line_anonymous_functions' => true,
        ],
        // This is a laravel standard.
        'not_operator_with_successor_space' => true,
    ])
    ->setFinder($finder);
