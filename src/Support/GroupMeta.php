<?php
/** Canonical group metadata keys and legacy migration map. @package PGW */
declare(strict_types=1);
namespace PGW\Support;
final class GroupMeta
{
    public const NAMES=['invite_url','invite_hash','owner_id','status','featured','featured_priority','featured_start','featured_end','card_excerpt','rules','link_status','link_failure_count','last_link_check','next_link_check','click_count','impression_count','source','demo','rejection_reason','correction_request','focal_x','focal_y','original_image_id','square_image_id','hero_image_id','last_approved_at','previous_status','publication_notes'];
    public static function key(string $name): string { if(!in_array($name,self::NAMES,true))throw new \InvalidArgumentException('Unknown group meta.');return '_pgw_'.$name; }
    /** @return array<string,string> */
    public static function legacyMap(): array { $map=[];foreach(self::NAMES as $name)$map['pgw_'.$name]=self::key($name);return $map; }
}
