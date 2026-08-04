<?php
/** Immutable identity for queries owned by the public group catalog. @package PGW */
declare(strict_types=1);
namespace PGW\Search;
final class CatalogQueryContract {
    public const FLAG='pgw_catalog_query';
    public function base(string $postType): array {
        return [self::FLAG=>1,'post_type'=>$postType,'post_status'=>'publish','ignore_sticky_posts'=>true,'suppress_filters'=>true];
    }
    public function owns(mixed $flag): bool {
        return $flag===1||$flag==='1'||$flag===true;
    }
}
