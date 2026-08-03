<?php
namespace PGW\SEO;

final class RobotsPolicy {
    private const PRIVATE_PATHS = ['entrar', 'criar-conta', 'confirmar-codigo', 'recuperar-senha', 'minha-conta', 'meus-grupos', 'enviar-grupo'];

    public function noindex(string $path, ?string $groupStatus = null, bool $demo = false, bool $redirect = false): bool {
        $path = trim(strtolower($path), '/');
        if ($redirect || $demo || in_array($path, self::PRIVATE_PATHS, true)) return true;
        return $groupStatus !== null && $groupStatus !== 'approved';
    }
}
