<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Accounts/SessionPolicy.php';
use PGW\Accounts\SessionPolicy;
$policy=new SessionPolicy();
$summary=$policy->summarize([['expiration'=>900],['expiration'=>1100],['expiration'=>1200],['expiration'=>'bad']],1000);
$checks=[$summary===['active'=>2,'expired'=>2,'next_expiration'=>1100],$policy->summarize([],1000)===['active'=>0,'expired'=>0,'next_expiration'=>null]];
if(in_array(false,$checks,true)){fwrite(STDERR,"Session policy case failed.\n");exit(1);}echo count($checks)." session policy cases passed.\n";
