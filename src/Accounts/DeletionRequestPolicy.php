<?php
namespace PGW\Accounts;

final class DeletionRequestPolicy {
    public function allows(string $confirmation, array $providers, bool $passwordVerified): bool {
        $providers = array_values(array_unique(array_intersect(['email', 'google'], array_map('strval', $providers))));
        $googleOnly = $providers === ['google'];
        return hash_equals('EXCLUIR', trim($confirmation)) && ($googleOnly || $passwordVerified);
    }
}
