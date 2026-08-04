<?php
require_once __DIR__.'/../src/Auth/ProviderPolicy.php';

$policy = new PGW\Auth\ProviderPolicy();
$assertions = 0;
$expect = static function (bool $condition) use (&$assertions): void { $assertions++; if (!$condition) throw new RuntimeException('ProviderPolicy test failed'); };
$expect($policy->normalize(['google', 'email', 'google', 'invalid']) === ['email', 'google']);
$expect($policy->linked(['email', 'google'], 'google'));
$expect($policy->canUnlink(['email', 'google'], 'google'));
$expect(!$policy->canUnlink(['google'], 'google'));
$expect($policy->without(['email', 'google'], 'google') === ['email']);
echo "ProviderPolicy: {$assertions} assertions\n";
