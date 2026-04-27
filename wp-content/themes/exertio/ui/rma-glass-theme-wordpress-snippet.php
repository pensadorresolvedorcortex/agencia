<?php
/**
 * Functions/snippet completo do fluxo RMA para colar no functions.php.
 *
 * O que entrega:
 * - Enqueue do UI kit + fonte Federo
 * - Shortcode [rma_glass_card_demo]
 * - Shortcode [rma_conta_setup] com formulário atualizado (novos inputs)
 * - Shortcode [rma-minha-conta] para edição cadastral da entidade
 * - Checklist de documentos (PDF, imagem ou Word) com tooltip + upload
 * - Redirect forçado para /conta/ até concluir governança + docs + financeiro
 */

if (! defined('ABSPATH')) {
    exit;
}

function rma_ui_theme_base() {
    $child_path = trailingslashit(get_stylesheet_directory()) . 'ui';
    if (is_dir($child_path)) {
        return [
            'uri'  => trailingslashit(get_stylesheet_directory_uri()) . 'ui',
            'path' => $child_path,
        ];
    }

    $parent_path = trailingslashit(get_template_directory()) . 'ui';
    if (is_dir($parent_path)) {
        return [
            'uri'  => trailingslashit(get_template_directory_uri()) . 'ui',
            'path' => $parent_path,
        ];
    }

    return null;
}

function rma_account_setup_url() {
    return trailingslashit(home_url('/conta/'));
}

function rma_account_setup_path() {
    $path = wp_parse_url(rma_account_setup_url(), PHP_URL_PATH);
    return is_string($path) ? untrailingslashit($path) : '';
}


function rma_theme_image_uri($filename) {
    $filename = ltrim((string) $filename, '/');
    return trailingslashit(get_template_directory_uri()) . 'images/' . $filename;
}

