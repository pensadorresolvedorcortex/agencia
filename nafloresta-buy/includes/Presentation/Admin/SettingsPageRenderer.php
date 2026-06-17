<?php

namespace NaFlorestaBuy\Presentation\Admin;

class SettingsPageRenderer
{
    private const OPTION_DEBUG = 'nafb_debug_mode';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_post_nafb_export_insights_json', [$this, 'exportInsightsJson']);
        add_action('admin_post_nafb_export_insights_csv', [$this, 'exportInsightsCsv']);
    }

    public function registerPage(): void
    {
        add_options_page(
            __('NaFlorestaBuy', 'nafloresta-buy'),
            __('NaFlorestaBuy', 'nafloresta-buy'),
            'manage_options',
            'nafloresta-buy',
            [$this, 'render']
        );
    }

    public function registerSettings(): void
    {
        register_setting('nafb_settings', self::OPTION_DEBUG, [
            'type' => 'boolean',
            'sanitize_callback' => static fn($value) => (bool) $value,
            'default' => false,
        ]);

        register_setting('nafb_settings', 'nafb_label_student', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => __('Nome do aluno', 'nafloresta-buy')]);
        register_setting('nafb_settings', 'nafb_text_add_to_cart', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => __('Adicionar ao carrinho', 'nafloresta-buy')]);
        register_setting('nafb_settings', 'nafb_text_validation_required', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => __('Nome obrigatório.', 'nafloresta-buy')]);
        register_setting('nafb_settings', 'nafb_text_all_ready', ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => __('Tudo pronto!', 'nafloresta-buy')]);

        add_settings_section('nafb_general', __('Configurações gerais', 'nafloresta-buy'), '__return_false', 'nafb_settings');

        add_settings_field(self::OPTION_DEBUG, __('Modo debug', 'nafloresta-buy'), [$this, 'renderDebugField'], 'nafb_settings', 'nafb_general');
        add_settings_field('nafb_label_student', __('Rótulo do aluno', 'nafloresta-buy'), [$this, 'renderTextField'], 'nafb_settings', 'nafb_general', ['key' => 'nafb_label_student']);
        add_settings_field('nafb_text_add_to_cart', __('Texto do botão principal', 'nafloresta-buy'), [$this, 'renderTextField'], 'nafb_settings', 'nafb_general', ['key' => 'nafb_text_add_to_cart']);
        add_settings_field('nafb_text_validation_required', __('Texto de erro obrigatório', 'nafloresta-buy'), [$this, 'renderTextField'], 'nafb_settings', 'nafb_general', ['key' => 'nafb_text_validation_required']);
        add_settings_field('nafb_text_all_ready', __('Texto de conclusão', 'nafloresta-buy'), [$this, 'renderTextField'], 'nafb_settings', 'nafb_general', ['key' => 'nafb_text_all_ready']);
    }

    public function renderDebugField(): void
    {
        $enabled = (bool) get_option(self::OPTION_DEBUG, false);
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_DEBUG) . '" value="1" ' . checked($enabled, true, false) . ' /> ';
        echo esc_html__('Ativar logs técnicos (payload sanitizado, erros e operações de carrinho).', 'nafloresta-buy');
        echo '</label>';
    }

    public function renderTextField(array $args): void
    {
        $key = sanitize_key((string) ($args['key'] ?? ''));
        $value = (string) get_option($key, '');
        echo '<input type="text" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('NaFlorestaBuy', 'nafloresta-buy') . '</h1>';
        $insights = get_option('nafb_insights', ['started' => 0, 'completed' => 0, 'errors' => 0, 'events' => 0]);

        echo '<div class="nafb-settings-insights">';
        echo '<h2>' . esc_html__('Insights rápidos', 'nafloresta-buy') . '</h2>';
        echo '<ul>';
        echo '<li>' . esc_html__('Configurações iniciadas', 'nafloresta-buy') . ': <strong>' . esc_html((string) ($insights['started'] ?? 0)) . '</strong></li>';
        echo '<li>' . esc_html__('Configurações concluídas', 'nafloresta-buy') . ': <strong>' . esc_html((string) ($insights['completed'] ?? 0)) . '</strong></li>';
        echo '<li>' . esc_html__('Erros registrados', 'nafloresta-buy') . ': <strong>' . esc_html((string) ($insights['errors'] ?? 0)) . '</strong></li>';
        echo '<li>' . esc_html__('Eventos totais', 'nafloresta-buy') . ': <strong>' . esc_html((string) ($insights['events'] ?? 0)) . '</strong></li>';
        echo '</ul>';
        echo '<p><a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=nafb_export_insights_json'), 'nafb_export_insights')) . '">' . esc_html__('Exportar insights (JSON)', 'nafloresta-buy') . '</a> ';
        echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=nafb_export_insights_csv'), 'nafb_export_insights')) . '">' . esc_html__('Exportar insights (CSV)', 'nafloresta-buy') . '</a></p>';
        echo '</div>';

        echo '<form action="options.php" method="post">';
        settings_fields('nafb_settings');
        do_settings_sections('nafb_settings');
        submit_button(__('Salvar configurações', 'nafloresta-buy'));
        echo '</form></div>';
    }

    public function exportInsightsJson(): void
    {
        $this->authorizeExport();
        $insights = get_option('nafb_insights', []);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=nafb-insights.json');
        echo wp_json_encode($insights, JSON_PRETTY_PRINT);
        exit;
    }

    public function exportInsightsCsv(): void
    {
        $this->authorizeExport();
        $insights = get_option('nafb_insights', []);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=nafb-insights.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['metric', 'value']);
        foreach ((array) $insights as $key => $value) {
            fputcsv($out, [(string) $key, (string) $value]);
        }
        fclose($out);
        exit;
    }

    private function authorizeExport(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Acesso negado.', 'nafloresta-buy'));
        }

        check_admin_referer('nafb_export_insights');
    }
}
