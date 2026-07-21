<?php
/**
 * Plugin Name: Blog Privilége
 * Plugin URI: https://example.com/blog-privilege
 * Description: Cria automaticamente artigos de blog sobre sites, SEO, marketing digital, marcas, design, e-commerce e naming com imagem destacada realista gerada por IA, direção de arte premium e controle visual avançado.
 * Version: 4.1.0
 * Author: Blog Privilége
 * License: GPLv2 or later
 * Text Domain: blog-privilege
 */

if (!defined('ABSPATH')) {
    exit;
}

final class BPV_Blog_Privilege {
    const VERSION               = '4.1.0';
    const OPTION_ART_STYLE       = 'bpv_ai_art_style';
    const OPTION_ART_BRAND       = 'bpv_ai_brand_profile';
    const OPTION_ART_AVOID       = 'bpv_ai_avoid_elements';
    const CRON_HOOK             = 'bpv_blog_privilege_generate_post';
    const OPTION_ENABLED        = 'bpv_blog_privilege_enabled';
    const OPTION_INDEX          = 'bpv_blog_privilege_topic_index';
    const OPTION_TOTAL          = 'bpv_blog_privilege_total_posts';
    const OPTION_LAST_RUN       = 'bpv_blog_privilege_last_run';
    const OPTION_LAST_POST      = 'bpv_blog_privilege_last_post_id';
    const OPTION_LAST_ERR       = 'bpv_blog_privilege_last_error';
    const OPTION_CONTENT_HASHES = 'bpv_blog_privilege_content_hashes';
    const OPTION_PHRASE_HASHES  = 'bpv_blog_privilege_phrase_hashes';
    const OPTION_TITLE_HASHES   = 'bpv_blog_privilege_title_hashes';
    const OPTION_IMAGE_LOG      = 'bpv_blog_privilege_image_log';
    const OPTION_DIAGNOSTIC_LOG = 'bpv_blog_privilege_diagnostic_log';
    const LOCK_KEY              = 'bpv_blog_privilege_generation_lock';
    const OPTION_EDITORIAL_ENGINE = 'bpv_editorial_photography_engine';
    const OPTION_SEO_ENGINE = 'bpv_seo_engine_v4';

    public static function init() {
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_interval'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'generate_scheduled_post'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'handle_admin_actions'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'plugin_action_links'));
    }

    public static function activate() {
        self::ensure_terms();

        if (get_option(self::OPTION_ENABLED, null) === null) {
            add_option(self::OPTION_ENABLED, 'yes');
        }
        if (get_option(self::OPTION_INDEX, null) === null) {
            add_option(self::OPTION_INDEX, 0);
        }
        if (get_option(self::OPTION_TOTAL, null) === null) {
            add_option(self::OPTION_TOTAL, 0);
        }
        if (get_option(self::OPTION_CONTENT_HASHES, null) === null) {
            add_option(self::OPTION_CONTENT_HASHES, array(), '', false);
        }
        if (get_option(self::OPTION_PHRASE_HASHES, null) === null) {
            add_option(self::OPTION_PHRASE_HASHES, array(), '', false);
        }
        if (get_option(self::OPTION_TITLE_HASHES, null) === null) {
            add_option(self::OPTION_TITLE_HASHES, array(), '', false);
        }
        if (get_option(self::OPTION_IMAGE_LOG, null) === null) {
            add_option(self::OPTION_IMAGE_LOG, array(), '', false);
        }
        if (get_option(self::OPTION_DIAGNOSTIC_LOG, null) === null) {
            add_option(self::OPTION_DIAGNOSTIC_LOG, array(), '', false);
        }
        if (get_option(self::OPTION_ART_STYLE, null) === null) {
            add_option(self::OPTION_ART_STYLE, 'Fotografia corporativa premium');
        }
        if (get_option(self::OPTION_ART_BRAND, null) === null) {
            add_option(self::OPTION_ART_BRAND, 'Marca premium, autoridade digital e negócios modernos');
        }
        if (get_option(self::OPTION_ART_AVOID, null) === null) {
            add_option(self::OPTION_ART_AVOID, 'imagens genéricas, texto dentro da imagem, aparência artificial');
        }

        if (get_option(self::OPTION_EDITORIAL_ENGINE, null) === null) {
            add_option(self::OPTION_EDITORIAL_ENGINE, 'enabled');
        }

        self::schedule_event();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        delete_transient(self::LOCK_KEY);
    }

    public static function add_cron_interval($schedules) {
        $schedules['bpv_two_minutes'] = array(
            'interval' => 120,
            'display'  => 'A cada 2 minutos - Blog Privilége',
        );
        return $schedules;
    }

