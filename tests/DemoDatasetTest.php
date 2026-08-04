<?php
declare(strict_types=1);
$items=require __DIR__.'/../data/demo-groups.php';
if(count($items)!==40)throw new RuntimeException('Demo dataset must contain exactly 40 cards.');
$titles=[];foreach($items as $item){if(empty($item['title'])||empty($item['category'])||empty($item['description']))throw new RuntimeException('Incomplete demo record.');$titles[]=$item['title'];}
if(count(array_unique($titles))!==40)throw new RuntimeException('Demo titles must be unique.');
echo "42 demo dataset checks passed.\n";
