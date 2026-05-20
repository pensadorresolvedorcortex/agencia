<?php

namespace NaFlorestaBuy\Infrastructure\Woo;

class ProductHooks
{
    public function register(): void
    {
        add_filter('woocommerce_is_purchasable', [$this, 'keepPurchasable'], 10, 2);
    }

    public function keepPurchasable(bool $purchasable, $product): bool
    {
        return $purchasable;
    }
}
