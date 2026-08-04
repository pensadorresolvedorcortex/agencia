<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Accounts/DeletionPolicy.php';
use PGW\Accounts\DeletionPolicy;
$policy = new DeletionPolicy();
$checks = [
    $policy->transition('approved') === ['previous_status'=>'approved','status'=>'inactive','post_status'=>'draft'],
    $policy->transition('pending')['previous_status'] === 'pending',
    $policy->transition('unknown')['previous_status'] === 'draft',
    $policy->transition('inactive')['status'] === 'inactive',
];
if (in_array(false, $checks, true)) { fwrite(STDERR, "Deletion policy case failed.\n"); exit(1); }
echo count($checks) . " deletion policy cases passed.\n";
