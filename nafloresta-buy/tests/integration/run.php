<?php

require __DIR__ . '/../bootstrap.php';

use NaFlorestaBuy\Application\BatchAddToCartService;
use NaFlorestaBuy\Application\OrderMetaPersisterService;
use NaFlorestaBuy\Application\ValidateSelectionService;
use NaFlorestaBuy\Infrastructure\Repository\PostMetaConfigRepository;

$repo = new PostMetaConfigRepository();
$repo->saveProductConfig(300, ['enabled' => true, 'variation_ids' => [301]]);
$GLOBALS['nafb_products'][301] = new NAFB_Test_Product(301, 300, 'Turma B');

$service = new BatchAddToCartService(new ValidateSelectionService($repo));
$payload = [
    'product_id' => 300,
    'mode' => 'matrix',
    'items' => [[
        'variation_id' => 301,
        'quantity' => 1,
        'unique_key' => 'line-1',
        'fields' => ['student_names' => [['name' => 'Joao']]],
    ]],
];

$result = $service->execute($payload);
nafb_assert($result['success'] === true, 'Batch add-to-cart should succeed');
nafb_assert(count(WC()->cart->added) === 1, 'Cart should contain one added line');

// duplicate protection by unique_key
$second = $service->execute($payload);
nafb_assert($second['success'] === true, 'Second execution should still return success');
nafb_assert(count(WC()->cart->added) === 1, 'Duplicate submission should be deduplicated');

// order item meta persistence
$orderItem = new WC_Order_Item_Product();
$line = current(WC()->cart->added);
$persister = new OrderMetaPersisterService();
$persister->persist($orderItem, $line['data']);
nafb_assert(isset($orderItem->meta['_nafb']), 'Order item should store _nafb');
nafb_assert(isset($orderItem->meta['_nafb_snapshot']), 'Order item should store _nafb_snapshot');

echo "Integration tests passed\n";
