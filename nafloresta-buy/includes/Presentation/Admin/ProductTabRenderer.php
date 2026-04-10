<?php

namespace NaFlorestaBuy\Presentation\Admin;

use NaFlorestaBuy\Infrastructure\Repository\ConfigRepositoryInterface;

class ProductTabRenderer
{
    private ConfigRepositoryInterface $repository;

    public function __construct(ConfigRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function register(): void
    {
        add_filter('woocommerce_product_data_tabs', [$this, 'registerTab']);
        add_action('woocommerce_product_data_panels', [$this, 'renderPanel']);
        add_action('woocommerce_process_product_meta', [$this, 'save']);
        add_action('admin_notices', [$this, 'renderAdminNotice']);
    }

    public function registerTab(array $tabs): array
    {
        $tabs['nafloresta_buy'] = [
            'label' => __('NaFlorestaBuy', 'nafloresta-buy'),
            'target' => 'nafloresta_buy_panel',
            'class' => ['show_if_variable'],
        ];

        return $tabs;
    }

    public function renderPanel(): void
    {
        global $post;

        $product = wc_get_product((int) $post->ID);
        $variationOptions = [];

        if ($product instanceof \WC_Product_Variable) {
            foreach ($product->get_children() as $variationId) {
                $variation = wc_get_product($variationId);
                if (!$variation) {
                    continue;
                }

                $variationOptions[] = [
                    'id' => (int) $variationId,
                    'label' => $variation->get_name(),
                    'price' => $variation->get_price_html(),
                ];
            }
        }

        $config = $this->repository->getProductConfig((int) $post->ID);
        $presets = get_option('nafb_presets', []);
        include NAFB_PLUGIN_PATH . 'templates/admin/product-tab.php';
    }

    public function save(int $productId): void
    {
        if (!isset($_POST['nafb_admin_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nafb_admin_nonce'])), 'nafb_save_product_config')) {
            return;
        }

        if (!current_user_can('edit_product', $productId)) {
            return;
        }

        $enabled = wc_string_to_bool(wp_unslash($_POST['nafb_enabled'] ?? 'no'));
        $rawVariationIds = isset($_POST['nafb_variation_ids']) ? (array) wp_unslash($_POST['nafb_variation_ids']) : [];
        $variationIds = array_values(array_map('absint', array_filter($rawVariationIds)));

        $fieldSchema = $this->sanitizeFieldSchema(wp_unslash($_POST['nafb_fields_schema'] ?? ''));

        if ($enabled && $variationIds === []) {
            set_transient('nafb_admin_error_' . get_current_user_id(), __('Selecione ao menos uma variação para habilitar o builder.', 'nafloresta-buy'), 30);
            return;
        }

        $config = [
            'enabled' => $enabled,
            'builder_mode' => sanitize_text_field(wp_unslash($_POST['nafb_builder_mode'] ?? 'matrix')),
            'ui_mode' => sanitize_text_field(wp_unslash($_POST['nafb_ui_mode'] ?? 'drawer')),
            'variation_ids' => $variationIds,
            'fields_schema' => $fieldSchema,
        ];

        $presetAction = sanitize_key((string) ($_POST['nafb_preset_action'] ?? ''));
        if ($presetAction === 'save_preset') {
            $this->savePreset($config, sanitize_text_field(wp_unslash($_POST['nafb_preset_name'] ?? '')));
        }

        if ($presetAction === 'apply_preset') {
            $presetKey = sanitize_key((string) ($_POST['nafb_preset_key'] ?? ''));
            $preset = $this->getPreset($presetKey);
            if ($preset !== null) {
                $config = $preset;
            }
        }

        if ($presetAction === 'import_config') {
            $imported = $this->parseImportedConfig(wp_unslash($_POST['nafb_import_json'] ?? ''));
            if ($imported !== null) {
                $config = $imported;
            }
        }

        $this->repository->saveProductConfig($productId, $config);
    }

    public function renderAdminNotice(): void
    {
        if (!is_admin()) {
            return;
        }

        $key = 'nafb_admin_error_' . get_current_user_id();
        $message = get_transient($key);
        if (!$message) {
            return;
        }

        delete_transient($key);
        echo '<div class="notice notice-error"><p>' . esc_html((string) $message) . '</p></div>';
    }

    private function sanitizeFieldSchema(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_map(static function (array $field): array {
            return [
                'id' => sanitize_key((string) ($field['id'] ?? '')),
                'type' => sanitize_key((string) ($field['type'] ?? 'text')),
                'label' => sanitize_text_field((string) ($field['label'] ?? '')),
                'required' => !empty($field['required']),
                'options' => array_values(array_map('sanitize_text_field', (array) ($field['options'] ?? []))),
            ];
        }, $decoded));
    }

    private function savePreset(array $config, string $name): void
    {
        if ($name === '') {
            return;
        }

        $presets = get_option('nafb_presets', []);
        $key = sanitize_title($name);
        $presets[$key] = [
            'name' => $name,
            'config' => $config,
        ];
        update_option('nafb_presets', $presets, false);
    }

    private function getPreset(string $key): ?array
    {
        $presets = get_option('nafb_presets', []);
        if (!isset($presets[$key]['config']) || !is_array($presets[$key]['config'])) {
            return null;
        }

        return $presets[$key]['config'];
    }

    private function parseImportedConfig(string $json): ?array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            set_transient('nafb_admin_error_' . get_current_user_id(), __('JSON de importação inválido.', 'nafloresta-buy'), 30);
            return null;
        }

        if (!isset($decoded['variation_ids']) || !is_array($decoded['variation_ids'])) {
            set_transient('nafb_admin_error_' . get_current_user_id(), __('Configuração importada inválida: variações ausentes.', 'nafloresta-buy'), 30);
            return null;
        }

        $decoded['variation_ids'] = array_values(array_map('absint', $decoded['variation_ids']));
        $decoded['fields_schema'] = $this->sanitizeFieldSchema(wp_json_encode($decoded['fields_schema'] ?? []));

        return [
            'enabled' => !empty($decoded['enabled']),
            'builder_mode' => sanitize_key((string) ($decoded['builder_mode'] ?? 'matrix')),
            'ui_mode' => sanitize_key((string) ($decoded['ui_mode'] ?? 'drawer')),
            'variation_ids' => $decoded['variation_ids'],
            'fields_schema' => $decoded['fields_schema'],
        ];
    }
}
