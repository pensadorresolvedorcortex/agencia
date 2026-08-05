<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Featured/FeaturedOrder.php';

use PGW\Featured\FeaturedOrder;

$order = new FeaturedOrder();
$cases = [
    [[101 => 1, 102 => 2, 103 => 3], [102, 101, 103]],
    [[9 => 3, 7 => 1], [7, 9]],
    [[5 => 0, 2 => 2, 4 => 8], [2, 5, 4]],
    [[0 => 1, -4 => 2, 3 => 1], [3]],
];
foreach ($cases as $index => [$input, $expected]) {
    $actual = $order->order($input);
    if ($actual !== $expected) {
        fwrite(STDERR, sprintf("Featured case %d failed: %s\n", $index + 1, json_encode($actual)));
        exit(1);
    }
}
echo count($cases) . " featured ordering cases passed.\n";
