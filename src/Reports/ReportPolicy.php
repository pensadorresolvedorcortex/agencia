<?php
/** Validation policy for public group reports. @package PGW */
declare(strict_types=1);
namespace PGW\Reports;
final class ReportPolicy
{
    public const STATUSES=['open','in_review','resolved','improper'];
    public const REASONS=['expired_link','missing_group','inappropriate','scam','fraud','spam','duplicate','improper_image','personal_data','other'];
    public function normalizeStatus(string $status): string
    {
        return in_array($status,self::STATUSES,true)?$status:'open';
    }
    public function normalizeReason(string $reason): string
    {
        $reason=strtolower(trim($reason));
        return in_array($reason,self::REASONS,true)?$reason:'other';
    }
    public function normalizeDetails(string $details,int $limit=1000): string
    {
        $details=trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$details)??'');
        return function_exists('mb_substr')?mb_substr($details,0,$limit,'UTF-8'):substr($details,0,$limit);
    }
}
