<?php
/** Account deletion policy for owned content. @package PGW */
declare(strict_types=1);

namespace PGW\Accounts;

final class DeletionPolicy
{
    private const KNOWN_STATUSES = ['pending', 'approved', 'rejected', 'correction_requested', 'inactive', 'expired', 'draft', 'trash'];

    /** @return array{previous_status:string,status:string,post_status:string} */
    public function transition(string $currentStatus): array
    {
        $currentStatus = in_array($currentStatus, self::KNOWN_STATUSES, true) ? $currentStatus : 'draft';
        return [
            'previous_status' => $currentStatus,
            'status' => 'inactive',
            'post_status' => 'draft',
        ];
    }
}
