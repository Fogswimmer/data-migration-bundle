<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src'
    ]);

return (new PhpCsFixer\Config())
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules([
        '@Symfony' => true,
        '@PER' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_extra_blank_lines' => [
            'tokens' => [
                'extra',
                'curly_brace_block',
                'parenthesis_brace_block',
                'square_brace_block',
                'throw',
                'use',
                'use_trait',
            ],
        ],
        'no_unused_imports' => true,
        'ordered_imports' => [
            'imports_order' => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
    ])
    ->setFinder($finder);