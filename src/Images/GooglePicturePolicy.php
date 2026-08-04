<?php
namespace PGW\Images;

final class GooglePicturePolicy {
    private const HOSTS = ['lh3.googleusercontent.com', 'lh4.googleusercontent.com', 'lh5.googleusercontent.com', 'lh6.googleusercontent.com'];

    public function normalize(string $url): string {
        $url = trim($url);
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') return '';
        if (!in_array(strtolower((string) ($parts['host'] ?? '')), self::HOSTS, true)) return '';
        if (isset($parts['user']) || isset($parts['pass']) || empty($parts['path']) || str_contains((string) $parts['path'], '..')) return '';
        return $url;
    }
}
