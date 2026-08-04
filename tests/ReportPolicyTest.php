<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Reports/ReportPolicy.php';
use PGW\Reports\ReportPolicy;
$policy=new ReportPolicy();
$checks=[
 $policy->normalizeReason('fraud')==='fraud',
 $policy->normalizeStatus('resolved')==='resolved',
 $policy->normalizeStatus('invalid')==='open',
 $policy->normalizeReason('arbitrary')==='other',
 $policy->normalizeReason(' SPAM ')==='spam',
 $policy->normalizeDetails(" texto\x00 seguro ")==='texto seguro',
 strlen($policy->normalizeDetails(str_repeat('a',1200)))===1000,
];
if(in_array(false,$checks,true)){fwrite(STDERR,"Report policy case failed.\n");exit(1);}echo count($checks)." report policy cases passed.\n";
