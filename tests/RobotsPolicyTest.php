<?php
require_once __DIR__.'/../src/SEO/RobotsPolicy.php';

$policy = new PGW\SEO\RobotsPolicy();
foreach (['entrar','/minha-conta/','confirmar-codigo','enviar-grupo'] as $path) if(!$policy->noindex($path))throw new RuntimeException('Private path indexable');
if(!$policy->noindex('grupo/demo','approved',true)||!$policy->noindex('ir/test',null,false,true))throw new RuntimeException('Special route indexable');
if(!$policy->noindex('grupo/pendente','pending')||$policy->noindex('grupo/publico','approved'))throw new RuntimeException('Group status policy failed');
echo "8 robots policy cases passed.\n";
