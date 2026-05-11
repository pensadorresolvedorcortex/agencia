<?php

namespace NaFlorestaBuy\Infrastructure\Woo;

use NaFlorestaBuy\Application\OrderMetaPersisterService;

class CheckoutHooks
{
    public function register(): void
    {
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'saveSnapshot'], 10, 4);
    }

    public function saveSnapshot($item, $cartItemKey, $values): void
    {
        $persister = new OrderMetaPersisterService();
        $persister->persist($item, $values);
    }
}
