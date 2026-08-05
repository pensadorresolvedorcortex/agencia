<?php
/** Normalizes shareable public catalog filters. @package PGW */
declare(strict_types=1);
namespace PGW\Search;
final class CatalogFilters
{
    public const ORDERS=['featured','recent','updated','accessed','az'];
    /** @param array<string,mixed> $input @return array{search:string,category:string,type:string,location:string,featured:bool,link:string,order:string} */
    public function normalize(array $input): array
    {
        $clean=static fn(mixed $v):string=>preg_replace('/[^a-z0-9_-]/','',strtolower(trim(is_scalar($v)?(string)$v:'')))??'';
        $order=$clean($input['pgw_order']??'featured');if(!in_array($order,self::ORDERS,true))$order='featured';
        $link=$clean($input['pgw_link']??'');if(!in_array($link,['active','possibly_active','unavailable','expired','temporary_error','unconfirmed'],true))$link='';
        $search=trim(strip_tags(is_scalar($input['pgw_q']??'')?(string)($input['pgw_q']??''):''));
        return ['search'=>function_exists('mb_substr')?mb_substr($search,0,100):substr($search,0,100),'category'=>$clean($input['pgw_category']??''),'type'=>$clean($input['pgw_type']??''),'location'=>$clean($input['pgw_location']??''),'featured'=>in_array($input['pgw_featured']??null,[1,'1',true],true),'link'=>$link,'order'=>$order];
    }
}
