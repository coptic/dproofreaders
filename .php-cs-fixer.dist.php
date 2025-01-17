<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('pinc/3rdparty')
    ->exclude('vendor')
    ->exclude('node_modules')
    ->in(__DIR__)
    ->name('*.php')
    ->name('*.inc')
    ->name('*.php.example')
;

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        '@PHP74Migration' => true,
        // PHP tags
        'linebreak_after_opening_tag' => true,
        'blank_line_after_opening_tag' => false,
        'echo_tag_syntax' => ['format' => 'long'],
        // arrays
        'array_indentation' => true,
        'trim_array_spaces' => true,
        'whitespace_after_comma_in_array' => true,
        // whitespace
        'no_spaces_around_offset' => ['positions' => ['inside', 'outside']],
        'binary_operator_spaces' => ['operators' => ['=' => 'single_space']],
        'space_after_semicolon' => true,
        // comments
        'single_line_comment_style' => ['comment_types' => ['asterisk', 'hash']],
        // misc
        'no_useless_return' => true,
        'no_mixed_echo_print' => ['use' => 'echo'],
        'method_chaining_indentation' => true,
    ])
    ->setFinder($finder)
;
