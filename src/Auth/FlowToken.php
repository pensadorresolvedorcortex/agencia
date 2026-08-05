<?php
/** Opaque signed context for pre-authentication browser flows. @package PGW */
declare(strict_types=1);

namespace PGW\Auth;

final class FlowToken
{
    public function __construct(private readonly string $secret)
    {
        if ($secret === '') throw new \InvalidArgumentException('A non-empty secret is required.');
    }

    public function issue(int $userId, string $purpose, int $ttl = 900, ?int $now = null): string
    {
        if ($userId < 1 || preg_match('/^[a-z_]{3,40}$/D', $purpose) !== 1) throw new \InvalidArgumentException('Invalid flow context.');
        $payload = json_encode(['u' => $userId, 'p' => $purpose, 'e' => ($now ?? time()) + max(60, min(1800, $ttl)), 'n' => bin2hex(random_bytes(12))], JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) throw new \RuntimeException('Could not encode flow context.');
        $encoded = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        return $encoded . '.' . hash_hmac('sha256', $encoded, $this->secret);
    }

    /** @return array{user_id:int,purpose:string}|null */
    public function verify(string $token, array $allowedPurposes, ?int $now = null): ?array
    {
        if (strlen($token) > 512 || substr_count($token, '.') !== 1) return null;
        [$encoded, $signature] = explode('.', $token, 2);
        if (preg_match('/^[A-Za-z0-9_-]+$/D', $encoded) !== 1 || preg_match('/^[a-f0-9]{64}$/D', $signature) !== 1) return null;
        if (!hash_equals(hash_hmac('sha256', $encoded, $this->secret), $signature)) return null;
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        $data = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($data) || !isset($data['u'], $data['p'], $data['e'], $data['n'])) return null;
        $userId = filter_var($data['u'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $purpose = is_string($data['p']) ? $data['p'] : '';
        $expires = filter_var($data['e'], FILTER_VALIDATE_INT);
        if ($userId === false || $expires === false || $expires < ($now ?? time()) || $expires > ($now ?? time()) + 1800 || !in_array($purpose, $allowedPurposes, true)) return null;
        return ['user_id' => $userId, 'purpose' => $purpose];
    }
}
