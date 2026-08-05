<?php
/** Privacy-preserving session aggregation. @package PGW */
declare(strict_types=1);
namespace PGW\Accounts;
final class SessionPolicy
{
    /** @param array<int|string,array<string,mixed>> $sessions @return array{active:int,expired:int,next_expiration:int|null} */
    public function summarize(array $sessions, ?int $now = null): array
    {
        $now ??= time(); $active=0; $expired=0; $next=null;
        foreach($sessions as $session){$expiration=filter_var($session['expiration']??null,FILTER_VALIDATE_INT);if($expiration===false||$expiration<=$now){$expired++;continue;}$active++;$next=$next===null?abs($expiration):min($next,abs($expiration));}
        return ['active'=>$active,'expired'=>$expired,'next_expiration'=>$next];
    }
}
