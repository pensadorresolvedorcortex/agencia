<?php
namespace PGW\Privacy;

final class AccountExportPolicy {
    private const USER_KEYS = ['first_name', 'last_name', 'description', 'pgw_phone', 'pgw_email_verified', 'pgw_auth_providers', 'pgw_google_sub', 'pgw_google_email', 'pgw_registered_at', 'pgw_last_login_at', 'pgw_terms_accepted_at', 'pgw_privacy_accepted_at'];

    public function userMeta(array $meta): array {
        $result = [];
        foreach (self::USER_KEYS as $key) if (array_key_exists($key, $meta)) $result[$key] = $this->scalar($meta[$key]);
        return $result;
    }

    private function scalar(mixed $value): mixed {
        if (is_array($value) && count($value) === 1) $value = reset($value);
        if (is_array($value)) return array_values(array_filter(array_map(fn($item) => is_scalar($item) ? (string) $item : null, $value), fn($item) => $item !== null));
        return is_scalar($value) || $value === null ? $value : null;
    }
}
