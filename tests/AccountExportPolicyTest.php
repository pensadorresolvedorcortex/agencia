<?php
require_once __DIR__.'/../src/Privacy/AccountExportPolicy.php';

$policy = new PGW\Privacy\AccountExportPolicy();
$result = $policy->userMeta(['first_name'=>['Maria'],'pgw_phone'=>['+5511999999999'],'pgw_auth_providers'=>['email','google'],'session_tokens'=>['secret'],'unknown'=>'private']);
if ($result['first_name'] !== 'Maria' || $result['pgw_phone'] !== '+5511999999999') throw new RuntimeException('Allowed scalar metadata missing');
if ($result['pgw_auth_providers'] !== ['email','google']) throw new RuntimeException('Provider list missing');
if (isset($result['session_tokens']) || isset($result['unknown'])) throw new RuntimeException('Sensitive metadata exported');
echo "4 account export policy cases passed.\n";
