<?php
/**
 * Plugin Name: RMA Admin Settings
 * Description: Configurações centralizadas para Equipe RMA (anuidade, API Maps, PIX e notificações).
 * Version: 0.2.0
 * Author: RMA
 */

if (! defined('ABSPATH')) {
    exit;
}

final class RMA_Admin_Settings {
    private const OPTION_GROUP = 'rma_admin_settings_group';

    private const OPTIONS = [
        'rma_annual_due_value',
        'rma_annual_dues_product_id',
        'rma_due_day_month',
        'rma_pix_key',
        'rma_google_maps_api_key',
        'rma_maps_only_adimplente',
        'rma_institutional_email',
        'rma_notifications_api_url',
        'rma_email_sender_mode',
        'rma_email_verification_header_image',
        'rma_email_verification_logo',
        'rma_email_verification_bg_color',
        'rma_email_verification_button_color',
        'rma_email_verification_body',
        'rma_email_verification_footer',
        'rma_email_verification_company',
        'rma_email_anexo2_confirmacao_subject',
        'rma_email_anexo2_confirmacao_body',
        'rma_email_anexo2_aviso_renovacao_subject',
        'rma_email_anexo2_aviso_renovacao_body',
        'rma_email_anexo2_lembrete_subject',
        'rma_email_anexo2_lembrete_body',
        'rma_email_anexo2_ultimo_aviso_subject',
        'rma_email_anexo2_ultimo_aviso_body',
        'rma_email_anexo2_cobranca_confirmada_subject',
        'rma_email_anexo2_cobranca_confirmada_body',
        'rma_email_anexo2_pos_vencimento_subject',
        'rma_email_anexo2_pos_vencimento_body',
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'block_non_admin_from_wp_admin'], 1);
        add_action('admin_post_rma_export_newsletter_csv', [$this, 'export_newsletter_csv']);
        add_action('admin_post_rma_export_newsletter_xml', [$this, 'export_newsletter_xml']);
        add_action('admin_post_rma_export_reports_csv', [$this, 'export_reports_csv']);
        add_action('admin_post_rma_export_reports_xml', [$this, 'export_reports_xml']);
        add_filter('show_admin_bar', [$this, 'maybe_hide_admin_bar_for_entities'], 20);
        add_action('admin_head', [$this, 'inject_admin_brand_styles']);
    }

    public function register_menu(): void {
        add_menu_page(
            'RMA Configurações',
            'RMA Configurações',
            'manage_options',
            'rma-admin-settings',
            [$this, 'render_page'],
            'dashicons-admin-generic',
            58
        );

        add_menu_page(
            'Lista Newsletter',
            'Lista Newsletter',
            'manage_options',
            'rma-newsletter-list',
            [$this, 'render_newsletter_page'],
            'dashicons-email-alt',
            59
        );

        add_menu_page(
            'Relatórios',
            'Relatórios',
            'manage_options',
            'rma-relatorios',
            [$this, 'render_reports_page'],
            'dashicons-chart-bar',
            60
        );

        add_menu_page(
            'Ticket',
            'Ticket',
            'manage_options',
            'rma-tickets',
            [$this, 'render_tickets_page'],
            'dashicons-tickets-alt',
            61
        );

        add_menu_page(
            'E-mails Assinatura',
            'E-mails Assinatura',
            'manage_options',
            'rma-emails-assinatura',
            [$this, 'render_subscription_emails_page'],
            'dashicons-email',
            62
        );
    }

    public function register_settings(): void {
        foreach (self::OPTIONS as $option) {
            register_setting(self::OPTION_GROUP, $option, [
                'type' => 'string',
                'sanitize_callback' => function ($value) use ($option) {
                    return $this->sanitize_option($option, $value);
                },
                'show_in_rest' => false,
            ]);
        }

        add_settings_section(
            'rma_admin_main',
            'Parâmetros principais da operação RMA',
            static function () {
                echo '<p>Campos personalizáveis para operação do ciclo anual, mapa, financeiro e notificações.</p>';
                echo '<p><strong>Status do ciclo anual:</strong> ' . esc_html(rma_is_annual_cycle_open() ? 'Vigente' : 'Ainda não iniciado neste ano') . '</p>';
            },
            'rma-admin-settings'
        );

        $this->add_field('rma_annual_due_value', 'Valor da anuidade (R$)', 'number', 'Ex.: 1200.00');
        $this->add_field('rma_annual_dues_product_id', 'ID do produto Woo da anuidade', 'number', 'Ex.: 123');
        $this->add_field('rma_due_day_month', 'Data de início do ciclo anual (dd-mm)', 'text', 'Ex.: 01-02');
        $this->add_field('rma_pix_key', 'Chave PIX institucional', 'text', 'Ex.: financeiro@rma.org.br');
        $this->add_field('rma_google_maps_api_key', 'Google Maps API Key', 'text', 'Usada pelo tema para renderização do mapa');
        $this->add_field('rma_maps_only_adimplente', 'Diretório mostra apenas adimplentes por padrão', 'checkbox', '');
        $this->add_field('rma_institutional_email', 'E-mail institucional (notificações)', 'email', 'Ex.: secretaria@rma.org.br');
        $this->add_field('rma_notifications_api_url', 'URL da API de notificações (opcional)', 'url', 'Ex.: https://api.seudominio/notify');
        $this->add_field('rma_email_sender_mode', 'Motor de envio de e-mails', 'select', '');

        add_settings_section(
            'rma_admin_emails_verification',
            'Configurações > Emails > Verificação',
            static function () {
                echo '<p>Personalize o template do e-mail de verificação em 2 fatores. Variáveis suportadas: <code>{{nome}}</code>, <code>{{codigo}}</code>, <code>{{data}}</code>, <code>{{empresa}}</code>.</p>';
            },
            'rma-admin-settings'
        );

        $this->add_field('rma_email_verification_header_image', 'Imagem de header (URL)', 'url', 'https://.../header.jpg', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_logo', 'Logo (URL)', 'url', 'https://.../logo.png', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_bg_color', 'Cor de fundo do e-mail', 'text', '#f8fafb', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_button_color', 'Cor do botão', 'text', '#7bad39', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_body', 'Texto editável do corpo', 'textarea', 'Olá {{nome}}, seu código é {{codigo}}.', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_footer', 'Footer editável', 'textarea', 'Equipe RMA • {{data}}', 'rma_admin_emails_verification');
        $this->add_field('rma_email_verification_company', 'Nome da empresa', 'text', 'RMA', 'rma_admin_emails_verification');

    }

    private function add_field(string $name, string $label, string $type, string $placeholder, string $section = 'rma_admin_main'): void {
        add_settings_field(
            $name,
            $label,
            function () use ($name, $type, $placeholder) {
                $value = get_option($name, '');

                if ($type === 'checkbox') {
                    echo '<input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($value, '1', false) . ' />';
                    return;
                }

                if ($type === 'textarea') {
                    if ($value === '') {
                        $value = $placeholder;
                    }
                    echo '<textarea name="' . esc_attr($name) . '" rows="4" cols="60" placeholder="' . esc_attr($placeholder) . '">' . esc_textarea((string) $value) . '</textarea>';
                    return;
                }

                if ($type === 'select') {
                    $current = $value !== '' ? (string) $value : 'wp_mail';
                    echo '<select name="' . esc_attr($name) . '">';
                    echo '<option value="wp_mail" ' . selected($current, 'wp_mail', false) . '>WP Mail</option>';
                    echo '<option value="woo_mail" ' . selected($current, 'woo_mail', false) . '>WooCommerce Mailer</option>';
                    echo '</select>';
                    return;
                }

                $input_type = in_array($type, ['text', 'number', 'email', 'url'], true) ? $type : 'text';
                $attrs = $input_type === 'number' ? ' step="0.01" min="0"' : '';
                echo '<input type="' . esc_attr($input_type) . '" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '" placeholder="' . esc_attr($placeholder) . '" class="regular-text"' . $attrs . ' />';
            },
            'rma-admin-settings',
            $section
        );
    }


    private function is_privileged_admin_user(?int $user_id = null): bool {
        $user_id = $user_id ?? get_current_user_id();
        if ($user_id <= 0) {
            return false;
        }

        if (is_multisite()) {
            return is_super_admin($user_id);
        }

        return user_can($user_id, 'manage_options');
    }

    public function maybe_hide_admin_bar_for_entities(bool $show): bool {
        if (! is_user_logged_in()) {
            return $show;
        }

        return $this->is_privileged_admin_user() ? $show : false;
    }

    public function block_non_admin_from_wp_admin(): void {
        if (! is_user_logged_in() || ! is_admin()) {
            return;
        }

        if (wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }

        if ($this->is_privileged_admin_user()) {
            return;
        }

        wp_safe_redirect(home_url('/dashboard/'));
        exit;
    }

    public function inject_admin_brand_styles(): void {
        if (! is_admin()) {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_key((string) wp_unslash($_GET['post_type'])) : '';
        $targets = ['rma-admin-settings', 'rma-newsletter-list', 'rma-relatorios', 'rma-emails-assinatura', 'rma-tickets'];
        if (! in_array($page, $targets, true) && $post_type !== 'rma_entidade') {
            return;
        }

        echo '<style>
        .rma-admin-wrap,.rma-admin-wrap *{font-family:"Maven Pro",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        .rma-admin-wrap{margin-top:14px;border-radius:24px;padding:24px;border:1px solid rgba(255,255,255,.9);background:linear-gradient(150deg,#fcfdff 0%,#f2f7ff 100%);box-shadow:0 24px 60px rgba(15,23,42,.11);color:#162538}
        .rma-admin-head{background:linear-gradient(135deg,#7bad39,#9dc26b 55%,#20cf98);color:#fff;border-radius:16px;padding:16px 18px;margin-bottom:14px}
        .rma-admin-head h1{margin:0 0 6px !important;color:#fff;font-size:28px}
        .rma-admin-head p{margin:0;color:#fff;opacity:.95}
        .rma-admin-toolbar{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 14px}
        .rma-admin-toolbar .button{border-radius:999px;padding:7px 12px}
        .rma-admin-wrap .widefat{border:1px solid rgba(15,23,42,.08);border-radius:12px;overflow:hidden;box-shadow:0 10px 24px rgba(15,23,42,.05)}
        .rma-admin-wrap .widefat th{background:#f8fbff;text-transform:uppercase;font-size:11px;letter-spacing:.06em;color:#5b6a7c}
        .rma-admin-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin:8px 0 14px}
        .rma-admin-card{background:#fff;border:1px solid #dce8f5;border-radius:12px;padding:10px}
        .rma-admin-card small{display:block;color:#607086;margin-bottom:4px}
        .rma-admin-card strong{font-size:20px}
        .rma-pies{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin:10px 0 14px}
        .rma-pie{display:grid;place-items:center;background:#fff;border:1px solid #dce8f5;border-radius:12px;padding:12px}
        .rma-pie-chart{width:110px;height:110px;border-radius:50%;display:grid;place-items:center;color:#162538;font-weight:700;background:conic-gradient(#7bad39 0 50%,#ce3f4a 50% 100%)}
        .rma-pie-chart span{background:#fff;border-radius:50%;width:72px;height:72px;display:grid;place-items:center;font-size:12px;text-align:center;padding:6px}
        .rma-email-block{background:#fff;border:1px solid #dce8f5;border-radius:12px;padding:12px;margin:0 0 12px}
        .rma-preview-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;z-index:99999;padding:4vh 3vw}
        .rma-preview-modal.is-open{display:block}
        .rma-preview-dialog{background:#fff;border-radius:14px;max-width:860px;margin:0 auto;max-height:92vh;overflow:auto;padding:14px}
        .edit-php.post-type-rma_entidade .wp-header-end{margin-bottom:12px}
        .edit-php.post-type-rma_entidade .wrap h1.wp-heading-inline{background:linear-gradient(135deg,#7bad39,#9dc26b 55%,#20cf98);color:#fff;padding:8px 12px;border-radius:10px}
        </style>';
    }

    public function render_newsletter_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $users = get_users([
            'orderby' => 'registered',
            'order' => 'DESC',
            'fields' => ['ID', 'display_name', 'user_email', 'user_registered', 'roles'],
        ]);

        $csv_url = wp_nonce_url(admin_url('admin-post.php?action=rma_export_newsletter_csv'), 'rma_export_newsletter');
        $xml_url = wp_nonce_url(admin_url('admin-post.php?action=rma_export_newsletter_xml'), 'rma_export_newsletter');
        echo '<div class="wrap rma-admin-wrap"><div class="rma-admin-head"><h1>Lista Newsletter</h1><p>Lista automática de todos os usuários cadastrados no site.</p></div>';
        echo '<div class="rma-admin-toolbar"><a class="button button-primary" href="' . esc_url($csv_url) . '">Exportar CSV</a> <a class="button" href="' . esc_url($xml_url) . '">Exportar XML</a></div>';
        echo '<div class="rma-admin-cards"><div class="rma-admin-card"><small>Total de usuários</small><strong>' . esc_html((string) count($users)) . '</strong></div></div>';
        echo '<table class="widefat striped"><thead><tr><th>Nome</th><th>E-mail</th><th>Cadastro</th><th>Perfil</th></tr></thead><tbody>';
        if (empty($users)) {
            echo '<tr><td colspan="4">Nenhum usuário encontrado.</td></tr>';
        } else {
            foreach ($users as $user) {
                $roles = is_array($user->roles) ? implode(', ', array_map('sanitize_text_field', $user->roles)) : '';
                echo '<tr><td>' . esc_html($user->display_name) . '</td><td>' . esc_html($user->user_email) . '</td><td>' . esc_html(wp_date('d/m/Y H:i', strtotime((string) $user->user_registered))) . '</td><td>' . esc_html($roles) . '</td></tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    public function export_newsletter_csv(): void {
        $this->handle_newsletter_export('csv');
    }

    public function export_newsletter_xml(): void {
        $this->handle_newsletter_export('xml');
    }

    private function handle_newsletter_export(string $format): void {
        if (! current_user_can('manage_options') || ! check_admin_referer('rma_export_newsletter')) {
            wp_die('Acesso negado.');
        }

        $users = get_users(['orderby' => 'registered', 'order' => 'DESC']);
        if ($format === 'csv') {
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="rma-newsletter.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['nome', 'email', 'cadastro', 'perfil']);
            foreach ($users as $user) {
                $roles = is_array($user->roles) ? implode('|', $user->roles) : '';
                fputcsv($out, [$user->display_name, $user->user_email, $user->user_registered, $roles]);
            }
            fclose($out);
            exit;
        }

        nocache_headers();
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="rma-newsletter.xml"');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<newsletter>' . "\n";
        foreach ($users as $user) {
            $roles = is_array($user->roles) ? implode('|', $user->roles) : '';
            echo '<cliente><nome>' . esc_html($user->display_name) . '</nome><email>' . esc_html($user->user_email) . '</email><cadastro>' . esc_html((string) $user->user_registered) . '</cadastro><perfil>' . esc_html($roles) . '</perfil></cliente>' . "\n";
        }
        echo "</newsletter>";
        exit;
    }

    public function render_tickets_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $entities = get_posts([
            'post_type' => 'rma_entidade',
            'post_status' => ['publish', 'draft'],
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        $rows = [];
        $open_count = 0;
        foreach ($entities as $entity_id) {
            $entity_id = (int) $entity_id;
            $tickets = get_post_meta($entity_id, 'rma_support_tickets', true);
            $tickets = is_array($tickets) ? $tickets : [];
            $entity_name = (string) get_the_title($entity_id);
            foreach ($tickets as $ticket) {
                $status = sanitize_key((string) ($ticket['status'] ?? 'aberto'));
                if ($status === 'aberto') {
                    $open_count++;
                }
                $rows[] = [
                    'entity' => $entity_name,
                    'ticket_id' => (string) ($ticket['id'] ?? '-'),
                    'subject' => (string) ($ticket['subject'] ?? ''),
                    'message' => (string) ($ticket['message'] ?? ''),
                    'priority' => sanitize_key((string) ($ticket['priority'] ?? 'outros')),
                    'status' => $status,
                    'created_at' => (string) ($ticket['created_at'] ?? ''),
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        $priority_labels = [
            'problema_tecnico' => '1 - Problema técnico',
            'duvida_institucional' => '2 - Dúvida institucional',
            'financeiro' => '3 - Financeiro',
            'outros' => '4 - Outros',
        ];

        echo '<div class="wrap rma-admin-wrap"><div class="rma-admin-head"><h1>Ticket</h1><p>Chamados abertos pelas entidades para atendimento da Equipe RMA.</p></div>';
        echo '<div class="rma-admin-cards">';
        echo '<div class="rma-admin-card"><small>Total de tickets</small><strong>' . esc_html((string) count($rows)) . '</strong></div>';
        echo '<div class="rma-admin-card"><small>Tickets abertos</small><strong>' . esc_html((string) $open_count) . '</strong></div>';
        echo '</div>';

        echo '<table class="widefat striped"><thead><tr><th>Data</th><th>Entidade</th><th>Ticket</th><th>Categoria</th><th>Status</th><th>Assunto</th><th>Mensagem</th></tr></thead><tbody>';
        if (empty($rows)) {
            echo '<tr><td colspan="7">Nenhum ticket recebido até o momento.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $date = (string) ($row['created_at'] ?? '');
                $date_label = $date !== '' ? wp_date('d/m/Y H:i', strtotime($date)) : '—';
                $priority_key = sanitize_key((string) ($row['priority'] ?? 'outros'));
                $priority_label = $priority_labels[$priority_key] ?? '4 - Outros';
                echo '<tr><td>' . esc_html($date_label) . '</td><td>' . esc_html((string) ($row['entity'] ?? '')) . '</td><td>' . esc_html((string) ($row['ticket_id'] ?? '-')) . '</td><td>' . esc_html($priority_label) . '</td><td>' . esc_html(strtoupper((string) ($row['status'] ?? 'aberto'))) . '</td><td>' . esc_html((string) ($row['subject'] ?? '')) . '</td><td>' . esc_html((string) ($row['message'] ?? '')) . '</td></tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    public function export_reports_csv(): void {
        $this->handle_reports_export('csv');
    }

    public function export_reports_xml(): void {
        $this->handle_reports_export('xml');
    }

    private function get_reports_snapshot(string $scope = 'all'): array {
        $entities = get_posts([
            'post_type' => 'rma_entidade',
            'post_status' => ['publish', 'draft'],
            'numberposts' => -1,
            'fields' => 'ids',
        ]);

        $summary = [
            'total_entities' => 0,
            'active' => 0,
            'inactive' => 0,
            'governance_approved' => 0,
            'governance_pending' => 0,
            'due_30' => 0,
            'revenue' => 0.0,
        ];
        $details = [];

        $scope = sanitize_key($scope);
        if (! in_array($scope, ['all', 'active', 'inactive'], true)) {
            $scope = 'all';
        }

        foreach ($entities as $entity_id) {
            $entity_id = (int) $entity_id;
            $finance_status = sanitize_key((string) get_post_meta($entity_id, 'finance_status', true));
            $governance_status = sanitize_key((string) get_post_meta($entity_id, 'governance_status', true));
            $due_raw = (string) get_post_meta($entity_id, 'anuidade_vencimento', true);
            if ($due_raw === '') {
                $due_raw = (string) get_post_meta($entity_id, 'finance_due_at', true);
            }

            $days_to_due = 9999;
            if ($due_raw !== '') {
                $due_ts = strtotime($due_raw . ' UTC');
                if ($due_ts) {
                    $days_to_due = (int) floor(($due_ts - time()) / DAY_IN_SECONDS);
                }
            }

            if ($scope === 'active' && $finance_status !== 'adimplente') {
                continue;
            }
            if ($scope === 'inactive' && $finance_status === 'adimplente') {
                continue;
            }

            $summary['total_entities']++;

            if ($finance_status === 'adimplente') {
                $summary['active']++;
            } else {
                $summary['inactive']++;
            }

            if ($governance_status === 'aprovado') {
                $summary['governance_approved']++;
            } else {
                $summary['governance_pending']++;
            }

            if ($days_to_due >= 0 && $days_to_due <= 30) {
                $summary['due_30']++;
            }

            $details[] = [
                'name' => (string) get_the_title($entity_id),
                'finance_status' => $finance_status !== '' ? $finance_status : 'indefinido',
                'governance_status' => $governance_status !== '' ? $governance_status : 'indefinido',
                'due_at' => $due_raw,
                'days_to_due' => $days_to_due,
            ];
        }

        usort($details, static function (array $a, array $b): int {
            return $a['days_to_due'] <=> $b['days_to_due'];
        });

        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders(['status' => ['processing', 'completed'], 'limit' => -1]);
            foreach ($orders as $order) {
                if ($order instanceof WC_Order) {
                    $summary['revenue'] += (float) $order->get_total();
                }
            }
        }

        return [
            'summary' => $summary,
            'details' => $details,
        ];
    }

    private function handle_reports_export(string $format): void {
        if (! current_user_can('manage_options') || ! check_admin_referer('rma_export_reports')) {
            wp_die('Acesso negado.');
        }

        $scope = isset($_GET['scope']) ? sanitize_key((string) wp_unslash($_GET['scope'])) : 'all';
        if (! in_array($scope, ['all', 'active', 'inactive'], true)) {
            $scope = 'all';
        }
        $snapshot = $this->get_reports_snapshot($scope);
        $summary = (array) ($snapshot['summary'] ?? []);
        $details = (array) ($snapshot['details'] ?? []);

        if ($format === 'csv') {
            nocache_headers();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="rma-relatorios-' . $scope . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['metrica', 'valor']);
            foreach ($summary as $key => $value) {
                $v = $key === 'revenue' ? number_format((float) $value, 2, '.', '') : (string) $value;
                fputcsv($out, [$key, $v]);
            }
            fputcsv($out, []);
            fputcsv($out, ['entidade', 'financeiro', 'governanca', 'vencimento', 'dias_para_vencer']);
            foreach ($details as $row) {
                fputcsv($out, [
                    (string) ($row['name'] ?? ''),
                    (string) ($row['finance_status'] ?? ''),
                    (string) ($row['governance_status'] ?? ''),
                    (string) ($row['due_at'] ?? ''),
                    (string) ($row['days_to_due'] ?? ''),
                ]);
            }
            fclose($out);
            exit;
        }

        nocache_headers();
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="rma-relatorios-' . $scope . '.xml"');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<relatorios>' . "\n";
        echo '<resumo>';
        echo '<total_entities>' . esc_html((string) ($summary['total_entities'] ?? 0)) . '</total_entities>';
        echo '<active>' . esc_html((string) ($summary['active'] ?? 0)) . '</active>';
        echo '<inactive>' . esc_html((string) ($summary['inactive'] ?? 0)) . '</inactive>';
        echo '<governance_approved>' . esc_html((string) ($summary['governance_approved'] ?? 0)) . '</governance_approved>';
        echo '<governance_pending>' . esc_html((string) ($summary['governance_pending'] ?? 0)) . '</governance_pending>';
        echo '<due_30>' . esc_html((string) ($summary['due_30'] ?? 0)) . '</due_30>';
        echo '<revenue>' . esc_html((string) ($summary['revenue'] ?? 0)) . '</revenue>';
        echo '</resumo>' . "\n";
        echo '<entidades>' . "\n";
        foreach ($details as $row) {
            echo '<entidade>';
            echo '<nome>' . esc_html((string) ($row['name'] ?? '')) . '</nome>';
            echo '<financeiro>' . esc_html((string) ($row['finance_status'] ?? '')) . '</financeiro>';
            echo '<governanca>' . esc_html((string) ($row['governance_status'] ?? '')) . '</governanca>';
            echo '<vencimento>' . esc_html((string) ($row['due_at'] ?? '')) . '</vencimento>';
            echo '<dias_para_vencer>' . esc_html((string) ($row['days_to_due'] ?? '')) . '</dias_para_vencer>';
            echo '</entidade>' . "\n";
        }
        echo '</entidades>' . "\n";
        echo '</relatorios>';
        exit;
    }

    public function render_reports_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $scope = isset($_GET['scope']) ? sanitize_key((string) wp_unslash($_GET['scope'])) : 'all';
        if (! in_array($scope, ['all', 'active', 'inactive'], true)) {
            $scope = 'all';
        }

        $snapshot = $this->get_reports_snapshot($scope);
        $summary = (array) ($snapshot['summary'] ?? []);
        $details = (array) ($snapshot['details'] ?? []);

        $total = max(1, (int) ($summary['total_entities'] ?? 0));
        $active_pct = (int) round((((int) ($summary['active'] ?? 0)) / $total) * 100);
        $inactive_pct = max(0, 100 - $active_pct);
        $gov_apr_pct = (int) round((((int) ($summary['governance_approved'] ?? 0)) / $total) * 100);
        $gov_pen_pct = max(0, 100 - $gov_apr_pct);

        $csv_url = wp_nonce_url(add_query_arg(['action' => 'rma_export_reports_csv', 'scope' => $scope], admin_url('admin-post.php')), 'rma_export_reports');
        $xml_url = wp_nonce_url(add_query_arg(['action' => 'rma_export_reports_xml', 'scope' => $scope], admin_url('admin-post.php')), 'rma_export_reports');

        echo '<div class="wrap rma-admin-wrap"><div class="rma-admin-head"><h1>Relatórios</h1><p>Relatórios detalhados de faturamento, entidades ativas/inativas e situação de governança.</p></div>';
        $scope_links = ['all' => 'Todos','active' => 'Apenas Ativos','inactive' => 'Apenas Inativos'];
        echo '<div class="rma-admin-toolbar">';
        foreach ($scope_links as $scope_key => $scope_label) {
            $url = add_query_arg(['page' => 'rma-relatorios', 'scope' => $scope_key], admin_url('admin.php'));
            $class = $scope === $scope_key ? 'button button-primary' : 'button';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($scope_label) . '</a>';
        }
        echo '<a class="button button-primary" href="' . esc_url($csv_url) . '">Exportar Relatório CSV</a><a class="button" href="' . esc_url($xml_url) . '">Exportar Relatório XML</a></div>';

        echo '<div class="rma-admin-cards">';
        echo '<div class="rma-admin-card"><small>Total de entidades</small><strong>' . esc_html((string) ($summary['total_entities'] ?? 0)) . '</strong></div>';
        echo '<div class="rma-admin-card"><small>Ativas</small><strong>' . esc_html((string) ($summary['active'] ?? 0)) . '</strong></div>';
        echo '<div class="rma-admin-card"><small>Inativas</small><strong>' . esc_html((string) ($summary['inactive'] ?? 0)) . '</strong></div>';
        echo '<div class="rma-admin-card"><small>Faturamento</small><strong>R$ ' . esc_html(number_format((float) ($summary['revenue'] ?? 0), 2, ',', '.')) . '</strong></div>';
        echo '</div>';

        echo '<div class="rma-pies">';
        echo '<div class="rma-pie"><div class="rma-pie-chart" style="background:conic-gradient(#20cf98 0 ' . esc_attr((string) $active_pct) . '%, #ff6b6b ' . esc_attr((string) $active_pct) . '% 100%)"><span>Financeiro<br/>' . esc_html((string) $active_pct) . '%</span></div></div>';
        echo '<div class="rma-pie"><div class="rma-pie-chart" style="background:conic-gradient(#7bad39 0 ' . esc_attr((string) $gov_apr_pct) . '%, #ffb347 ' . esc_attr((string) $gov_apr_pct) . '% 100%)"><span>Governança<br/>' . esc_html((string) $gov_apr_pct) . '%</span></div></div>';
        echo '</div>';

        echo '<h2 style="margin-top:10px">Detalhamento das entidades (' . esc_html(strtoupper($scope)) . ')</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Entidade</th><th>Financeiro</th><th>Governança</th><th>Vencimento</th><th>Dias p/ vencer</th></tr></thead><tbody>';
        if (empty($details)) {
            echo '<tr><td colspan="5">Sem entidades para exibir.</td></tr>';
        } else {
            foreach (array_slice($details, 0, 120) as $row) {
                $due_label = (string) ($row['due_at'] ?? '') !== '' ? wp_date('d/m/Y', strtotime((string) $row['due_at'])) : '—';
                $days_label = ((int) ($row['days_to_due'] ?? 9999)) === 9999 ? '—' : (string) ($row['days_to_due'] ?? '');
                echo '<tr><td>' . esc_html((string) ($row['name'] ?? '')) . '</td><td>' . esc_html(strtoupper((string) ($row['finance_status'] ?? ''))) . '</td><td>' . esc_html(strtoupper((string) ($row['governance_status'] ?? ''))) . '</td><td>' . esc_html($due_label) . '</td><td>' . esc_html($days_label) . '</td></tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    public function render_subscription_emails_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $events = [
            'aviso_renovacao' => '30 dias antes',
            'lembrete' => '7 dias antes',
            'ultimo_aviso' => '1 dia antes',
            'pos_vencimento' => 'pós-vencimento',
        ];

        if (isset($_POST['rma_save_subscription_emails']) && check_admin_referer('rma_save_subscription_emails')) {
            foreach ($events as $key => $label) {
                update_option('rma_email_anexo2_' . $key . '_subject', sanitize_text_field((string) wp_unslash($_POST['subject_' . $key] ?? '')));
                update_option('rma_email_anexo2_' . $key . '_body', wp_kses_post((string) wp_unslash($_POST['body_' . $key] ?? '')));
            }
            echo '<div class="updated"><p>E-mails de assinatura atualizados com sucesso.</p></div>';
        }

        echo '<div class="wrap rma-admin-wrap"><div class="rma-admin-head"><h1>E-mails Assinatura</h1><p>Edite os e-mails programados da assinatura anual.</p></div>';
        echo '<form method="post">';
        wp_nonce_field('rma_save_subscription_emails');
        foreach ($events as $key => $label) {
            $subject = (string) get_option('rma_email_anexo2_' . $key . '_subject', '');
            $body = (string) get_option('rma_email_anexo2_' . $key . '_body', '');
            echo '<div class="rma-email-block">';
            echo '<h2 style="margin-top:0">' . esc_html($label) . '</h2>';
            echo '<p><label>Assunto<br/><input class="regular-text rma-mail-subject" data-key="' . esc_attr($key) . '" type="text" name="subject_' . esc_attr($key) . '" value="' . esc_attr($subject) . '" /></label></p>';
            echo '<p><label>Corpo<br/><textarea name="body_' . esc_attr($key) . '" rows="6" class="large-text rma-mail-body" data-key="' . esc_attr($key) . '">' . esc_textarea($body) . '</textarea></label></p>';
            echo '<p><button type="button" class="button rma-open-preview" data-key="' . esc_attr($key) . '">Pré-visualizar e-mail</button></p>';
            echo '</div>';
        }
        echo '<p><button type="submit" name="rma_save_subscription_emails" class="button button-primary">Salvar e-mails</button></p>';
        echo '</form>';
        echo '<div id="rma-email-preview-modal" class="rma-preview-modal"><div class="rma-preview-dialog"><p style="text-align:right"><button type="button" class="button" id="rma-email-preview-close">Fechar</button></p><iframe id="rma-email-preview-frame" style="width:100%;height:72vh;border:1px solid #dce8f5;border-radius:10px"></iframe></div></div>';
        echo '</div>';
        $preview_logo = trim((string) get_option('rma_email_verification_logo', ''));
        if ($preview_logo === '') {
            $preview_logo = 'https://www.agenciadigitalsaopaulo.com.br/rma/wp-content/uploads/2021/02/logo-.png';
        }
        $preview_favicon = 'https://www.agenciadigitalsaopaulo.com.br/rma/wp-content/uploads/2021/02/favicon.png';
        echo '<script>(function(){var modal=document.getElementById("rma-email-preview-modal");if(!modal){return;}var frame=document.getElementById("rma-email-preview-frame");var logo=' . wp_json_encode($preview_logo) . ';var favicon=' . wp_json_encode($preview_favicon) . ';document.querySelectorAll(".rma-open-preview").forEach(function(btn){btn.addEventListener("click",function(){var key=btn.getAttribute("data-key");var subj=document.querySelector(".rma-mail-subject[data-key=\""+key+"\"]");var body=document.querySelector(".rma-mail-body[data-key=\""+key+"\"]");var subject=(subj?subj.value:"Assunto");var message=(body?body.value:"Corpo da mensagem").replace(/\n/g,"<br>");var logoHtml=logo?`<img src="${logo}" alt="Logo RMA" style="display:block;max-width:180px;width:100%;height:auto;margin:0 auto 12px;"/>`:"";var faviconHtml=favicon?`<img src="${favicon}" alt="RMA" style="display:block;max-width:20px;width:20px;height:20px;margin:0 auto 6px;"/>`:"";var html=`<div style="background:#ffffff;padding:24px 12px;font-family:Maven Pro,Segoe UI,Arial,sans-serif"><div style="max-width:520px;margin:0 auto;background:rgba(255,255,255,.94);border-radius:20px;overflow:hidden;border:1px solid #e9eef3;box-shadow:0 16px 42px rgba(15,23,42,.10)"><div style="background-image:linear-gradient(135deg,#7bad39,#5ddabb);padding:24px 20px;text-align:center;color:#fff">${logoHtml}<p style="margin:0;font-size:12px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;opacity:.95">ANEXO 2</p><h2 style="margin:8px 0 0;font-size:30px;line-height:1.1;font-weight:800;color:#ffffff">${subject}</h2></div><div style="padding:26px 24px 20px;text-align:center;background:rgba(255,255,255,.92)"><p style="margin:0 0 16px;color:#334155;line-height:1.7;font-size:16px">${message}</p></div><div style="background-image:linear-gradient(135deg,#7bad39,#5ddabb);padding:14px 24px;text-align:center">${faviconHtml}<p style="margin:0;color:#ffffff;font-size:16px;line-height:1.4;font-weight:700;letter-spacing:.02em">rma.org.br</p></div></div></div>`;frame.srcdoc=html;modal.classList.add("is-open");});});document.getElementById("rma-email-preview-close").addEventListener("click",function(){modal.classList.remove("is-open");});modal.addEventListener("click",function(e){if(e.target===modal){modal.classList.remove("is-open");}});})();</script>';
    }

    private function sanitize_option(string $option, $value) {
        if ($option === 'rma_maps_only_adimplente') {
            return $value === '1' ? '1' : '0';
        }

        if (in_array($option, ['rma_annual_due_value'], true)) {
            return (string) max(0, (float) $value);
        }

        if (in_array($option, ['rma_annual_dues_product_id'], true)) {
            return (string) max(0, (int) $value);
        }

        if ($option === 'rma_due_day_month') {
            $value = preg_replace('/[^0-9\-]/', '', (string) $value);
            return preg_match('/^\d{2}-\d{2}$/', $value) ? $value : '01-01';
        }

        if ($option === 'rma_institutional_email') {
            return sanitize_email((string) $value);
        }

        if ($option === 'rma_notifications_api_url') {
            return esc_url_raw((string) $value);
        }

        if (in_array($option, ['rma_email_verification_header_image', 'rma_email_verification_logo'], true)) {
            return esc_url_raw((string) $value);
        }

        if (in_array($option, ['rma_email_verification_bg_color', 'rma_email_verification_button_color'], true)) {
            $color = sanitize_hex_color((string) $value);
            return $color ?: '#7bad39';
        }

        if (str_contains($option, '_body') || str_contains($option, '_footer')) {
            return wp_kses_post((string) $value);
        }

        if ($option === 'rma_email_sender_mode') {
            $mode = sanitize_key((string) $value);
            return in_array($mode, ['wp_mail', 'woo_mail'], true) ? $mode : 'wp_mail';
        }

        return sanitize_text_field((string) $value);
    }

    public function render_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap rma-admin-wrap">
          <div class="rma-admin-head">
            <h1>RMA Configurações (Equipe RMA)</h1>
            <p>Parâmetros principais da operação RMA. Campos personalizáveis para ciclo anual, mapa, financeiro e notificações.</p>
          </div>
          <form method="post" action="options.php">
            <?php
            settings_fields(self::OPTION_GROUP);
            do_settings_sections('rma-admin-settings');
            submit_button('Salvar configurações');
            ?>
          </form>
        </div>
        <?php
    }
}

new RMA_Admin_Settings();

function rma_get_anexo2_events(): array {
    return [
        'confirmacao' => [
            'label' => 'Confirmação',
            'timing' => 'imediato',
            'default_subject' => 'Confirmação de abertura do ciclo anual',
            'default_body' => 'Olá {{nome}}, o ciclo anual da anuidade de {{entidade}} foi iniciado. Vencimento: {{vencimento}}.',
        ],
        'aviso_renovacao' => [
            'label' => 'Aviso renovação',
            'timing' => '30 dias',
            'default_subject' => 'Renovação da anuidade em 30 dias',
            'default_body' => 'Sua anuidade vence em 30 dias ({{vencimento}}). Gere seu pagamento para evitar bloqueios.',
        ],
        'lembrete' => [
            'label' => 'Lembrete',
            'timing' => '7 dias',
            'default_subject' => 'Lembrete: anuidade vence em 7 dias',
            'default_body' => 'Este é um lembrete: faltam 7 dias para o vencimento da sua anuidade ({{vencimento}}).',
        ],
        'ultimo_aviso' => [
            'label' => 'Último aviso',
            'timing' => '1 dia',
            'default_subject' => 'Último aviso: anuidade vence amanhã',
            'default_body' => 'Último aviso: sua anuidade vence amanhã ({{vencimento}}). Regularize para manter seus acessos ativos.',
        ],
        'cobranca_confirmada' => [
            'label' => 'Cobrança confirmada',
            'timing' => 'imediato',
            'default_subject' => 'Pagamento confirmado com sucesso',
            'default_body' => 'Recebemos a confirmação do seu pagamento. Status atual: {{status}}. Obrigado por manter a entidade regular.',
        ],
        'pos_vencimento' => [
            'label' => 'Pós-vencimento',
            'timing' => 'após vencimento',
            'default_subject' => 'Anuidade em atraso',
            'default_body' => 'Identificamos pendência na sua anuidade (vencimento: {{vencimento}}). Gere seu pagamento para regularizar o status da entidade.',
        ],
    ];
}

function rma_is_annual_cycle_open(): bool {
    $day_month = (string) get_option('rma_due_day_month', '01-01');
    if (! preg_match('/^(\d{2})-(\d{2})$/', $day_month, $matches)) {
        return true;
    }

    $day = (int) $matches[1];
    $month = (int) $matches[2];
    if (! checkdate($month, $day, (int) gmdate('Y'))) {
        return true;
    }

    $current_md = gmdate('m-d');
    $target_md = sprintf('%02d-%02d', $month, $day);
    return $current_md >= $target_md;
}

function rma_render_verification_email_template(array $context = []): string {
    $context = wp_parse_args($context, [
        'nome' => 'Associado',
        'codigo' => '000000',
        'data' => wp_date('d/m/Y H:i'),
        'empresa' => (string) get_option('rma_email_verification_company', 'RMA'),
    ]);

    $body = (string) get_option('rma_email_verification_body', 'Utilize o código abaixo para confirmar seu acesso à plataforma RMA.');
    $body = strtr($body, [
        '{{nome}}' => (string) $context['nome'],
        '{{codigo}}' => (string) $context['codigo'],
        '{{data}}' => (string) $context['data'],
        '{{empresa}}' => (string) $context['empresa'],
    ]);

    return rma_render_email_shell('Verificação em 2 fatores', 'Proteja seu acesso', $body, '<div style="display:inline-block;margin:0 auto 16px;padding:16px 24px;border-radius:14px;background:rgba(255,255,255,.96);border:1px solid #dbe7f3;color:#0f172a;font-size:34px;letter-spacing:9px;font-weight:800;box-shadow:0 10px 24px rgba(15,23,42,.08);">' . esc_html((string) $context['codigo']) . '</div><p style="margin:0 0 16px;color:#64748b;font-size:13px;line-height:1.5;">Este código expira em poucos minutos. Nunca compartilhe com terceiros.</p>');
}

function rma_render_anexo2_email_template(string $event_key, array $context = []): string {
    $events = rma_get_anexo2_events();
    if (! isset($events[$event_key])) {
        $event_key = 'confirmacao';
    }

    $entity_name = (string) ($context['entidade'] ?? 'Entidade RMA');
    $defaults = [
        'nome' => 'Associado',
        'entidade' => $entity_name,
        'vencimento' => (string) ($context['vencimento'] ?? '—'),
        'link_pagamento' => (string) ($context['link_pagamento'] ?? apply_filters('rma_checkout_url', home_url('/checkout/'))),
        'valor' => (string) ($context['valor'] ?? ''),
        'status' => (string) ($context['status'] ?? 'adimplente'),
        'data' => wp_date('d/m/Y H:i'),
        'empresa' => (string) get_option('rma_email_verification_company', 'RMA'),
    ];
    $context = wp_parse_args($context, $defaults);

    $subject = (string) get_option('rma_email_anexo2_' . $event_key . '_subject', $events[$event_key]['default_subject']);
    $body = (string) get_option('rma_email_anexo2_' . $event_key . '_body', $events[$event_key]['default_body']);
    $body = strtr($body, [
        '{{nome}}' => (string) $context['nome'],
        '{{entidade}}' => (string) $context['entidade'],
        '{{vencimento}}' => (string) $context['vencimento'],
        '{{link_pagamento}}' => (string) $context['link_pagamento'],
        '{{valor}}' => (string) $context['valor'],
        '{{status}}' => (string) $context['status'],
        '{{data}}' => (string) $context['data'],
        '{{empresa}}' => (string) $context['empresa'],
    ]);

    $cta = '<div style="margin:8px 0 0;"><a href="' . esc_url((string) $context['link_pagamento']) . '" style="display:inline-block;text-decoration:none;background-image:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;padding:12px 24px;border-radius:999px;font-weight:700;font-size:14px;">Acessar pagamento</a></div>';

    return rma_render_email_shell('ANEXO 2 • ' . $events[$event_key]['label'], $subject, $body, $cta);
}

function rma_render_email_shell(string $eyebrow, string $title, string $body_html, string $extra_html = ''): string {
    $logo = trim((string) get_option('rma_email_verification_logo', ''));
    if ($logo === '') {
        $logo = 'https://www.agenciadigitalsaopaulo.com.br/rma/wp-content/uploads/2021/02/logo-.png';
    }

    $favicon = 'https://www.agenciadigitalsaopaulo.com.br/rma/wp-content/uploads/2021/02/favicon.png';

    ob_start();
    ?>
    <div style="background:#ffffff;padding:24px 12px;font-family:'Maven Pro','Segoe UI',Arial,sans-serif;">
      <div style="max-width:520px;margin:0 auto;background:rgba(255,255,255,.94);border-radius:20px;overflow:hidden;border:1px solid #e9eef3;box-shadow:0 16px 42px rgba(15,23,42,.10);">
        <div style="background-image:linear-gradient(135deg,#7bad39,#5ddabb);padding:24px 20px;text-align:center;color:#fff;">
          <?php if ($logo) : ?><img src="<?php echo esc_url($logo); ?>" alt="Logo RMA" style="display:block;max-width:180px;width:100%;height:auto;margin:0 auto 14px;" /><?php endif; ?>
          <p style="margin:0;font-size:12px;line-height:1.4;letter-spacing:.12em;text-transform:uppercase;font-weight:700;opacity:.95;color:#fff;text-align:center;"><?php echo esc_html($eyebrow); ?></p>
          <h2 style="margin:8px 0 0;font-size:30px;line-height:1.1;font-weight:800;color:#ffffff;text-align:center;"><?php echo esc_html($title); ?></h2>
        </div>
        <div style="padding:26px 24px 20px;text-align:center;background:rgba(255,255,255,.92);">
          <p style="margin:0 0 16px;color:#334155;line-height:1.7;font-size:16px;"><?php echo wp_kses_post($body_html); ?></p>
          <?php echo $extra_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <div style="background-image:linear-gradient(135deg,#7bad39,#5ddabb);padding:14px 24px;text-align:center;">
          <img src="<?php echo esc_url($favicon); ?>" alt="RMA" style="display:block;max-width:20px;width:20px;height:20px;margin:0 auto 6px;" />
          <p style="margin:0;color:#ffffff;font-size:16px;line-height:1.4;font-weight:700;letter-spacing:.02em;">rma.org.br</p>
        </div>
      </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

function rma_send_anexo2_email(string $event_key, string $email, array $context = []): bool {
    if (! is_email($email)) {
        return false;
    }

    $events = rma_get_anexo2_events();
    if (! isset($events[$event_key])) {
        return false;
    }

    $subject = (string) get_option('rma_email_anexo2_' . $event_key . '_subject', $events[$event_key]['default_subject']);
    $message = rma_render_anexo2_email_template($event_key, $context);
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    $sender_mode = (string) get_option('rma_email_sender_mode', 'wp_mail');
    if ($sender_mode === 'woo_mail' && function_exists('WC') && WC() && method_exists(WC(), 'mailer')) {
        $mailer = WC()->mailer();
        if ($mailer) {
            $wrapped = method_exists($mailer, 'wrap_message') ? $mailer->wrap_message($subject, $message) : $message;
            return (bool) $mailer->send($email, $subject, $wrapped, $headers, []);
        }
    }

    return (bool) wp_mail($email, $subject, $message, $headers);
}

final class RMA_Login_2FA_Gate {
    private const TRANSIENT_PREFIX = 'rma_2fa_login_';
    private const PENDING_PREFIX = 'rma_2fa_pending_';

    public function __construct() {
        // 2FA de login desativado por decisão de produto.
        // Mantemos apenas tratamento de URL legada para evitar exibição do wp-login 2FA.
        add_action('template_redirect', [$this, 'redirect_legacy_2fa_requests'], 1);
    }

    public function redirect_legacy_2fa_requests(): void {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key((string) wp_unslash($_GET['action'])) : '';
        if ($action !== 'rma_2fa') {
            return;
        }

        $target = home_url('/gerenciador/');
        wp_safe_redirect($target);
        exit;
    }

    public function intercept_login($user, string $username, string $password) {
        if (! empty($_POST['rma_2fa_token']) && ! empty($_POST['rma_2fa_code']) && ! empty($_POST['log'])) {
            $login = sanitize_user(wp_unslash($_POST['log']));
            $step_user = get_user_by('login', $login);
            if (! $step_user instanceof WP_User) {
                return new WP_Error('rma_2fa_user_invalid', 'Usuário inválido para validação do 2FA.');
            }

            return $this->validate_second_step($step_user, sanitize_text_field(wp_unslash($_POST['rma_2fa_token'])), sanitize_text_field(wp_unslash($_POST['rma_2fa_code'])));
        }

        if (! $user instanceof WP_User || is_wp_error($user)) {
            return $user;
        }

        if ($this->is_bypass_user($user)) {
            return $user;
        }

        $token = wp_generate_password(32, false, false);
        $code = (string) random_int(100000, 999999);
        set_transient(self::TRANSIENT_PREFIX . $token, [
            'user_id' => (int) $user->ID,
            'code_hash' => wp_hash_password($code),
        ], 10 * MINUTE_IN_SECONDS);

        $email = (string) $user->user_email;
        if (function_exists('rma_render_verification_email_template')) {
            $html = rma_render_verification_email_template([
                'nome' => $user->display_name ?: $user->user_login,
                'codigo' => $code,
            ]);
            wp_mail($email, 'Seu código de verificação', $html, ['Content-Type: text/html; charset=UTF-8']);
        }

        set_transient(self::PENDING_PREFIX . (int) $user->ID, $token, 10 * MINUTE_IN_SECONDS);

        $url = $this->build_2fa_url($token, (int) $user->ID);

        if (! wp_doing_ajax() && ! (defined('REST_REQUEST') && REST_REQUEST)) {
            wp_safe_redirect($url);
            exit;
        }

        return $user;
    }

    private function validate_second_step(WP_User $user, string $token, string $code) {
        $payload = get_transient(self::TRANSIENT_PREFIX . $token);
        if (! is_array($payload) || (int) ($payload['user_id'] ?? 0) !== (int) $user->ID) {
            return new WP_Error('rma_2fa_expired', 'Código expirado. Faça login novamente.');
        }

        if (! wp_check_password($code, (string) ($payload['code_hash'] ?? ''), $user->ID)) {
            return new WP_Error('rma_2fa_invalid', 'Código inválido.');
        }

        delete_transient(self::TRANSIENT_PREFIX . $token);
        delete_transient(self::PENDING_PREFIX . (int) $user->ID);
        return $user;
    }


    public function force_2fa_redirect(string $redirect_to, string $requested_redirect_to, $user): string {
        if (! $user instanceof WP_User || is_wp_error($user) || $this->is_bypass_user($user)) {
            return $redirect_to;
        }

        $token = get_transient(self::PENDING_PREFIX . (int) $user->ID);
        if (! is_string($token) || $token === '') {
            return $redirect_to;
        }

        return $this->build_2fa_url($token, (int) $user->ID);
    }

    public function enforce_pending_2fa(): void {
        if (! is_user_logged_in() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $user = wp_get_current_user();
        if (! $user instanceof WP_User || $this->is_bypass_user($user)) {
            return;
        }

        $token = get_transient(self::PENDING_PREFIX . (int) $user->ID);
        if (! is_string($token) || $token === '') {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key((string) wp_unslash($_GET['action'])) : '';
        if ($action === 'rma_2fa') {
            return;
        }

        $url = $this->build_2fa_url($token, (int) $user->ID);

        wp_safe_redirect($url);
        exit;
    }

    public function maybe_render_frontend_2fa(): void {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        $action = isset($_GET['action']) ? sanitize_key((string) wp_unslash($_GET['action'])) : '';
        if ($action !== 'rma_2fa') {
            return;
        }

        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $user_id = isset($_GET['user']) ? (int) $_GET['user'] : 0;
        $user = $user_id > 0 ? get_user_by('id', $user_id) : false;
        if (! $user instanceof WP_User || $token === '') {
            wp_die('Sessão de 2 fatores inválida.');
        }

        $error_message = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rma_front_2fa_submit'])) {
            check_admin_referer('rma_front_2fa_validate');
            $posted_token = sanitize_text_field((string) wp_unslash($_POST['rma_2fa_token'] ?? ''));
            $code = sanitize_text_field((string) wp_unslash($_POST['rma_2fa_code'] ?? ''));
            $validated = $this->validate_second_step($user, $posted_token, $code);
            if ($validated instanceof WP_User) {
                wp_set_current_user((int) $validated->ID);
                wp_set_auth_cookie((int) $validated->ID, true);
                $redirect = home_url('/dashboard/');
                wp_safe_redirect($redirect);
                exit;
            }
            if (is_wp_error($validated)) {
                $error_message = $validated->get_error_message();
            }
        }

        status_header(200);
        nocache_headers();
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>" />
            <meta name="viewport" content="width=device-width,initial-scale=1" />
            <title>Validação de 2 fatores</title>
            <style>
                body{margin:0;background:#eef2f7;font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;color:#1f2937}
                .rma-2fa-wrap{min-height:100vh;display:grid;place-items:center;padding:24px}
                .rma-2fa-card{width:min(460px,94vw);background:rgba(255,255,255,.88);border:1px solid rgba(255,255,255,.75);border-radius:22px;backdrop-filter:blur(10px);box-shadow:0 20px 50px rgba(15,23,42,.14);padding:26px}
                .rma-2fa-logo{display:block;max-width:180px;margin:0 auto 16px}
                .rma-2fa-title{margin:0 0 6px;font-size:31px;line-height:1.05;color:#17324d;font-weight:800}
                .rma-2fa-sub{margin:0 0 16px;color:#50657a}
                .rma-2fa-input{width:100%;padding:12px 14px;border-radius:12px;border:1px solid #d0deeb;font-size:18px;letter-spacing:4px;box-sizing:border-box}
                .rma-2fa-btn{margin-top:14px;width:100%;padding:12px 14px;border:0;border-radius:12px;background:#7bad39;color:#fff;font-size:16px;font-weight:700;cursor:pointer}
                .rma-2fa-err{margin:0 0 12px;padding:10px 12px;border-radius:10px;background:#ffe9ea;border:1px solid #f7c2c6;color:#ad1f2d}
                .rma-2fa-back{display:block;margin-top:12px;text-align:center;color:#4f46e5;text-decoration:none}
            </style>
        </head>
        <body>
            <div class="rma-2fa-wrap">
                <form class="rma-2fa-card" method="post">
                    <img class="rma-2fa-logo" src="https://www.agenciadigitalsaopaulo.com.br/rma/wp-content/uploads/2021/02/logo-.png" alt="RMA"/>
                    <h1 class="rma-2fa-title">Valide seu acesso</h1>
                    <p class="rma-2fa-sub">Digite o código enviado para <strong><?php echo esc_html($user->user_email); ?></strong>.</p>
                    <?php if ($error_message !== '') : ?><p class="rma-2fa-err"><?php echo esc_html($error_message); ?></p><?php endif; ?>
                    <input class="rma-2fa-input" type="text" name="rma_2fa_code" maxlength="6" required placeholder="000000" />
                    <input type="hidden" name="rma_2fa_token" value="<?php echo esc_attr($token); ?>" />
                    <?php wp_nonce_field('rma_front_2fa_validate'); ?>
                    <button class="rma-2fa-btn" type="submit" name="rma_front_2fa_submit" value="1">Validar e entrar</button>
                    <a class="rma-2fa-back" href="<?php echo esc_url(home_url('/gerenciador/')); ?>">Voltar ao login</a>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    private function build_2fa_url(string $token, int $user_id): string {
        return add_query_arg([
            'action' => 'rma_2fa',
            'token' => rawurlencode($token),
            'user' => $user_id,
        ], wp_login_url());
    }

    public function render_2fa_form(): void {
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        $user_id = isset($_GET['user']) ? (int) $_GET['user'] : 0;
        $user = $user_id > 0 ? get_user_by('id', $user_id) : false;

        if (! $user instanceof WP_User || $token === '') {
            wp_die('Sessão de 2 fatores inválida.');
        }

        login_header('Validação de 2 fatores', '', null);
        echo '<form method="post" action="' . esc_url(wp_login_url()) . '">';
        echo '<p>Digite o código de 6 dígitos enviado para <strong>' . esc_html($user->user_email) . '</strong>.</p>';
        echo '<p><label for="rma_2fa_code">Código</label><input type="text" name="rma_2fa_code" id="rma_2fa_code" class="input" maxlength="6" required /></p>';
        echo '<input type="hidden" name="log" value="' . esc_attr($user->user_login) . '" />';
        echo '<input type="hidden" name="pwd" value="__rma_2fa__" />';
        echo '<input type="hidden" name="rma_2fa_token" value="' . esc_attr($token) . '" />';
        echo '<p class="submit"><button type="submit" class="button button-primary button-large">Validar e entrar</button></p>';
        echo '</form>';
        login_footer();
        exit;
    }

    private function is_bypass_user(WP_User $user): bool {
        return user_can($user, 'manage_options');
    }
}

new RMA_Login_2FA_Gate();
