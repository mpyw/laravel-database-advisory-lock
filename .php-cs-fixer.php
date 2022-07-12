<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setFinder(
        (new Finder())
            ->in([
                __DIR__ . '/src',
                __DIR__ . '/phpstan',
                __DIR__ . '/tests',
            ]),
    )
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        '@PhpCsFixer' => true,
        '@PhpCsFixer:risky' => true,
        '@PHP80Migration' => true,
        '@PHP80Migration:risky' => true,
        '@PSR12' => true,
        '@PHPUnit84Migration:risky' => true,
        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'case',
                'continue',
                'declare',
                'default',
                'exit',
                'goto',
                'include',
                'include_once',
                'phpdoc',
                'require',
                'require_once',
                'return',
                'switch',
                'throw',
                'try',
            ],
        ],
        'cast_spaces' => ['space' => 'none'],
        'concat_space' => ['spacing' => 'one'],
        'control_structure_continuation_position' => true,
        'date_time_immutable' => true,
        'declare_parentheses' => true,
        'echo_tag_syntax' => ['format' => 'short'],
        'final_internal_class' => false,
        'general_phpdoc_annotation_remove' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],
        'heredoc_indentation' => false,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'native_constant_invocation' => false,
        'native_function_invocation' => false,
        'nullable_type_declaration_for_default_null_value' => true,
        'php_unit_internal_class' => false,
        'php_unit_method_casing' => false,
        'php_unit_strict' => false,
        'php_unit_test_annotation' => false,
        'php_unit_test_case_static_method_calls' => ['call_type' => 'this'],
        'php_unit_test_class_requires_covers' => false,
        'phpdoc_line_span' => true,
        'phpdoc_separation' => false,
        'phpdoc_summary' => false,
        'phpdoc_to_comment' => ['ignored_tags' => ['noinspection']],
        'phpdoc_types_order' => false,
        'regular_callable_call' => true,
        'simplified_if_return' => true,
        'simplified_null_return' => true,
        'single_line_throw' => false,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],
        'types_spaces' => false,
        'use_arrow_functions' => false,
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
    ])
    ->setRiskyAllowed(true);