    private static function schedule_event() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 120, 'bpv_two_minutes', self::CRON_HOOK);
        }
    }

    private static function unschedule_event() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function plugin_action_links($links) {
        $url = admin_url('admin.php?page=blog-privilege');
        array_unshift($links, '<a href="' . esc_url($url) . '">Painel</a>');
        return $links;
    }

    public static function admin_menu() {
        add_menu_page(
            'Blog Privilége',
            'Blog Privilége',
            'manage_options',
            'blog-privilege',
            array(__CLASS__, 'render_admin_page'),
            'dashicons-welcome-write-blog',
            58
        );
    }

    public static function handle_admin_actions() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (empty($_POST['bpv_blog_privilege_action'])) {
            return;
        }

        check_admin_referer('bpv_blog_privilege_action', 'bpv_blog_privilege_nonce');
        $action = sanitize_key(wp_unslash($_POST['bpv_blog_privilege_action']));

        if ($action === 'save_premium') {
            update_option(self::OPTION_ART_STYLE, sanitize_text_field(wp_unslash($_POST['bpv_art_style'] ?? '')));
            update_option(self::OPTION_ART_BRAND, sanitize_textarea_field(wp_unslash($_POST['bpv_art_brand'] ?? '')));
            update_option(self::OPTION_ART_AVOID, sanitize_textarea_field(wp_unslash($_POST['bpv_art_avoid'] ?? '')));
            add_settings_error('bpv_blog_privilege', 'bpv_saved', 'Configuração visual premium salva.', 'updated');
        }

        if ($action === 'generate_now') {
            $post_id = self::generate_scheduled_post(true);
            if (is_wp_error($post_id)) {
                add_settings_error('bpv_blog_privilege', 'bpv_error', $post_id->get_error_message(), 'error');
            } else {
                add_settings_error('bpv_blog_privilege', 'bpv_success', 'Post criado com sucesso. ID: ' . absint($post_id), 'updated');
            }
        }

        if ($action === 'pause') {
            update_option(self::OPTION_ENABLED, 'no');
            self::unschedule_event();
            add_settings_error('bpv_blog_privilege', 'bpv_paused', 'Geração automática pausada.', 'updated');
        }

        if ($action === 'resume') {
            update_option(self::OPTION_ENABLED, 'yes');
            if (get_option(self::OPTION_EDITORIAL_ENGINE, null) === null) {
            add_option(self::OPTION_EDITORIAL_ENGINE, 'enabled');
        }

        self::schedule_event();
            add_settings_error('bpv_blog_privilege', 'bpv_resumed', 'Geração automática retomada.', 'updated');
        }

        if ($action === 'reschedule') {
            self::unschedule_event();
            if (get_option(self::OPTION_EDITORIAL_ENGINE, null) === null) {
            add_option(self::OPTION_EDITORIAL_ENGINE, 'enabled');
        }

        self::schedule_event();
            add_settings_error('bpv_blog_privilege', 'bpv_rescheduled', 'Agendamento recriado para execução a cada 2 minutos.', 'updated');
        }

        if ($action === 'clear_lock') {
            delete_transient(self::LOCK_KEY);
            update_option(self::OPTION_LAST_ERR, '');
            add_settings_error('bpv_blog_privilege', 'bpv_lock_cleared', 'Trava de geração liberada manualmente.', 'updated');
        }

        if ($action === 'clear_history') {
            update_option(self::OPTION_CONTENT_HASHES, array(), false);
            update_option(self::OPTION_PHRASE_HASHES, array(), false);
            update_option(self::OPTION_TITLE_HASHES, array(), false);
            update_option(self::OPTION_IMAGE_LOG, array(), false);
            update_option(self::OPTION_DIAGNOSTIC_LOG, array(), false);
            add_settings_error('bpv_blog_privilege', 'bpv_cleared', 'Histórico de anti-repetição limpo.', 'updated');
        }
    }

    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        self::ensure_terms();

        $enabled    = get_option(self::OPTION_ENABLED, 'yes');
        $total      = absint(get_option(self::OPTION_TOTAL, 0));
        $index      = absint(get_option(self::OPTION_INDEX, 0));
        $last_run   = get_option(self::OPTION_LAST_RUN, 'Nunca');
        $last_post  = absint(get_option(self::OPTION_LAST_POST, 0));
        $last_error = get_option(self::OPTION_LAST_ERR, '');
        $next       = wp_next_scheduled(self::CRON_HOOK);
        $topics     = self::topics();
        $next_topic = isset($topics[$index % count($topics)]) ? $topics[$index % count($topics)] : $topics[0];
        $photo_engine = function_exists('wp_remote_get') ? 'Openverse + LoremFlickr + Picsum + IA fotográfica' : 'HTTP indisponível';
        $curl       = function_exists('wp_remote_get') ? 'Disponível' : 'Indisponível';
        $history    = is_array(get_option(self::OPTION_CONTENT_HASHES, array())) ? count(get_option(self::OPTION_CONTENT_HASHES, array())) : 0;
        $image_log  = get_option(self::OPTION_IMAGE_LOG, array());
        $last_image = is_array($image_log) && !empty($image_log) ? end($image_log) : 'Nenhuma imagem gerada ainda';
        $diagnostics = get_option(self::OPTION_DIAGNOSTIC_LOG, array());
        $last_diag = is_array($diagnostics) && !empty($diagnostics) ? end($diagnostics) : array();
        $current_lock = get_transient(self::LOCK_KEY);
        $lock_age = $current_lock ? max(0, time() - self::lock_started_at($current_lock)) : 0;

        echo '<style>
        .bpv-saas{margin:18px 20px 0 0;min-height:760px;color:#f8fbff;background:radial-gradient(circle at 8% 0%,rgba(20,184,166,.35),transparent 28%),radial-gradient(circle at 92% 6%,rgba(99,102,241,.38),transparent 32%),linear-gradient(135deg,#07111f 0%,#111827 48%,#020617 100%);border-radius:32px;padding:34px;overflow:hidden;position:relative;box-shadow:0 24px 80px rgba(2,6,23,.35)}.bpv-shell{max-width:1180px;margin:0 auto;position:relative;z-index:1}
        .bpv-saas:before{content:"";position:absolute;inset:0;background:linear-gradient(120deg,rgba(255,255,255,.11),transparent 35%,rgba(255,255,255,.06));pointer-events:none}.bpv-hero,.bpv-card{position:relative;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.22);box-shadow:0 20px 60px rgba(0,0,0,.28),inset 0 1px 0 rgba(255,255,255,.24);backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);border-radius:28px}.bpv-hero{padding:32px;margin-bottom:22px;display:grid;grid-template-columns:1.4fr .6fr;gap:20px}.bpv-kicker{text-transform:uppercase;letter-spacing:.16em;color:#67e8f9;font-size:12px;font-weight:800}.bpv-title{font-size:42px;line-height:1.04;margin:10px 0 12px;color:#fff}.bpv-sub{font-size:15px;max-width:760px;color:#cbd5e1}.bpv-orb{min-height:170px;border-radius:26px;background:radial-gradient(circle at 30% 28%,#fff 0 2%,transparent 3%),linear-gradient(135deg,rgba(34,211,238,.24),rgba(168,85,247,.22));border:1px solid rgba(255,255,255,.18);position:relative;overflow:hidden}.bpv-orb:after{content:"AI";position:absolute;right:24px;bottom:14px;font-size:74px;font-weight:900;color:rgba(255,255,255,.13)}.bpv-grid{position:relative;display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:16px;margin:0 auto 20px}.bpv-metric{padding:20px;border-radius:24px;background:linear-gradient(135deg,rgba(34,211,238,.18),rgba(139,92,246,.14));border:1px solid rgba(255,255,255,.18);box-shadow:0 18px 45px rgba(2,6,23,.22),inset 0 1px 0 rgba(255,255,255,.22)}.bpv-metric span{display:block;color:#94a3b8;font-size:12px;text-transform:uppercase;letter-spacing:.08em}.bpv-metric strong{display:block;margin-top:8px;font-size:18px;color:#fff}.bpv-card{padding:22px;margin-top:18px}.bpv-card h2{color:#fff;margin-top:0}.bpv-table{width:100%;border-collapse:separate;border-spacing:0 10px}.bpv-table th{color:#93c5fd;text-align:left;width:210px}.bpv-table td{color:#e2e8f0}.bpv-badge{display:inline-flex;align-items:center;border-radius:999px;padding:6px 11px;background:rgba(34,197,94,.16);color:#bbf7d0;border:1px solid rgba(74,222,128,.26);font-weight:800}.bpv-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.bpv-saas .button{border-radius:999px!important;border:1px solid rgba(255,255,255,.24)!important;background:rgba(255,255,255,.12)!important;color:#fff!important;box-shadow:none!important}.bpv-saas .button-primary{background:linear-gradient(135deg,#22d3ee,#8b5cf6)!important;border:none!important}.bpv-art-card{border:1px solid rgba(34,211,238,.18);background:linear-gradient(135deg,rgba(15,23,42,.58),rgba(30,41,59,.35))}.bpv-art-card p{color:#cbd5e1}.bpv-form-table th{color:#bfdbfe}.bpv-form-table textarea,.bpv-form-table input{background:rgba(15,23,42,.72)!important;color:#fff!important;border:1px solid rgba(255,255,255,.22)!important;border-radius:16px!important}
        @media(max-width:1100px){.bpv-hero{grid-template-columns:1fr}.bpv-grid{grid-template-columns:repeat(2,1fr)}}
        </style>';
        echo '<div class="wrap"><div class="bpv-saas"><div class="bpv-shell">';
        settings_errors('bpv_blog_privilege');
        echo '<section class="bpv-hero"><div><div class="bpv-kicker">Blog Privilège AI • Premium SaaS 2026</div><h1 class="bpv-title">Centro editorial autônomo com fotografia real e SEO limpo</h1><p class="bpv-sub">Dashboard glassmorphism ultra para operar geração automática, publicação WordPress, diagnóstico por etapa e direção de arte fotográfica premium sem fallback ilustrativo.</p></div><div class="bpv-orb"></div></section>';
        echo '<div class="bpv-grid">';
        echo '<div class="bpv-metric"><span>Status</span><strong>' . esc_html($enabled === 'yes' ? 'Ativo' : 'Pausado') . '</strong></div>';
        echo '<div class="bpv-metric"><span>Frequência</span><strong>2 min</strong></div>';
        echo '<div class="bpv-metric"><span>Total</span><strong>' . esc_html($total) . '</strong></div>';
        echo '<div class="bpv-metric"><span>HTTP</span><strong>' . esc_html($curl) . '</strong></div>';
        echo '<div class="bpv-metric"><span>Anti-repetição</span><strong>' . esc_html($history) . '</strong></div>';
        echo '</div>';
        echo '<section class="bpv-card"><h2>Operação automática</h2><table class="bpv-table"><tbody>';
        echo '<tr><th>Próxima execução</th><td>' . esc_html($next ? wp_date('d/m/Y H:i:s', $next) : 'Não agendada') . '</td></tr>';
        echo '<tr><th>Próximo tema</th><td>' . esc_html($next_topic) . '</td></tr>';
        echo '<tr><th>Última execução</th><td>' . esc_html($last_run) . '</td></tr>';
        echo '<tr><th>Último post</th><td>' . ($last_post ? '<a style="color:#67e8f9" href="' . esc_url(get_edit_post_link($last_post)) . '">Editar post #' . esc_html($last_post) . '</a>' : 'Nenhum') . '</td></tr>';
        echo '<tr><th>Motor de imagem</th><td>' . esc_html($photo_engine) . ' — somente fotografia real/editorial; fallback ilustrativo local removido.</td></tr>';
        echo '<tr><th>Última imagem</th><td>' . esc_html($last_image) . '</td></tr>';
        echo '<tr><th>Último erro</th><td>' . esc_html($last_error ? $last_error : 'Nenhum') . '</td></tr>';
        echo '<tr><th>Trava de geração</th><td>' . esc_html($current_lock ? 'Ativa há ' . human_time_diff(time() - $lock_age, time()) : 'Livre') . '</td></tr>';
        echo '</tbody></table>';
        echo '<div class="bpv-actions">';
        echo '<form method="post" class="bpv-actions">';
        wp_nonce_field('bpv_blog_privilege_action', 'bpv_blog_privilege_nonce');
        echo '<button class="button button-primary" name="bpv_blog_privilege_action" value="generate_now">Gerar post agora</button>';
        echo $enabled === 'yes' ? '<button class="button" name="bpv_blog_privilege_action" value="pause">Pausar automático</button>' : '<button class="button" name="bpv_blog_privilege_action" value="resume">Retomar automático</button>';
        echo '<button class="button" name="bpv_blog_privilege_action" value="reschedule">Recriar agendamento</button><button class="button" name="bpv_blog_privilege_action" value="clear_lock">Liberar trava</button><button class="button" name="bpv_blog_privilege_action" value="clear_history">Limpar histórico</button></form></div></section>';
        echo '<section class="bpv-card"><h2>Diagnóstico da última geração</h2><table class="bpv-table"><tbody>';
        foreach (array('Artigo','SEO','Slug','Imagem','Publicação') as $label) {
            $key = sanitize_key($label);
            $status = isset($last_diag[$key]['status']) ? $last_diag[$key]['status'] : 'Sem registro';
            $message = isset($last_diag[$key]['message']) ? $last_diag[$key]['message'] : '';
            echo '<tr><th>' . esc_html($label) . '</th><td><span class="bpv-badge">' . esc_html($status) . '</span>' . ($message ? ' &nbsp; ' . esc_html($message) : '') . '</td></tr>';
        }
        if (!empty($last_diag['error']) && is_array($last_diag['error'])) {
            echo '<tr><th>Erro técnico</th><td>' . esc_html(($last_diag['error']['file'] ?? '') . ' :: ' . ($last_diag['error']['function'] ?? '') . ' — ' . ($last_diag['error']['message'] ?? '')) . '</td></tr>';
        }
        echo '</tbody></table></section>';
        echo '<section class="bpv-card"><h2>Regras aplicadas</h2><p>Posts automáticos com conteúdo estruturado, SEO title separado, meta description, slug curto e fotografia editorial premium. O fluxo evita ilustrações, cartoons, vetores, avatares, texto, logos e watermarks.</p></section>';
        echo '<section class="bpv-card bpv-art-card"><h2>Diretor de Arte IA Premium</h2>';
        echo '<p>Configure a identidade visual usada pelas imagens fotográficas: realismo editorial, equipe trabalhando, luz natural e ambiente corporativo moderno.</p>';
        echo '<form method="post">';
        wp_nonce_field('bpv_blog_privilege_action', 'bpv_blog_privilege_nonce');
        echo '<input type="hidden" name="bpv_blog_privilege_action" value="save_premium">';
        echo '<table class="form-table bpv-form-table">';
        echo '<tr><th>Estilo visual</th><td><input class="regular-text" name="bpv_art_style" value="' . esc_attr(get_option(self::OPTION_ART_STYLE, 'Fotografia corporativa premium')) . '"></td></tr>';
        echo '<tr><th>Perfil da marca</th><td><textarea class="large-text" name="bpv_art_brand">' . esc_textarea(get_option(self::OPTION_ART_BRAND, '')) . '</textarea></td></tr>';
        echo '<tr><th>Evitar</th><td><textarea class="large-text" name="bpv_art_avoid">' . esc_textarea(get_option(self::OPTION_ART_AVOID, '')) . '</textarea></td></tr>';
        echo '</table>';
        echo '<button class="button button-primary">Salvar identidade visual</button>';
        echo '</form>';
        echo '</section>';
        echo '</div></div></div>';
    }
