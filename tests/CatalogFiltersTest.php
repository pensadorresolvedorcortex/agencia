<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Search/CatalogFilters.php';
use PGW\Search\CatalogFilters;
$p=new CatalogFilters();$v=$p->normalize(['pgw_q'=>' <b>PHP</b> ','pgw_category'=>'Tecnologia!','pgw_featured'=>'1','pgw_link'=>'active','pgw_order'=>'az']);$checks=[$v['search']==='PHP',$v['category']==='tecnologia',$v['featured'],$v['link']==='active',$v['order']==='az',$p->normalize(['pgw_order'=>'bad'])['order']==='featured',$p->normalize(['pgw_link'=>'bad'])['link']===''];
if(in_array(false,$checks,true)){fwrite(STDERR,"Catalog filter case failed.\n");exit(1);}echo count($checks)." catalog filter cases passed.\n";
