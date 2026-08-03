<?php
require_once __DIR__.'/../src/Support/Diagnostics.php';

$diagnostics=new PGW\Support\Diagnostics();
$healthy=$diagnostics->evaluate(['php'=>'8.1.0','wordpress'=>'6.4','https'=>1,'cleanup_cron'=>1,'link_cron'=>1,'tables'=>1,'image_editor'=>1]);
if(in_array(false,$healthy,true))throw new RuntimeException('Healthy environment failed');
$broken=$diagnostics->evaluate(['php'=>'8.0','wordpress'=>'6.3','https'=>0,'cleanup_cron'=>1,'link_cron'=>0,'tables'=>0,'image_editor'=>0]);
if(array_filter($broken)!==[])throw new RuntimeException('Broken environment accepted');
echo "12 diagnostic checks passed.\n";