function rma_get_entity_id_by_author($user_id) {
    $query = new WP_Query([
        'post_type'              => 'rma_entidade',
        'post_status'            => ['publish', 'draft', 'pending', 'private'],
        'author'                 => (int) $user_id,
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    $entity_id = ! empty($query->posts) ? (int) $query->posts[0] : 0;
    wp_reset_postdata();

    return $entity_id;
}

function rma_flow_debug_enabled() {
    return defined('WP_DEBUG') && WP_DEBUG && isset($_GET['rma_debug_flow']) && sanitize_text_field((string) $_GET['rma_debug_flow']) === '1';
}

function rma_flow_debug_log($message, array $context = []) {
    if (! rma_flow_debug_enabled()) {
        return;
    }

    if (! empty($context)) {
        $message .= ' | ' . wp_json_encode($context);
    }

    error_log('[RMA_FLOW] ' . $message);
}


function rma_session_bootstrap(): void {
    if (headers_sent()) {
        return;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}
add_action('init', 'rma_session_bootstrap', 1);

function rma_mark_2fa_verified(int $user_id): void {
    rma_session_bootstrap();
    $_SESSION['rma_2fa_verified'] = true;
    $_SESSION['rma_2fa_verified_expires_at'] = time() + (30 * MINUTE_IN_SECONDS);
    update_user_meta($user_id, 'rma_otp_verified_at', time());
}



function rma_clear_2fa_verification_state(?int $user_id = null): void {
    rma_session_bootstrap();

    unset($_SESSION['rma_2fa_verified'], $_SESSION['rma_2fa_verified_expires_at']);

    $target_user_id = $user_id ?: get_current_user_id();
    if ($target_user_id > 0) {
        delete_user_meta($target_user_id, 'rma_otp_verified_at');
    }
}

add_action('wp_logout', function () {
    $user_id = get_current_user_id();
    rma_clear_2fa_verification_state($user_id > 0 ? (int) $user_id : null);
});

add_action('wp_login', function ($user_login, $user) {
    if ($user instanceof WP_User) {
        // Garante sessão limpa para novo login e força 2FA por sessão.
        rma_clear_2fa_verification_state((int) $user->ID);
    }
}, 10, 2);

function rma_is_2fa_verified(int $user_id): bool {
    rma_session_bootstrap();

    if (isset($_SESSION['rma_2fa_verified'], $_SESSION['rma_2fa_verified_expires_at']) && $_SESSION['rma_2fa_verified'] === true) {
        $expires_at = (int) $_SESSION['rma_2fa_verified_expires_at'];
        if ($expires_at > time()) {
            return true;
        }
    }

    $verified_at = (int) get_user_meta($user_id, 'rma_otp_verified_at', true);
    if ($verified_at > 0 && ($verified_at + (30 * MINUTE_IN_SECONDS)) > time()) {
        $_SESSION['rma_2fa_verified'] = true;
        $_SESSION['rma_2fa_verified_expires_at'] = $verified_at + (30 * MINUTE_IN_SECONDS);
        return true;
    }

    unset($_SESSION['rma_2fa_verified'], $_SESSION['rma_2fa_verified_expires_at']);
    return false;
}


function rma_otp_transient_key(int $user_id): string {
    return 'rma_otp_code_' . $user_id;
}

function rma_otp_send_lock_key(int $user_id): string {
    return 'rma_otp_send_lock_' . $user_id;
}

function rma_send_security_email(string $to, string $subject, string $html_message): bool {
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $sender_mode = (string) get_option('rma_email_sender_mode', 'wp_mail');

    if ($sender_mode === 'woo_mail' && function_exists('WC') && WC() && method_exists(WC(), 'mailer')) {
        $mailer = WC()->mailer();
        if ($mailer && method_exists($mailer, 'send')) {
            // Não usar wrap_message para OTP: manter layout custom sem o cabeçalho/container padrão do Woo.
            return (bool) $mailer->send($to, $subject, $html_message, $headers, []);
        }
    }

    return (bool) wp_mail($to, $subject, $html_message, $headers);
}

function rma_send_otp_code_for_user(int $user_id) {
    $user = get_user_by('id', $user_id);
    if (! $user || ! $user->user_email) {
        return new WP_Error('rma_otp_user_invalid', 'Usuário inválido para verificação.');
    }

    if (get_transient(rma_otp_send_lock_key($user_id))) {
        return new WP_Error('rma_otp_rate_limited', 'Aguarde alguns segundos antes de solicitar um novo código.');
    }

    set_transient(rma_otp_send_lock_key($user_id), '1', 30);

    $code = (string) wp_rand(100000, 999999);
    $payload = [
        'code' => $code,
        'expires_at' => time() + (10 * MINUTE_IN_SECONDS),
        'attempts' => 0,
    ];
    set_transient(rma_otp_transient_key($user_id), $payload, 10 * MINUTE_IN_SECONDS);

    $subject = 'Código de verificação de segurança - RMA';
    $message = function_exists('rma_render_verification_email_template')
        ? rma_render_verification_email_template([
            'nome' => (string) $user->display_name,
            'codigo' => $code,
            'data' => wp_date('d/m/Y H:i'),
            'empresa' => (string) get_option('rma_email_verification_company', 'RMA'),
        ])
        : ('Seu código de verificação é: ' . $code);

    $sent = rma_send_security_email((string) $user->user_email, $subject, (string) $message);

    if (! $sent) {
        delete_transient(rma_otp_transient_key($user_id));
        return new WP_Error('rma_otp_send_failed', 'Não foi possível enviar o código no momento. Tente novamente ou verifique sua caixa de spam.');
    }

    return true;
}

add_action('rest_api_init', function () {
    register_rest_route('rma/v1', '/otp/send', [
        'methods' => 'POST',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'callback' => function () {
            $result = rma_send_otp_code_for_user(get_current_user_id());
            if (is_wp_error($result)) {
                return new WP_REST_Response(['message' => $result->get_error_message()], 503);
            }
            return new WP_REST_Response(['sent' => true, 'message' => 'Código enviado para seu email institucional.']);
        },
    ]);


    register_rest_route('rma/v1', '/otp/status', [
        'methods' => 'GET',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'callback' => function () {
            $user_id = get_current_user_id();
            $is_valid = rma_is_2fa_verified($user_id);
            $verified_at = (int) get_user_meta($user_id, 'rma_otp_verified_at', true);
            $valid_until = $verified_at > 0 ? ($verified_at + (30 * MINUTE_IN_SECONDS)) : 0;
            return new WP_REST_Response([
                'verified' => $is_valid,
                'verified_at' => $verified_at,
                'valid_until' => $valid_until,
            ]);
        },
    ]);



    // Compat route para evitar 404 em ambientes com JS legado apontando para /onboarding/status.
    // Evita re-registro quando o plugin rma-core-entities já expõe a mesma rota.
    $routes = rest_get_server()->get_routes();
    if (! isset($routes['/rma/v1/onboarding/status'])) {
        register_rest_route('rma/v1', '/onboarding/status', [
            'methods' => 'GET',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'callback' => function () {
                $user_id = get_current_user_id();
                $entity_id = rma_get_entity_id_by_author($user_id);
                if ($entity_id <= 0) {
                    return new WP_REST_Response([
                        'entity_id' => 0,
                        'governance_status' => 'pendente',
                        'finance_status' => 'pendente',
                        'documentos_status' => 'pendente',
                        'rejected_document_types' => [],
                    ]);
                }

                return new WP_REST_Response([
                    'entity_id' => $entity_id,
                    'governance_status' => (string) get_post_meta($entity_id, 'governance_status', true),
                    'finance_status' => (string) get_post_meta($entity_id, 'finance_status', true),
                    'documentos_status' => (string) get_post_meta($entity_id, 'documentos_status', true),
                    'rejected_document_types' => array_values(array_filter(array_map('sanitize_key', (array) get_post_meta($entity_id, 'documentos_reprovados', true)))),
                ]);
            },
        ]);
    }

    register_rest_route('rma/v1', '/otp/verify', [
        'methods' => 'POST',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'callback' => function (WP_REST_Request $request) {
            $user_id = get_current_user_id();
            $code = preg_replace('/\D+/', '', (string) $request->get_param('code'));

            $payload = get_transient(rma_otp_transient_key($user_id));
            if (! is_array($payload) || empty($payload['code'])) {
                return new WP_REST_Response(['verified' => false, 'message' => 'Código expirado. Solicite um novo envio.'], 410);
            }

            $attempts = (int) ($payload['attempts'] ?? 0) + 1;
            $payload['attempts'] = $attempts;
            set_transient(rma_otp_transient_key($user_id), $payload, max(30, ((int) ($payload['expires_at'] ?? time())) - time()));

            if ($attempts > 5) {
                delete_transient(rma_otp_transient_key($user_id));
                return new WP_REST_Response(['verified' => false, 'message' => 'Muitas tentativas inválidas. Solicite novo código.'], 429);
            }

            if ((int) ($payload['expires_at'] ?? 0) < time()) {
                delete_transient(rma_otp_transient_key($user_id));
                return new WP_REST_Response(['verified' => false, 'message' => 'Código expirado. Solicite novo envio.'], 410);
            }

            if (! hash_equals((string) $payload['code'], (string) $code)) {
                return new WP_REST_Response(['verified' => false, 'message' => 'Código inválido. Digite os 6 dígitos enviados por email.'], 422);
            }

            rma_mark_2fa_verified($user_id);
            delete_transient(rma_otp_transient_key($user_id));

            return new WP_REST_Response(['verified' => true, 'message' => 'Verificação confirmada.']);
        },
    ]);
});

add_action('wp_enqueue_scripts', function () {
    $base = rma_ui_theme_base();
    if (! $base) {
        return;
    }

    $css_file = trailingslashit($base['path']) . 'rma-glass-theme.css';
    $js_file  = trailingslashit($base['path']) . 'rma-glass-theme.js';

    if (file_exists($css_file)) {
        wp_enqueue_style('rma-glass-theme', trailingslashit($base['uri']) . 'rma-glass-theme.css', [], (string) filemtime($css_file));
    }

    if (file_exists($js_file)) {
        wp_enqueue_script('rma-glass-theme-js', trailingslashit($base['uri']) . 'rma-glass-theme.js', [], (string) filemtime($js_file), true);
    }

    wp_enqueue_style('rma-federo-font', 'https://fonts.googleapis.com/css2?family=Federo&display=swap', [], null);
    wp_enqueue_style('rma-mavenpro-font', 'https://fonts.googleapis.com/css2?family=Maven+Pro:wght@400;500;600;700&display=swap', [], null);
});

add_shortcode('rma_glass_card_demo', function () {
    ob_start();
    ?>
    <section class="rma-glass-card" style="margin:20px 0;">
        <span class="rma-badge">RMA • Glasmorphism Ultra White</span>
        <h2 class="rma-glass-title">Card de demonstração</h2>
        <p class="rma-glass-subtitle">Este bloco usa Federo, #7bad39 e #37302c com acabamento translúcido branco.</p>
        <div class="rma-actions">
            <a class="rma-button" href="<?php echo esc_url(rma_account_setup_url()); ?>">Ir para conta</a>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
});

add_shortcode('rma_conta_setup', function () {
    if (! is_user_logged_in()) {
        return '<p>Você precisa estar logado para completar o cadastro da entidade.</p>';
    }

    $required_docs = [
        'ficha_inscricao' => ['label' => 'Ficha de inscrição cadastral', 'tip' => 'Ficha preenchida e assinada. Assinatura gov.br é aceita.'],
        'comprovante_cnpj' => ['label' => 'Comprovante de CNPJ', 'tip' => 'Comprovante de Inscrição e Situação Cadastral (Receita Federal).'],
        'ata_fundacao' => ['label' => 'Ata de fundação', 'tip' => 'Ata registrada ou PDF escaneado legível.'],
        'ata_diretoria' => ['label' => 'Ata da diretoria atual', 'tip' => 'Ata da eleição da diretoria atual.'],
        'estatuto' => ['label' => 'Estatuto e alterações', 'tip' => 'Estatuto e alterações consolidadas.'],
        'relatorio_atividades' => ['label' => 'Relatório de atividades', 'tip' => 'Últimos 2 anos de atividades.'],
        'cartas_recomendacao' => ['label' => '2 cartas de recomendação', 'tip' => 'De organizações filiadas à RMA.'],
    ];

    $current_user_id = get_current_user_id();
    $entity_id = rma_get_entity_id_by_author($current_user_id);
    $otp_verified = rma_is_2fa_verified($current_user_id);
    $rest_base = rest_url('rma/v1');
    $rest_nonce = wp_create_nonce('wp_rest');

    $dashboard_url = home_url('/dashboard/');
    $docs_url = apply_filters('rma_docs_page_url', home_url('/documentos/'));
    $finance_url = apply_filters('rma_finance_page_url', home_url('/financeiro/'));
    $checkout_url = apply_filters('rma_checkout_url', home_url('/checkout/'));
    $annual_product_id = (int) get_option('rma_annual_dues_product_id', 0);
    if ($annual_product_id <= 0) {
        $annual_product_id = (int) get_option('rma_woo_product_id', 0);
    }
    $checkout_payment_url = $annual_product_id > 0
        ? add_query_arg('add-to-cart', $annual_product_id, $checkout_url)
        : $checkout_url;
    $checkout_payment_path = (string) wp_parse_url($checkout_payment_url, PHP_URL_PATH);
    $checkout_payment_query = (string) wp_parse_url($checkout_payment_url, PHP_URL_QUERY);
    $checkout_payment_url_attr = $checkout_payment_path . ($checkout_payment_query !== '' ? ('?' . $checkout_payment_query) : '');
    if ($checkout_payment_url_attr === '') {
        $checkout_payment_url_attr = $checkout_payment_url;
    }
    $layout_tune_css = '<style id="rma-layout-tune">
'
        . '.rma-premium-card,.rma-premium-card--setup{max-width:900px;margin:0 auto;border-radius:18px;background:#fff;border:1px solid #edf1f4;box-shadow:0 16px 40px rgba(0,0,0,.05);padding:28px;}
'
        . '.rma-premium-form{display:grid;gap:16px;}
'
        . '.rma-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;}
'
        . '.rma-grid-3{display:grid;grid-template-columns:1.2fr .6fr .8fr;gap:14px;}
'
        . '.rma-field-label{display:block;font-size:.88rem;color:#5a6472;margin:0 0 6px;}
'
        . '.rma-auth-card{background:#fff;border:1px solid #edf1f4;border-radius:18px;padding:20px;margin:14px 0 18px;}
'
        . '.rma-auth-title{margin:0 0 6px;font-size:1.35rem;font-weight:700;color:#1f2937;text-align:center;}
'
        . '.rma-auth-subtitle{margin:0 0 14px;color:#4b5563;font-size:.95rem;text-align:center;}
'
        . '.rma-otp-grid{display:grid;grid-template-columns:repeat(6,minmax(0,50px));gap:10px;margin:0 auto 12px;justify-content:center;}
'
        . '.rma-otp-input{height:50px;border:1px solid #d7dee7;border-radius:12px;text-align:center;font-size:1.1rem;font-weight:600;outline:none;transition:border-color .2s ease,box-shadow .2s ease;}
'
        . '.rma-otp-input:focus{border-color:#7bad39;box-shadow:0 0 0 3px rgba(123,173,57,.16);}
'
        . '.rma-otp-input.is-error{border-color:#ef4444;}
'
        . '.rma-otp-input.is-success{border-color:#22c55e;}
'
        . '.rma-resend-link{color:#4b5563;font-size:.86rem;text-decoration:none;display:inline-block;margin-top:8px;}
'
        . '.rma-resend-link[aria-disabled="true"]{pointer-events:none;opacity:.55;}
'
        . '.rma-flow-stepper{position:relative;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin:12px 0 14px;--rma-progress:0%;}
'
        . '.rma-flow-stepper::before{content:"";position:absolute;left:8%;right:8%;top:24px;height:4px;border-radius:999px;background:#e9eef3;z-index:0;}
'
        . '.rma-flow-stepper::after{content:"";position:absolute;left:8%;top:24px;height:4px;border-radius:999px;width:var(--rma-progress);background:linear-gradient(135deg,#7bad39,#5ddabb);z-index:1;transition:width .35s ease;}
'
        . '.rma-flow-step{position:relative;z-index:2;background:#fff;border:1px solid #e8edf2;border-radius:12px;padding:10px 14px;box-shadow:0 10px 30px rgba(0,0,0,.06);display:flex;flex-direction:column;align-items:center;gap:8px;text-align:center;transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease,filter .25s ease;}
'
        . '.rma-flow-step:hover{transform:translateY(-2px);box-shadow:0 14px 32px rgba(0,0,0,.1);}
'
        . '.rma-flow-step-figure{width:40px;height:40px;border-radius:12px;background:radial-gradient(circle at 30% 25%,rgba(93,218,187,.35),rgba(123,173,57,.14));display:flex;align-items:center;justify-content:center;box-shadow:inset 0 0 0 1px rgba(123,173,57,.2);}
'
        . '.rma-flow-step-figure svg,.rma-flow-step-figure img{width:40px;height:40px;display:block;}\n'
        . '.rma-flow-step-label{font-size:13px;font-weight:500;color:#475569;line-height:1.1;}
'
        . '.rma-flow-step-badge{position:absolute;top:6px;right:6px;width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;background:#e7edf4;color:#90a0b3;}
'
        . '.rma-flow-step.is-done{border-color:#bfe3b0;}
'
        . '.rma-flow-step.is-done .rma-flow-step-label{color:#2f7d32;}
'
        . '.rma-flow-step.is-done .rma-flow-step-badge{background:#2f7d32;color:#fff;}
'
        . '.rma-flow-step.is-current{border-color:transparent;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,#7bad39,#5ddabb) border-box;transform:translateY(-2px);}
'
        . '.rma-flow-step.is-current .rma-flow-step-figure{transform:scale(1.06);box-shadow:0 8px 20px rgba(93,218,187,.32), inset 0 0 0 1px rgba(93,218,187,.4);animation:rma-step-float 1.8s ease-in-out infinite;}
'
        . '.rma-flow-step.is-current .rma-flow-step-label{color:#0f172a;}
'
        . '.rma-flow-step.is-current .rma-flow-step-badge{background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;}
'
        . '.rma-flow-step.is-locked{background:#f8fafc;border-color:#e5e9ef;filter:saturate(.3);}
'
        . '.rma-flow-step.is-locked .rma-flow-step-label{color:#94a3b8;}
'
        . '.rma-flow-step.is-locked .rma-flow-step-badge{background:#eef2f7;color:#a9b4c2;}
'
        . '@keyframes rma-step-float{0%,100%{transform:translateY(0) scale(1.06);}50%{transform:translateY(-2px) scale(1.06);}}
'
        . '.rma-actions{display:flex;justify-content:space-between;align-items:center;margin-top:18px;gap:10px;width:100%;}\n'
        . '.rma-primary-cta,.rma-nav-actions{display:flex;gap:10px;align-items:center;}\n'
        . '.rma-primary-cta{margin-left:auto;}\n'
        . '.rma-primary-cta .btn-rma-primary{margin-left:auto;}\n'
        . '.rma-auth-actions{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:8px 0 2px;}\n'
        . '.rma-auth-actions .btn-rma-primary{margin-left:auto;}\n'
        . '.rma-auth-card{text-align:center;}\n'
        . '.rma-glass-title{color:#1f2937;font-weight:700;}\n'
        . '.rma-glass-subtitle{color:#4b5563;}\n'
        . '.btn-rma-primary{background:linear-gradient(135deg,#7bad39,#5ddabb)!important;border:none!important;color:#fff!important;font-weight:600;padding:12px 24px;border-radius:14px;transition:all .3s ease;box-shadow:0 8px 18px rgba(0,0,0,.08);}
'
        . '.btn-rma-primary:hover{transform:translateY(-2px);filter:brightness(1.04);box-shadow:0 12px 24px rgba(0,0,0,.12);}
'
        . '.btn-rma-secondary{background:#fff!important;border:1px solid #d7dee7!important;color:#4b5563!important;font-weight:600;padding:12px 20px;border-radius:14px;transition:all .3s ease;}
'
        . '.btn-rma-secondary:hover{transform:translateY(-2px);box-shadow:0 8px 16px rgba(0,0,0,.06);}
'
        . '.rma-modern-dropzone{border:1px dashed #cfd8e3;border-radius:14px;padding:12px;background:#fbfcfd;}
'
        . '.rma-phone-row{display:grid;grid-template-columns:120px 1fr;gap:10px;}\n'
        . '.rma-drop-item{position:relative;}\n'
        . '.rma-dropzone{border:1px dashed #cfd8e3;border-radius:12px;padding:12px;background:#fbfcfd;display:flex;flex-direction:column;gap:8px;}\n'
        . '.rma-dropzone.is-drag{border-color:#7bad39;background:#f4fbef;}\n'
        . '.rma-file-preview{font-size:.82rem;color:#4b5563;word-break:break-word;}\n'
        . '@media(max-width:860px){.rma-grid-2,.rma-grid-3{grid-template-columns:1fr;}.rma-flow-stepper{display:flex;overflow-x:auto;padding-bottom:6px;scroll-snap-type:x mandatory;}.rma-flow-step{min-width:132px;scroll-snap-align:start;}.rma-flow-stepper::before,.rma-flow-stepper::after{display:none;}.rma-otp-grid{grid-template-columns:repeat(6,minmax(0,1fr));}}\n'
        . '@media(max-width:600px){.rma-actions{flex-direction:column;gap:10px;align-items:stretch;}.rma-primary-cta,.rma-nav-actions{width:100%;}.rma-primary-cta .btn-rma-primary,.rma-nav-actions .btn-rma-secondary{width:100%;}.rma-primary-hint,#rma-primary-hint{text-align:left!important;}}\n'
        . '</style>';

    if ($entity_id > 0) {
        $governance = (string) get_post_meta($entity_id, 'governance_status', true);
        $finance = (string) get_post_meta($entity_id, 'finance_status', true);
        $docs_status = (string) get_post_meta($entity_id, 'documentos_status', true);
        $all_steps_done = ($governance === 'aprovado' && $finance === 'adimplente' && $docs_status === 'enviado');

        $docs_reupload_statuses = ['rejeitado', 'negado', 'pendente_reenvio', 'correcao', 'reprovado'];
        $docs_upload_sent = ($docs_status === 'enviado');
        $show_docs_upload = (! $docs_upload_sent) || in_array($docs_status, $docs_reupload_statuses, true);

        $rejected_document_types = get_post_meta($entity_id, 'documentos_reprovados', true);
        $rejected_document_types = is_array($rejected_document_types)
            ? array_values(array_filter(array_map('sanitize_key', $rejected_document_types)))
            : [];

        $docs_to_render = $required_docs;
        $docs_approved_statuses = ['aprovado', 'validado', 'aceito'];
        $docs_approved = in_array($docs_status, $docs_approved_statuses, true);
        $docs_waiting_approval = ($docs_status === 'enviado' && empty($rejected_document_types));

        if ($show_docs_upload && ! empty($rejected_document_types)) {
            $docs_to_render = [];
            foreach ($rejected_document_types as $doc_key) {
                if (isset($required_docs[$doc_key])) {
                    $docs_to_render[$doc_key] = $required_docs[$doc_key];
                }
            }
            if (empty($docs_to_render)) {
                $docs_to_render = $required_docs;
            }
        }

        ob_start();
        ?>
        <?php echo $layout_tune_css; ?>
        <section class="rma-glass-card rma-premium-card" style="margin:20px 0;">
            <h2 class="rma-glass-title">Central de Ativação RMA</h2>
            <p class="rma-glass-subtitle">Finalize as etapas pendentes para liberar seu ambiente exclusivo na plataforma RMA.</p>

            <section class="rma-auth-card" id="rma-auth-card">
                <h3 class="rma-auth-title">Confirmação de Identidade</h3>
                <p class="rma-auth-subtitle">Para sua segurança, valide o código enviado ao seu email institucional.</p>
                <div class="rma-otp-grid" id="rma-otp-grid">
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="0" />
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="1" />
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="2" />
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="3" />
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="4" />
                    <input class="rma-otp-input" inputmode="numeric" maxlength="1" data-otp-index="5" />
                </div>
                <div class="rma-auth-actions">
                    <button class="rma-button btn-rma-secondary" type="button" id="btnVoltar">Voltar</button>
                    <button class="rma-button btn-rma-primary" type="button" id="rma-validate-code">Validar Código</button>
                </div>
                <a href="#" class="rma-resend-link" id="rma-resend-code" aria-disabled="true">Reenviar código em <span id="rma-resend-timer">60</span>s</a>
            </section>

            <div id="rma-onboarding-main" style="display:none;">
            <div class="rma-flow-stepper" id="rma-flow-stepper">
                <div class="rma-flow-step is-done" data-step="1">
                    <span class="rma-flow-step-badge">✓</span>
                    <div class="rma-flow-step-figure"><img src="<?php echo esc_url(rma_theme_image_uri('verificacao.gif')); ?>" alt="Verificação" loading="lazy" /></div>
                    <div class="rma-flow-step-label">Verificação</div>
                </div>
                <div class="rma-flow-step is-done" data-step="2">
                    <span class="rma-flow-step-badge">✓</span>
                    <div class="rma-flow-step-figure"><img src="<?php echo esc_url(rma_theme_image_uri('cadastro.gif')); ?>" alt="Cadastro" loading="lazy" /></div>
                    <div class="rma-flow-step-label">Cadastro</div>
                </div>
                <div class="rma-flow-step is-current" data-step="3">
                    <span class="rma-flow-step-badge">•</span>
                    <div class="rma-flow-step-figure"><img src="<?php echo esc_url(rma_theme_image_uri('documentos.gif')); ?>" alt="Documentos" loading="lazy" /></div>
                    <div class="rma-flow-step-label">Documentos</div>
                </div>
                <div class="rma-flow-step is-locked" data-step="4">
                    <span class="rma-flow-step-badge">○</span>
                    <div class="rma-flow-step-figure"><img src="<?php echo esc_url(rma_theme_image_uri('validacao.gif')); ?>" alt="Validação" loading="lazy" /></div>
                    <div class="rma-flow-step-label">Validação</div>
                </div>
                <div class="rma-flow-step is-locked" data-step="5">
                    <span class="rma-flow-step-badge">○</span>
                    <div class="rma-flow-step-figure"><img src="<?php echo esc_url(rma_theme_image_uri('pagamento.gif')); ?>" alt="Pagamento" loading="lazy" /></div>
                    <div class="rma-flow-step-label">Pagamento</div>
                </div>
            </div>

            <ul style="margin:12px 0 16px 18px;">
                <li><strong>Governança:</strong> <span id="rma-status-governanca"><?php echo esc_html($governance ?: 'pendente'); ?></span></li>
                <li><strong>Documentos:</strong> <span id="rma-status-documentos"><?php echo esc_html($docs_status ?: 'pendente'); ?></span></li>
                <li><strong>Financeiro:</strong> <span id="rma-status-financeiro"><?php echo esc_html($finance ?: 'pendente'); ?></span></li>
            </ul>

            <div style="margin:0 0 14px;">
                <h3 class="rma-premium-section-title">Etapa de documentos</h3>

                <?php if ($show_docs_upload) : ?>
                <?php if (! empty($rejected_document_types)) : ?>
                    <div class="rma-alert" style="margin:8px 0 10px;">
                        A Equipe RMA identificou ajustes necessários. Apenas os itens abaixo precisam ser reenviados para continuidade da validação.
                    </div>
                <?php endif; ?>

                    <ul class="rma-docs-list rma-modern-dropzone" id="rma-doc-upload-block">
                        <?php foreach ($docs_to_render as $doc_key => $doc_meta) : ?>
                            <li>
                                <label style="display:flex;align-items:center;gap:8px;">
                                    <span><?php echo esc_html($doc_meta['label']); ?></span>
                                    <span title="<?php echo esc_attr($doc_meta['tip']); ?>" style="cursor:help;">ⓘ</span>
                                </label>
                                <div class="rma-drop-item"><label class="rma-dropzone" for="rma-doc-file-<?php echo esc_attr($doc_key); ?>"><span>Arraste e solte ou selecione arquivo</span><input type="file" id="rma-doc-file-<?php echo esc_attr($doc_key); ?>" data-doc-key="<?php echo esc_attr($doc_key); ?>" class="rma-doc-file" accept="application/pdf,image/*,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" /><small class="rma-file-preview" id="rma-preview-<?php echo esc_attr($doc_key); ?>">Nenhum arquivo selecionado</small></label></div>
                                <button class="rma-button rma-doc-upload" type="button" data-doc-key="<?php echo esc_attr($doc_key); ?>">Enviar arquivo</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="rma-alert" id="rma-doc-upload-block" style="margin-top:8px;">
                        <?php echo esc_html($governance === 'aprovado' ? 'Seus documentos foram aceitos pela RMA.' : 'Seus documentos foram recebidos e estão em análise pela Equipe RMA.'); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="rma-actions">
                <div class="rma-nav-actions">
                    <button class="rma-button btn-rma-secondary" type="button" id="rma-back-action">Voltar</button>
                </div>
                <div class="rma-primary-cta">
                    <button class="rma-button btn-rma-primary" type="button" id="btnPagamento" data-checkout-url="<?php echo esc_url($checkout_payment_url_attr); ?>" data-product-id="<?php echo (int) $annual_product_id; ?>" data-rma-pay="1">Avançar para Pagamento</button>
                </div>
            </div>
            <span id="rma-primary-hint" style="display:block;width:100%;text-align:right;font-size:.92rem;color:#6b6b6b;margin-top:6px;"></span>

            <div id="rma-flow-feedback" class="rma-feedback"></div>
            </div>
        </section>

        <script>
        (function () {
            var entityId = <?php echo (int) $entity_id; ?>;
            var base = <?php echo wp_json_encode($rest_base); ?>;
            var nonce = <?php echo wp_json_encode($rest_nonce); ?>;
            var feedback = document.getElementById('rma-flow-feedback');
            var primaryAction = document.getElementById('btnPagamento') || document.getElementById('rma-primary-action');
            var primaryHint = document.getElementById('rma-primary-hint');
            var checkoutUrl = <?php echo wp_json_encode($checkout_url); ?>;
            var checkoutPaymentUrl = <?php echo wp_json_encode($checkout_payment_url); ?>;
            var annualProductId = <?php echo (int) $annual_product_id; ?>;
            var dashboardUrl = <?php echo wp_json_encode($dashboard_url); ?>;
            var stepper = document.getElementById('rma-flow-stepper');
            var backAction = document.getElementById('rma-back-action');
            var authBackButton = document.getElementById('btnVoltar');
            var onboardingMain = document.getElementById('rma-onboarding-main');
            var currentUiStep = 1;
            var isOtpVerified = <?php echo $otp_verified ? 'true' : 'false'; ?>;
            var paymentUnlocked = false;

            function revealMainFlow() {
                var card = document.getElementById('rma-auth-card');
                if (card) card.style.display = 'none';
                if (onboardingMain) onboardingMain.style.display = 'block';
            }

            function sendOtpCode(initial) {
                return fetch(base + '/otp/send', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } })
                    .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                    .then(function (result) {
                        if (!result.ok) {
                            showFeedback((result.json && result.json.message) ? result.json.message : 'Não foi possível enviar o código no momento. Tente novamente ou verifique sua caixa de spam.', false);
                            return false;
                        }
                        if (!initial) showFeedback('Novo código enviado para seu email.', true);
                        return true;
                    })
                    .catch(function () {
                        showFeedback('Não foi possível enviar o código no momento. Tente novamente ou verifique sua caixa de spam.', false);
                        return false;
                    });
            }

            function resolveCheckoutPaymentUrl() {
                var productId = annualProductId > 0 ? annualProductId : 3407;
                if (productId <= 0) {
                    return checkoutUrl;
                }
                return checkoutUrl + (checkoutUrl.indexOf('?') === -1 ? '?' : '&') + 'add-to-cart=' + productId;
            }

            function redirectToCheckout() {
                var paymentUrl = resolveCheckoutPaymentUrl();

                if (!primaryAction) {
                    window.location.href = paymentUrl;
                    return;
                }

                primaryAction.disabled = true;
                primaryAction.textContent = 'Processando pagamento...';
                showFeedback('Processando pagamento... redirecionando para checkout.', true);
                window.location.href = paymentUrl;
            }

            if (authBackButton) {
                authBackButton.addEventListener('click', function () {
                    window.history.back();
                });
            }

            function showFeedback(message, ok) {
                if (!feedback) return;
                feedback.innerHTML = '<div style="padding:10px;border-radius:10px;background:' + (ok ? '#edf9ec' : '#fdecec') + ';">' + message + '</div>';
            }


            function initDropzones(selector) {
                document.querySelectorAll(selector).forEach(function (input) {
                    var zone = input.closest('.rma-dropzone');
                    if (!zone || zone.getAttribute('data-drop-init') === '1') return;
                    zone.setAttribute('data-drop-init', '1');
                    var preview = zone.querySelector('.rma-file-preview');
                    function update() {
                        var file = input.files && input.files[0] ? input.files[0] : null;
                        if (preview) preview.textContent = file ? (file.name + ' • ' + Math.ceil(file.size / 1024) + ' KB') : 'Nenhum arquivo selecionado';
                    }
                    input.addEventListener('change', update);
                    zone.addEventListener('dragover', function (event) { event.preventDefault(); zone.classList.add('is-drag'); });
                    zone.addEventListener('dragleave', function () { zone.classList.remove('is-drag'); });
                    zone.addEventListener('drop', function (event) {
                        event.preventDefault();
                        zone.classList.remove('is-drag');
                        if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]) {
                            input.files = event.dataTransfer.files;
                            update();
                        }
                    });
                });
            }

            function applyStepper(currentStep, doneOverrides) {
                if (!stepper) return;
                currentUiStep = currentStep;
                doneOverrides = Array.isArray(doneOverrides) ? doneOverrides : [];
                var progress = ((Math.max(1, Math.min(5, currentStep)) - 1) / 4) * 84;
                stepper.style.setProperty('--rma-progress', progress + '%');

                stepper.querySelectorAll('.rma-flow-step').forEach(function (item) {
                    var n = parseInt(item.getAttribute('data-step') || '0', 10);
                    var badge = item.querySelector('.rma-flow-step-badge');
                    item.classList.remove('is-done', 'is-current', 'is-locked');
                    if (n < currentStep || doneOverrides.indexOf(n) !== -1) {
                        item.classList.add('is-done');
                        if (badge) badge.textContent = '✓';
                    } else if (n === currentStep) {
                        item.classList.add('is-current');
                        if (badge) badge.textContent = '•';
                    } else {
                        item.classList.add('is-locked');
                        if (badge) badge.textContent = '○';
                    }
                });

                if (backAction) {
                    backAction.disabled = currentStep <= 1;
                }
            }

            function formatStatusLabel(value, context) {
                var raw = String(value || 'pendente');
                var map = {
                    governance: {
                        pendente: 'Pendente',
                        em_analise: 'Em Análise',
                        aprovado: 'Liberado',
                        recusado: 'Recusado',
                        suspenso: 'Suspenso'
                    },
                    documentos: {
                        pendente: 'Pendente',
                        pendente_reenvio: 'Pendente de Reenvio',
                        enviado: 'Enviado',
                        aprovado: 'Aprovado',
                        validado: 'Validado',
                        aceito: 'Aceito'
                    },
                    financeiro: {
                        pendente: 'Pendente',
                        inadimplente: 'Inadimplente',
                        adimplente: 'Adimplente'
                    }
                };

                if (context && map[context] && map[context][raw]) {
                    return map[context][raw];
                }

                return raw.replace(/_/g, ' ').replace(/\b\w/g, function (char) { return char.toUpperCase(); });
            }

            function applyPrimaryAction(payload) {
                if (!primaryAction) return;
                if (!isOtpVerified) {
                    primaryAction.disabled = true;
                    primaryAction.textContent = 'Valide o código para continuar';
                    primaryAction.removeAttribute('data-rma-pay');
                    if (primaryHint) primaryHint.textContent = 'Confirme o código enviado para seu email para liberar as próximas etapas.';
                    return;
                }
                var docs = payload.documentos_status || 'pendente';
                var finance = payload.finance_status || 'pendente';
                var governance = payload.governance_status || 'pendente';
                var rejected = Array.isArray(payload.rejected_document_types) ? payload.rejected_document_types : [];
                var docsApproved = ['aprovado', 'validado', 'aceito'].indexOf(docs) !== -1;
                var docsWaiting = docs === 'enviado' && rejected.length === 0;
                var docsNeedUpload = (docs !== 'enviado' && !docsApproved) || rejected.length > 0;

                paymentUnlocked = false;
                primaryAction.disabled = false;
                primaryAction.textContent = 'Continuar';
                primaryAction.removeAttribute('data-rma-pay');
                primaryAction.onclick = null;
                if (primaryHint) primaryHint.textContent = '';

                if (governance === 'aprovado' && finance === 'adimplente') {
                    applyStepper(5, [5]);
                    primaryAction.textContent = 'Seguir para Central da Entidade';
                    primaryAction.disabled = false;
                    primaryAction.removeAttribute('data-rma-pay');
                    primaryAction.removeAttribute('data-checkout-url');
                    primaryAction.onclick = function () { window.location.assign(dashboardUrl); };
                    if (primaryHint) primaryHint.textContent = 'Tudo concluído. Acesse sua central para acompanhar os próximos passos.';
                    return;
                }

                if (governance === 'suspenso') {
                    applyStepper(4);
                    primaryAction.textContent = 'Acesso Suspenso';
                    primaryAction.disabled = true;
                    primaryAction.removeAttribute('data-rma-pay');
                    primaryAction.removeAttribute('data-checkout-url');
                    if (primaryHint) primaryHint.textContent = 'Sua entidade foi suspensa pela equipe administrativa. Entre em contato com a RMA.';
                    return;
                }

                if (docsNeedUpload) {
                    applyStepper(3);
                    primaryAction.textContent = 'Reenviar Documentos';
                    primaryAction.onclick = function () {
                        var el = document.getElementById('rma-doc-upload-block');
                        if (el) el.scrollIntoView({behavior: 'smooth', block: 'start'});
                    };
                    if (primaryHint) primaryHint.textContent = 'Identificamos ajustes necessários na documentação enviada. Revise as orientações e realize o reenvio para continuidade do processo.';
                    return;
                }

                if (docsWaiting && finance === 'adimplente') {
                    applyStepper(4, [5]);
                    primaryAction.textContent = 'Em Análise';
                    primaryAction.disabled = true;
                    if (primaryHint) primaryHint.textContent = 'Pagamento confirmado. Sua entidade permanece em validação até o parecer final da equipe.';
                    return;
                }

                if (docsWaiting) {
                    applyStepper(4);
                    paymentUnlocked = true;
                    primaryAction.textContent = annualProductId > 0 ? 'Avançar para Pagamento' : 'Ir para Checkout';
                    primaryAction.setAttribute('data-rma-pay', '1');
                    primaryAction.setAttribute('data-checkout-url', checkoutPaymentUrl);
                    primaryAction.onclick = redirectToCheckout;
                    if (primaryHint) primaryHint.textContent = '';
                    return;
                }

                if (docsApproved && finance !== 'adimplente') {
                    applyStepper(4);
                    paymentUnlocked = true;
                    primaryAction.textContent = annualProductId > 0 ? 'Avançar para Pagamento' : 'Ir para Checkout';
                    primaryAction.id = 'btnPagamento';
                    primaryAction.setAttribute('data-rma-pay', '1');
                    primaryAction.setAttribute('data-checkout-url', checkoutPaymentUrl);
                    primaryAction.onclick = redirectToCheckout;
                    if (primaryHint) primaryHint.textContent = 'Próxima etapa: concluir pagamento no checkout.';
                    return;
                }

                if (docsApproved && finance === 'adimplente' && governance !== 'aprovado') {
                    applyStepper(4, [5]);
                    primaryAction.textContent = 'Em Análise';
                    primaryAction.disabled = true;
                    primaryAction.removeAttribute('data-rma-pay');
                    if (primaryHint) primaryHint.textContent = 'Pagamento confirmado. Sua entidade permanece em validação até o parecer final da equipe.';
                    return;
                }

                applyStepper(1);
                primaryAction.textContent = 'Aguardando Status';
                primaryAction.disabled = true;
                if (primaryHint) primaryHint.textContent = 'Acompanhe a etapa de status/governança.';
            }

            function updateState(payload) {
                var g = document.getElementById('rma-status-governanca');
                var d = document.getElementById('rma-status-documentos');
                var f = document.getElementById('rma-status-financeiro');
                if (g) g.textContent = formatStatusLabel(payload.governance_status || 'pendente', 'governance');
                if (d) d.textContent = formatStatusLabel(payload.documentos_status || 'pendente', 'documentos');
                if (f) f.textContent = formatStatusLabel(payload.finance_status || 'pendente', 'financeiro');
                applyPrimaryAction(payload || {});
            }

            function refreshStatus() {
                fetch(base + '/entities/' + entityId + '/status', {
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': nonce }
                })
                .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                .then(function (result) {
                    if (!result.ok) {
                        showFeedback((result.json && result.json.message) ? result.json.message : 'Falha ao atualizar status automaticamente.', false);
                        return;
                    }
                    updateState(result.json || {});
                })
                .catch(function () {
                    showFeedback('Erro de conexão ao atualizar status automaticamente.', false);
                });
            }

            function uploadDoc(docKey, file) {
                if (!file) {
                    showFeedback('Selecione um arquivo antes de enviar.', false);
                    return;
                }

                var data = new FormData();
                data.append('document_type', docKey);
                data.append('file', file);

                showFeedback('Enviando ' + docKey + '...', true);
                fetch(base + '/entities/' + entityId + '/documents', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': nonce },
                    body: data
                })
                .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                .then(function (result) {
                    if (!result.ok) {
                        showFeedback((result.json && result.json.message) ? result.json.message : 'Falha no upload do documento.', false);
                        return;
                    }
                    showFeedback('Documento enviado com sucesso (' + docKey + ').', true);
                    refreshStatus();
                })
                .catch(function () {
                    showFeedback('Erro de conexão no upload.', false);
                });
            }

            initDropzones('.rma-doc-file');

            if (backAction) {
                backAction.addEventListener('click', function () {
                    if (currentUiStep <= 1) {
                        return;
                    }

                    if (currentUiStep <= 2) {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return;
                    }

                    if (document.referrer) {
                        window.history.back();
                        return;
                    }

                    var uploadBlock = document.getElementById('rma-doc-upload-block');
                    if (uploadBlock) uploadBlock.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }


            var otpInputs = Array.prototype.slice.call(document.querySelectorAll('.rma-otp-input'));
            var validateCodeButton = document.getElementById('rma-validate-code');
            var resendCodeLink = document.getElementById('rma-resend-code');
            var resendTimerElement = document.getElementById('rma-resend-timer');
            var resendSeconds = 60;

            function setOtpState(state) {
                otpInputs.forEach(function (input) {
                    input.classList.remove('is-error', 'is-success');
                    if (state) input.classList.add(state);
                });
            }

            function otpCode() {
                return otpInputs.map(function (input) { return (input.value || '').trim(); }).join('');
            }

            otpInputs.forEach(function (input, index) {
                input.addEventListener('input', function () {
                    input.value = (input.value || '').replace(/\D+/g, '').slice(0, 1);
                    if (input.value && otpInputs[index + 1]) otpInputs[index + 1].focus();
                });
                input.addEventListener('keydown', function (event) {
                    if (event.key === 'Backspace' && !input.value && otpInputs[index - 1]) {
                        otpInputs[index - 1].focus();
                    }
                });
                input.addEventListener('paste', function (event) {
                    var text = (event.clipboardData || window.clipboardData).getData('text') || '';
                    var digits = text.replace(/\D+/g, '').slice(0, 6).split('');
                    if (!digits.length) return;
                    event.preventDefault();
                    otpInputs.forEach(function (el, i) { el.value = digits[i] || ''; });
                    var next = otpInputs[Math.min(digits.length, 5)];
                    if (next) next.focus();
                });
            });

            if (validateCodeButton) {
                validateCodeButton.addEventListener('click', function () {
                    if (otpCode().length !== 6) {
                        setOtpState('is-error');
                        showFeedback('Código inválido. Digite os 6 dígitos enviados por email.', false);
                        return;
                    }

                    fetch(base + '/otp/verify', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                        body: JSON.stringify({ code: otpCode() })
                    })
                    .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                    .then(function (result) {
                        if (!result.ok || !result.json || !result.json.verified) {
                            setOtpState('is-error');
                            showFeedback((result.json && result.json.message) ? result.json.message : 'Código inválido. Digite os 6 dígitos enviados por email.', false);
                            return;
                        }

                        isOtpVerified = true;
                        setOtpState('is-success');
                    var card = document.getElementById('rma-auth-card');
                    if (card) card.style.display = 'none';
                    if (onboardingMain) onboardingMain.style.display = 'block';
                        showFeedback('Documentos enviados.', true);
                        refreshStatus();
                    })
                    .catch(function () {
                        setOtpState('is-error');
                        showFeedback('Não foi possível validar o código no momento. Tente novamente.', false);
                    });
                });
            }

            var resendTimerInterval = setInterval(function () {
                resendSeconds = Math.max(0, resendSeconds - 1);
                if (resendTimerElement) resendTimerElement.textContent = String(resendSeconds);
                if (resendSeconds === 0 && resendCodeLink) {
                    resendCodeLink.setAttribute('aria-disabled', 'false');
                    resendCodeLink.textContent = 'Reenviar código';
                    clearInterval(resendTimerInterval);
                }
            }, 1000);

            if (resendCodeLink) {
                resendCodeLink.addEventListener('click', function (event) {
                    if (resendCodeLink.getAttribute('aria-disabled') === 'true') {
                        event.preventDefault();
                        return;
                    }
                    event.preventDefault();
                    resendSeconds = 60;
                    resendCodeLink.setAttribute('aria-disabled', 'true');
                    resendCodeLink.innerHTML = 'Reenviar código em <span id="rma-resend-timer">60</span>s';
                    resendTimerElement = document.getElementById('rma-resend-timer');
                    sendOtpCode(false);
                    resendTimerInterval = setInterval(function () {
                        resendSeconds = Math.max(0, resendSeconds - 1);
                        if (resendTimerElement) resendTimerElement.textContent = String(resendSeconds);
                        if (resendSeconds === 0) {
                            resendCodeLink.setAttribute('aria-disabled', 'false');
                            resendCodeLink.textContent = 'Reenviar código';
                            clearInterval(resendTimerInterval);
                        }
                    }, 1000);
                });
            }


            if (onboardingMain) onboardingMain.style.display = 'none';

            if (isOtpVerified) {
                revealMainFlow();
            }

            fetch(base + '/otp/status', { credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } })
                .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                .then(function (result) {
                    if (result.ok && result.json && result.json.verified) {
                        isOtpVerified = true;
                        revealMainFlow();
                        return;
                    }
                    if (isOtpVerified) {
                        return;
                    }
                    sendOtpCode(true).then(function (sent) {
                        if (sent) showFeedback('Código enviado para seu email institucional.', true);
                    });
                })
                .catch(function () {
                    if (isOtpVerified) {
                        return;
                    }
                    sendOtpCode(true).then(function (sent) {
                        if (sent) showFeedback('Código enviado para seu email institucional.', true);
                    });
                });

            updateState({
                governance_status: <?php echo wp_json_encode($governance); ?>,
                documentos_status: <?php echo wp_json_encode($docs_status); ?>,
                finance_status: <?php echo wp_json_encode($finance); ?>,
                rejected_document_types: <?php echo wp_json_encode($rejected_document_types); ?>
            });

            document.querySelectorAll('.rma-doc-upload').forEach(function (button) {
                button.addEventListener('click', function () {
                    var key = button.getAttribute('data-doc-key');
                    var fileInput = document.querySelector('.rma-doc-file[data-doc-key="' + key + '"]');
                    uploadDoc(key, fileInput && fileInput.files ? fileInput.files[0] : null);
                });
            });

            function pollStatusSilently() {
                fetch(base + '/entities/' + entityId + '/status', {
                    credentials: 'same-origin',
                    headers: { 'X-WP-Nonce': nonce }
                })
                .then(function (res) { return res.ok ? res.json() : Promise.reject(); })
                .then(function (data) { updateState(data || {}); })
                .catch(function () {
                    // polling silencioso para não bloquear o CTA de pagamento
                });
            }

            setInterval(pollStatusSilently, 10000);
        })();
        </script>
        <?php
        return (string) ob_get_clean();
    }

    ob_start();
    ?>
    <?php echo $layout_tune_css; ?>
    <section class="rma-glass-card rma-premium-card rma-premium-card--setup" style="margin:20px 0;">
        <span class="rma-badge">RMA • Conta da Entidade</span>
        <h2 class="rma-glass-title">Complete seu cadastro institucional</h2>
        <p class="rma-glass-subtitle">Valide o CNPJ, confirme dados e envie para análise.</p>

        <form id="rma-conta-setup-form" class="rma-premium-form">
            <div class="rma-cnpj-row">
                <input type="text" id="rma-cnpj" placeholder="CNPJ" required />
                <button class="rma-button rma-button--ghost" type="button" id="rma-buscar-cnpj">Buscar CNPJ</button>
            </div>

            <div class="rma-grid-2">
                <input type="text" id="rma-razao-social" placeholder="Razão social" required />
                <input type="text" id="rma-nome-fantasia" placeholder="Nome fantasia" />
            </div>

            <div class="rma-grid-2">
                <input type="email" id="rma-email" placeholder="E-mail de contato" required />
                <input type="text" id="rma-representante" placeholder="Nome do representante legal" />
            </div>

            <div class="rma-grid-2">
                <input type="text" id="rma-whatsapp-representante-legal" placeholder="WhatsApp do Representante Legal" />
                <input type="text" id="rma-nome-responsavel-contato-rma" placeholder="Nome do responsável pelo contato com a RMA" />
            </div>

            <div class="rma-grid-2">
                <input type="text" id="rma-whatsapp-responsavel-contato-rma" placeholder="WhatsApp do responsável" />
                <input type="text" id="rma-endereco" placeholder="Endereço Principal" />
            </div>

            <div class="rma-grid-2">
                <input type="text" id="rma-endereco-correspondencia" placeholder="Endereço para correspondência" />
                <input type="text" id="rma-bairro" placeholder="Bairro" />
            </div>

            <div class="rma-grid-3">
                <input type="text" id="rma-cidade" placeholder="Cidade" />
                <input type="text" id="rma-uf" placeholder="UF" maxlength="2" />
                <input type="text" id="rma-cep" placeholder="CEP" />
            </div>

            <div class="rma-grid-2">
                <div class="rma-phone-row"><select id="rma-phone-country"><option value="55">🇧🇷 +55</option><option value="1">🇺🇸 +1</option><option value="351">🇵🇹 +351</option><option value="34">🇪🇸 +34</option></select><input type="tel" id="rma-telefone" placeholder="(11) 99999-9999" /></div>
                <div>
                    <label for="rma-data-fundacao" class="rma-field-label">Data de Criação da Entidade</label>
                    <input type="date" id="rma-data-fundacao" />
                </div>
            </div>

            <div class="rma-grid-2">
                <input type="text" id="rma-nome-assessor-imprensa" placeholder="Nome do assessor de imprensa" />
                <input type="email" id="rma-email-assessoria-imprensa" placeholder="E-mail assessoria de imprensa" />
            </div>

            <div class="rma-grid-2">
                <input type="text" id="rma-whatsapp-assessor-imprensa" placeholder="WhatsApp do assessor de imprensa" />
            </div>

            <textarea id="rma-atividades" placeholder="Resumo de atividades (últimos 2 anos)" rows="4"></textarea>

            <div class="rma-docs-block">
                <p class="rma-premium-section-title"><strong>Documentos obrigatórios (PDF, imagem ou Word)</strong></p>
                <ul class="rma-docs-list">
                    <?php foreach ($required_docs as $doc_key => $doc_meta) : ?>
                        <li>
                            <label style="display:flex;align-items:center;gap:8px;">
                                <span><?php echo esc_html($doc_meta['label']); ?></span>
                                <span title="<?php echo esc_attr($doc_meta['tip']); ?>" style="cursor:help;">ⓘ</span>
                            </label>
                            <div class="rma-drop-item"><label class="rma-dropzone" for="rma-pre-doc-file-<?php echo esc_attr($doc_key); ?>"><span>Arraste e solte ou selecione arquivo</span><input type="file" id="rma-pre-doc-file-<?php echo esc_attr($doc_key); ?>" class="rma-pre-doc-file" data-doc-key="<?php echo esc_attr($doc_key); ?>" accept="application/pdf,image/*,.doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" /><small class="rma-file-preview" id="rma-pre-preview-<?php echo esc_attr($doc_key); ?>">Nenhum arquivo selecionado</small></label></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <label><input type="checkbox" id="rma-consent-lgpd" required /> Concordo com LGPD</label>

            <div class="rma-actions rma-actions--left">
                <button class="rma-button" type="submit">Salvar entidade</button>
            </div>
        </form>

        <div id="rma-feedback" class="rma-feedback"></div>
    </section>

    <script>
    (function () {
        var base = <?php echo wp_json_encode($rest_base); ?>;
        var nonce = <?php echo wp_json_encode($rest_nonce); ?>;
        var contaUrl = <?php echo wp_json_encode(rma_account_setup_url()); ?>;

        var form = document.getElementById('rma-conta-setup-form');
        if (!form) return;

        var feedback = document.getElementById('rma-feedback');
        var fields = {
            cnpj: document.getElementById('rma-cnpj'),
            razao_social: document.getElementById('rma-razao-social'),
            nome_fantasia: document.getElementById('rma-nome-fantasia'),
            email_contato: document.getElementById('rma-email'),
            representante: document.getElementById('rma-representante'),
            whatsapp_representante_legal: document.getElementById('rma-whatsapp-representante-legal'),
            nome_responsavel_contato_rma: document.getElementById('rma-nome-responsavel-contato-rma'),
            whatsapp_responsavel_contato_rma: document.getElementById('rma-whatsapp-responsavel-contato-rma'),
            endereco_correspondencia: document.getElementById('rma-endereco-correspondencia'),
            endereco: document.getElementById('rma-endereco'),
            bairro: document.getElementById('rma-bairro'),
            cidade: document.getElementById('rma-cidade'),
            uf: document.getElementById('rma-uf'),
            cep: document.getElementById('rma-cep'),
            phone_country: document.getElementById('rma-phone-country'),
            telefone_contato: document.getElementById('rma-telefone'),
            data_fundacao: document.getElementById('rma-data-fundacao'),
            nome_assessor_imprensa: document.getElementById('rma-nome-assessor-imprensa'),
            email_assessoria_imprensa: document.getElementById('rma-email-assessoria-imprensa'),
            whatsapp_assessor_imprensa: document.getElementById('rma-whatsapp-assessor-imprensa'),
            atividades: document.getElementById('rma-atividades'),
            consent_lgpd: document.getElementById('rma-consent-lgpd')
        };

        if (fields.telefone_contato) {
            fields.telefone_contato.addEventListener('input', function () {
                var country = fields.phone_country ? fields.phone_country.value : '55';
                var digits = normalizePhone(fields.telefone_contato.value);
                fields.telefone_contato.value = formatPhoneByCountry(country, digits);
            });
        }

        initDropzones('.rma-pre-doc-file');

        function cleanCnpj(value) { return (value || '').replace(/\D+/g, ''); }
        function showMessage(message, ok) {
            if (!feedback) return;
            feedback.innerHTML = '<div style="padding:10px;border-radius:10px;background:' + (ok ? '#edf9ec' : '#fdecec') + ';">' + message + '</div>';
        }

        function normalizePhone(raw) {
            return (raw || '').replace(/\D+/g, '');
        }

        function formatPhoneByCountry(country, digits) {
            if (country === '55') {
                if (digits.length >= 11) return '(' + digits.slice(0,2) + ') ' + digits.slice(2,7) + '-' + digits.slice(7,11);
                if (digits.length >= 10) return '(' + digits.slice(0,2) + ') ' + digits.slice(2,6) + '-' + digits.slice(6,10);
            }
            return digits;
        }

        function initDropzones(selector) {
            document.querySelectorAll(selector).forEach(function (input) {
                var zone = input.closest('.rma-dropzone');
                if (!zone) return;
                var preview = zone.querySelector('.rma-file-preview');
                function update() {
                    var file = input.files && input.files[0] ? input.files[0] : null;
                    if (preview) preview.textContent = file ? (file.name + ' • ' + Math.ceil(file.size / 1024) + ' KB') : 'Nenhum arquivo selecionado';
                }
                input.addEventListener('change', update);
                zone.addEventListener('dragover', function (event) { event.preventDefault(); zone.classList.add('is-drag'); });
                zone.addEventListener('dragleave', function () { zone.classList.remove('is-drag'); });
                zone.addEventListener('drop', function (event) {
                    event.preventDefault();
                    zone.classList.remove('is-drag');
                    if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0]) {
                        input.files = event.dataTransfer.files;
                        update();
                    }
                });
            });
        }

        function collectPreSelectedDocs() {
            var items = [];
            document.querySelectorAll('.rma-pre-doc-file').forEach(function (input) {
                if (input.files && input.files[0]) {
                    items.push({ key: input.getAttribute('data-doc-key'), file: input.files[0] });
                }
            });
            return items;
        }

        function uploadPreDocs(entityId, docs) {
            if (!docs.length) {
                return Promise.resolve();
            }

            var chain = Promise.resolve();
            docs.forEach(function (doc) {
                chain = chain.then(function () {
                    var data = new FormData();
                    data.append('document_type', doc.key);
                    data.append('file', doc.file);
                    showMessage('Enviando documento: ' + doc.key + '...', true);
                    return fetch(base + '/entities/' + entityId + '/documents', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-WP-Nonce': nonce },
                        body: data
                    })
                    .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
                    .then(function (result) {
                        if (!result.ok) {
                            throw new Error((result.json && result.json.message) ? result.json.message : 'Falha no upload de ' + doc.key);
                        }
                    });
                });
            });
            return chain;
        }

        document.getElementById('rma-buscar-cnpj').addEventListener('click', function () {
            var cnpj = cleanCnpj(fields.cnpj.value);
            if (!cnpj) {
                showMessage('Informe um CNPJ válido.', false);
                return;
            }

            showMessage('Consultando CNPJ...', true);
            fetch(base + '/cnpj/' + encodeURIComponent(cnpj), {
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': nonce }
            })
            .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
            .then(function (result) {
                if (!result.ok) {
                    showMessage((result.json && result.json.message) ? result.json.message : 'Não foi possível consultar o CNPJ.', false);
                    return;
                }
                fields.razao_social.value = result.json.razao_social || fields.razao_social.value;
                fields.nome_fantasia.value = result.json.nome_fantasia || fields.nome_fantasia.value;
                fields.cidade.value = result.json.cidade || fields.cidade.value;
                fields.uf.value = (result.json.uf || fields.uf.value || '').toUpperCase();
                fields.endereco.value = result.json.logradouro || fields.endereco.value;
                fields.bairro.value = result.json.bairro || fields.bairro.value;
                fields.cep.value = result.json.cep || fields.cep.value;
                showMessage('CNPJ validado e dados preenchidos.', true);
            })
            .catch(function () {
                showMessage('Erro de conexão ao consultar CNPJ.', false);
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!fields.consent_lgpd.checked) {
                showMessage('É obrigatório aceitar LGPD para continuar.', false);
                return;
            }

            var docs = collectPreSelectedDocs();
            var payload = {
                cnpj: cleanCnpj(fields.cnpj.value),
                razao_social: (fields.razao_social.value || '').trim(),
                nome_fantasia: (fields.nome_fantasia.value || '').trim(),
                email_contato: (fields.email_contato.value || '').trim(),
                telefone_contato: '+' + (fields.phone_country ? fields.phone_country.value : '55') + ' ' + (fields.telefone_contato.value || '').trim(),
                endereco_correspondencia: (fields.endereco_correspondencia.value || '').trim(),
                whatsapp_representante_legal: (fields.whatsapp_representante_legal.value || '').trim(),
                nome_responsavel_contato_rma: (fields.nome_responsavel_contato_rma.value || '').trim(),
                whatsapp_responsavel_contato_rma: (fields.whatsapp_responsavel_contato_rma.value || '').trim(),
                nome_assessor_imprensa: (fields.nome_assessor_imprensa.value || '').trim(),
                email_assessoria_imprensa: (fields.email_assessoria_imprensa.value || '').trim(),
                whatsapp_assessor_imprensa: (fields.whatsapp_assessor_imprensa.value || '').trim(),
                cidade: (fields.cidade.value || '').trim(),
                uf: (fields.uf.value || '').trim().toUpperCase(),
                endereco: (fields.endereco.value || '').trim(),
                bairro: (fields.bairro.value || '').trim(),
                cep: (fields.cep.value || '').trim(),
                consent_lgpd: true,
                observacoes: [
                    'Representante legal: ' + (fields.representante.value || '').trim(),
                    'WhatsApp representante legal: ' + (fields.whatsapp_representante_legal.value || '').trim(),
                    'Responsável pelo contato com a RMA: ' + (fields.nome_responsavel_contato_rma.value || '').trim(),
                    'WhatsApp do responsável: ' + (fields.whatsapp_responsavel_contato_rma.value || '').trim(),
                    'Endereço de correspondência: ' + (fields.endereco_correspondencia.value || '').trim(),
                    'Assessor de imprensa: ' + (fields.nome_assessor_imprensa.value || '').trim(),
                    'Email assessoria de imprensa: ' + (fields.email_assessoria_imprensa.value || '').trim(),
                    'WhatsApp assessor de imprensa: ' + (fields.whatsapp_assessor_imprensa.value || '').trim(),
                    'DDI telefone: +' + (fields.phone_country ? fields.phone_country.value : '55'),
                    'Telefone limpo: ' + normalizePhone(fields.telefone_contato.value || ''),
                    'Data de fundação: ' + (fields.data_fundacao.value || '').trim(),
                    'Atividades: ' + (fields.atividades.value || '').trim()
                ].join(' | ')
            };

            showMessage('Salvando entidade...', true);

            fetch(base + '/entities', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify(payload)
            })
            .then(function (res) { return res.json().then(function (json) { return {ok: res.ok, json: json}; }); })
            .then(function (result) {
                if (!result.ok) {
                    showMessage((result.json && result.json.message) ? result.json.message : 'Falha ao salvar entidade.', false);
                    return Promise.reject(new Error('create-failed'));
                }

                var entityId = result.json && (result.json.id || result.json.post_id) ? (result.json.id || result.json.post_id) : 0;
                if (!entityId) {
                    showMessage('Entidade criada, mas não foi possível identificar o ID (id/post_id) para upload dos documentos.', false);
                    return Promise.reject(new Error('missing-id'));
                }

                return uploadPreDocs(entityId, docs).then(function () {
                    showMessage('Entidade criada e documentos enviados. Redirecionando para checkout...', true);
                    setTimeout(function () {
                        window.location.replace(<?php echo wp_json_encode($checkout_url); ?>);
                    }, 900);
                });
            })
            .catch(function (error) {
                if (error && (error.message === 'create-failed' || error.message === 'missing-id')) {
                    return;
                }
                showMessage(error && error.message ? error.message : 'Erro de conexão ao salvar entidade.', false);
            });
        });
    })();
    </script>
    <?php

    return (string) ob_get_clean();
});



