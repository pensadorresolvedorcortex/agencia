<?php
namespace PGW\Accounts;

final class EmailChangePolicy {
    public function request(string $candidate, string $current, ?int $existingUserId, int $userId): array {
        $email = strtolower(trim($candidate));
        $valid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false && $email !== strtolower(trim($current))
            && ($existingUserId === null || $existingUserId === $userId);
        return ['email'=>$email, 'valid'=>$valid];
    }

    public function fresh(int $requestedAt, int $now, int $ttl = 600): bool {
        return $requestedAt > 0 && $requestedAt <= $now && ($now - $requestedAt) <= $ttl;
    }
}
