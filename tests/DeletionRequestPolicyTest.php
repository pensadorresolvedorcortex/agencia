<?php
require_once __DIR__.'/../src/Accounts/DeletionRequestPolicy.php';

$policy = new PGW\Accounts\DeletionRequestPolicy();
if (!$policy->allows('EXCLUIR',['email'],true)) throw new RuntimeException('Password account rejected');
if ($policy->allows('EXCLUIR',['email'],false)) throw new RuntimeException('Password bypass accepted');
if (!$policy->allows('EXCLUIR',['google'],false)) throw new RuntimeException('Google-only account rejected');
if ($policy->allows('excluir',['google'],false)) throw new RuntimeException('Confirmation mismatch accepted');
if ($policy->allows('EXCLUIR',['email','google'],false)) throw new RuntimeException('Multi-provider password bypass accepted');
echo "5 deletion request cases passed.\n";
