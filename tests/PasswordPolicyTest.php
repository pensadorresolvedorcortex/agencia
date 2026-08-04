<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Auth/PasswordPolicy.php';
use PGW\Auth\PasswordPolicy;
$policy=new PasswordPolicy();
$checks=[
 $policy->accepts('senha-forte-123','senha-forte-123'),
 !$policy->accepts('curta123','curta123'),
 !$policy->accepts('senha-forte-123','senha-forte-124'),
 $policy->accepts('áéíóú-12345','áéíóú-12345'),
];
if(in_array(false,$checks,true)){fwrite(STDERR,"Password policy case failed.\n");exit(1);}echo count($checks)." password policy cases passed.\n";
