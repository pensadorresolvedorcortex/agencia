<?php
/**
 * Plugin Name: RMA Flex Onboarding
 * Description: Modo assistido e reversível para pausar bloqueios de onboarding (2FA, obrigatoriedades e logo) durante cadastros assistidos.
 * Version: 0.1.0
 * Author: RMA
 */

if (! defined('ABSPATH')) {
    exit;
}

final class RMA_Flex_Onboarding {
    private const OPTION_ENABLED = 'rma_flex_onboarding_enabled';
    private const CPT = 'rma_entidade';

    public static function init(): void {
        add_action('admin_init', [__CLASS__, 'register_setting']);

        add_filter('wp_redirect', [__CLASS__, 'filter_redirects'], 999, 2);
        add_filter('rest_pre_dispatch', [__CLASS__, 'intercept_rest_routes'], 10, 3);

        add_action('wp_footer', [__CLASS__, 'inject_frontend_relaxations'], 999);
    }

    public static function register_setting(): void {
        register_setting('general', self::OPTION_ENABLED, [
            'type' => 'boolean',
            'sanitize_callback' => static function ($value): bool {
                return (bool) rest_sanitize_boolean($value);
            },
            'default' => false,
        ]);

        add_settings_field(
            self::OPTION_ENABLED,
            'RMA Flex Onboarding',
            [__CLASS__, 'render_setting_field'],
            'general'
        );
    }

