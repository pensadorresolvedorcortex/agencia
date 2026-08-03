<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Auth/FlowToken.php';
use PGW\Auth\FlowToken;
$flows = new FlowToken('test-secret');
$token = $flows->issue(42, 'login', 600, 1000);
$checks = [
    $flows->verify($token, ['login'], 1001) === ['user_id' => 42, 'purpose' => 'login'],
    $flows->verify($token, ['registration'], 1001) === null,
    $flows->verify($token, ['login'], 1601) === null,
    $flows->verify($token . 'x', ['login'], 1001) === null,
    $flows->verify('invalid', ['login'], 1001) === null,
];
if (in_array(false, $checks, true)) { fwrite(STDERR, "Flow token case failed.\n"); exit(1); }
echo count($checks) . " flow token cases passed.\n";
