<?php

namespace NaFlorestaBuy\Core;

class AssetsManager
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdmin']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueFront']);
    }

    public function enqueueAdmin(): void
    {
        wp_enqueue_style('nafb-admin', NAFB_PLUGIN_URL . 'assets/css/admin.css', [], NAFB_VERSION);
        wp_enqueue_script('nafb-admin-product-config', NAFB_PLUGIN_URL . 'assets/js/admin/product-config.js', ['jquery'], NAFB_VERSION, true);
    }

    public function enqueueFront(): void
    {
        wp_enqueue_style('nafb-front', NAFB_PLUGIN_URL . 'assets/css/front.css', [], NAFB_VERSION);
        wp_enqueue_script('nafb-front-store', NAFB_PLUGIN_URL . 'assets/js/front/store.js', [], NAFB_VERSION, true);
        wp_enqueue_script('nafb-front-drawer', NAFB_PLUGIN_URL . 'assets/js/front/components/drawer.js', [], NAFB_VERSION, true);
        wp_enqueue_script('nafb-front-toast', NAFB_PLUGIN_URL . 'assets/js/front/components/toast.js', [], NAFB_VERSION, true);
        wp_enqueue_script('nafb-front-events', NAFB_PLUGIN_URL . 'assets/js/front/utils/events.js', [], NAFB_VERSION, true);
        wp_enqueue_script('nafb-front-app', NAFB_PLUGIN_URL . 'assets/js/front/app.js', ['nafb-front-store', 'nafb-front-drawer', 'nafb-front-toast', 'nafb-front-events'], NAFB_VERSION, true);

        wp_localize_script('nafb-front-app', 'nafbApp', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nafb_batch_add_to_cart'),
            'mode' => 'matrix',
            'trackNonce' => wp_create_nonce('nafb_track_event'),
            'texts' => [
                'studentLabel' => (string) get_option('nafb_label_student', __('Nome do aluno', 'nafloresta-buy')),
                'addToCart' => (string) get_option('nafb_text_add_to_cart', __('Adicionar ao carrinho', 'nafloresta-buy')),
                'validationRequired' => (string) get_option('nafb_text_validation_required', __('Nome obrigatório.', 'nafloresta-buy')),
                'allReady' => (string) get_option('nafb_text_all_ready', __('Tudo pronto!', 'nafloresta-buy')),
            ],
        ]);
    }
}
