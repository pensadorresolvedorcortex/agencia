<?php

namespace NaFlorestaBuy\Infrastructure\Woo;

use NaFlorestaBuy\Presentation\Shared\OrderItemPresenter;

class AdminOrderHooks
{
    public function register(): void
    {
        add_action('woocommerce_after_order_itemmeta', [$this, 'renderMeta'], 10, 3);
    }

    public function renderMeta($itemId, $item): void
    {
        if (!$item instanceof \WC_Order_Item_Product) {
            return;
        }

        $presenter = new OrderItemPresenter();
        echo $presenter->renderAdminHtml($item);
    }
}
