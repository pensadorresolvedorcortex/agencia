<?php
/** Google OpenID Connect claim validation after signature validation by Google tokeninfo. @package PGW */
declare(strict_types=1);
namespace PGW\Auth;
final class GoogleClaims
{
    /** @param array<string,mixed> $claims */
    public function validate(array $claims,string $audience,string $nonce,?int $now=null): bool
    {
        $now??=time();$issuer=(string)($claims['iss']??'');$aud=(string)($claims['aud']??'');$exp=filter_var($claims['exp']??null,FILTER_VALIDATE_INT);$verified=$claims['email_verified']??false;
        return in_array($issuer,['https://accounts.google.com','accounts.google.com'],true)&&$audience!==''&&hash_equals($audience,$aud)&&$exp!==false&&$exp>=$now-30&&$exp<=$now+86400&&is_string($claims['sub']??null)&&preg_match('/^[0-9]{6,64}$/D',(string)$claims['sub'])===1&&is_string($claims['email']??null)&&filter_var($claims['email'],FILTER_VALIDATE_EMAIL)!==false&&in_array($verified,[true,'true',1,'1'],true)&&$nonce!==''&&hash_equals($nonce,(string)($claims['nonce']??''));
    }
}
