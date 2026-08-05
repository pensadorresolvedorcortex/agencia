<?php
/** Signed form-age and honeypot verification. @package PGW */
declare(strict_types=1);

namespace PGW\Security;

final class FormProof
{
    public function __construct(private readonly string $secret)
    {
        if ($secret === '') throw new \InvalidArgumentException('A non-empty secret is required.');
    }

    /** @return array{started:int,identifier:string,proof:string,honeypot:string} */
    public function issue(string $purpose, ?int $now = null, ?string $identifier = null): array
    {
        $started = $now ?? time();
        $identifier = $identifier ?? bin2hex(random_bytes(16));
        if (preg_match('/^[a-f0-9]{32}$/D', $identifier) !== 1) throw new \InvalidArgumentException('Invalid proof identifier.');
        return [
            'started' => $started,
            'identifier' => $identifier,
            'proof' => hash_hmac('sha256', $purpose . '|' . $started . '|' . $identifier, $this->secret),
            'honeypot' => 'pgw_' . substr(hash_hmac('sha256', 'hp|' . $purpose, $this->secret), 0, 12),
        ];
    }

    public function verify(string $purpose, mixed $started, mixed $identifier, mixed $proof, mixed $honeypotValue, int $minimumAge, int $maximumAge = 7200, ?int $now = null): bool
    {
        if (!is_scalar($started) || !is_scalar($identifier) || !is_scalar($proof) || !is_scalar($honeypotValue) || trim((string) $honeypotValue) !== '') return false;
        $timestamp = filter_var($started, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($timestamp === false) return false;
        $age = ($now ?? time()) - $timestamp;
        if ($age < max(0, $minimumAge) || $age > max($minimumAge, $maximumAge)) return false;
        $identifier = (string) $identifier;
        if (preg_match('/^[a-f0-9]{32}$/D', $identifier) !== 1) return false;
        $expected = hash_hmac('sha256', $purpose . '|' . $timestamp . '|' . $identifier, $this->secret);
        return preg_match('/^[a-f0-9]{64}$/D', (string) $proof) === 1 && hash_equals($expected, (string) $proof);
    }
}
