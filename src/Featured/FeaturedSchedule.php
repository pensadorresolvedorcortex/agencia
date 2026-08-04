<?php
/** Administrative highlight scheduling rules. @package PGW */
declare(strict_types=1);
namespace PGW\Featured;
final class FeaturedSchedule
{
    public function priority(mixed $value): int { return max(1,min(999,(int)$value)); }
    /** @return array{valid:bool,start:int|null,end:int|null} */
    public function window(?string $start,?string $end): array
    {
        $startAt=$start?strtotime($start):false;$endAt=$end?strtotime($end):false;
        $startAt=$startAt===false?null:$startAt;$endAt=$endAt===false?null:$endAt;
        return ['valid'=>!($startAt&&$endAt&&$endAt<=$startAt),'start'=>$startAt,'end'=>$endAt];
    }
    public function active(bool $enabled,?int $start,?int $end,?int $now=null): bool
    {
        $now??=time();return $enabled&&($start===null||$start<=$now)&&($end===null||$end>$now);
    }
}
