<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Security/FormProof.php';
use PGW\Security\FormProof;

$guard = new FormProof('unit-test-secret');
$id = str_repeat('a', 32);
$issued = $guard->issue('register', 1000, $id);
$verify = static fn(string $purpose, mixed $started, mixed $identifier, mixed $proof, mixed $honeypot, int $minimum, int $now): bool =>
    $guard->verify($purpose, $started, $identifier, $proof, $honeypot, $minimum, 7200, $now);
$checks = [
    $verify('register', 1000, $id, $issued['proof'], '', 2, 1002) === true,
    $verify('register', 1000, $id, $issued['proof'], '', 2, 1001) === false,
    $verify('login', 1000, $id, $issued['proof'], '', 1, 1002) === false,
    $verify('register', 1000, str_repeat('b', 32), $issued['proof'], '', 2, 1002) === false,
    $verify('register', 1000, $id, str_repeat('0', 64), '', 2, 1002) === false,
    $verify('register', 1000, $id, $issued['proof'], 'spam', 2, 1002) === false,
    $verify('register', 1000, $id, $issued['proof'], '', 2, 9000) === false,
    $verify('register', 'bad', $id, $issued['proof'], '', 2, 1002) === false,
    str_starts_with($issued['honeypot'], 'pgw_'),
];
if (in_array(false, $checks, true)) { fwrite(STDERR, "Form proof case failed.\n"); exit(1); }
echo count($checks) . " form proof cases passed.\n";
