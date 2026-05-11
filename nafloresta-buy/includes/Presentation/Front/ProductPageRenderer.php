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
        add_action('woocommerce_after_variations_form', [$this, 'renderNativeLayer']);
    }

    public function renderNativeLayer(): void
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
        $variations = [];

        foreach ($variationIds as $id) {
            $variation = wc_get_product($id);
            if (!$variation) {
                continue;
            }

            $variations[$id] = [
                'id' => $id,
                'label' => $variation->get_name(),
                'in_stock' => $variation->is_in_stock(),
            ];
        }

        $nativeConfig = [
            'product_id' => (int) $product->get_id(),
            'mode' => $config['builder_mode'] ?? 'matrix',
            'variations' => $variations,
        ];

        include NAFB_PLUGIN_PATH . 'templates/front/native-personalization.php';
    }
}
