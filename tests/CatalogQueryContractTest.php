<?php
require_once __DIR__.'/../src/Search/CatalogQueryContract.php';
$contract=new PGW\Search\CatalogQueryContract();$args=$contract->base('pgw_group');
if(($args['post_type']??'')!=='pgw_group'||($args['post_status']??'')!=='publish'||empty($args['ignore_sticky_posts'])||empty($args['suppress_filters']))throw new RuntimeException('Catalog base contract failed');
foreach([1,'1',true] as $flag)if(!$contract->owns($flag))throw new RuntimeException('Owned flag rejected');
foreach([0,'',false,null] as $flag)if($contract->owns($flag))throw new RuntimeException('Foreign query accepted');
echo "8 catalog query contract cases passed.\n";
