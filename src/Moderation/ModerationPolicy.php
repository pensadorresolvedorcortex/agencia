<?php
/** Group moderation transition policy. @package PGW */
declare(strict_types=1);
namespace PGW\Moderation;
final class ModerationPolicy
{
    public const STATUSES=['approved','rejected','correction_requested','inactive'];
    /** @return array{valid:bool,status:string,post_status:string,reason:string} */
    public function transition(string $status,string $reason=''): array
    {
        $status=strtolower(trim($status));$reason=trim($reason);
        $valid=in_array($status,self::STATUSES,true);
        if(in_array($status,['rejected','correction_requested'],true)&&$reason==='')$valid=false;
        return ['valid'=>$valid,'status'=>$valid?$status:'pending','post_status'=>$valid&&$status==='approved'?'publish':($status==='inactive'?'draft':'pending'),'reason'=>$reason];
    }
}
