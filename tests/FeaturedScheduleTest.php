<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Featured/FeaturedSchedule.php';
use PGW\Featured\FeaturedSchedule;
$p=new FeaturedSchedule();
$checks=[$p->priority(0)===1,$p->priority(2000)===999,$p->active(true,900,1100,1000),!$p->active(true,1100,null,1000),!$p->active(true,null,900,1000),!$p->window('2026-08-03 12:00','2026-08-03 11:00')['valid'],$p->window(null,null)['valid']];
if(in_array(false,$checks,true)){fwrite(STDERR,"Featured schedule case failed.\n");exit(1);}echo count($checks)." featured schedule cases passed.\n";
