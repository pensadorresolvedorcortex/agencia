<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Groups/SubmissionPolicy.php';
use PGW\Groups\SubmissionPolicy;
$p=new SubmissionPolicy();$v=$p->normalize(' <b>Grupo</b> ',' descrição ',' regras ');$checks=[$v['valid'],$v['title']==='Grupo',$v['description']==='descrição',!$p->normalize('','texto')['valid'],strlen($p->normalize(str_repeat('a',120),'texto')['title'])===100,$p->inviteHash('https://chat.whatsapp.com/AbCdEf012345','secret')===hash_hmac('sha256','https://chat.whatsapp.com/AbCdEf012345','secret')];
if(in_array(false,$checks,true)){fwrite(STDERR,"Submission policy case failed.\n");exit(1);}echo count($checks)." submission policy cases passed.\n";