public static function generate_scheduled_post($force = false) {
        if (!$force && get_option(self::OPTION_ENABLED, 'yes') !== 'yes') {
            return new WP_Error('bpv_disabled', 'A geração automática está pausada.');
        }

        $lock = get_transient(self::LOCK_KEY);
        if ($lock) {
            // Evita travamento permanente caso uma geração seja interrompida por timeout, fatal error ou limite externo.
            // Locks acima de 5 minutos são considerados órfãos porque o cron roda a cada 2 minutos.
            if (self::lock_is_stale($lock)) {
                delete_transient(self::LOCK_KEY);
            } else {
                $age = max(0, time() - self::lock_started_at($lock));
                return new WP_Error('bpv_locked', 'Já existe uma geração em andamento há ' . human_time_diff(time() - $age, time()) . '. Se estiver travado, use Liberar trava no painel.');
            }
        }

        // Guarda token e horário para permitir liberação segura, inclusive no shutdown da requisição.
        $lock_token = self::new_lock_token();
        set_transient(self::LOCK_KEY, array('time' => time(), 'token' => $lock_token), 360);
        register_shutdown_function(array(__CLASS__, 'release_generation_lock'), $lock_token);

        self::ensure_terms();

        $topics = self::topics();
        $index  = absint(get_option(self::OPTION_INDEX, 0));
        $total  = absint(get_option(self::OPTION_TOTAL, 0));
        $topic  = $topics[$index % count($topics)];
        $seed   = self::seed($topic, $total, 0);

        $category_name = self::category_for_topic($topic);
        $category_id   = self::get_category_id($category_name);
        $tags          = self::tags_for_topic($topic, $total);
        $title         = self::generate_unique_title($topic, $seed, $total);
        $slug          = self::generate_slug($topic, $title, $category_name, $total, $seed);
        $seo           = self::generate_seo_metadata($topic, $title, $category_name, $attempt_seed ?? $seed);
        $content       = '';
        $attempt_seed  = $seed;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $attempt_seed = self::seed($topic, $total, $attempt);
            $content = self::generate_content($topic, $category_name, $tags, $title, $attempt_seed, $total + $attempt);
            if (!self::content_was_used($content)) {
                break;
            }
        }

        $excerpt = self::generate_excerpt($topic, $attempt_seed);
        if (!self::validate_article_quality($title, $content)) {
            update_option(self::OPTION_LAST_ERR, 'Validação editorial reprovada antes da publicação.');
            self::release_generation_lock($lock_token);
            return new WP_Error('bpv_article_quality', 'Validação editorial reprovada antes da publicação.');
        }

        $post_data = array(
            'post_title'    => $title,
            'post_name'     => $slug,
            'post_content'  => $content,
            'post_excerpt'  => $excerpt,
            'post_status'   => 'publish',
            'post_type'     => 'post',
            'post_author'   => self::get_author_id(),
            'post_category' => array($category_id),
            'meta_input'    => array(
                '_bpv_blog_privilege_topic'   => $topic,
                '_bpv_blog_privilege_seed'    => $attempt_seed,
                '_bpv_blog_privilege_version' => self::VERSION,
                '_bpv_seo_title'              => $seo['title'],
                '_bpv_meta_description'       => $seo['description'],
            ),
        );

        $post_id = wp_insert_post(wp_slash($post_data), true);

        if (is_wp_error($post_id)) {
            update_option(self::OPTION_LAST_ERR, $post_id->get_error_message());
            self::release_generation_lock($lock_token);
            return $post_id;
        }

        wp_set_post_tags($post_id, $tags, false);
        self::remember_title($title);
        self::remember_content($content);

        $image_result = self::create_featured_image($post_id, $topic, $category_name, $title, $excerpt, $attempt_seed);
        if (is_wp_error($image_result)) {
            update_option(self::OPTION_LAST_ERR, 'Post criado, mas a imagem não foi gerada: ' . $image_result->get_error_message());
        } else {
            update_option(self::OPTION_LAST_ERR, '');
        }

        update_option(self::OPTION_INDEX, ($index + 1) % count($topics));
        update_option(self::OPTION_TOTAL, $total + 1);
        update_option(self::OPTION_LAST_RUN, wp_date('d/m/Y H:i:s'));
        update_option(self::OPTION_LAST_POST, $post_id);
        self::remember_generation_diagnostic($post_id, $slug, $seo, $image_result);

        self::release_generation_lock($lock_token);
        return $post_id;
    }


    public static function release_generation_lock($token = '') {
        $lock = get_transient(self::LOCK_KEY);
        if (!$lock) {
            return;
        }
        if (is_array($lock) && !empty($lock['token']) && $token && hash_equals((string) $lock['token'], (string) $token)) {
            delete_transient(self::LOCK_KEY);
            return;
        }
        if (!is_array($lock) && $token === '') {
            delete_transient(self::LOCK_KEY);
        }
    }

    private static function lock_started_at($lock) {
        if (is_array($lock) && !empty($lock['time'])) {
            return (int) $lock['time'];
        }
        if (is_numeric($lock)) {
            return (int) $lock;
        }
        return time();
    }

    private static function lock_is_stale($lock) {
        return (time() - self::lock_started_at($lock)) > 300;
    }

    private static function new_lock_token() {
        if (function_exists('wp_generate_uuid4')) {
            return wp_generate_uuid4();
        }
        return md5(uniqid('', true) . '|' . wp_rand());
    }

    private static function ensure_terms() {
        foreach (array('Notícias', 'Tendências', 'Dicas') as $category) {
            if (!term_exists($category, 'category')) {
                wp_insert_term($category, 'category');
            }
        }
    }

    private static function get_category_id($name) {
        $term = get_term_by('name', $name, 'category');
        if (!$term) {
            $created = wp_insert_term($name, 'category');
            if (!is_wp_error($created) && !empty($created['term_id'])) {
                return absint($created['term_id']);
            }
            return 1;
        }
        return absint($term->term_id);
    }

    private static function get_author_id() {
        $users = get_users(array(
            'role__in' => array('administrator', 'editor', 'author'),
            'number'   => 1,
            'orderby'  => 'ID',
            'order'    => 'ASC',
            'fields'   => array('ID'),
        ));
        if (!empty($users[0]->ID)) {
            return absint($users[0]->ID);
        }
        return 1;
    }

    private static function topics() {
        return array(
            'Planejamento estratégico de sites',
            'Arquitetura e estrutura de páginas',
            'Experiência do usuário (UX)',
            'Interface e design de sites (UI)',
            'Sites institucionais',
            'Sites corporativos',
            'Sites profissionais para pequenas empresas',
            'Sites de alta conversão',
            'Landing pages',
            'Otimização de sites existentes',
            'Reformulação de sites antigos',
            'Velocidade e performance de sites',
            'Segurança de sites',
            'Responsividade para dispositivos móveis',
            'Jornada do cliente dentro do site',
            'Fundamentos de SEO',
            'SEO para empresas locais',
            'SEO técnico',
            'SEO de conteúdo',
            'Pesquisa de palavras-chave',
            'Google e posicionamento orgânico',
            'Autoridade digital',
            'Conteúdo estratégico para busca',
            'Erros comuns de SEO',
            'Otimização de páginas existentes',
            'Google Search Console',
            'Google Meu Negócio / Perfil da Empresa',
            'Estratégias para aparecer antes dos concorrentes',
            'Tráfego orgânico',
            'Marketing de busca',
            'Estratégias digitais para empresas',
            'Presença digital profissional',
            'Funil de vendas digital',
            'Geração de leads',
            'Conversão de clientes pela internet',
            'Marketing de conteúdo',
            'Estratégia de canais digitais',
            'Planejamento digital',
            'Métricas e análise de resultados',
            'Erros de marketing digital',
            'Posicionamento online',
            'Autoridade de marca na internet',
            'Comunicação digital empresarial',
            'Aquisição de clientes',
            'Escalabilidade através do digital',
            'Construção de marcas',
            'Estratégia de marca (branding)',
            'Identidade visual corporativa',
            'Elementos visuais da marca',
            'Psicologia das cores',
            'Tipografia e percepção de marca',
            'Posicionamento de marca',
            'Personalidade da marca',
            'Consistência visual',
            'Marcas premium',
            'Percepção de valor',
            'Diferenciação no mercado',
            'Confiança através da marca',
            'Experiência de marca',
            'Gestão da identidade visual',
            'Rebranding',
            'Atualização de marcas antigas',
            'Evolução da identidade visual',
            'Mudança de posicionamento',
            'Expansão de empresas e marcas',
            'Recuperação da percepção da marca',
            'Modernização visual',
            'Erros de marcas desatualizadas',
            'Estratégia antes do redesign',
            'Transformação de marcas',
            'Design estratégico',
            'Comunicação visual',
            'Materiais institucionais',
            'Apresentações empresariais',
            'Criativos para campanhas',
            'Design para redes sociais',
            'Design comercial',
            'Hierarquia visual',
            'Composição gráfica',
            'Design profissional versus amador',
            'Estratégia para lojas virtuais',
            'Estrutura de e-commerce',
            'Experiência de compra online',
            'Conversão em lojas virtuais',
            'Jornada do consumidor no e-commerce',
            'Confiança e credibilidade online',
            'Otimização de vendas',
            'Fotografias e apresentação de produtos',
            'Checkout e abandono de carrinho',
            'Integração de sistemas de venda',
            'Criação de nomes de empresas',
            'Estratégia de naming',
            'Identidade verbal',
            'Diferenciação através do nome',
            'Memorização de marcas',
            'Arquitetura de nomes',
            'Posicionamento através da linguagem',
            'Registro e proteção de nomes',
            'Tom de voz da marca',
            'Construção de nomes fortes',
        );
    }

    private static function category_for_topic($topic) {
        $t = self::lower($topic);
        $tendencias = array('tendências', 'inteligência artificial', 'web design', 'design', 'ux/ui', 'experiência do usuário', 'mobile first', 'layouts responsivos', 'marketplaces digitais', 'produtos digitais', 'social media', 'instagram', 'facebook', 'branding', 'identidade visual');
        foreach ($tendencias as $needle) {
            if (strpos($t, $needle) !== false) {
                return 'Tendências';
            }
        }
        $noticias = array('google ads', 'meta ads', 'analytics', 'search console', 'plugins', 'woocommerce', 'wordpress', 'dropshipping', 'hospedagem', 'domínios');
        foreach ($noticias as $needle) {
            if (strpos($t, $needle) !== false) {
                return 'Notícias';
            }
        }
        return 'Dicas';
    }

    private static function compact_topic($topic) {
        $topic = self::normalize_spaces($topic);
        if (self::strlen($topic) <= 34) {
            return $topic;
        }

        $replacements = array(
            'Planejamento estratégico' => 'Planejamento',
            'Experiência do usuário' => 'UX',
            'Interface e design' => 'UI e design',
            'dispositivos móveis' => 'mobile',
            'Google Meu Negócio / Perfil da Empresa' => 'Perfil da Empresa',
            'Estratégias para aparecer antes dos concorrentes' => 'Aparecer antes dos concorrentes',
            'Estratégias digitais para empresas' => 'Estratégias digitais',
            'Identidade visual corporativa' => 'Identidade visual',
            'Tipografia e percepção de marca' => 'Tipografia de marca',
            'Design profissional versus amador' => 'Design profissional',
            'Fotografias e apresentação de produtos' => 'Fotos de produtos',
            'Posicionamento através da linguagem' => 'Linguagem de marca',
        );

        foreach ($replacements as $from => $to) {
            if ($topic === $from) {
                return $to;
            }
        }

        return self::truncate_text($topic, 34);
    }

    private static function generate_unique_title($topic, $seed, $total) {
        $base = self::compact_topic($topic);
        $prefixes = array('Guia de', 'Como usar', 'Estratégia de', 'O valor de', 'Como melhorar', 'Plano de', 'Tendência em', 'Caminho para');
        $suffixes = array('na prática', 'com mais clareza', 'para vender mais', 'com foco em resultado', 'sem complicação', 'para crescer', 'com estratégia', 'que gera confiança', 'para negócios', 'com alto impacto', 'sem desperdício', 'que atrai clientes');

        $candidates = array();
        $candidates[] = $base;
        for ($i = 0; $i < 16; $i++) {
            $candidates[] = self::pick($prefixes, $seed, $i) . ' ' . $base;
            $candidates[] = $base . ' ' . self::pick($suffixes, $seed, $i + 20);
        }

        $used = get_option(self::OPTION_TITLE_HASHES, array());
        if (!is_array($used)) {
            $used = array();
        }

        foreach ($candidates as $candidate) {
            $candidate = self::normalize_spaces($candidate);
            $candidate = self::limit_text($candidate, 50);
            if (self::strlen($candidate) > 50 || self::strlen($candidate) < 12) {
                continue;
            }
            $hash = md5(self::lower(remove_accents($candidate)));
            if (!isset($used[$hash])) {
                return $candidate;
            }
        }

        $numbered = self::limit_text($base . ' ' . (($total % 97) + 1), 50);
        return self::normalize_spaces($numbered);
    }

    private static function generate_slug($topic, $title, $category, $total, $seed) {
        // SEO Engine 4.1: URL curta, estável e sem repetição, datas, IDs ou hashes.
        $source = sanitize_title(remove_accents($title . ' ' . $topic));
        $source = preg_replace('/\b(19|20)\d{2}\b|\b\d{1,2}h\d{0,2}\b|\b\d{6,}\b|\b[a-f0-9]{8,}\b/i', ' ', $source);
        $stopwords = array('de','do','da','dos','das','para','por','com','sem','uma','um','o','a','e','em','no','na','nos','nas','ao','aos','como','que','mais','completo','estrategico','estrategia','negocios','digitais','marketing');
        $seen = array();
        $words = array();
        foreach (array_filter(explode('-', $source)) as $word) {
            if (in_array($word, $stopwords, true) || isset($seen[$word])) {
                continue;
            }
            $seen[$word] = true;
            $words[] = $word;
            if (count($words) >= 6) {
                break;
            }
        }
        if (count($words) < 3) {
            $words = array_slice(array_values(array_unique(array_filter(explode('-', sanitize_title($topic))))), 0, 6);
        }
        $slug = implode('-', array_slice($words, 0, 6));
        while (strlen($slug) > 60 && count($words) > 3) {
            array_pop($words);
            $slug = implode('-', $words);
        }
        return sanitize_title($slug);
    }

    private static function generate_seo_metadata($topic, $title, $category, $seed) {
        $seo_title = self::limit_text(self::normalize_spaces(str_replace(array('Guia de ', 'Como usar '), '', $title) . ' para sites profissionais e conversão'), 60);
        $description = self::normalize_spaces('Entenda como aplicar ' . self::lower($topic) . ' com estratégia, clareza editorial e foco em autoridade, conversão e crescimento digital.');
        $description = self::limit_text($description, 160);
        if (self::strlen($description) < 140) {
            $description = self::limit_text($description . ' Veja critérios práticos para melhorar resultados.', 160);
        }
        return array('title' => $seo_title, 'description' => $description, 'category' => $category);
    }

    private static function validate_article_quality($title, $content) {
        $plain = wp_strip_all_tags($content);
        return self::strlen($title) >= 12 && substr_count($content, '<h2>') >= 4 && strpos($content, '<ul>') !== false && self::strlen($plain) >= 2500;
    }

    private static function generate_excerpt($topic, $seed) {
        $openers = array(
            'Veja uma análise prática para transformar presença digital em oportunidade comercial.',
            'Entenda como ajustar estratégia, conteúdo e experiência para atrair visitantes melhores.',
            'Descubra pontos de decisão que ajudam empresas a vender com mais clareza no digital.',
            'Confira caminhos objetivos para melhorar percepção de valor, tráfego e conversão online.',
        );
        return self::normalize_spaces(self::pick($openers, $seed, 9) . ' Tema: ' . $topic . '.');
    }

    private static function generate_content($topic, $category, $tags, $title, $seed, $serial) {
        $profile     = self::topic_profile($topic);
        $audience    = self::pick(self::audiences(), $seed, 1);
        $moment      = self::pick(self::business_moments(), $seed, 2);
        $scenario    = self::pick(self::scenarios(), $seed, 3);
        $metric      = self::pick($profile['metrics'], $seed, 4);
        $objection   = self::pick($profile['objections'], $seed, 5);
        $asset       = self::pick($profile['assets'], $seed, 6);
        $verb        = self::pick(array('organizar', 'validar', 'reposicionar', 'simplificar', 'priorizar', 'refinar', 'conectar', 'acelerar'), $seed, 7);
        $tone        = self::pick(array('direto', 'consultivo', 'comercial', 'analítico', 'editorial', 'estratégico'), $seed, 8);
        $opening     = self::pick(self::opening_patterns(), $seed, 10);
        $insight     = self::pick($profile['insights'], $seed, 11);
        $mistake     = self::pick($profile['mistakes'], $seed, 12);
        $opportunity = self::pick($profile['opportunities'], $seed, 13);
        $proof       = self::pick($profile['proofs'], $seed, 14);
        $action      = self::pick($profile['actions'], $seed, 15);
        $unique_ref  = 'Leitura editorial ' . (($serial % 31) + 1) . ', foco ' . self::pick(array('aquisição', 'retenção', 'posicionamento', 'vendas', 'usabilidade', 'autoridade'), $seed, 16) . '.';
        $bridge     = self::pick(array(
            'Com esse raciocínio, o artigo deixa de soar genérico e passa a orientar uma escolha concreta, com começo, contexto e próximo passo.',
            'Quando essa lógica entra na pauta, a publicação ganha função comercial e não fica presa a uma explicação comum sobre o tema.',
            'Esse enquadramento ajuda o leitor a enxergar utilidade imediata, em vez de apenas consumir uma lista de conceitos parecidos.',
            'A força do conteúdo aparece quando cada bloco reduz uma dúvida específica e aproxima o visitante de uma decisão possível.',
        ), $seed, 101);
        $metric_reading = self::pick(array(
            'A leitura desse dado separa curiosidade de oportunidade e mostra onde a comunicação precisa ser mais convincente.',
            'Essa métrica evita decisões por impressão visual e revela se o caminho digital está realmente aproximando pessoas qualificadas.',
            'Com esse acompanhamento, a empresa entende se o post gera apenas visitas ou se contribui para conversas comerciais melhores.',
            'O número funciona como termômetro para ajustar promessa, navegação e profundidade do conteúdo sem depender de achismo.',
        ), $seed, 102);
        $recorte_phrase = self::pick(array(
            'Em vez de repetir conceitos amplos, escolha uma dor específica, uma promessa verificável e um exemplo que converse com a rotina do cliente.',
            'Para escapar do lugar-comum, transforme o assunto em uma situação prática, com problema reconhecível e consequência clara.',
            'O texto ganha força quando troca definições abertas por uma análise aplicada ao momento de compra, pesquisa ou comparação.',
            'A melhor saída é tratar o tema como decisão: o que avaliar, qual risco evitar e qual benefício deve ficar evidente.',
        ), $seed, 103);
        $tone_phrase = self::pick(array(
            'Mostre o que muda antes e depois da melhoria, quais sinais indicam problema e qual ajuste deve vir primeiro.',
            'Traga critérios simples, exemplos plausíveis e uma explicação que pareça escrita para alguém prestes a decidir.',
            'Conduza a leitura por perguntas objetivas, provas de confiança e pequenas escolhas que reduzem incerteza.',
            'Organize a narrativa para que o visitante entenda valor, risco, benefício e ação sem precisar decifrar termos técnicos.',
        ), $seed, 104);
        $mistake_context = self::pick(array(
            'O problema cresce porque a falha parece discreta no começo, mas depois enfraquece a clareza e empurra a marca para uma disputa por preço.',
            'A consequência não aparece apenas no visual: ela atinge confiança, posicionamento e qualidade das oportunidades geradas.',
            'Esse tipo de deslize diminui relevância porque o leitor encontra pouca diferença entre uma empresa e outra.',
            'Quando isso acontece, a marca perde chance de explicar valor e deixa o usuário formar conclusões sozinho.',
        ), $seed, 105);
        $click_phrase = self::pick(array(
            'O título curto abre a porta, mas a entrega precisa sustentar a promessa com uma imagem humanizada, uma tese clara e uma estrutura útil.',
            'Um clique nasce da curiosidade, porém a permanência depende de conteúdo específico, visual crível e leitura sem aparência automática.',
            'Para gerar interesse sem exagero, conecte promessa, contexto e benefício já nas primeiras linhas do artigo.',
            'A publicação fica mais forte quando parece uma orientação de consultoria, não uma peça montada apenas para preencher calendário.',
        ), $seed, 106);
        $follow_phrase = self::pick(array(
            'Depois disso, acompanhe comportamento, origem de tráfego e conversões para evoluir com base em sinais reais.',
            'Na sequência, observe a resposta do público e ajuste a próxima publicação a partir dos dados encontrados.',
            'O ciclo fica mais eficiente quando cada novo post aproveita o que métricas, comentários e dúvidas já revelaram.',
            'A melhoria contínua ganha força quando a empresa testa pequenos ajustes antes de fazer mudanças maiores.',
        ), $seed, 107);

        $html  = '<p>' . esc_html(sprintf($opening, $topic, $audience, $moment)) . '</p>';
        $html .= '<p>' . esc_html('Nesta abordagem, o ponto de partida não é preencher o blog com palavras-chave soltas. O objetivo é ' . $verb . ' a jornada de quem pesquisa, compara e decide. Para ' . $audience . ', ' . $topic . ' precisa aparecer como solução aplicada a uma situação real: ' . $scenario . '.') . '</p>';

        $html .= '<h2>' . esc_html(self::pick(array('Por que este assunto muda a percepção da marca', 'Onde a estratégia começa a gerar valor', 'O que separa presença digital de presença útil', 'Como transformar atenção em decisão'), $seed, 17)) . '</h2>';
        $html .= '<p>' . esc_html($insight . ' ' . $bridge) . '</p>';
        $html .= '<p>' . esc_html('O indicador que merece atenção aqui é ' . $metric . '. ' . $metric_reading) . '</p>';

        $html .= '<h2>' . esc_html(self::pick(array('Como aplicar sem cair no conteúdo repetitivo', 'Um método simples para deixar o artigo mais forte', 'Como criar uma peça que merece ser lida', 'O caminho para fugir do óbvio'), $seed, 18)) . '</h2>';
        $html .= '<p>' . esc_html('Uma publicação sobre ' . $topic . ' precisa ter recorte. ' . $recorte_phrase . ' O tema ganha relevância quando explica uma decisão que o leitor está prestes a tomar.') . '</p>';
        $html .= '<p>' . esc_html('Para manter o texto com tom ' . $tone . ', use frases que respondam dúvidas concretas. ' . $tone_phrase . ' Esse formato aumenta permanência, reduz rejeição e deixa o post mais útil para busca orgânica.') . '</p>';

        $html .= '<h2>' . esc_html(self::pick(array('Pontos que merecem revisão no projeto', 'Critérios que ajudam a decidir melhor', 'Detalhes que interferem na conversão', 'Ajustes práticos para aumentar confiança'), $seed, 19)) . '</h2>';
        $items = self::checklist_items($topic, $seed, $profile, $asset, $objection, $proof, $action);
        $html .= '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . esc_html($item) . '</li>';
        }
        $html .= '</ul>';

        $html .= '<h2>' . esc_html(self::pick(array('Erros que deixam a página fraca', 'O que costuma travar o resultado', 'Riscos silenciosos na estratégia', 'Sinais de que o projeto precisa evoluir'), $seed, 20)) . '</h2>';
        $html .= '<p>' . esc_html($mistake . ' ' . $mistake_context) . '</p>';
        $html .= '<p>' . esc_html('Outro ponto crítico é ignorar a objeção principal: ' . $objection . '. Se o conteúdo não responde esse bloqueio, o visitante tende a adiar a decisão. Uma boa estrutura antecipa dúvidas, mostra critérios e oferece um caminho seguro para avançar.') . '</p>';

        $html .= '<h2>' . esc_html(self::pick(array('Como tornar o conteúdo mais clicável', 'Como criar interesse sem exagero', 'Como aumentar leitura e ação', 'Como aproximar conteúdo e venda'), $seed, 21)) . '</h2>';
        $html .= '<p>' . esc_html($click_phrase) . '</p>';
        $html .= '<p>' . esc_html($opportunity . ' Esse é o tipo de oportunidade que transforma ' . $topic . ' em ativo comercial. O post pode atrair tráfego, educar o público, reforçar autoridade e preparar o contato para uma conversa mais objetiva com a empresa.') . '</p>';

        $html .= '<h2>' . esc_html(self::pick(array('Próximo passo recomendado', 'Como colocar em prática agora', 'A decisão que vem antes da ferramenta', 'O ajuste mais inteligente para começar'), $seed, 22)) . '</h2>';
        $html .= '<p>' . esc_html($action . ' ' . $follow_phrase) . '</p>';
        $html .= '<p>' . esc_html($unique_ref . ' Em resumo, ' . $topic . ' funciona melhor quando nasce de uma pergunta comercial clara: como facilitar a decisão do usuário e mostrar valor antes que ele procure outra opção? Quando essa pergunta guia o conteúdo, cada publicação ganha mais utilidade, mais diferenciação e mais chance de atrair cliques qualificados.') . '</p>';

        $html = self::fit_content_length($html, $topic, $seed);
        return $html;
    }

    private static function topic_profile($topic) {
        $t = self::lower($topic);
        $base = array(
            'metrics' => array('taxa de conversão', 'tempo de permanência', 'cliques em chamadas principais', 'qualidade dos leads', 'origem do tráfego', 'custo por oportunidade'),
            'objections' => array('falta de confiança no primeiro contato', 'dúvida sobre preço e entrega', 'medo de escolher uma solução errada', 'comparação com concorrentes mais conhecidos', 'sensação de que o site não explica o suficiente'),
            'assets' => array('página principal', 'conteúdo de apoio', 'formulário de contato', 'menu de navegação', 'proposta de valor', 'chamada para ação'),
            'insights' => array('A atenção do visitante é conquistada quando a página responde rapidamente o que ele ganha, por que deve confiar e qual passo deve seguir.', 'Conteúdo útil não depende de volume; depende de precisão, intenção clara e coerência entre promessa, prova e chamada para ação.', 'A experiência digital precisa reduzir dúvida, não aumentar etapas. Cada bloco deve aproximar a pessoa de uma decisão consciente.'),
            'mistakes' => array('O erro mais comum é publicar uma explicação bonita, mas sem recorte, sem prioridade e sem relação direta com uma decisão de compra.', 'Muitas empresas tratam o tema como tendência visual e esquecem de revisar mensagem, navegação, prova e velocidade.', 'O projeto perde força quando todos os conteúdos usam a mesma estrutura e não apresentam um ponto de vista novo.'),
            'opportunities' => array('A oportunidade está em criar uma jornada mais clara, capaz de transformar curiosidade em confiança e confiança em contato.', 'O ganho aparece quando o conteúdo aproxima estratégia, experiência e oferta em uma narrativa simples de entender.', 'Existe espaço para diferenciar a marca ao traduzir decisões técnicas em benefícios comerciais percebidos pelo cliente.'),
            'proofs' => array('demonstrações visuais', 'exemplos práticos', 'dados de navegação', 'depoimentos', 'comparativos', 'sinais de segurança'),
            'actions' => array('Comece revisando a página que mais recebe visitas e identifique onde o usuário pode ficar em dúvida.', 'Escolha uma oferta principal e reorganize o conteúdo para conduzir o visitante até ela com menos ruído.', 'Revise título, promessa, prova, imagem e chamada final antes de investir em mais tráfego.'),
        );

        if (strpos($t, 'seo') !== false || strpos($t, 'search console') !== false) {
            $base['metrics'] = array('impressões orgânicas', 'cliques qualificados', 'posição média', 'indexação de páginas importantes', 'taxa de clique nos resultados', 'crescimento de termos relevantes');
            $base['objections'] = array('conteúdo parecido com concorrentes', 'páginas sem intenção de busca clara', 'títulos pouco atrativos', 'estrutura técnica frágil', 'ausência de autoridade temática');
            $base['assets'] = array('título SEO', 'descrição da página', 'estrutura de headings', 'links internos', 'conteúdo principal', 'dados do Search Console');
            $base['insights'][] = 'SEO forte nasce quando o conteúdo resolve uma intenção de busca real e prova que a página merece estar entre as melhores respostas.';
        } elseif (strpos($t, 'ads') !== false || strpos($t, 'tráfego') !== false) {
            $base['metrics'] = array('custo por lead', 'retorno sobre investimento', 'taxa de clique dos anúncios', 'taxa de conversão da landing page', 'frequência de exibição', 'qualidade do criativo');
            $base['objections'] = array('anúncio que promete mais do que a página entrega', 'segmentação ampla demais', 'criativos sem contraste', 'página de destino lenta', 'oferta pouco específica');
            $base['assets'] = array('criativo do anúncio', 'página de destino', 'segmentação', 'orçamento diário', 'evento de conversão', 'copy principal');
            $base['opportunities'][] = 'A mídia paga fica mais eficiente quando anúncio, oferta e página de destino contam a mesma história.';
        } elseif (strpos($t, 'woocommerce') !== false || strpos($t, 'e-commerce') !== false || strpos($t, 'lojas virtuais') !== false || strpos($t, 'pagamento') !== false) {
            $base['metrics'] = array('adição ao carrinho', 'abandono de checkout', 'ticket médio', 'taxa de recompra', 'conversão por dispositivo', 'tempo até a compra');
            $base['objections'] = array('medo de comprar em uma loja desconhecida', 'frete pouco claro', 'checkout longo', 'descrição fraca do produto', 'falta de prova social');
            $base['assets'] = array('página de produto', 'checkout', 'carrinho', 'banner principal', 'menu de categorias', 'meios de pagamento');
            $base['insights'][] = 'Em lojas virtuais, cada dúvida não respondida vira atrito, e cada atrito aumenta a chance de abandono.';
        } elseif (strpos($t, 'design') !== false || strpos($t, 'ux') !== false || strpos($t, 'layout') !== false || strpos($t, 'mobile') !== false) {
            $base['metrics'] = array('profundidade de rolagem', 'cliques em botões', 'interação mobile', 'taxa de rejeição', 'tempo de leitura', 'conclusão de formulários');
            $base['objections'] = array('visual bonito sem orientação', 'botões pouco visíveis', 'hierarquia confusa', 'experiência ruim no celular', 'excesso de elementos competindo por atenção');
            $base['assets'] = array('hero section', 'menu mobile', 'botões de ação', 'grid de conteúdo', 'contraste visual', 'layout responsivo');
            $base['proofs'] = array('mapas de calor', 'testes de usabilidade', 'comparação mobile', 'análise de cliques', 'feedback de usuários', 'protótipos');
        } elseif (strpos($t, 'whatsapp') !== false || strpos($t, 'crm') !== false || strpos($t, 'automação') !== false || strpos($t, 'e-mail') !== false) {
            $base['metrics'] = array('tempo de resposta', 'taxa de abertura', 'taxa de resposta', 'leads recuperados', 'contatos qualificados', 'oportunidades acompanhadas');
            $base['objections'] = array('atendimento demorado', 'perda de histórico', 'falta de follow-up', 'mensagens genéricas', 'ausência de prioridade comercial');
            $base['assets'] = array('fluxo de atendimento', 'lista de leads', 'sequência de mensagens', 'integração com CRM', 'botão de contato', 'automação de relacionamento');
        } elseif (strpos($t, 'copywriting') !== false || strpos($t, 'conteúdo') !== false || strpos($t, 'branding') !== false || strpos($t, 'identidade') !== false) {
            $base['metrics'] = array('cliques no CTA', 'tempo de leitura', 'respostas comerciais', 'compartilhamentos', 'engajamento qualificado', 'lembrança de marca');
            $base['objections'] = array('promessa genérica', 'tom desalinhado com o público', 'falta de prova', 'argumentos pouco específicos', 'chamada final fraca');
            $base['assets'] = array('headline', 'subtítulo', 'proposta de valor', 'storytelling', 'argumento principal', 'CTA');
            $base['opportunities'][] = 'Uma mensagem bem posicionada faz a marca parecer mais segura antes mesmo do primeiro contato.';
        }

        return $base;
    }

    private static function audiences() {
        return array('empreendedores locais', 'gestores de marketing', 'donos de lojas virtuais', 'prestadores de serviço', 'pequenas empresas em expansão', 'marcas que vendem online', 'equipes comerciais', 'negócios que querem profissionalizar o digital');
    }

    private static function business_moments() {
        return array('fase de reposicionamento', 'momento de aumentar vendas', 'início de um novo site', 'revisão da presença online', 'planejamento de campanha', 'expansão de canais digitais', 'melhoria da experiência mobile', 'ajuste de oferta e conversão');
    }

    private static function scenarios() {
        return array('um cliente acessa pelo celular e decide em poucos segundos se continua', 'uma campanha atrai visitantes, mas a página precisa convencer sem depender do vendedor', 'a marca recebe tráfego, porém ainda não transforma atenção em contatos qualificados', 'o concorrente parece mais confiável porque explica melhor a própria oferta', 'o usuário compara preços e precisa enxergar diferença de valor', 'a equipe quer escalar sem perder consistência na comunicação');
    }

    private static function opening_patterns() {
        return array(
            '%1$s ganhou importância porque %2$s precisam decidir com mais velocidade durante uma %3$s.',
            'Falar sobre %1$s é falar sobre como %2$s podem criar vantagem em uma %3$s.',
            'Quando %2$s olham para %1$s com método, a %3$s deixa de ser improviso.',
            '%1$s se tornou um tema decisivo para %2$s que atravessam uma %3$s.',
            'O valor de %1$s aparece quando %2$s precisam transformar uma %3$s em resultado concreto.'
        );
    }

    private static function checklist_items($topic, $seed, $profile, $asset, $objection, $proof, $action) {
        $items = array(
            'Revise se o principal ativo do projeto, como ' . $asset . ', comunica valor antes de pedir uma ação.',
            'Mapeie a objeção mais provável, especialmente ' . $objection . ', e responda isso no próprio conteúdo.',
            'Inclua ' . $proof . ' quando fizer sentido, porque prova reduz incerteza e aumenta confiança.',
            'Defina uma única próxima ação para que o visitante não se perca entre muitas alternativas.',
            'Compare a experiência no celular e no desktop para evitar quedas de leitura e conversão.',
            'Atualize o post sempre que métricas ou comportamento indicarem perda de relevância.',
        );
        $extra = array(
            'Use exemplos do mercado do cliente para evitar um texto com aparência genérica.',
            'Troque frases amplas por orientações que ajudem o leitor a tomar uma decisão concreta.',
            'Conecte a promessa do título com a primeira dobra da página ou do artigo.',
            'Evite repetir blocos idênticos em temas diferentes, pois isso enfraquece percepção de utilidade.',
            $action,
        );
        $items[] = self::pick($extra, $seed, 30);
        shuffle($items);
        return array_slice($items, 0, 6);
    }

    private static function fit_content_length($html, $topic, $seed) {
        $plain_length = self::strlen(wp_strip_all_tags($html));
        if ($plain_length < 3000) {
            $html .= '<h2>' . esc_html(self::pick(array('Complemento estratégico', 'Leitura final', 'Ponto de atenção'), $seed, 40)) . '</h2>';
            $html .= '<p>' . esc_html('Para ampliar o resultado, observe como ' . $topic . ' se conecta com oferta, velocidade, conteúdo e atendimento. Nenhum desses pontos trabalha sozinho. Quando a empresa mede cada etapa, ela identifica gargalos com mais precisão e evita mudanças baseadas apenas em opinião.') . '</p>';
        }
        $plain_length = self::strlen(wp_strip_all_tags($html));
        if ($plain_length < 3000) {
            $html .= '<p>' . esc_html('A recomendação é criar um pequeno plano de revisão, escolher uma métrica principal e testar melhorias em ciclos curtos. Esse processo mantém o conteúdo vivo, reduz repetição e aumenta a chance de conquistar visitantes com intenção real.') . '</p>';
        }
        return $html;
    }

    private static function tags_for_topic($topic, $total = 0) {
        $generic = array(
            $topic, 'WordPress', 'WooCommerce', 'E-commerce', 'Marketing Digital', 'Sites Profissionais',
            'Criação de Sites', 'Lojas Virtuais', 'SEO', 'Performance Web', 'Web Design', 'UX Design',
            'UI Design', 'Conversão Online', 'Vendas Online', 'Tráfego Pago', 'Google Ads', 'Meta Ads',
            'Social Media', 'Instagram para Empresas', 'Facebook para Empresas', 'Conteúdo Digital',
            'Branding', 'Identidade Visual', 'Landing Pages', 'Funil de Vendas', 'Automação de Marketing',
            'E-mail Marketing', 'Copywriting', 'Analytics', 'Google Analytics', 'Google Search Console',
            'CRM', 'WhatsApp Business', 'Meios de Pagamento', 'Hospedagem WordPress', 'Segurança WordPress',
            'Mobile First', 'Layout Responsivo', 'Marketplace', 'Dropshipping', 'Produtos Digitais',
            'Inteligência Artificial', 'Tendências Digitais', 'Experiência do Usuário', 'Pequenas Empresas',
            'Estratégia Digital', 'Presença Online', 'Criação de Conteúdo'
        );

        $t = self::lower($topic);
        $specific = array();
        if (strpos($t, 'seo') !== false) {
            $specific = array('SEO Técnico', 'SEO On Page', 'Palavras-chave', 'Indexação', 'Busca Orgânica', 'Otimização de Conteúdo', 'Intenção de Busca');
        } elseif (strpos($t, 'ads') !== false || strpos($t, 'tráfego') !== false) {
            $specific = array('Campanhas Online', 'Anúncios Digitais', 'ROI', 'Remarketing', 'Criativos de Anúncios', 'Mídia Paga', 'Landing Page de Alta Conversão');
        } elseif (strpos($t, 'woocommerce') !== false || strpos($t, 'e-commerce') !== false || strpos($t, 'lojas virtuais') !== false) {
            $specific = array('Checkout', 'Carrinho de Compras', 'Página de Produto', 'Catálogo Online', 'Venda pela Internet', 'Gestão de Pedidos', 'Conversão de Loja Virtual');
        } elseif (strpos($t, 'design') !== false || strpos($t, 'ux') !== false || strpos($t, 'layout') !== false || strpos($t, 'mobile') !== false) {
            $specific = array('Design Responsivo', 'Interface Digital', 'Experiência Mobile', 'Hierarquia Visual', 'Usabilidade', 'Design Moderno', 'Arquitetura de Informação');
        } elseif (strpos($t, 'whatsapp') !== false || strpos($t, 'crm') !== false || strpos($t, 'automação') !== false) {
            $specific = array('Atendimento Digital', 'Relacionamento com Cliente', 'Leads', 'Processo Comercial', 'Integrações Digitais', 'Automação de Vendas', 'Follow-up');
        } elseif (strpos($t, 'copywriting') !== false || strpos($t, 'conteúdo') !== false) {
            $specific = array('Redação Persuasiva', 'Oferta Digital', 'Conteúdo Estratégico', 'CTA', 'Storytelling', 'Comunicação de Valor', 'Headline');
        }

        $tokens = preg_split('/\s+/', remove_accents($topic));
        foreach ($tokens as $token) {
            $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
            if (strlen($token) > 3) {
                $specific[] = ucfirst(strtolower($token)) . ' Digital';
            }
        }

        $pool = array_merge($specific, $generic);
        $offset = $total % max(1, count($pool));
        $pool = array_merge(array_slice($pool, $offset), array_slice($pool, 0, $offset));

        $tags = array();
        foreach ($pool as $tag) {
            $tag = self::normalize_spaces($tag);
            if (!$tag) {
                continue;
            }
            $key = self::lower(remove_accents($tag));
            if (!isset($tags[$key])) {
                $tags[$key] = $tag;
            }
        }
        return array_slice(array_values($tags), 0, 30);
    }

    private static function create_featured_image($post_id, $topic, $category, $title, $excerpt, $seed) {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return new WP_Error('bpv_upload_dir', $upload_dir['error']);
        }

        $filename = 'blog-privilege-' . absint($post_id) . '-' . sanitize_title($topic) . '-' . substr(md5($seed), 0, 8) . '.jpg';
        $filepath = trailingslashit($upload_dir['path']) . $filename;

        $image_meta = self::try_openverse_human_photo($filepath, $topic, $seed);
        if (is_wp_error($image_meta)) {
            $image_meta = self::try_loremflickr_business_photo($filepath, $topic, $seed);
        }
        if (is_wp_error($image_meta)) {
            $image_meta = self::try_picsum_editorial_photo($filepath, $topic, $seed);
        }
        if (is_wp_error($image_meta)) {
            for ($image_attempt = 0; $image_attempt < 3; $image_attempt++) {
                $image_meta = self::try_ai_contextual_photo($filepath, $topic, $category, $title, $excerpt, $seed . '|image-attempt-' . $image_attempt);
                if (!is_wp_error($image_meta)) {
                    break;
                }
            }
        }
        if (is_wp_error($image_meta)) {
            return $image_meta;
        }

        $filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/jpeg',
            'post_title'     => sanitize_text_field($title . ' - imagem'),
            'post_content'   => !empty($image_meta['attribution']) ? sanitize_textarea_field($image_meta['attribution']) : '',
            'post_excerpt'   => !empty($image_meta['attribution']) ? sanitize_text_field($image_meta['attribution']) : '',
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);
        if (is_wp_error($attach_id) || !$attach_id) {
            return new WP_Error('bpv_attachment', 'Não foi possível registrar a imagem na biblioteca de mídia.');
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);
        update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field('Imagem humanizada relacionada a ' . $topic));
        update_post_meta($attach_id, '_bpv_image_meta', $image_meta);
        if (!set_post_thumbnail($post_id, $attach_id)) {
            update_post_meta($post_id, '_thumbnail_id', $attach_id);
        }

        self::remember_image_log(!empty($image_meta['source']) ? $image_meta['source'] : 'imagem-humanizada');
        return $attach_id;
    }


    private static function try_ai_contextual_photo($filepath, $topic, $category, $title, $excerpt, $seed) {
        if (!function_exists('wp_remote_get')) {
            return new WP_Error('bpv_no_http', 'HTTP do WordPress indisponível para gerar imagem de IA.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $prompt = self::ai_image_prompt($topic, $category, $title, $excerpt, $seed);
        $params = array(
            'width'       => 1920,
            'height'      => 1080,
            'seed'        => abs(crc32($seed . '|ai-image')),
            'model'       => 'flux',
            'nologo'      => 'true',
            'enhance'     => 'true',
            'private'     => 'true',
            'safe'        => 'true',
        );
        $url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt) . '?' . http_build_query($params, '', '&');
        $saved = self::download_crop_save_image($url, $filepath);
        if (!is_wp_error($saved) && !self::validate_generated_image($filepath, $title, $topic)) {
            return new WP_Error('bpv_image_validation', 'Imagem gerada por IA reprovada na validação editorial.');
        }
        if (is_wp_error($saved)) {
            return $saved;
        }

        return array(
            'source'      => 'ai-contextual-realistic',
            'prompt'      => $prompt,
            'attribution' => 'Imagem realista gerada por IA para o contexto editorial: ' . $topic,
        );
    }

    private static function ai_image_prompt($topic, $category, $title, $excerpt, $seed) {
        $briefing = self::premium_visual_briefing($topic, $category, $title, $excerpt, $seed);
        return self::normalize_spaces(
            'VISUAL BRIEFING: ' . $briefing['brief'] . '. FINAL IMAGE PROMPT: REALISTIC EDITORIAL PHOTOGRAPHY, REAL PEOPLE, NATURAL HUMAN EXPRESSIONS, PROFESSIONAL BUSINESS ENVIRONMENT, CORPORATE LIFESTYLE PHOTOGRAPHY, CINEMATIC LIGHTING, 35MM CAMERA, SHALLOW DEPTH OF FIELD, HIGH DETAIL, PREMIUM MAGAZINE STYLE, Forbes and Harvard Business Review quality, modern corporate office, article title: ' . $title . ', article summary: ' . $excerpt . ', target audience: ' . $briefing['audience'] . ', category: ' . $category . ', brand identity: ' . $briefing['brand'] . ', scene: ' . $briefing['scene'] . '. NEVER GENERATE: cartoon, illustration, vector art, flat design, 3D characters, avatars, fake people, AI looking faces, text inside image, logos, watermarks.'
        );
    }



    private static function premium_visual_briefing($topic, $category, $title, $excerpt, $seed) {
        $direction = self::premium_art_direction($topic);
        return array(
            'brief'    => 'Criar fotografia editorial corporativa premium conectada ao título, resumo, público, categoria e identidade da marca',
            'audience' => self::pick(self::audiences(), $seed, 33),
            'brand'    => $direction['brand'] ? $direction['brand'] : 'autoridade digital premium, consultoria estratégica e negócios modernos',
            'scene'    => self::image_context_for_topic($topic) . ', no readable text, no logos, natural candid interaction',
        );
    }

    private static function validate_generated_image($filepath, $title, $topic) {
        $size = @getimagesize($filepath);
        if (!$size || absint($size[0]) < 1200 || absint($size[1]) < 675) {
            return false;
        }
        $ratio = absint($size[0]) / max(1, absint($size[1]));
        $allowed_types = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP);
        if (!in_array((int) $size[2], $allowed_types, true) || $ratio < 1.55 || $ratio > 1.95) {
            return false;
        }
        // Validação técnica objetiva antes de salvar: formato real, proporção editorial e arquivo não vazio.
        return filesize($filepath) > 80000;
    }

    private static function image_context_for_topic($topic) {
        $t = self::lower($topic);
        if (strpos($t, 'e-commerce') !== false || strpos($t, 'lojas virtuais') !== false || strpos($t, 'checkout') !== false || strpos($t, 'produto') !== false) {
            return 'entrepreneur managing an online store on a laptop, product samples on a clean desk, packaging materials in the background, credible ecommerce atmosphere';
        }
        if (strpos($t, 'seo') !== false || strpos($t, 'google') !== false || strpos($t, 'busca') !== false || strpos($t, 'tráfego orgânico') !== false || strpos($t, 'palavras-chave') !== false) {
            return 'marketing analyst reviewing organic search dashboards on a laptop with a colleague, modern office, charts visible but unreadable';
        }
        if (strpos($t, 'marca') !== false || strpos($t, 'branding') !== false || strpos($t, 'identidade') !== false || strpos($t, 'rebranding') !== false || strpos($t, 'naming') !== false || strpos($t, 'nome') !== false) {
            return 'brand strategy workshop with designers discussing color palettes, typography samples and mood boards on a table, realistic agency setting';
        }
        if (strpos($t, 'design') !== false || strpos($t, 'visual') !== false || strpos($t, 'criativos') !== false || strpos($t, 'apresentações') !== false) {
            return 'professional designer working on campaign visuals in a bright creative studio, laptop and design boards, realistic human scene';
        }
        if (strpos($t, 'site') !== false || strpos($t, 'landing') !== false || strpos($t, 'ux') !== false || strpos($t, 'ui') !== false || strpos($t, 'responsividade') !== false) {
            return 'web design team planning a professional website on laptop and mobile screens, user journey sketches on desk, realistic office environment';
        }
        if (strpos($t, 'leads') !== false || strpos($t, 'funil') !== false || strpos($t, 'marketing') !== false || strpos($t, 'digital') !== false || strpos($t, 'clientes') !== false) {
            return 'digital marketing team planning a customer acquisition funnel around a laptop, business meeting, natural realistic photo';
        }
        return 'professional business team in a modern agency office working on digital strategy around a laptop, realistic people, clean premium composition';
    }

    private static function try_openverse_human_photo($filepath, $topic, $seed) {
        if (!function_exists('wp_remote_get')) {
            return new WP_Error('bpv_no_http', 'HTTP do WordPress indisponível.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $queries = self::openverse_queries($topic, $seed);
        $license_attempts = array(
            array('license' => 'cc0,pdm', 'label' => 'openverse-cc0-pdm'),
            array('license_type' => 'commercial', 'label' => 'openverse-commercial'),
        );

        foreach ($license_attempts as $license_params) {
            foreach ($queries as $q_index => $query) {
                $params = array(
                    'q'         => $query,
                    'page_size' => 12,
                    'page'      => (abs(crc32($seed . '|page|' . $q_index)) % 8) + 1,
                    'mature'    => 'false',
                    'category'  => 'photograph',
                );
                foreach ($license_params as $k => $v) {
                    if ($k !== 'label') {
                        $params[$k] = $v;
                    }
                }

                $url = 'https://api.openverse.org/v1/images/?' . http_build_query($params, '', '&');
                $response = wp_remote_get($url, array(
                    'timeout' => 12,
                    'headers' => array(
                        'User-Agent' => 'Blog Privilege WordPress Plugin/' . self::VERSION . '; ' . home_url('/'),
                        'Accept'     => 'application/json',
                    ),
                ));

                if (is_wp_error($response)) {
                    continue;
                }

                $code = absint(wp_remote_retrieve_response_code($response));
                if ($code < 200 || $code >= 300) {
                    continue;
                }

                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (empty($data['results']) || !is_array($data['results'])) {
                    continue;
                }

                $results = self::prioritize_photo_results($data['results'], $seed);
                foreach ($results as $result) {
                    if (empty($result['url'])) {
                        continue;
                    }
                    if (!empty($result['width']) && !empty($result['height'])) {
                        if (absint($result['width']) < 1200 || absint($result['height']) < 675) {
                            continue;
                        }
                    }
                    if (self::openverse_result_looks_illustrative($result)) {
                        continue;
                    }

                    $saved = self::download_crop_save_image($result['url'], $filepath);
                    if (is_wp_error($saved)) {
                        continue;
                    }

                    $attribution = self::format_openverse_attribution($result);
                    return array(
                        'source'              => $license_params['label'],
                        'query'               => $query,
                        'title'               => isset($result['title']) ? sanitize_text_field($result['title']) : '',
                        'creator'             => isset($result['creator']) ? sanitize_text_field($result['creator']) : '',
                        'license'             => isset($result['license']) ? sanitize_text_field($result['license']) : '',
                        'license_url'         => isset($result['license_url']) ? esc_url_raw($result['license_url']) : '',
                        'foreign_landing_url' => isset($result['foreign_landing_url']) ? esc_url_raw($result['foreign_landing_url']) : '',
                        'attribution'         => $attribution,
                    );
                }
            }
        }

        return new WP_Error('bpv_photo_not_found', 'Não foi possível obter uma foto humanizada externa.');
    }



    private static function openverse_result_looks_illustrative($result) {
        $haystack_parts = array();
        foreach (array('title', 'creator', 'source', 'category', 'url', 'foreign_landing_url') as $key) {
            if (!empty($result[$key]) && is_scalar($result[$key])) {
                $haystack_parts[] = (string) $result[$key];
            }
        }
        if (!empty($result['tags']) && is_array($result['tags'])) {
            foreach ($result['tags'] as $tag) {
                if (is_array($tag) && !empty($tag['name'])) {
                    $haystack_parts[] = (string) $tag['name'];
                } elseif (is_scalar($tag)) {
                    $haystack_parts[] = (string) $tag;
                }
            }
        }
        $haystack = self::lower(remove_accents(implode(' ', $haystack_parts)));
        $blocked = array('illustration', 'illustrator', 'vector', 'cartoon', 'clipart', 'clip art', 'avatar', 'icon', '3d render', 'render', 'drawing', 'sketch', 'anime', 'mascot', 'logo', 'watermark', 'typography', 'text');
        foreach ($blocked as $term) {
            if (strpos($haystack, $term) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function openverse_queries($topic, $seed) {
        $t = self::lower($topic);
        $base = array(
            'business person laptop office website',
            'entrepreneur working laptop digital marketing',
            'small business owner laptop online store',
            'marketing team office laptop meeting',
            'designer working website laptop office',
            'business people planning website strategy',
        );

        if (strpos($t, 'e-commerce') !== false || strpos($t, 'woocommerce') !== false || strpos($t, 'lojas virtuais') !== false) {
            $base = array_merge(array('online store owner laptop packing orders', 'ecommerce entrepreneur laptop office', 'person managing online shop laptop'), $base);
        } elseif (strpos($t, 'seo') !== false || strpos($t, 'analytics') !== false || strpos($t, 'search console') !== false) {
            $base = array_merge(array('marketing analyst laptop office', 'person analyzing website data laptop', 'business analytics laptop meeting'), $base);
        } elseif (strpos($t, 'ads') !== false || strpos($t, 'tráfego') !== false || strpos($t, 'social') !== false || strpos($t, 'instagram') !== false || strpos($t, 'facebook') !== false) {
            $base = array_merge(array('digital marketing team planning campaign', 'social media manager laptop office', 'advertising strategy meeting laptop'), $base);
        } elseif (strpos($t, 'design') !== false || strpos($t, 'ux') !== false || strpos($t, 'layout') !== false || strpos($t, 'mobile') !== false) {
            $base = array_merge(array('web designer laptop office', 'ux designer working laptop', 'creative professional designing website'), $base);
        } elseif (strpos($t, 'whatsapp') !== false || strpos($t, 'crm') !== false || strpos($t, 'automação') !== false || strpos($t, 'e-mail') !== false) {
            $base = array_merge(array('customer service team laptop office', 'business communication laptop office', 'sales team crm laptop'), $base);
        }

        $offset = abs(crc32($seed . '|query')) % count($base);
        return array_slice(array_merge(array_slice($base, $offset), array_slice($base, 0, $offset)), 0, 4);
    }



    private static function try_loremflickr_business_photo($filepath, $topic, $seed) {
        if (!function_exists('wp_remote_get')) {
            return new WP_Error('bpv_no_http', 'HTTP do WordPress indisponível.');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $keywords = 'business,team,office,laptop,people,meeting';
        $url = 'https://loremflickr.com/1920/1080/' . $keywords . '/all?lock=' . (abs(crc32($topic . $seed)) % 99999);
        $saved = self::download_crop_save_image($url, $filepath);
        if (is_wp_error($saved) || !self::validate_generated_image($filepath, 'business team', $topic)) {
            return is_wp_error($saved) ? $saved : new WP_Error('bpv_loremflickr_validation', 'Foto gratuita reprovada na validação técnica.');
        }
        return array(
            'source' => 'loremflickr-business-photo',
            'query' => 'business team office laptop people meeting',
            'attribution' => 'Foto gratuita obtida via LoremFlickr para padrão editorial corporativo.',
        );
    }



    private static function try_picsum_editorial_photo($filepath, $topic, $seed) {
        if (!function_exists('wp_remote_get')) {
            return new WP_Error('bpv_no_http', 'HTTP do WordPress indisponível.');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $url = 'https://picsum.photos/seed/' . rawurlencode('blog-privilege-' . sanitize_title($topic) . '-' . substr(md5($seed), 0, 8)) . '/1920/1080';
        $saved = self::download_crop_save_image($url, $filepath);
        if (is_wp_error($saved) || !self::validate_generated_image($filepath, 'editorial business photo', $topic)) {
            return is_wp_error($saved) ? $saved : new WP_Error('bpv_picsum_validation', 'Foto editorial gratuita reprovada na validação técnica.');
        }
        return array(
            'source' => 'picsum-editorial-photo',
            'query' => 'editorial business photo fallback',
            'attribution' => 'Foto editorial gratuita obtida via Picsum como fallback fotográfico.',
        );
    }

    private static function prioritize_photo_results($results, $seed) {
        usort($results, function($a, $b) use ($seed) {
            $aw = !empty($a['width']) ? absint($a['width']) : 0;
            $ah = !empty($a['height']) ? absint($a['height']) : 0;
            $bw = !empty($b['width']) ? absint($b['width']) : 0;
            $bh = !empty($b['height']) ? absint($b['height']) : 0;
            $ascore = ($aw >= 1600 ? 2 : 0) + ($ah >= 900 ? 2 : 0) + abs(crc32($seed . ($a['id'] ?? $a['url'] ?? 'a'))) % 3;
            $bscore = ($bw >= 1600 ? 2 : 0) + ($bh >= 900 ? 2 : 0) + abs(crc32($seed . ($b['id'] ?? $b['url'] ?? 'b'))) % 3;
            return $bscore <=> $ascore;
        });
        return $results;
    }

    private static function download_crop_save_image($url, $filepath) {
        $tmp = download_url($url, 20);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $size = @getimagesize($tmp);
        if (!$size || empty($size[0]) || empty($size[1])) {
            @unlink($tmp);
            return new WP_Error('bpv_not_image', 'Arquivo baixado não é imagem válida.');
        }

        $editor = wp_get_image_editor($tmp);
        if (is_wp_error($editor)) {
            if (@copy($tmp, $filepath)) {
                @unlink($tmp);
                return true;
            }
            @unlink($tmp);
            return $editor;
        }

        $resized = $editor->resize(1920, 1080, true);
        if (is_wp_error($resized)) {
            @unlink($tmp);
            return $resized;
        }

        $saved = $editor->save($filepath, 'image/jpeg');
        @unlink($tmp);

        if (is_wp_error($saved)) {
            return $saved;
        }
        if (!file_exists($filepath)) {
            return new WP_Error('bpv_image_save', 'Imagem externa não foi salva.');
        }
        return true;
    }

    private static function format_openverse_attribution($result) {
        $parts = array();
        if (!empty($result['title'])) {
            $parts[] = 'Imagem: ' . wp_strip_all_tags($result['title']);
        }
        if (!empty($result['creator'])) {
            $parts[] = 'Criador: ' . wp_strip_all_tags($result['creator']);
        }
        if (!empty($result['license'])) {
            $parts[] = 'Licença: ' . wp_strip_all_tags($result['license']);
        }
        if (!empty($result['foreign_landing_url'])) {
            $parts[] = 'Fonte: ' . esc_url_raw($result['foreign_landing_url']);
        }
        return self::normalize_spaces(implode(' | ', $parts));
    }

    private static function content_was_used($html) {
        $plain = self::content_signature_text($html);
        $hashes = get_option(self::OPTION_CONTENT_HASHES, array());
        if (!is_array($hashes)) {
            $hashes = array();
        }
        $hash = md5($plain);
        if (isset($hashes[$hash])) {
            return true;
        }

        $stored_phrases = get_option(self::OPTION_PHRASE_HASHES, array());
        if (!is_array($stored_phrases)) {
            $stored_phrases = array();
        }
        $current = self::phrase_hashes($html);
        foreach ($current as $phrase_hash) {
            if (isset($stored_phrases[$phrase_hash])) {
                return true;
            }
        }
        return false;
    }

    private static function remember_content($html) {
        $plain = self::content_signature_text($html);
        $hashes = get_option(self::OPTION_CONTENT_HASHES, array());
        if (!is_array($hashes)) {
            $hashes = array();
        }
        $hashes[md5($plain)] = time();
        $hashes = self::trim_assoc($hashes, 700);
        update_option(self::OPTION_CONTENT_HASHES, $hashes, false);

        $phrases = get_option(self::OPTION_PHRASE_HASHES, array());
        if (!is_array($phrases)) {
            $phrases = array();
        }
        foreach (self::phrase_hashes($html) as $phrase_hash) {
            $phrases[$phrase_hash] = time();
        }
        $phrases = self::trim_assoc($phrases, 1800);
        update_option(self::OPTION_PHRASE_HASHES, $phrases, false);
    }

    private static function remember_title($title) {
        $used = get_option(self::OPTION_TITLE_HASHES, array());
        if (!is_array($used)) {
            $used = array();
        }
        $used[md5(self::lower(remove_accents($title)))] = time();
        $used = self::trim_assoc($used, 1000);
        update_option(self::OPTION_TITLE_HASHES, $used, false);
    }

    private static function remember_image_log($source) {
        $log = get_option(self::OPTION_IMAGE_LOG, array());
        if (!is_array($log)) {
            $log = array();
        }
        $log[] = wp_date('d/m/Y H:i') . ' - ' . $source;
        if (count($log) > 25) {
            $log = array_slice($log, -25);
        }
        update_option(self::OPTION_IMAGE_LOG, $log, false);
    }



    private static function remember_generation_diagnostic($post_id, $slug, $seo, $image_result) {
        $log = get_option(self::OPTION_DIAGNOSTIC_LOG, array());
        if (!is_array($log)) {
            $log = array();
        }
        $entry = array(
            'time' => wp_date('d/m/Y H:i:s'),
            'artigo' => array('status' => 'OK', 'message' => 'Post #' . absint($post_id) . ' criado.'),
            'seo' => array('status' => 'OK', 'message' => $seo['title'] . ' | ' . $seo['description']),
            'slug' => array('status' => (strlen($slug) <= 60 && count(explode('-', $slug)) >= 3 ? 'OK' : 'ERRO'), 'message' => $slug),
            'imagem' => array('status' => is_wp_error($image_result) ? 'ERRO' : 'OK', 'message' => is_wp_error($image_result) ? $image_result->get_error_message() : 'Imagem destacada registrada.'),
            'publicacao' => array('status' => get_post_status($post_id) === 'publish' ? 'OK' : 'ERRO', 'message' => get_post_status($post_id)),
        );
        if (is_wp_error($image_result)) {
            $entry['error'] = array('file' => basename(__FILE__), 'function' => 'create_featured_image', 'message' => $image_result->get_error_message());
        }
        $log[] = $entry;
        update_option(self::OPTION_DIAGNOSTIC_LOG, array_slice($log, -20), false);
    }

    private static function content_signature_text($html) {
        $plain = wp_strip_all_tags($html);
        $plain = self::lower(remove_accents($plain));
        $plain = preg_replace('/\s+/', ' ', $plain);
        return trim($plain);
    }

    private static function phrase_hashes($html) {
        $plain = wp_strip_all_tags($html);
        $plain = preg_replace('/\s+/', ' ', $plain);
        $parts = preg_split('/(?<=[.!?])\s+/', $plain);
        $hashes = array();
        foreach ($parts as $part) {
            $part = self::normalize_spaces($part);
            if (self::strlen($part) < 110) {
                continue;
            }
            $hashes[] = md5(self::lower(remove_accents($part)));
        }
        return array_unique($hashes);
    }

    private static function trim_assoc($array, $max) {
        if (count($array) <= $max) {
            return $array;
        }
        asort($array);
        return array_slice($array, -$max, null, true);
    }

    private static function seed($topic, $total, $attempt = 0) {
        return $topic . '|' . absint($total) . '|' . absint($attempt) . '|' . wp_date('Y-m-d-H-i') . '|' . wp_salt('nonce');
    }

    private static function pick($items, $seed, $shift = 0) {
        if (empty($items)) {
            return '';
        }
        $key = abs(crc32((string) $seed . '|' . (string) $shift));
        return $items[$key % count($items)];
    }

    private static function limit_text($text, $max) {
        $text = self::normalize_spaces($text);
        if (self::strlen($text) <= $max) {
            return $text;
        }
        $cut = self::safe_substr($text, 0, $max);
        $pos = strrpos($cut, ' ');
        if ($pos !== false && $pos > 10) {
            $cut = substr($cut, 0, $pos);
        }
        $cut = rtrim($cut, ' ,.;:-');
        if (self::strlen($cut) > $max) {
            $cut = self::safe_substr($cut, 0, $max);
        }
        return self::normalize_spaces($cut);
    }

    private static function normalize_spaces($text) {
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return trim($text);
    }


    /**
     * Diretor de Arte IA Premium.
     * Cria uma camada de briefing visual antes da geração da imagem.
     */
    private static function premium_art_direction($topic) {
        return array(
            'style' => get_option(self::OPTION_ART_STYLE, 'Fotografia corporativa premium'),
            'brand' => get_option(self::OPTION_ART_BRAND, ''),
            'avoid' => get_option(self::OPTION_ART_AVOID, ''),
            'prompt' => 'Imagem editorial premium relacionada ao tema: ' . $topic . '. Fotografia corporativa realista de alta qualidade. Pessoas reais em ambiente profissional, interação natural, estilo revista Forbes/Harvard Business Review, lente 35mm, profundidade de campo, iluminação cinematográfica, composição premium para capa de blog. Proibido: ilustração, desenho, avatar, personagem artificial, render 3D, texto dentro da imagem.'
        );
    }

    private static function lower($text) {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower((string) $text, 'UTF-8');
        }
        return strtolower((string) $text);
    }

    private static function strlen($text) {
        if (function_exists('mb_strlen')) {
            return mb_strlen((string) $text, 'UTF-8');
        }
        return strlen((string) $text);
    }

    private static function safe_substr($text, $start, $length = null) {
        if (function_exists('mb_substr')) {
            return $length === null ? mb_substr((string) $text, $start, null, 'UTF-8') : mb_substr((string) $text, $start, $length, 'UTF-8');
        }
        return $length === null ? substr((string) $text, $start) : substr((string) $text, $start, $length);
    }
}

register_activation_hook(__FILE__, array('BPV_Blog_Privilege', 'activate'));
register_deactivation_hook(__FILE__, array('BPV_Blog_Privilege', 'deactivate'));
BPV_Blog_Privilege::init();