add_shortcode('rma-minha-conta', function () {
    if (! is_user_logged_in()) {
        return '<div class="rma-feedback"><div style="padding:10px;border-radius:10px;background:#fdecec;">Você precisa estar logado para editar os dados da entidade.</div></div>';
    }

    $user_id = get_current_user_id();
    $entity_id = rma_get_entity_id_by_author($user_id);
    if ($entity_id <= 0) {
        return '<div class="rma-feedback"><div style="padding:10px;border-radius:10px;background:#fdecec;">Nenhuma entidade vinculada foi encontrada para sua conta.</div></div>';
    }

    $notice = '';
    $notice_ok = false;

    if (isset($_POST['rma_minha_conta_submit'])) {
        $nonce = isset($_POST['rma_minha_conta_nonce']) ? (string) wp_unslash($_POST['rma_minha_conta_nonce']) : '';
        if (! wp_verify_nonce($nonce, 'rma_minha_conta_update_' . $entity_id)) {
            $notice = 'Falha de segurança ao atualizar os dados.';
        } else {
            $email = sanitize_email((string) ($_POST['email_contato'] ?? ''));
            $uf = strtoupper(sanitize_text_field((string) ($_POST['uf'] ?? '')));
            if ($email !== '' && ! is_email($email)) {
                $notice = 'E-mail de contato inválido.';
            } elseif ($uf !== '' && ! preg_match('/^[A-Z]{2}$/', $uf)) {
                $notice = 'UF inválida. Use 2 letras (ex.: SP).';
            } else {
                $fields = [
                    'nome_fantasia' => sanitize_text_field((string) ($_POST['nome_fantasia'] ?? '')),
                    'email_contato' => $email,
                    'telefone_contato' => preg_replace('/[^0-9\+\-\(\)\s]/', '', (string) ($_POST['telefone_contato'] ?? '')),
                    'endereco_correspondencia' => sanitize_text_field((string) ($_POST['endereco_correspondencia'] ?? '')),
                    'whatsapp_representante_legal' => preg_replace('/[^0-9\+\-\(\)\s]/', '', (string) ($_POST['whatsapp_representante_legal'] ?? '')),
                    'nome_responsavel_contato_rma' => sanitize_text_field((string) ($_POST['nome_responsavel_contato_rma'] ?? '')),
                    'whatsapp_responsavel_contato_rma' => preg_replace('/[^0-9\+\-\(\)\s]/', '', (string) ($_POST['whatsapp_responsavel_contato_rma'] ?? '')),
                    'nome_assessor_imprensa' => sanitize_text_field((string) ($_POST['nome_assessor_imprensa'] ?? '')),
                    'email_assessoria_imprensa' => sanitize_email((string) ($_POST['email_assessoria_imprensa'] ?? '')),
                    'whatsapp_assessor_imprensa' => preg_replace('/[^0-9\+\-\(\)\s]/', '', (string) ($_POST['whatsapp_assessor_imprensa'] ?? '')),
                    'cep' => sanitize_text_field((string) ($_POST['cep'] ?? '')),
                    'logradouro' => sanitize_text_field((string) ($_POST['logradouro'] ?? '')),
                    'numero' => sanitize_text_field((string) ($_POST['numero'] ?? '')),
                    'complemento' => sanitize_text_field((string) ($_POST['complemento'] ?? '')),
                    'bairro' => sanitize_text_field((string) ($_POST['bairro'] ?? '')),
                    'cidade' => sanitize_text_field((string) ($_POST['cidade'] ?? '')),
                    'uf' => $uf,
                ];

                foreach ($fields as $key => $val) {
                    update_post_meta($entity_id, $key, $val);
                }

                $descricao = sanitize_textarea_field((string) ($_POST['descricao'] ?? ''));
                wp_update_post([
                    'ID' => $entity_id,
                    'post_content' => $descricao,
                ]);

                $notice = 'Dados da entidade atualizados com sucesso.';
                $notice_ok = true;
            }
        }
    }

    $values = [
        'razao_social' => (string) get_post_meta($entity_id, 'razao_social', true),
        'nome_fantasia' => (string) get_post_meta($entity_id, 'nome_fantasia', true),
        'email_contato' => (string) get_post_meta($entity_id, 'email_contato', true),
        'telefone_contato' => (string) get_post_meta($entity_id, 'telefone_contato', true),
        'endereco_correspondencia' => (string) get_post_meta($entity_id, 'endereco_correspondencia', true),
        'whatsapp_representante_legal' => (string) get_post_meta($entity_id, 'whatsapp_representante_legal', true),
        'nome_responsavel_contato_rma' => (string) get_post_meta($entity_id, 'nome_responsavel_contato_rma', true),
        'whatsapp_responsavel_contato_rma' => (string) get_post_meta($entity_id, 'whatsapp_responsavel_contato_rma', true),
        'nome_assessor_imprensa' => (string) get_post_meta($entity_id, 'nome_assessor_imprensa', true),
        'email_assessoria_imprensa' => (string) get_post_meta($entity_id, 'email_assessoria_imprensa', true),
        'whatsapp_assessor_imprensa' => (string) get_post_meta($entity_id, 'whatsapp_assessor_imprensa', true),
        'cep' => (string) get_post_meta($entity_id, 'cep', true),
        'logradouro' => (string) get_post_meta($entity_id, 'logradouro', true),
        'numero' => (string) get_post_meta($entity_id, 'numero', true),
        'complemento' => (string) get_post_meta($entity_id, 'complemento', true),
        'bairro' => (string) get_post_meta($entity_id, 'bairro', true),
        'cidade' => (string) get_post_meta($entity_id, 'cidade', true),
        'uf' => (string) get_post_meta($entity_id, 'uf', true),
        'descricao' => (string) get_post_field('post_content', $entity_id),
    ];

    ob_start();
    ?>
    <section class="rma-glass-card" style="margin:20px 0;">
        <h2 class="rma-glass-title">Minha Conta da Entidade</h2>
        <p class="rma-glass-subtitle">Atualize endereço e contatos para manter os dados da entidade e do mapa sempre corretos.</p>

        <?php if ($notice !== '') : ?>
            <div class="rma-feedback" style="margin-bottom:12px;">
                <div style="padding:10px;border-radius:10px;background:<?php echo $notice_ok ? '#edf9ec' : '#fdecec'; ?>;">
                    <?php echo esc_html($notice); ?>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" class="rma-grid rma-grid-2" style="gap:12px;">
            <?php wp_nonce_field('rma_minha_conta_update_' . $entity_id, 'rma_minha_conta_nonce'); ?>
            <div>
                <label>Razão social</label>
                <input type="text" value="<?php echo esc_attr($values['razao_social']); ?>" readonly>
            </div>
            <div>
                <label>Nome fantasia</label>
                <input type="text" name="nome_fantasia" value="<?php echo esc_attr($values['nome_fantasia']); ?>">
            </div>
            <div>
                <label>E-mail de contato</label>
                <input type="email" name="email_contato" value="<?php echo esc_attr($values['email_contato']); ?>">
            </div>
            <div>
                <label>Telefone</label>
                <input type="text" name="telefone_contato" value="<?php echo esc_attr($values['telefone_contato']); ?>">
            </div>
            <div>
                <label>Endereço para correspondência</label>
                <input type="text" name="endereco_correspondencia" value="<?php echo esc_attr($values['endereco_correspondencia']); ?>">
            </div>
            <div>
                <label>WhatsApp do representante legal</label>
                <input type="text" name="whatsapp_representante_legal" value="<?php echo esc_attr($values['whatsapp_representante_legal']); ?>">
            </div>
            <div>
                <label>Nome do responsável pelo contato com a RMA</label>
                <input type="text" name="nome_responsavel_contato_rma" value="<?php echo esc_attr($values['nome_responsavel_contato_rma']); ?>">
            </div>
            <div>
                <label>WhatsApp do responsável</label>
                <input type="text" name="whatsapp_responsavel_contato_rma" value="<?php echo esc_attr($values['whatsapp_responsavel_contato_rma']); ?>">
            </div>
            <div>
                <label>Nome do assessor de imprensa</label>
                <input type="text" name="nome_assessor_imprensa" value="<?php echo esc_attr($values['nome_assessor_imprensa']); ?>">
            </div>
            <div>
                <label>E-mail assessoria de imprensa</label>
                <input type="email" name="email_assessoria_imprensa" value="<?php echo esc_attr($values['email_assessoria_imprensa']); ?>">
            </div>
            <div>
                <label>WhatsApp do assessor de imprensa</label>
                <input type="text" name="whatsapp_assessor_imprensa" value="<?php echo esc_attr($values['whatsapp_assessor_imprensa']); ?>">
            </div>
            <div>
                <label>CEP</label>
                <input type="text" name="cep" value="<?php echo esc_attr($values['cep']); ?>">
            </div>
            <div>
                <label>Logradouro</label>
                <input type="text" name="logradouro" value="<?php echo esc_attr($values['logradouro']); ?>">
            </div>
            <div>
                <label>Número</label>
                <input type="text" name="numero" value="<?php echo esc_attr($values['numero']); ?>">
            </div>
            <div>
                <label>Complemento</label>
                <input type="text" name="complemento" value="<?php echo esc_attr($values['complemento']); ?>">
            </div>
            <div>
                <label>Bairro</label>
                <input type="text" name="bairro" value="<?php echo esc_attr($values['bairro']); ?>">
            </div>
            <div>
                <label>Cidade</label>
                <input type="text" name="cidade" value="<?php echo esc_attr($values['cidade']); ?>">
            </div>
            <div>
                <label>UF</label>
                <input type="text" name="uf" maxlength="2" value="<?php echo esc_attr(strtoupper($values['uf'])); ?>">
            </div>
            <div style="grid-column:1 / -1;">
                <label>Descrição institucional</label>
                <textarea name="descricao" rows="4"><?php echo esc_textarea($values['descricao']); ?></textarea>
            </div>
            <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;">
                <button class="rma-button btn-rma-primary" type="submit" name="rma_minha_conta_submit" value="1">Salvar dados da entidade</button>
            </div>
        </form>
    </section>
    <?php
    return (string) ob_get_clean();
});

