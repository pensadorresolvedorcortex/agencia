<?php

namespace NaFlorestaBuy\Infrastructure\Repository;

use NaFlorestaBuy\Infrastructure\Support\DataIntegrityGuard;

class PostMetaConfigRepository implements ConfigRepositoryInterface
{
    private const META_KEY = '_nafb_product_config';

    private DataIntegrityGuard $guard;

    public function __construct(?DataIntegrityGuard $guard = null)
    {
        $this->guard = $guard ?? new DataIntegrityGuard();
    }

    public function getProductConfig(int $productId): array
    {
        $raw = get_post_meta($productId, self::META_KEY, true);
        return $this->guard->normalizeProductConfig($raw);
    }

    public function saveProductConfig(int $productId, array $config): void
    {
        update_post_meta($productId, self::META_KEY, $this->guard->normalizeProductConfig($config));
    }
}