    public static function render_setting_field(): void {
        $enabled = self::is_enabled();
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_ENABLED) . '" value="1" ' . checked($enabled, true, false) . '> Ativar modo assistido (pausa bloqueios de onboarding/2FA/logo)</label>';
    }

    private static function is_enabled(): bool {
        $enabled = (bool) get_option(self::OPTION_ENABLED, false);

        if (defined('RMA_FLEX_ONBOARDING_ENABLED')) {
            $enabled = (bool) RMA_FLEX_ONBOARDING_ENABLED;
        }

        return (bool) apply_filters('rma_flex_onboarding/is_enabled', $enabled);
    }

    public static function filter_redirects($location, $status) {
        if (! self::is_enabled() || ! is_string($location) || $location === '') {
            return $location;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $current_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $target_path = (string) wp_parse_url($location, PHP_URL_PATH);
        $account_path = (string) wp_parse_url(home_url('/conta-da-entidade/'), PHP_URL_PATH);

        $is_logo_redirect = strpos($location, 'rma_logo_required=1') !== false;
        $is_account_lock = $account_path !== '' && untrailingslashit($target_path) === untrailingslashit($account_path);
        $is_auth_path = in_array(untrailingslashit($current_path), [untrailingslashit('/login'), untrailingslashit('/register'), untrailingslashit('/wp-login.php')], true);

        if ($is_logo_redirect || ($is_account_lock && ! $is_auth_path)) {
            return false;
        }

        return $location;
    }

    public static function intercept_rest_routes($result, WP_REST_Server $server, WP_REST_Request $request) {
        if (! self::is_enabled()) {
            return $result;
        }

        $route = (string) $request->get_route();
        $method = strtoupper((string) $request->get_method());

        if ($route === '/rma/v1/otp/send' && $method === 'POST') {
            self::mark_2fa_verified(get_current_user_id());
            return new WP_REST_Response([
                'sent' => true,
                'verified' => true,
                'bypassed' => true,
                'message' => 'Modo assistido ativo: validação OTP temporariamente pausada.',
            ], 200);
        }

        if ($route === '/rma/v1/otp/verify' && $method === 'POST') {
            self::mark_2fa_verified(get_current_user_id());
            return new WP_REST_Response([
                'verified' => true,
                'bypassed' => true,
                'message' => 'Modo assistido ativo: OTP marcado como verificado.',
            ], 200);
        }

        if ($route === '/rma/v1/otp/status' && $method === 'GET') {
            self::mark_2fa_verified(get_current_user_id());
            return new WP_REST_Response([
                'verified' => true,
                'bypassed' => true,
                'expires_in' => 1800,
            ], 200);
        }

        if ($route === '/rma/v1/entities' && $method === 'POST') {
            return self::handle_flex_entity_create($request);
        }

        return $result;
    }

    private static function mark_2fa_verified(int $user_id): void {
        if ($user_id <= 0) {
            return;
        }

        if (! session_id()) {
            @session_start(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }

        $_SESSION['rma_2fa_verified'] = true;
        $_SESSION['rma_2fa_verified_expires_at'] = time() + (30 * MINUTE_IN_SECONDS);
        update_user_meta($user_id, 'rma_otp_verified_at', time());
    }

    private static function handle_flex_entity_create(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_REST_Response(['message' => 'Usuário não autenticado.'], 401);
        }

        $params = $request->get_json_params();
        if (! is_array($params) || empty($params)) {
            $params = $request->get_params();
        }
        $params = is_array($params) ? $params : [];

        $existing = self::get_entity_id_by_author($user_id);
        if ($existing > 0) {
            self::save_entity_meta($existing, $params);
            do_action('rma/entity_created', $existing, $params);

            return new WP_REST_Response([
                'post_id' => $existing,
                'status' => (string) get_post_meta($existing, 'governance_status', true),
                'bypassed' => true,
                'message' => 'Modo assistido ativo: entidade existente atualizada.',
            ], 200);
        }

        $user = get_userdata($user_id);
        $title = sanitize_text_field((string) ($params['razao_social'] ?? $params['nome_fantasia'] ?? ($user ? $user->display_name : 'Entidade RMA')));
        if ($title === '') {
            $title = 'Entidade RMA';
        }

        $post_id = wp_insert_post([
            'post_type' => self::CPT,
            'post_status' => 'draft',
            'post_author' => $user_id,
            'post_title' => $title,
            'post_content' => sanitize_textarea_field((string) ($params['descricao'] ?? '')),
        ], true);

        if (is_wp_error($post_id)) {
            return new WP_REST_Response(['message' => 'Falha ao criar entidade em modo assistido.'], 500);
        }

        self::save_entity_meta($post_id, $params);
        update_post_meta($post_id, 'consent_lgpd', true);
        update_post_meta($post_id, 'governance_status', (string) get_post_meta($post_id, 'governance_status', true) ?: 'pendente');
        update_post_meta($post_id, 'finance_status', (string) get_post_meta($post_id, 'finance_status', true) ?: 'pendente');
        update_post_meta($post_id, 'documentos_status', (string) get_post_meta($post_id, 'documentos_status', true) ?: 'pendente');

        do_action('rma/entity_created', $post_id, $params);

        return new WP_REST_Response([
            'post_id' => $post_id,
            'status' => 'pendente',
            'bypassed' => true,
            'message' => 'Modo assistido ativo: entidade criada sem bloqueios rígidos.',
        ], 201);
    }

    private static function save_entity_meta(int $post_id, array $params): void {
        $fields = [
            'cnpj', 'razao_social', 'nome_fantasia', 'cnae_principal', 'email_contato', 'telefone_contato',
            'endereco_correspondencia', 'whatsapp_representante_legal', 'nome_responsavel_contato_rma',
            'whatsapp_responsavel_contato_rma', 'nome_assessor_imprensa', 'email_assessoria_imprensa',
            'whatsapp_assessor_imprensa', 'cep', 'logradouro', 'endereco', 'numero', 'complemento', 'bairro',
            'cidade', 'uf', 'lat', 'lng', 'mandato_fim',
        ];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $params)) {
                continue;
            }

            $value = self::sanitize_meta_value($field, $params[$field]);
            if ($field === 'endereco') {
                update_post_meta($post_id, 'logradouro', $value);
                continue;
            }

            update_post_meta($post_id, $field, $value);
        }
    }

    private static function sanitize_meta_value(string $field, $value) {
        if (in_array($field, ['email_contato', 'email_assessoria_imprensa'], true)) {
            return sanitize_email((string) $value);
        }

        if (in_array($field, ['telefone_contato', 'whatsapp_representante_legal', 'whatsapp_responsavel_contato_rma', 'whatsapp_assessor_imprensa'], true)) {
            return preg_replace('/[^0-9\+\-\(\)\s]/', '', (string) $value);
        }

        if (in_array($field, ['lat', 'lng'], true)) {
            return is_numeric($value) ? (float) $value : 0.0;
        }

        if ($field === 'cnpj') {
            return preg_replace('/\D+/', '', (string) $value);
        }

        return sanitize_text_field((string) $value);
    }

    private static function get_entity_id_by_author(int $user_id): int {
        if ($user_id <= 0) {
            return 0;
        }

        $query = new WP_Query([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'author' => $user_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        return ! empty($query->posts) ? (int) $query->posts[0] : 0;
    }

    public static function inject_frontend_relaxations(): void {
        if (! self::is_enabled() || is_admin()) {
            return;
        }
        ?>
        <script>
        (function () {
            try {
                var form = document.getElementById('rma-conta-setup-form');
                if (form) {
                    form.querySelectorAll('input[required],select[required],textarea[required]').forEach(function (el) {
                        el.removeAttribute('required');
                    });
                    var consent = document.getElementById('rma-consent-lgpd');
                    if (consent) {
                        consent.checked = true;
                    }
                }

                var otpCard = document.getElementById('rma-auth-card');
                var main = document.getElementById('rma-onboarding-main');
                if (otpCard && main) {
                    otpCard.style.display = 'none';
                    main.style.display = 'block';
                }

                document.querySelectorAll('#rma-logo-required-alert,.rma-logo-required-alert').forEach(function (node) {
                    node.style.display = 'none';
                });

                document.querySelectorAll('*').forEach(function (node) {
                    var text = (node.textContent || '').trim();
                    if (/Documentos obrigatórios/i.test(text)) {
                        node.textContent = text.replace(/Documentos obrigatórios/i, 'Documentos recomendados no modo assistido');
                    }
                });
            } catch (e) {}
        })();
        </script>
        <?php
    }
}

RMA_Flex_Onboarding::init();
