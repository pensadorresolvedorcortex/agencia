<?php
/** Builds the public status contract for published groups. @package PGW */
declare(strict_types=1);
namespace PGW\Search;
final class PublicStatusPolicy {
    public function metaQuery(): array {
        return ['relation'=>'OR',['key'=>'_pgw_status','value'=>'approved'],['key'=>'_pgw_status','compare'=>'NOT EXISTS']];
    }
}
