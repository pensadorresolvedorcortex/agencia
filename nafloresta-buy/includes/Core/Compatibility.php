<?php

namespace NaFlorestaBuy\Core;

class Compatibility
{
    public function declareHposCompatibility(): void
    {
        add_action('before_woocommerce_init', static function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', NAFB_PLUGIN_FILE, true);
            }
        });
    }
}