add_action('template_redirect', function () {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    if (! is_user_logged_in()) {
        return;
    }

    $request_uri  = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = wp_parse_url($request_uri, PHP_URL_PATH);
    $request_path = is_string($request_path) ? untrailingslashit($request_path) : '';

    if ($request_path === '') {
        return;
    }

    $account_path = rma_account_setup_path();
    if ($account_path !== '' && $request_path === $account_path) {
        return;
    }

    foreach (['/login', '/register', '/wp-login.php'] as $safe_suffix) {
        if (substr($request_path, -strlen($safe_suffix)) === $safe_suffix) {
            return;
        }
    }

    $current_user_id = get_current_user_id();
    $entity_id = rma_get_entity_id_by_author($current_user_id);
    $otp_verified = rma_is_2fa_verified($current_user_id);
    if ($entity_id <= 0) {
        rma_flow_debug_log('redirect_no_entity', ['to' => rma_account_setup_url()]);
        wp_safe_redirect(rma_account_setup_url());
        exit;
    }

    $governance = (string) get_post_meta($entity_id, 'governance_status', true);
    $finance = (string) get_post_meta($entity_id, 'finance_status', true);
    $docs_status = (string) get_post_meta($entity_id, 'documentos_status', true);

    $all_steps_done = ($governance === 'aprovado' && $finance === 'adimplente' && $docs_status === 'enviado');
    if ($all_steps_done) {
        return;
    }

    $checkout_path = (string) wp_parse_url(apply_filters('rma_checkout_url', home_url('/checkout/')), PHP_URL_PATH);

    $allowed_paths = array_filter(array_map('untrailingslashit', [
        rma_account_setup_path(),
        (string) wp_parse_url(home_url('/documentos/'), PHP_URL_PATH),
        (string) wp_parse_url(home_url('/financeiro/'), PHP_URL_PATH),
        (string) wp_parse_url(home_url('/status/'), PHP_URL_PATH),
        ($otp_verified ? $checkout_path : ''),
    ]));

    foreach ($allowed_paths as $allowed_path) {
        if ($allowed_path !== '' && $request_path === $allowed_path) {
            return;
        }
    }

    rma_flow_debug_log('redirect_incomplete_flow', [
        'to' => rma_account_setup_url(),
        'entity_id' => $entity_id,
        'governance' => $governance,
        'finance' => $finance,
        'docs_status' => $docs_status,
    ]);
    wp_safe_redirect(rma_account_setup_url());
    exit;
}, 20);

