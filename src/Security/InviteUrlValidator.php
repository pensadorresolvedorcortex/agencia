<?php
/**
 * Validates and canonicalizes public WhatsApp invitation URLs without network I/O.
 *
 * @package PGW
 */
declare(strict_types=1);

namespace PGW\Security;

final class InviteUrlValidator
{
    private const HOSTS = ['chat.whatsapp.com', 'whatsapp.com', 'www.whatsapp.com'];

    public function normalize(string $candidate): string
    {
        $candidate = trim(html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($candidate === '' || preg_match('/[\x00-\x20\x7f]/', $candidate)) {
            return '';
        }

        $parts = parse_url($candidate);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return '';
        }

        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (!in_array($host, self::HOSTS, true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return '';
        }

        $path = '/' . ltrim((string) ($parts['path'] ?? ''), '/');
        $validPath = $host === 'chat.whatsapp.com'
            ? preg_match('~^/[A-Za-z0-9_-]{10,64}/?$~D', $path)
            : preg_match('~^/(?:channel|community)/[A-Za-z0-9_-]{6,128}/?$~D', $path);

        if ($validPath !== 1) {
            return '';
        }

        return 'https://' . $host . rtrim($path, '/');
    }
}
