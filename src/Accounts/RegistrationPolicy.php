<?php
namespace PGW\Accounts;

final class RegistrationPolicy {
    public function normalize(array $input, int $minimumPasswordLength = 10): array {
        $firstName = trim(strip_tags((string) ($input['first_name'] ?? '')));
        $lastName = trim(strip_tags((string) ($input['last_name'] ?? '')));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['confirmation'] ?? '');
        $rawPhone = trim((string) ($input['phone'] ?? ''));
        $phone = $this->normalizePhone($rawPhone);
        $validEmail = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        if ($rawPhone !== '' && $phone === '') $phone = '';
        $valid = $firstName !== '' && mb_strlen($firstName) <= 100 && mb_strlen($lastName) <= 100
            && $validEmail && strlen($password) >= $minimumPasswordLength && hash_equals($password, $confirmation)
            && !empty($input['terms']) && !empty($input['privacy']);
        return compact('firstName', 'lastName', 'email', 'password', 'phone', 'valid');
    }

    public function normalizePhone(string $phone): string {
        if ($phone === '') return '';
        $international = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (!$international && in_array(strlen($digits), [10, 11], true)) $digits = '55'.$digits;
        return strlen($digits) >= 10 && strlen($digits) <= 15 ? '+'.$digits : '';
    }
}
