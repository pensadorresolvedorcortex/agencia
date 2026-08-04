<?php
/** Image upload limits independent from request transport. @package PGW */
declare(strict_types=1);
namespace PGW\Images;
final class UploadPolicy
{
    public const MIMES=['image/jpeg','image/png','image/webp'];
    public function accepts(string $mime,int $bytes,int $maximumBytes=5242880): bool
    {
        return in_array(strtolower($mime),self::MIMES,true)&&$bytes>0&&$bytes<=max(1,$maximumBytes);
    }
    public function focal(mixed $value): float
    {
        return max(0.0,min(100.0,is_numeric($value)?(float)$value:50.0));
    }
}
