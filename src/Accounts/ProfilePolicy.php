<?php
namespace PGW\Accounts;

final class ProfilePolicy {
    public function normalize(array $input): array {
        $firstName = trim(strip_tags((string) ($input['first_name'] ?? '')));
        $lastName = trim(strip_tags((string) ($input['last_name'] ?? '')));
        $displayName = trim(strip_tags((string) ($input['display_name'] ?? '')));
        $bio = trim(strip_tags((string) ($input['bio'] ?? '')));
        $rawPhone = trim((string) ($input['phone'] ?? ''));
        $phone = (new RegistrationPolicy())->normalizePhone($rawPhone);
        $valid = $firstName !== '' && $displayName !== '' && mb_strlen($firstName) <= 100
            && mb_strlen($lastName) <= 100 && mb_strlen($displayName) <= 100 && mb_strlen($bio) <= 300
            && ($rawPhone === '' || $phone !== '');
        return compact('firstName', 'lastName', 'displayName', 'bio', 'phone', 'valid');
    }
}
