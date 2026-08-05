<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Auth/GoogleClaims.php';
use PGW\Auth\GoogleClaims;
$p=new GoogleClaims();$base=['iss'=>'https://accounts.google.com','aud'=>'client','exp'=>1100,'sub'=>'1234567890','email'=>'user@example.com','email_verified'=>'true','nonce'=>'nonce'];$checks=[$p->validate($base,'client','nonce',1000),!$p->validate($base+['aud'=>'bad'],'other','nonce',1000),!$p->validate(array_merge($base,['exp'=>900]),'client','nonce',1000),!$p->validate(array_merge($base,['nonce'=>'bad']),'client','nonce',1000),!$p->validate(array_merge($base,['email_verified'=>'false']),'client','nonce',1000)];
if(in_array(false,$checks,true)){fwrite(STDERR,"Google claims case failed.\n");exit(1);}echo count($checks)." google claim cases passed.\n";
