<?php

namespace NaFlorestaBuy\Presentation\Front;

use NaFlorestaBuy\Infrastructure\Repository\ConfigRepositoryInterface;

class ProductPageRenderer
{
    private ConfigRepositoryInterface $repository;

    public function __construct(ConfigRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function register(): void
    {
        add_action('woocommerce_before_add_to_cart_form', [$this, 'renderRoot']);
        add_action('woocommerce_before_add_to_cart_form', [$this, 'hideNativeFormWhenEnabled'], 1);
    }

    public function hideNativeFormWhenEnabled(): void
    {
        global $product;
        if (!$product) {
            return;
        }

        $config = $this->repository->getProductConfig((int) $product->get_id());
        if (empty($config['enabled'])) {
            return;
        }

        echo '<style>.single-product form.variations_form{display:none!important;}</style>';
    }

    public function renderRoot(): void
    {
        global $product;
        if (!$product || !$product->is_type('variable')) {
            return;
        }

        $config = $this->repository->getProductConfig((int) $product->get_id());
        if (empty($config['enabled'])) {
            return;
        }

        $variationIds = array_map('intval', $config['variation_ids'] ?? []);
        $variations = array_map(static function (int $id): array {
            $variation = wc_get_product($id);
            if (!$variation) {
                return [];
            }

            return [
                'id' => $id,
                'name' => $variation->get_name(),
                'price' => $variation->get_price_html(),
                'raw_price' => (float) $variation->get_price(),
                'max_qty' => $variation->managing_stock() && $variation->get_stock_quantity() !== null ? max(0, (int) $variation->get_stock_quantity()) : 999,
                'in_stock' => $variation->is_in_stock(),
            ];
        }, $variationIds);

        $builderConfig = [
            'product_id' => (int) $product->get_id(),
            'mode' => $config['builder_mode'] ?? 'matrix',
            'ui_mode' => $config['ui_mode'] ?? 'drawer',
            'items' => array_values(array_filter($variations)),
        ];

        include NAFB_PLUGIN_PATH . 'templates/front/builder-root.php';
    }
}
