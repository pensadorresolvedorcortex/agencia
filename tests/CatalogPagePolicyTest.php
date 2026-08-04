<?php
require_once __DIR__.'/../src/Search/CatalogPagePolicy.php';
$policy=new PGW\Search\CatalogPagePolicy();
$cases=[
    [['[pgw_mostrar_grupos]'],true],
    [['texto','{"shortcode":"[PGW_MOSTRAR_GRUPOS limit=30]"}'],true],
    [['[pgw_busca]',''],false],
    [[null,123,[]],false],
];
foreach($cases as [$sources,$expected])if($policy->containsCatalog(...$sources)!==$expected)throw new RuntimeException('Catalog page detection failed');
echo "4 catalog page policy cases passed.\n";
