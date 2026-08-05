<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Search/PaginationWindow.php';

use PGW\Search\PaginationWindow;

$window = new PaginationWindow();
$cases = [
    [[30, 15], ['offset' => 30, 'amount' => 15, 'next_offset' => 45]],
    [['30', '500'], ['offset' => 30, 'amount' => 50, 'next_offset' => 80]],
    [[-10, 0], ['offset' => 0, 'amount' => 15, 'next_offset' => 15]],
    [['invalid', 'invalid'], ['offset' => 0, 'amount' => 15, 'next_offset' => 15]],
];
foreach ($cases as $index => [$input, $expected]) {
    $actual = $window->fromInput($input[0], $input[1]);
    if ($actual !== $expected) {
        fwrite(STDERR, sprintf("Pagination case %d failed: %s\n", $index + 1, json_encode($actual)));
        exit(1);
    }
}
$moreCases = [[30, 15, 60, true], [45, 15, 60, false], [30, 0, 60, false]];
foreach ($moreCases as [$offset, $returned, $total, $expected]) {
    if ($window->hasMore($offset, $returned, $total) !== $expected) exit(1);
}
echo count($cases) + count($moreCases) . " pagination cases passed.\n";
