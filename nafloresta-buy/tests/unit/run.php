<?php

require __DIR__ . '/../bootstrap.php';

use NaFlorestaBuy\Application\ValidateSelectionService;
use NaFlorestaBuy\Infrastructure\Repository\PostMetaConfigRepository;
use NaFlorestaBuy\Infrastructure\Support\DataIntegrityGuard;
use NaFlorestaBuy\Presentation\Admin\ProductTabRenderer;

// validation quantity vs student_names
$repo = new PostMetaConfigRepository();
$repo->saveProductConfig(100, ['enabled' => true, 'variation_ids' => [201]]);
$GLOBALS['nafb_products'][201] = new NAFB_Test_Product(201, 100, 'Turma A');

$validator = new ValidateSelectionService($repo);
$result = $validator->validate([
    'product_id' => 100,
    'mode' => 'matrix',
    'items' => [[
        'variation_id' => 201,
        'quantity' => 2,
        'fields' => ['student_names' => [['name' => 'Ana']]],
    ]],
]);
nafb_assert($result->hasErrors(), 'Expected validation errors for student_names mismatch');
nafb_assert($result->errors()[0]['code'] === 'student_count_mismatch', 'Expected student_count_mismatch code');

// snapshot normalization
$guard = new DataIntegrityGuard();
$snapshot = $guard->normalizeSnapshot(['variation_label' => '<b>Kids</b>', 'student_names' => [['name' => ' A '], '', ['name' => '']]]);
nafb_assert($snapshot['variation_label'] === 'Kids', 'Expected sanitized variation label');
nafb_assert(count($snapshot['student_names']) === 1 && $snapshot['student_names'][0] === 'A', 'Expected only valid student names');

// preset save/apply logic through private methods
$renderer = new ProductTabRenderer($repo);
$savePreset = new ReflectionMethod($renderer, 'savePreset');
$savePreset->setAccessible(true);
$savePreset->invoke($renderer, ['enabled' => true, 'variation_ids' => [1]], 'Preset A');
nafb_assert(isset($GLOBALS['nafb_options']['nafb_presets']['preset-a']), 'Expected preset to be saved');

$getPreset = new ReflectionMethod($renderer, 'getPreset');
$getPreset->setAccessible(true);
$preset = $getPreset->invoke($renderer, 'preset-a');
nafb_assert(is_array($preset) && !empty($preset['enabled']), 'Expected preset config to be retrievable');

echo "Unit tests passed\n";
