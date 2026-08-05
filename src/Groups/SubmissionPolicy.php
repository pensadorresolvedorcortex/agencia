<?php
/** Text validation and canonical duplicate hash for group submissions. @package PGW */
declare(strict_types=1);
namespace PGW\Groups;
final class SubmissionPolicy
{
    /** @return array{valid:bool,title:string,description:string,rules:string} */
    public function normalize(string $title,string $description,string $rules=''): array
    {
        $cut=static function(string $value,int $limit):string{$value=trim(strip_tags($value));return function_exists('mb_substr')?mb_substr($value,0,$limit,'UTF-8'):substr($value,0,$limit);};
        $title=$cut($title,100);$description=$cut($description,1000);$rules=$cut($rules,1000);
        return ['valid'=>$title!==''&&$description!=='','title'=>$title,'description'=>$description,'rules'=>$rules];
    }
    public function inviteHash(string $normalizedUrl,string $secret): string
    {
        if($normalizedUrl===''||$secret==='')throw new \InvalidArgumentException('URL and secret are required.');return hash_hmac('sha256',$normalizedUrl,$secret);
    }
}
