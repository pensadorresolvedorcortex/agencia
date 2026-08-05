<?php
/** Detects pages whose catalog output must remain dynamic. @package PGW */
declare(strict_types=1);
namespace PGW\Search;
final class CatalogPagePolicy {
    public function containsCatalog(mixed ...$sources): bool {
        foreach($sources as $source)if(is_string($source)&&stripos($source,'pgw_mostrar_grupos')!==false)return true;
        return false;
    }
}
