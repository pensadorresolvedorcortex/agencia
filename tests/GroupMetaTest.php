<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Support/GroupMeta.php';
use PGW\Support\GroupMeta;
$map=GroupMeta::legacyMap();$checks=[GroupMeta::key('status')==='_pgw_status',$map['pgw_invite_url']==='_pgw_invite_url',count($map)===count(GroupMeta::NAMES),count(array_unique($map))===count($map)];try{GroupMeta::key('invalid');$checks[]=false;}catch(InvalidArgumentException){$checks[]=true;}
if(in_array(false,$checks,true)){fwrite(STDERR,"Group meta case failed.\n");exit(1);}echo count($checks)." group meta cases passed.\n";
