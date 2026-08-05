<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Groups/LinkCheckPolicy.php';
use PGW\Groups\LinkCheckPolicy;
$p=new LinkCheckPolicy();
$checks=[
 $p->evaluate(200,2)===['status'=>'active','failures'=>0,'confirmed'=>true],
 $p->evaluate(403,1)===['status'=>'possibly_active','failures'=>1,'confirmed'=>false],
 $p->evaluate(404,0)['status']==='unavailable',
 $p->evaluate(404,2)===['status'=>'expired','failures'=>3,'confirmed'=>false],
 $p->evaluate(503,0)['status']==='temporary_error',
 $p->evaluate(null,1,true)===['status'=>'temporary_error','failures'=>2,'confirmed'=>false],
];
if(in_array(false,$checks,true)){fwrite(STDERR,"Link policy case failed.\n");exit(1);}echo count($checks)." link policy cases passed.\n";
