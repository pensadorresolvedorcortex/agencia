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
        add_action('wp', [__CLASS__, 'disable_theme_onboarding_redirect_hooks'], 1);

        add_filter('wp_redirect', [__CLASS__, 'filter_redirects'], 999, 2);
        add_filter('rest_pre_dispatch', [__CLASS__, 'intercept_rest_routes'], 10, 3);
        add_filter('woocommerce_get_checkout_order_received_url', [__CLASS__, 'filter_checkout_success_redirect'], 10, 2);
        add_action('template_redirect', [__CLASS__, 'force_dashboard_after_checkout_finish'], 1);

        add_action('wp_footer', [__CLASS__, 'inject_frontend_relaxations'], 999);
    }

    public static function disable_theme_onboarding_redirect_hooks(): void {
        if (! self::is_enabled()) {
            return;
        }

        global $wp_filter;
        if (! isset($wp_filter['template_redirect']) || ! ($wp_filter['template_redirect'] instanceof WP_Hook)) {
            return;
        }

        $callbacks = $wp_filter['template_redirect']->callbacks;
        if (! is_array($callbacks) || empty($callbacks)) {
            return;
        }

        foreach ($callbacks as $priority => $group) {
            if (! is_array($group)) {
                continue;
            }

            foreach ($group as $item) {
                $fn = $item['function'] ?? null;
                if (! ($fn instanceof Closure)) {
                    continue;
                }

                try {
                    $ref = new ReflectionFunction($fn);
                } catch (ReflectionException $e) {
                    continue;
                }

                $file = (string) $ref->getFileName();
                $line = (int) $ref->getStartLine();
                $is_theme_functions = $file !== '' && substr(str_replace('\\', '/', $file), -strlen('/themes/exertio/functions.php')) === '/themes/exertio/functions.php';
                $is_rma_block_redirect_closure = $line >= 3021 && $line <= 3180;

                if ($is_theme_functions && $is_rma_block_redirect_closure) {
                    remove_action('template_redirect', $fn, (int) $priority);
                }
            }
        }
    }

    public static function register_setting(): void {
        register_setting('general', self::OPTION_ENABLED, [
            'type' => 'boolean',
            'sanitize_callback' => static function ($value): bool {
                return (bool) rest_sanitize_boolean($value);
            },
            'default' => true,
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
        $stored = get_option(self::OPTION_ENABLED, null);
        $enabled = $stored === null ? true : (bool) rest_sanitize_boolean($stored);

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
        $account_url = function_exists('rma_account_setup_url') ? (string) rma_account_setup_url() : home_url('/conta-da-entidade/');
        $account_path = (string) wp_parse_url($account_url, PHP_URL_PATH);

        $is_logo_redirect = strpos($location, 'rma_logo_required=1') !== false;
        $is_account_lock = $account_path !== '' && untrailingslashit($target_path) === untrailingslashit($account_path);
        $is_auth_path = in_array(untrailingslashit($current_path), [untrailingslashit('/login'), untrailingslashit('/register'), untrailingslashit('/wp-login.php')], true);

        if ($is_logo_redirect || ($is_account_lock && ! $is_auth_path)) {
            return false;
        }

        return $location;
    }

    public static function filter_checkout_success_redirect(string $order_received_url, $order): string {
        if (! self::is_enabled()) {
            return $order_received_url;
        }

        if (! is_user_logged_in()) {
            return $order_received_url;
        }

        $order_id = 0;
        if (class_exists('WC_Order') && $order instanceof WC_Order) {
            $order_id = (int) $order->get_id();
        } elseif (is_object($order) && method_exists($order, 'get_id')) {
            $order_id = (int) $order->get_id();
        }

        return add_query_arg([
            'rma_checkout_done' => '1',
            'rma_order_id' => $order_id > 0 ? $order_id : '',
        ], home_url('/dashboard/'));
    }

    public static function force_dashboard_after_checkout_finish(): void {
        if (! self::is_enabled() || ! is_user_logged_in()) {
            return;
        }

        if (! function_exists('is_checkout')) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $checkout_path = (string) wp_parse_url(home_url('/checkout/'), PHP_URL_PATH);

        $is_checkout_path = $checkout_path !== '' && untrailingslashit($request_path) === untrailingslashit($checkout_path);
        $has_order_received = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('order-received');
        $checkout_done_flag = isset($_GET['rma_checkout_done']) && sanitize_key((string) wp_unslash($_GET['rma_checkout_done'])) === '1';

        if ($checkout_done_flag && $request_path !== '' && untrailingslashit($request_path) === untrailingslashit((string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH))) {
            return;
        }

        if ($has_order_received || ($is_checkout_path && isset($_GET['key']) && strpos((string) wp_unslash($_GET['key']), 'wc_order_') === 0)) {
            wp_safe_redirect(add_query_arg('rma_checkout_done', '1', home_url('/dashboard/')));
            exit;
        }
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

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $allowed_paths = [
            (string) wp_parse_url(home_url('/conta-da-entidade/'), PHP_URL_PATH),
            (string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH),
        ];
        $should_inject = false;
        foreach ($allowed_paths as $allowed_path) {
            if ($allowed_path !== '' && strpos(untrailingslashit($request_path), untrailingslashit($allowed_path)) === 0) {
                $should_inject = true;
                break;
            }
        }

        if (! $should_inject) {
            return;
        }
        ?>
        <script>
        (function () {
            try {
                var form = document.getElementById('rma-conta-setup-form');
                var otpCard = document.getElementById('rma-auth-card');
                var main = document.getElementById('rma-onboarding-main');
                if (!form && !otpCard && !main) {
                    return;
                }

                if (form) {
                    form.querySelectorAll('input[required],select[required],textarea[required]').forEach(function (el) {
                        el.removeAttribute('required');
                    });
                    var consent = document.getElementById('rma-consent-lgpd');
                    if (consent) {
                        consent.checked = true;
                    }
                }

                if (otpCard && main) {
                    otpCard.style.display = 'none';
                    main.style.display = 'block';
                }

                document.querySelectorAll('#rma-logo-required-alert,.rma-logo-required-alert').forEach(function (node) {
                    node.style.display = 'none';
                });

                document.querySelectorAll('.rma-premium-section-title strong').forEach(function (node) {
                    var text = (node.textContent || '').trim();
                    node.textContent = text.replace(/Documentos obrigatórios/i, 'Documentos recomendados no modo assistido');
                });
            } catch (e) {}
        })();
        </script>
        <?php
    }
}

register_activation_hook(__FILE__, static function (): void {
    if (get_option('rma_flex_onboarding_enabled', null) === null) {
        add_option('rma_flex_onboarding_enabled', 1);
    }
});

RMA_Flex_Onboarding::init();
