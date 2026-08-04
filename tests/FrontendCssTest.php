<?php
$css=(string)file_get_contents(__DIR__.'/../assets/dist/frontend.css');
$required=['font-family:"Maven Pro"','.pgw-group-card--catalog{min-height:350px}', 'width:100%!important;max-width:none!important;height:100%!important', '.pgw-card__header{position:absolute', '.pgw-card__backplate,.pgw-card__media:before{display:none}'];
foreach($required as $rule)if(strpos($css,$rule)===false)throw new RuntimeException('Missing compact card rule: '.$rule);
echo "5 full-bleed card CSS checks passed.\n";
