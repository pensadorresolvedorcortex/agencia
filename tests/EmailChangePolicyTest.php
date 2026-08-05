<?php
require_once __DIR__.'/../src/Accounts/EmailChangePolicy.php';

$policy = new PGW\Accounts\EmailChangePolicy();
if (!$policy->request(' NOVO@example.test ', 'old@example.test', null, 5)['valid']) throw new RuntimeException('Valid email rejected');
if ($policy->request('old@example.test', 'old@example.test', null, 5)['valid']) throw new RuntimeException('Same email accepted');
if ($policy->request('novo@example.test', 'old@example.test', 8, 5)['valid']) throw new RuntimeException('Collision accepted');
if (!$policy->request('novo@example.test', 'old@example.test', 5, 5)['valid']) throw new RuntimeException('Own email collision rejected');
if (!$policy->fresh(1000, 1599) || $policy->fresh(1000, 1601) || $policy->fresh(1700, 1600)) throw new RuntimeException('Freshness failed');
echo "5 email change policy cases passed.\n";
