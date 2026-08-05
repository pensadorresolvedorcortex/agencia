<?php
namespace PGW\Auth;

final class PostOtpRedirectPolicy {
    /** @var list<string> */
    private array $authSlugs;

    /** @param list<string> $authSlugs */
    public function __construct(array $authSlugs = ['entrar', 'criar-conta', 'confirmar-codigo']) {
        $this->authSlugs = array_values(array_filter(array_map(static fn($slug) => strtolower(trim($slug)), $authSlugs)));
    }

    public function isAuthDestination(string $url): bool {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $slug = strtolower(basename($path));
        $query = (string) parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);
        return ($slug !== '' && in_array($slug, $this->authSlugs, true)) || array_key_exists('pgw_flow', $params);
    }

    public function allows(string $url): bool {
        return $url !== '' && !$this->isAuthDestination($url);
    }
}
