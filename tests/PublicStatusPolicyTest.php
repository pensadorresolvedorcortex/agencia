<?php
require_once __DIR__.'/../src/Search/PublicStatusPolicy.php';
$query=(new PGW\Search\PublicStatusPolicy())->metaQuery();
if(($query['relation']??'')!=='OR'||count($query)!==3)throw new RuntimeException('Public status relation failed');
if(($query[0]['value']??'')!=='approved')throw new RuntimeException('Approved status missing');
if(($query[1]['compare']??'')!=='NOT EXISTS')throw new RuntimeException('Legacy published fallback missing');
echo "3 public status policy cases passed.\n";
