<?php
$css=(string)file_get_contents(__DIR__.'/../assets/dist/frontend.css');
$required=['font-family:"Maven Pro"','.pgw-group-card--catalog{min-height:342px}', 'width:124px;height:124px', '.pgw-group-card--showcase{min-height:382px'];
foreach($required as $rule)if(strpos($css,$rule)===false)throw new RuntimeException('Missing compact card rule: '.$rule);
echo "4 compact card CSS checks passed.\n";
