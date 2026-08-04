<?php
/** Conservative interpretation of remote invite checks. @package PGW */
declare(strict_types=1);
namespace PGW\Groups;
final class LinkCheckPolicy
{
    /** @return array{status:string,failures:int,confirmed:bool} */
    public function evaluate(?int $httpCode, int $previousFailures, bool $transportError = false): array
    {
        $previousFailures=max(0,$previousFailures);
        if($transportError||$httpCode===null||$httpCode===0)return ['status'=>'temporary_error','failures'=>$previousFailures+1,'confirmed'=>false];
        if($httpCode>=200&&$httpCode<400)return ['status'=>'active','failures'=>0,'confirmed'=>true];
        if(in_array($httpCode,[401,403,405,429],true))return ['status'=>'possibly_active','failures'=>$previousFailures,'confirmed'=>false];
        $failures=$previousFailures+1;
        if(in_array($httpCode,[404,410],true))return ['status'=>$failures>=3?'expired':'unavailable','failures'=>$failures,'confirmed'=>false];
        if($httpCode>=500)return ['status'=>'temporary_error','failures'=>$failures,'confirmed'=>false];
        return ['status'=>'unconfirmed','failures'=>$failures,'confirmed'=>false];
    }
}
