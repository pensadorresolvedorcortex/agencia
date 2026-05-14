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
        add_action('init', [__CLASS__, 'migrate_wrong_theme_directory'], 1);
        add_action('admin_init', [__CLASS__, 'register_setting']);
                add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

        add_filter('wp_redirect', [__CLASS__, 'filter_redirects'], 999, 2);
        add_filter('registration_redirect', [__CLASS__, 'force_registration_redirect_to_setup'], 20, 1);
        add_filter('login_redirect', [__CLASS__, 'maybe_force_login_redirect_to_setup'], 20, 3);
        add_filter('rest_pre_dispatch', [__CLASS__, 'intercept_rest_routes'], 10, 3);
        add_filter('woocommerce_get_checkout_order_received_url', [__CLASS__, 'filter_checkout_success_redirect'], 10, 2);
        add_action('template_redirect', [__CLASS__, 'force_resume_from_dashboard'], 0);
        add_action('template_redirect', [__CLASS__, 'force_dashboard_after_checkout_finish'], 1);

        add_action('wp_footer', [__CLASS__, 'inject_frontend_relaxations'], 999);
    }

    public static function migrate_wrong_theme_directory(): void {
        $wrong_dir = trailingslashit(WP_CONTENT_DIR) . 'theme';
        $correct_dir = trailingslashit(WP_CONTENT_DIR) . 'themes';

        if (! is_dir($wrong_dir) || ! is_dir($correct_dir)) {
            return;
        }

        $items = array_diff(scandir($wrong_dir) ?: [], ['.', '..']);
        if (! empty($items)) {
            foreach ($items as $item) {
                $from = $wrong_dir . DIRECTORY_SEPARATOR . $item;
                $to = $correct_dir . DIRECTORY_SEPARATOR . $item;

                if (file_exists($to)) {
                    continue;
                }

                @rename($from, $to); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
        }

        $remaining = array_diff(scandir($wrong_dir) ?: [], ['.', '..']);
        if (empty($remaining)) {
            @rmdir($wrong_dir); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
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

    public static function register_rest_routes(): void {
        register_rest_route('rma-flex/v1', '/profile-site', [
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => [__CLASS__, 'rest_get_profile_site'],
        ]);

        register_rest_route('rma-flex/v1', '/profile-site', [
            'methods' => 'POST',
            'permission_callback' => static function (): bool {
                return is_user_logged_in();
            },
            'callback' => [__CLASS__, 'rest_save_profile_site'],
        ]);
    }

    public static function rest_get_profile_site(WP_REST_Request $request): WP_REST_Response {
        $post_id = absint((int) $request->get_param('post_id'));
        if ($post_id <= 0) {
            return new WP_REST_Response(['message' => 'Post inválido.'], 422);
        }

        return new WP_REST_Response([
            'post_id' => $post_id,
            'site_url' => esc_url_raw((string) get_post_meta($post_id, 'rma_site_url', true)),
        ], 200);
    }

    public static function rest_save_profile_site(WP_REST_Request $request): WP_REST_Response {
        $post_id = absint((int) $request->get_param('post_id'));
        if ($post_id <= 0) {
            return new WP_REST_Response(['message' => 'Post inválido.'], 422);
        }

        if (! self::can_manage_profile_post($post_id)) {
            return new WP_REST_Response(['message' => 'Sem permissão para editar este perfil.'], 403);
        }

        $site_url = esc_url_raw((string) $request->get_param('site_url'));
        update_post_meta($post_id, 'rma_site_url', $site_url);

        return new WP_REST_Response([
            'saved' => true,
            'post_id' => $post_id,
            'site_url' => $site_url,
        ], 200);
    }

    private static function can_manage_profile_post(int $post_id): bool {
        if ($post_id <= 0) {
            return false;
        }

        if (current_user_can('edit_others_posts')) {
            return true;
        }

        return (int) get_post_field('post_author', $post_id) === get_current_user_id();
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
        $dashboard_path = untrailingslashit((string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH));
        $is_dashboard_target = $dashboard_path !== '' && untrailingslashit($target_path) === $dashboard_path;

        if ($is_logo_redirect || ($is_account_lock && ! $is_auth_path)) {
            return false;
        }

        if ($is_dashboard_target && is_user_logged_in()) {
            $resume = self::resolve_resume_url_for_user(get_current_user_id());
            $resume_path = (string) wp_parse_url($resume, PHP_URL_PATH);
            if ($resume !== '' && untrailingslashit($resume_path) !== $dashboard_path) {
                return $resume;
            }
        }

        return $location;
    }



    private static function resolve_resume_url_for_user(int $user_id): string {
        if ($user_id > 0 && function_exists('rma_get_onboarding_resume_url')) {
            $resume = (string) rma_get_onboarding_resume_url($user_id);
            if ($resume !== '') {
                return $resume;
            }
        }

        if (function_exists('rma_account_setup_url')) {
            return (string) rma_account_setup_url();
        }

        return (string) home_url('/conta/');
    }

    public static function force_registration_redirect_to_setup(string $redirect_to): string {
        if (! self::is_enabled()) {
            return $redirect_to;
        }

        return self::resolve_resume_url_for_user(get_current_user_id());
    }

    public static function maybe_force_login_redirect_to_setup(string $redirect_to, string $requested_redirect_to, $user): string {
        if (! self::is_enabled() || ! ($user instanceof WP_User)) {
            return $redirect_to;
        }

        if (self::find_entity_id_for_user((int) $user->ID) > 0) {
            return $redirect_to;
        }

        return self::resolve_resume_url_for_user(get_current_user_id());
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


    public static function force_resume_from_dashboard(): void {
        if (! self::is_enabled() || ! is_user_logged_in()) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $request_path = untrailingslashit((string) wp_parse_url($request_uri, PHP_URL_PATH));
        $dashboard_path = untrailingslashit((string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH));

        if ($request_path === '' || $dashboard_path === '' || $request_path !== $dashboard_path) {
            return;
        }

        $resume_url = self::resolve_resume_url_for_user(get_current_user_id());
        $resume_path = untrailingslashit((string) wp_parse_url($resume_url, PHP_URL_PATH));
        if ($resume_url !== '' && $resume_path !== '' && $resume_path !== $dashboard_path) {
            wp_safe_redirect($resume_url);
            exit;
        }
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
        $checkout_url = (string) apply_filters('rma_checkout_url', home_url('/checkout/'));
        $checkout_path = (string) wp_parse_url($checkout_url, PHP_URL_PATH);

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
        $required_fields = [
            'cnpj' => 'CNPJ',
            'razao_social' => 'Razão social',
            'email_contato' => 'E-mail de contato',
            'endereco_correspondencia' => 'Endereço',
            'nome_responsavel_contato_rma' => 'Nome do responsável',
        ];
        foreach ($required_fields as $field_key => $field_label) {
            if (!isset($params[$field_key]) || trim((string) $params[$field_key]) === '') {
                return new WP_REST_Response([
                    'message' => sprintf('%s é obrigatório no cadastro assistido.', $field_label),
                ], 422);
            }
        }
        $cnpj = preg_replace('/\D+/', '', (string) $params['cnpj']);
        if (strlen($cnpj) !== 14) {
            return new WP_REST_Response(['message' => 'CNPJ inválido.'], 422);
        }
        if (!is_email((string) $params['email_contato'])) {
            return new WP_REST_Response(['message' => 'E-mail de contato inválido.'], 422);
        }

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
            (string) wp_parse_url(home_url('/employer/'), PHP_URL_PATH),
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
        $rest_nonce = wp_create_nonce('wp_rest');
        ?>
        <script>
        (function () {
            try {
                var restNonce = <?php echo wp_json_encode($rest_nonce); ?>;
                var restBase = <?php echo wp_json_encode(rest_url('rma-flex/v1/profile-site')); ?>;
                var form = document.getElementById('rma-conta-setup-form');
                var otpCard = document.getElementById('rma-auth-card');
                var main = document.getElementById('rma-onboarding-main');
                var employerForm = document.getElementById('employer_form');
                if (!form && !otpCard && !main && !employerForm) {
                    return;
                }

                if (form) {
                    var keepRequiredNames = ['cnpj', 'razao_social', 'email_contato', 'telefone_contato', 'endereco_correspondencia', 'nome_responsavel_contato_rma', 'whatsapp_responsavel_contato_rma'];
                    form.querySelectorAll('input[required],select[required],textarea[required]').forEach(function (el) {
                        var fieldName = (el.getAttribute('name') || '').replace(/\[\]$/, '');
                        if (keepRequiredNames.indexOf(fieldName) !== -1) {
                            return;
                        }
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

                if (employerForm) {
                    function findLabelByText(pattern) {
                        return Array.prototype.slice.call(employerForm.querySelectorAll('label')).find(function (label) {
                            return pattern.test((label.textContent || '').trim());
                        }) || null;
                    }

                    var emailInput = employerForm.querySelector('input[name=\"emp_email\"]');
                    if (emailInput) {
                        emailInput.removeAttribute('disabled');
                        emailInput.removeAttribute('readonly');
                    }

                    var twitterLabel = findLabelByText(/URL do perfil do Twitter/i);
                    if (twitterLabel) {
                        twitterLabel.textContent = 'URL do perfil do X (Twitter)';
                    }

                    var dribbbleLabel = findLabelByText(/URL do perfil do drib+le/i);
                    if (dribbbleLabel) {
                        var dribbbleGroup = dribbbleLabel.closest('.form-group,.col-md-6,.col-md-12');
                        if (dribbbleGroup) dribbbleGroup.style.display = 'none';
                    }

                    var behanceLabel = findLabelByText(/URL do perfil no Behance/i);
                    if (behanceLabel) {
                        var behanceGroup = behanceLabel.closest('.form-group,.col-md-6,.col-md-12');
                        if (behanceGroup) behanceGroup.style.display = 'none';
                    }

                    employerForm.querySelectorAll('p').forEach(function (p) {
                        var text = (p.textContent || '').trim();
                        if (/N[ãa]o [ée] poss[ií]vel alterar seu endere[çc]o de e-?mail/i.test(text)) {
                            p.textContent = 'E-mail editável no modo assistido.';
                        }
                    });

                    var preserveFields = Array.prototype.slice.call(employerForm.querySelectorAll('input[name],textarea[name],select[name]'));
                    preserveFields.forEach(function (field) {
                        if (!field.name) return;
                        if (field.type === 'password' || field.type === 'file') return;
                        var key = 'rma_flex_original_' + field.name;
                        if (sessionStorage.getItem(key) === null) {
                            sessionStorage.setItem(key, field.value || '');
                        }
                    });

                    employerForm.addEventListener('submit', function () {
                        preserveFields.forEach(function (field) {
                            if (!field.name) return;
                            if (field.type === 'password' || field.type === 'file') return;
                            var key = 'rma_flex_original_' + field.name;
                            var original = sessionStorage.getItem(key);
                            if ((field.value || '').trim() === '' && original && original.trim() !== '') {
                                field.value = original;
                            }
                        });
                    }, {capture: true});

                    var submitBtn = employerForm.querySelector('#employer_profile_btn');
                    var postId = submitBtn ? parseInt(submitBtn.getAttribute('data-post-id') || '0', 10) : 0;
                    var siteField = employerForm.querySelector('input[name=\"rma_site_url\"]');
                    if (!siteField) {
                        var formRows = employerForm.querySelectorAll('.form-row');
                        var lastRow = formRows.length ? formRows[formRows.length - 1] : null;
                        if (lastRow) {
                            var col = document.createElement('div');
                            col.className = 'form-group col-md-6';
                            col.innerHTML = '<label>URL do site</label><input type=\"url\" class=\"form-control\" name=\"rma_site_url\" placeholder=\"https://...\" />';
                            lastRow.appendChild(col);
                            siteField = col.querySelector('input[name=\"rma_site_url\"]');
                        }
                    }

                    function syncSiteUrl() {
                        if (!postId || !siteField) return;
                        fetch(restBase, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': restNonce
                            },
                            body: JSON.stringify({ post_id: postId, site_url: siteField.value || '' }),
                            keepalive: true
                        }).catch(function () {});
                    }

                    if (postId && siteField) {
                        fetch(restBase + '?post_id=' + encodeURIComponent(String(postId)), {
                            credentials: 'same-origin',
                            headers: { 'X-WP-Nonce': restNonce }
                        })
                        .then(function (res) { return res.ok ? res.json() : null; })
                        .then(function (json) {
                            if (json && json.site_url) {
                                siteField.value = json.site_url;
                            }
                        })
                        .catch(function () {});

                        if (submitBtn) {
                            submitBtn.addEventListener('click', syncSiteUrl, {capture: true});
                        }
                        employerForm.addEventListener('submit', syncSiteUrl, {capture: true});
                    }
                }

                function formatBrazilPhone(raw) {
                    var digits = (raw || '').replace(/\D+/g, '');
                    if (digits.length === 11) {
                        return '(' + digits.slice(0, 2) + ') ' + digits.slice(2, 7) + '-' + digits.slice(7);
                    }
                    if (digits.length === 10) {
                        return '(' + digits.slice(0, 2) + ') ' + digits.slice(2, 6) + '-' + digits.slice(6);
                    }
                    return raw;
                }

                document.querySelectorAll('.fr-c-full-details span, .fr-ca-full-details span').forEach(function (labelNode) {
                    var txt = (labelNode.textContent || '').trim();
                    if (!/Telefone\/WhatsApp/i.test(txt)) return;
                    var wrapper = labelNode.closest('.fr-c-full-details,.fr-ca-full-details');
                    if (!wrapper) return;
                    var valueNode = wrapper.querySelector('p');
                    if (!valueNode) return;
                    var formatted = formatBrazilPhone((valueNode.textContent || '').trim());
                    valueNode.textContent = formatted;
                });

                (function attachSiteUrlToPublicProfile() {
                    var bodyClass = document.body ? (document.body.className || '') : '';
                    var match = bodyClass.match(/postid-(\d+)/);
                    var postId = match ? parseInt(match[1], 10) : 0;
                    if (!postId) return;
                    var detailsList = document.querySelector('.fr-c-details .fr-c-more-details ul, .fr-c-details .fr-ca-more-details ul');
                    if (!detailsList) return;

                    fetch(restBase + '?post_id=' + encodeURIComponent(String(postId)), {
                        credentials: 'same-origin',
                        headers: { 'X-WP-Nonce': restNonce }
                    })
                    .then(function (res) { return res.ok ? res.json() : null; })
                    .then(function (json) {
                        if (!json || !json.site_url) return;
                        var detailsWrap = detailsList.closest('.fr-c-more-details,.fr-ca-more-details');
                        var detailClass = detailsWrap && detailsWrap.classList.contains('fr-ca-more-details') ? 'fr-ca-full-details' : 'fr-c-full-details';
                        var li = document.createElement('li');
                        li.innerHTML = '<div class=\"' + detailClass + '\"><span>URL do site</span><p><a href=\"' + String(json.site_url).replace(/\"/g, '&quot;') + '\" target=\"_blank\" rel=\"noopener\">' + String(json.site_url) + '</a></p></div>';
                        detailsList.appendChild(li);
                    })
                    .catch(function () {});
                })();
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
