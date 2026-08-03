<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Images/UploadPolicy.php';
use PGW\Images\UploadPolicy;
$p=new UploadPolicy();$checks=[$p->accepts('image/jpeg',100),$p->accepts('image/webp',5242880),!$p->accepts('image/svg+xml',100),!$p->accepts('image/png',0),!$p->accepts('image/png',5242881),$p->focal(-2)===0.0,$p->focal(150)===100.0,$p->focal('bad')===50.0];
if(in_array(false,$checks,true)){fwrite(STDERR,"Upload policy case failed.\n");exit(1);}echo count($checks)." upload policy cases passed.\n";
