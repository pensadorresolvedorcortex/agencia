<?php

namespace NaFlorestaBuy\Infrastructure\Woo;

use NaFlorestaBuy\Infrastructure\Support\DataIntegrityGuard;
use NaFlorestaBuy\Presentation\Shared\CartItemPresenter;

class CartHooks
{
    private DataIntegrityGuard $guard;

    public function __construct(?DataIntegrityGuard $guard = null)
    {
        $this->guard = $guard ?? new DataIntegrityGuard();
    }

    public function register(): void
    {
        add_filter('woocommerce_get_item_data', [$this, 'renderItemData'], 10, 2);
        add_filter('woocommerce_cart_item_name', [$this, 'appendEditAction'], 20, 3);
    }

    public function renderItemData(array $itemData, array $cartItem): array
    {
        $presenter = new CartItemPresenter();
        $rows = $presenter->present($cartItem);

        if ($rows === []) {
            return $itemData;
        }

        return array_merge($itemData, $rows);
    }

    public function appendEditAction(string $productName, array $cartItem, string $cartItemKey): string
    {
        if (!is_cart()) {
            return $productName;
        }

        $nafb = $this->normalizeNafb($cartItem);
        if ($nafb === []) {
            return $productName;
        }

        $payload = base64_encode(wp_json_encode($nafb));
        $button = sprintf(
            '<button type="button" class="nafb-cart-edit vs-btn" data-cart-key="%1$s" data-nafb="%2$s">%3$s</button>',
            esc_attr($cartItemKey),
            esc_attr($payload),
            esc_html__('Editar personalização', 'nafloresta-buy')
        );

        return $productName . '<div class="nafb-cart-actions">' . $button . '</div>';
    }

    private function normalizeNafb(array $cartItem): array
    {
        if (!empty($cartItem['nafb']) && is_array($cartItem['nafb'])) {
            return $this->guard->normalizeNafb($cartItem['nafb'], $cartItem);
        }

        if (empty($cartItem['nafb_snapshot']) || !is_array($cartItem['nafb_snapshot'])) {
            return [];
        }

        $snapshot = $this->guard->normalizeSnapshot($cartItem['nafb_snapshot']);

        return $this->guard->normalizeNafb([
            'version' => 'legacy',
            'unique_key' => $cartItem['nafb_unique_key'] ?? '',
            'product_id' => $cartItem['product_id'] ?? 0,
            'variation_id' => $cartItem['variation_id'] ?? 0,
            'variation_label' => $snapshot['variation_label'],
            'quantity' => isset($cartItem['quantity']) ? (int) $cartItem['quantity'] : 0,
            'fields' => [
                'student_names' => array_map(static fn($name) => ['name' => (string) $name], $snapshot['student_names']),
            ],
        ], $cartItem);
    }
}
