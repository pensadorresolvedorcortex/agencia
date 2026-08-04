<?php
namespace PGW\Accounts;

final class PasswordResetLink {
    public function build(string $baseUrl, string $key, string $login): string {
        $baseUrl = trim($baseUrl); $key = trim($key); $login = trim($login);
        if ($baseUrl === '' || $key === '' || $login === '' || preg_match('/[\x00-\x1F\x7F]/', $key.$login)) return '';
        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl.$separator.http_build_query(['key'=>$key, 'login'=>$login], '', '&', PHP_QUERY_RFC3986);
    }
}
