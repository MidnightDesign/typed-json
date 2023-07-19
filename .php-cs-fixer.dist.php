<?php

$rules = [
    '@PSR12' => true,
    'binary_operator_spaces' => true,
    'class_attributes_separation' => [
        'elements' => [
            'method' => 'one',
        ],
    ],
    'function_declaration' => [
        'closure_fn_spacing' => 'none',
    ],
    'function_typehint_space' => true,
    'no_blank_lines_after_phpdoc' => true,
    'no_empty_comment' => true,
    'no_empty_phpdoc' => true,
    'no_extra_blank_lines' => [
        'tokens' => [
            'extra',
            'use',
        ],
    ],
    'no_unused_imports' => true,
    'ordered_class_elements' => [
        'order' => [
            'use_trait',
            'case',
            'constant_public',
            'constant_protected',
            'constant_private',
            'property',
            'property_static',
            'construct',
            'destruct',
            'method_public_static',
            'method_public_abstract_static',
            'method_protected_static',
            'method_protected_abstract_static',
            'method_private_static',
            'method_private_abstract_static',
            'magic',
            'method_public',
            'method_public_abstract',
            'phpunit',
            'method_protected_abstract',
            'method_protected',
            'method_private',
            'method_private_abstract',
        ],
    ],
    'ordered_imports' => [
        'imports_order' => ['class', 'function', 'const'],
        'sort_algorithm' => 'alpha',
    ],
    'phpdoc_trim' => true,
    'php_unit_data_provider_static' => true,
    'self_accessor' => true,
    'single_space_after_construct' => true,
    'static_lambda' => true,
    'trailing_comma_in_multiline' => [
        'elements' => [
            'arguments',
            'arrays',
            'match',
            'parameters',
        ],
    ],
];

$finder = PhpCsFixer\Finder::create()
    ->notPath('vendor')
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRules($rules)
    ->setFinder($finder)
    ->setRiskyAllowed(true);