if (! function_exists('rma_theme_get_profile_author_id')) {
    function rma_theme_get_profile_author_id(): int
    {
        $author_id = 0;
        $object_id = get_queried_object_id();

        if ($object_id > 0) {
            $author_id = (int) get_post_field('post_author', $object_id);
        }

        if ($author_id <= 0) {
            $author_id = absint((int) get_query_var('author'));
        }

        return $author_id;
    }
}

add_action('wp_footer', function (): void {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
    $request_path = untrailingslashit($request_path);

    $is_edit_profile = is_user_logged_in()
        && $request_path === untrailingslashit((string) wp_parse_url(home_url('/dashboard/'), PHP_URL_PATH))
        && isset($_GET['ext']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        && sanitize_key((string) $_GET['ext']) === 'edit-profile'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $is_public_profile = (strpos($request_path, '/employer') === 0);
    if (! $is_edit_profile && ! $is_public_profile) {
        return;
    }

    $entity_id = 0;
    if ($is_edit_profile) {
        $entity_id = rma_get_entity_id_by_author(get_current_user_id());
    } elseif ($is_public_profile) {
        $author_id = rma_theme_get_profile_author_id();
        if ($author_id > 0) {
            $entity_id = rma_get_entity_id_by_author($author_id);
        }
    }

    if ($entity_id <= 0) {
        return;
    }

    $site_url = esc_url_raw((string) get_post_meta($entity_id, 'rma_site_url', true));
    $phone = (string) get_post_meta($entity_id, 'telefone_contato', true);
    ?>
    <script>
    (function(){
        var isEditProfile = <?php echo $is_edit_profile ? 'true' : 'false'; ?>;
        var entityId = <?php echo (int) $entity_id; ?>;
        var siteUrl = <?php echo wp_json_encode($site_url); ?>;
        var fallbackPhone = <?php echo wp_json_encode($phone); ?>;
        var restUrl = <?php echo wp_json_encode(esc_url_raw(rest_url('rma/v1/entities/' . (int) $entity_id . '/site-url'))); ?>;
        var nonce = <?php echo wp_json_encode((string) wp_create_nonce('wp_rest')); ?>;

        function formatPhoneBR(raw) {
            var digits = String(raw || '').replace(/\D+/g, '');
            if (digits.length === 11) return digits.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
            if (digits.length === 10) return digits.replace(/^(\d{2})(\d{4})(\d{4})$/, '($1) $2-$3');
            return String(raw || '').trim();
        }

        function applyPhoneMask() {
            var wrappers = document.querySelectorAll('.fr-c-full-details, .fr-ca-full-details');
            wrappers.forEach(function(wrapper){
                var labelNode = wrapper.querySelector('span');
                var valueNode = wrapper.querySelector('p');
                if (!labelNode || !valueNode) return;
                if (!/telefone/i.test((labelNode.textContent || '').trim())) return;
                var current = (valueNode.textContent || '').trim();
                var formatted = formatPhoneBR(current || fallbackPhone);
                if (formatted) valueNode.textContent = formatted;
            });
        }

        function injectPublicSiteUrl() {
            if (!siteUrl) return;
            var list = document.querySelector('.fr-c-details .fr-c-more-details ul, .fr-c-details .fr-ca-more-details ul, .fr-ca-details .fr-ca-more-details ul, .fr-ca-details .fr-c-more-details ul, .fr-ca-more-details ul, .fr-c-more-details ul');
            if (!list || list.querySelector('[data-rma-site-url=\"1\"]')) return;

            var detailsWrap = list.closest('.fr-c-more-details, .fr-ca-more-details');
            var detailClass = (detailsWrap && detailsWrap.classList.contains('fr-ca-more-details')) ? 'fr-ca-full-details' : 'fr-c-full-details';
            var li = document.createElement('li');
            li.setAttribute('data-rma-site-url', '1');
            li.innerHTML = '<div class=\"' + detailClass + '\"><span>URL do site</span><p><a href=\"' + siteUrl.replace(/\"/g, '&quot;') + '\" target=\"_blank\" rel=\"noopener\">' + siteUrl + '</a></p></div>';
            list.appendChild(li);
        }

        function setupEditProfileSiteField() {
            if (!isEditProfile) return;
            var form = document.getElementById('employer_form');
            if (!form) return;

            function findLabelByText(pattern) {
                var labels = Array.prototype.slice.call(form.querySelectorAll('label'));
                return labels.find(function (label) {
                    return pattern.test((label.textContent || '').trim());
                }) || null;
            }

            var emailInput = form.querySelector('input[name=\"emp_email\"]');
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

            form.querySelectorAll('p').forEach(function (p) {
                var text = (p.textContent || '').trim();
                if (/N[ãa]o [ée] poss[ií]vel alterar seu endere[çc]o de e-?mail/i.test(text)) {
                    p.textContent = 'E-mail editável.';
                }
            });

            var preserveFields = Array.prototype.slice.call(form.querySelectorAll('input[name],textarea[name],select[name]'));
            preserveFields.forEach(function (field) {
                if (!field.name) return;
                if (field.type === 'password' || field.type === 'file') return;
                var key = 'rma_profile_original_' + field.name;
                if (sessionStorage.getItem(key) === null) {
                    sessionStorage.setItem(key, field.value || '');
                }
            });

            form.addEventListener('submit', function () {
                preserveFields.forEach(function (field) {
                    if (!field.name) return;
                    if (field.type === 'password' || field.type === 'file') return;
                    var key = 'rma_profile_original_' + field.name;
                    var original = sessionStorage.getItem(key);
                    if ((field.value || '').trim() === '' && original && original.trim() !== '') {
                        field.value = original;
                    }
                });
            }, {capture: true});

            var siteField = form.querySelector('input[name=\"rma_site_url\"]');
            if (!siteField) {
                var target = form.querySelector('.row');
                if (!target) return;
                var col = document.createElement('div');
                col.className = 'col-md-6 col-sm-12';
                col.innerHTML = '<div class=\"form-group\"><label>URL do site</label><input type=\"url\" name=\"rma_site_url\" class=\"form-control\" placeholder=\"https://...\"></div>';
                target.appendChild(col);
                siteField = col.querySelector('input[name=\"rma_site_url\"]');
            }

            if (siteField && siteUrl && !siteField.value) {
                siteField.value = siteUrl;
            }

            form.addEventListener('submit', function(){
                if (!siteField) return;
                fetch(restUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce
                    },
                    body: JSON.stringify({ id: entityId, site_url: siteField.value || '' })
                }).catch(function(){});
            }, { passive: true });
        }

        function boot() {
            applyPhoneMask();
            injectPublicSiteUrl();
            setupEditProfileSiteField();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    })();
    </script>
    <?php
}, 120);
