<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Moderation/ModerationPolicy.php';
use PGW\Moderation\ModerationPolicy;
$p=new ModerationPolicy();
$checks=[
 $p->transition('approved')['post_status']==='publish',
 $p->transition('rejected','Duplicado')['valid'],
 !$p->transition('rejected')['valid'],
 !$p->transition('correction_requested')['valid'],
 $p->transition('inactive')['post_status']==='draft',
 !$p->transition('invalid')['valid'],
];
if(in_array(false,$checks,true)){fwrite(STDERR,"Moderation policy case failed.\n");exit(1);}echo count($checks)." moderation policy cases passed.\n";
