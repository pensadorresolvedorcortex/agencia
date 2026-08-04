<?php
namespace PGW\Auth;

final class ProviderPolicy {
    private const ALLOWED = ['email', 'google'];

    public function normalize(mixed $providers): array {
        $providers = is_array($providers) ? $providers : [];
        $providers = array_values(array_unique(array_intersect(self::ALLOWED, array_map('strval', $providers))));
        sort($providers);
        return $providers;
    }

    public function linked(mixed $providers, string $provider): bool {
        return in_array($provider, $this->normalize($providers), true);
    }

    public function without(mixed $providers, string $provider): array {
        return array_values(array_diff($this->normalize($providers), [$provider]));
    }

    public function canUnlink(mixed $providers, string $provider): bool {
        return $this->linked($providers, $provider) && count($this->without($providers, $provider)) > 0;
    }
}
