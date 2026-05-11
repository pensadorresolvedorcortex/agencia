<?php

$doc = [
    'hooks' => [
        'nafb_validation_failed' => 'Triggered when payload validation fails.',
        'nafb_ajax_error' => 'Triggered when AJAX handler returns an error.',
        'nafb_unexpected_state' => 'Triggered on inconsistent runtime state.',
        'nafb_before_add_to_cart' => 'Before each Woo add_to_cart call.',
        'nafb_after_add_to_cart' => 'After each successful Woo add_to_cart call.',
    ],
    'snapshot_schema' => [
        'variation_label' => 'string',
        'student_names' => 'string[]',
    ],
    'config_schema' => [
        'enabled' => 'bool',
        'builder_mode' => 'string(matrix)',
        'ui_mode' => 'string(drawer)',
        'variation_ids' => 'int[]',
        'fields_schema' => 'array',
    ],
    'ajax_contract' => [
        'action' => 'nafb_batch_add_to_cart',
        'payload' => ['product_id', 'mode', 'items[]'],
        'result' => ['success', 'code', 'errors', 'lines', 'added'],
    ],
];

$outputPath = __DIR__ . '/../docs/internal-contracts.json';
file_put_contents($outputPath, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Generated {$outputPath}\n";
