<?php
/**
 * Plugin Name: Portal Grupos WhatsApp
 * Plugin URI: https://portalgruposwhatsapp.com.br
 * Description: Diretório independente de grupos, canais e comunidades do WhatsApp.
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Portal Grupos WhatsApp
 * Text Domain: portal-grupos-whatsapp
 * Domain Path: /languages
 */

declare(strict_types=1);

namespace PGW;

if (!defined('ABSPATH')) { exit; }

final class Plugin {
    private const VERSION = '1.0.0';
    private const CPT = 'pgw_group';
    private const CAT = 'pgw_category';
    private const TYPE = 'pgw_group_type';
    private const LOCATION = 'pgw_location';
    private const OPTION_DB = 'pgw_db_version';

    public static function boot(): void {
        add_action('init', [self::class, 'register_content']);
        add_action('init', [self::class, 'register_shortcodes']);
        add_action('init', [self::class, 'register_rewrites']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('admin_init', [self::class, 'settings']);
        add_action('admin_post_pgw_submit_group', [self::class, 'handle_submit']);
        add_action('admin_post_nopriv_pgw_submit_group', [self::class, 'require_login']);
        add_action('admin_post_pgw_update_profile', [self::class, 'handle_profile']);
        add_action('admin_post_pgw_moderate_group', [self::class, 'handle_moderation']);
        add_action('admin_post_pgw_logout', [self::class, 'handle_logout']);
        add_action('admin_post_pgw_update_email', [self::class, 'handle_email_change']);
        add_action('admin_post_pgw_update_password', [self::class, 'handle_password_change']);
        add_action('admin_post_pgw_delete_group', [self::class, 'handle_delete_group']);
        add_action('admin_post_pgw_update_group', [self::class, 'handle_update_group']);
        add_action('admin_post_pgw_upload_avatar', [self::class, 'handle_avatar_upload']);
        add_action('admin_post_pgw_delete_account_request', [self::class, 'handle_account_deletion_request']);
        add_action('admin_post_pgw_resolve_report', [self::class, 'handle_resolve_report']);
        add_action('wp_ajax_pgw_load_groups', [self::class, 'ajax_groups']);
        add_action('wp_ajax_pgw_resend_otp', [self::class, 'ajax_resend_otp']);
        add_action('wp_ajax_nopriv_pgw_resend_otp', [self::class, 'ajax_resend_otp']);
        add_action('wp_ajax_nopriv_pgw_load_groups', [self::class, 'ajax_groups']);
        add_action('wp_ajax_pgw_report_group', [self::class, 'ajax_report']);
        add_action('wp_enqueue_scripts', [self::class, 'frontend_runtime'], 20);
        add_action('save_post_' . self::CPT, [self::class, 'sync_business_status'], 20, 3);
        add_action('transition_post_status', [self::class, 'audit_status_transition'], 10, 3);
        add_action('wp_ajax_nopriv_pgw_report_group', [self::class, 'ajax_report']);
        add_action('template_redirect', [self::class, 'redirect_group']);
        add_filter('the_content', [self::class, 'single_content']);
        add_filter('pre_get_document_title', [self::class, 'seo_title']);
        add_action('wp_head', [self::class, 'seo_meta'], 1);
        add_action('pgw_cleanup_cron', [self::class, 'cron_cleanup']);
        add_action('pgw_link_check_cron', [self::class, 'cron_check_links']);
        add_action('user_register', [self::class, 'on_user_register'], 10, 1);
        add_action('delete_user', [self::class, 'on_user_delete'], 10, 1);
        add_filter('get_avatar', [self::class, 'filter_avatar'], 10, 6);
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'register_privacy_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'register_privacy_eraser']);
        add_action('admin_notices', [self::class, 'admin_notices']);
    }

    public static function activate(): void {
        self::register_content(); self::roles(); self::defaults(); self::seed_terms(); self::create_tables();
        update_option(self::OPTION_DB, 2, false);
        if (!wp_next_scheduled('pgw_cleanup_cron')) wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'pgw_cleanup_cron');
        if (!wp_next_scheduled('pgw_link_check_cron')) wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'twicedaily', 'pgw_link_check_cron');
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('pgw_cleanup_cron');
        wp_clear_scheduled_hook('pgw_link_check_cron');
        flush_rewrite_rules();
    }

    public static function on_user_register(int $user_id): void {
        if (!get_user_meta($user_id, 'pgw_account_status', true)) update_user_meta($user_id, 'pgw_account_status', 'active');
        if (!get_user_meta($user_id, 'pgw_auth_providers', true)) update_user_meta($user_id, 'pgw_auth_providers', ['email']);
        update_user_meta($user_id, 'pgw_registered_at', current_time('mysql', true));
    }

    private static function request_ip_hash(): string {
        return hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), wp_salt('nonce'));
    }

    private static function avatar_html(int $user_id, int $size = 96, string $class = 'pgw-avatar'): string {
        $attachment_id = absint(get_user_meta($user_id, 'pgw_avatar_id', true));
        if ($attachment_id) {
            $image = wp_get_attachment_image($attachment_id, [$size, $size], false, ['class' => $class, 'loading' => 'lazy', 'alt' => '']);
            if ($image) return $image;
        }
        $user = get_userdata($user_id);
        $initial = $user ? mb_strtoupper(mb_substr((string) $user->display_name, 0, 1)) : '?';
        return '<span class="'.esc_attr($class).' pgw-avatar--initials" aria-hidden="true">'.esc_html($initial).'</span>';
    }

    public static function filter_avatar(string $avatar, mixed $id_or_email, int $size, string $default, string $alt, array $args): string {
        $user = false;
        if ($id_or_email instanceof \WP_User) $user = $id_or_email;
        elseif (is_numeric($id_or_email)) $user = get_userdata((int) $id_or_email);
        elseif (is_object($id_or_email) && !empty($id_or_email->user_id)) $user = get_userdata((int) $id_or_email->user_id);
        elseif (is_string($id_or_email) && is_email($id_or_email)) $user = get_user_by('email', $id_or_email);
        if (!$user) return $avatar;
        $attachment_id = absint(get_user_meta($user->ID, 'pgw_avatar_id', true));
        if (!$attachment_id) return $avatar;
        $image = wp_get_attachment_image($attachment_id, [$size, $size], false, ['class' => trim('avatar avatar-'.$size.' photo '.($args['class'] ?? '')), 'alt' => $alt, 'loading' => $args['loading'] ?? 'lazy']);
        return $image ?: $avatar;
    }

    private static function allowed_image_upload(array $file, int $max_bytes = 5242880): bool {
        if (empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name']) || (int) ($file['size'] ?? 0) < 1 || (int) $file['size'] > $max_bytes) return false;
        $checked = wp_check_filetype_and_ext((string) $file['tmp_name'], (string) ($file['name'] ?? ''), ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']);
        return !empty($checked['type']) && in_array($checked['type'], ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    private static function attach_group_image(string $field, int $group_id): int {
        if (empty($_FILES[$field]) || !self::allowed_image_upload($_FILES[$field])) return 0;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_handle_upload($field, $group_id, [], ['test_form' => false, 'mimes' => ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']]);
        if (is_wp_error($attachment_id)) return 0;
        set_post_thumbnail($group_id, $attachment_id);
        update_post_meta($group_id, 'pgw_original_image_id', $attachment_id);
        update_post_meta($group_id, 'pgw_square_image_id', $attachment_id);
        return $attachment_id;
    }

    private static function create_otp(int $user_id, string $purpose = 'login'): string {
        global $wpdb;
        $purpose = sanitize_key($purpose);
        $code = (string) random_int(100000, 999999);
        $selector = bin2hex(random_bytes(12));
        $table = $wpdb->prefix . 'pgw_auth_challenges';
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET consumed_at = %s WHERE user_id = %d AND purpose = %s AND consumed_at IS NULL", current_time('mysql', true), $user_id, $purpose));
        $wpdb->insert($table, ['selector'=>$selector,'token_hash'=>hash_hmac('sha256', $selector, wp_salt('auth')),'code_hash'=>wp_hash_password($code),'user_id'=>$user_id,'purpose'=>$purpose,'email_hash'=>hash_hmac('sha256',(string) get_userdata($user_id)->user_email,wp_salt('auth')),'attempts'=>0,'max_attempts'=>5,'expires_at'=>gmdate('Y-m-d H:i:s', time() + 10 * MINUTE_IN_SECONDS),'created_at'=>current_time('mysql', true),'ip_hash'=>self::request_ip_hash(),'user_agent_hash'=>hash_hmac('sha256',(string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),wp_salt('nonce'))], ['%s','%s','%s','%d','%s','%s','%d','%d','%s','%s','%s','%s','%s']);
        update_user_meta($user_id, 'pgw_otp_selector', $selector);
        return $code;
    }

    private static function verify_otp(int $user_id, string $code, string $purpose): bool {
        global $wpdb;
        $selector = (string) get_user_meta($user_id, 'pgw_otp_selector', true);
        if (!$selector || !preg_match('/^[a-f0-9]{24}$/', $selector)) return false;
        $table = $wpdb->prefix . 'pgw_auth_challenges';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE selector = %s AND user_id = %d AND purpose = %s AND consumed_at IS NULL ORDER BY id DESC LIMIT 1", $selector, $user_id, sanitize_key($purpose)));
        if (!$row || strtotime((string) $row->expires_at) < time() || (int) $row->attempts >= (int) $row->max_attempts) return false;
        $wpdb->update($table, ['attempts'=>(int) $row->attempts + 1], ['id'=>(int) $row->id], ['%d'], ['%d']);
        if (!wp_check_password($code, (string) $row->code_hash, $user_id)) return false;
        $wpdb->update($table, ['consumed_at'=>current_time('mysql', true)], ['id'=>(int) $row->id], ['%s'], ['%d']);
        delete_user_meta($user_id, 'pgw_otp_selector');
        return true;
    }

    private static function rate_limit(string $action, string $subject, int $limit, int $window = 3600): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'pgw_rate_limits';
        $action = sanitize_key($action); $subject_hash = hash_hmac('sha256', $subject, wp_salt('nonce')); $now = time();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE action_key = %s AND subject_hash = %s LIMIT 1", $action, $subject_hash));
        if (!$row || strtotime((string) $row->window_start) + $window <= $now) {
            if ($row) $wpdb->update($table, ['window_start'=>gmdate('Y-m-d H:i:s',$now),'count'=>1,'blocked_until'=>null,'updated_at'=>current_time('mysql',true)], ['id'=>(int)$row->id]);
            else $wpdb->insert($table, ['action_key'=>$action,'subject_hash'=>$subject_hash,'window_start'=>gmdate('Y-m-d H:i:s',$now),'count'=>1,'updated_at'=>current_time('mysql',true)]);
            return true;
        }
        if (!empty($row->blocked_until) && strtotime((string)$row->blocked_until) > $now) return false;
        if ((int) $row->count >= $limit) { $wpdb->update($table, ['blocked_until'=>gmdate('Y-m-d H:i:s',$now + min(DAY_IN_SECONDS, 300 * max(1, (int)$row->count - $limit + 1))),'updated_at'=>current_time('mysql',true)], ['id'=>(int)$row->id]); return false; }
        $wpdb->update($table, ['count'=>(int)$row->count + 1,'updated_at'=>current_time('mysql',true)], ['id'=>(int)$row->id]);
        return true;
    }

    private static function send_otp(int $user_id, string $purpose): bool {
        $user = get_userdata($user_id); if (!$user || !is_email($user->user_email)) return false;
        if (!self::rate_limit('otp_' . $purpose, strtolower($user->user_email), 5, HOUR_IN_SECONDS)) return false;
        $last = (int) get_user_meta($user_id, 'pgw_otp_sent_at', true);
        if ($last && (time() - $last) < 60) return false;
        $code = self::create_otp($user_id, $purpose);
        update_user_meta($user_id, 'pgw_otp_sent_at', time());
        return wp_mail($user->user_email, 'Seu código de segurança — Portal Grupos WhatsApp', "Seu código é: {$code}\n\nEle expira em 10 minutos. Se você não solicitou este código, ignore esta mensagem.");
    }

    public static function on_user_delete(int $user_id): void {
        $groups = get_posts(['post_type' => self::CPT, 'author' => $user_id, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']);
        foreach ($groups as $group_id) {
            update_post_meta($group_id, 'pgw_previous_owner_id', $user_id);
            wp_update_post(['ID' => $group_id, 'post_author' => 0, 'post_status' => 'draft']);
            update_post_meta($group_id, 'pgw_status', 'inactive');
        }
    }

    public static function cron_cleanup(): void {
        global $wpdb;
        $events = $wpdb->prefix . 'pgw_events';
        $reports = $wpdb->prefix . 'pgw_reports';
        $cutoff = gmdate('Y-m-d H:i:s', time() - (int) apply_filters('pgw_event_retention_seconds', YEAR_IN_SECONDS));
        $wpdb->query($wpdb->prepare("DELETE FROM {$events} WHERE created_at < %s", $cutoff));
        $wpdb->query($wpdb->prepare("DELETE FROM {$reports} WHERE status IN ('resolved','improper') AND updated_at < %s", gmdate('Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS)));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}pgw_auth_challenges WHERE expires_at < %s OR (consumed_at IS NOT NULL AND consumed_at < %s)", current_time('mysql', true), gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)));
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}pgw_rate_limits WHERE updated_at < %s", gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)));
        $pending = get_users(['role' => 'pgw_pending_member', 'fields' => 'ids', 'meta_query' => [['key' => 'pgw_registered_at', 'value' => gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS), 'compare' => '<', 'type' => 'DATETIME']]]);
        foreach ($pending as $user_id) {
            if (!get_posts(['post_type' => self::CPT, 'author' => $user_id, 'numberposts' => 1, 'fields' => 'ids'])) wp_delete_user($user_id);
        }
    }

    public static function cron_check_links(): void {
        $groups = get_posts(['post_type' => self::CPT, 'post_status' => 'publish', 'numberposts' => 25, 'meta_query' => [['key' => 'pgw_link_status', 'value' => ['expired', 'inactive'], 'compare' => 'NOT IN']]]);
        foreach ($groups as $group) {
            $url = self::sanitize_invite(get_post_meta($group->ID, 'pgw_invite_url', true));
            if (!$url) { update_post_meta($group->ID, 'pgw_link_status', 'invalid'); continue; }
            $response = wp_safe_remote_head($url, ['timeout' => 5, 'redirection' => 2, 'user-agent' => 'PGW-LinkChecker/1.0']);
            $status = is_wp_error($response) ? 'unknown' : ((int) wp_remote_retrieve_response_code($response) >= 400 ? 'possibly_inactive' : 'active');
            update_post_meta($group->ID, 'pgw_link_status', sanitize_key($status));
            update_post_meta($group->ID, 'pgw_last_link_check', current_time('mysql', true));
        }
    }

    private static function defaults(): void {
        $defaults = [
            'pgw_logo_url' => 'https://portalgruposwhatsapp.com.br/wp-content/uploads/2026/08/logo.png',
            'pgw_initial_limit' => 30, 'pgw_load_amount' => 15, 'pgw_require_approval' => 1,
            'pgw_email_otp_enabled' => 1, 'pgw_otp_expiry' => 10, 'pgw_allow_location' => 1,
            'pgw_card_title_limit' => 52, 'pgw_card_description_limit' => 145,
        ];
        foreach ($defaults as $key => $value) { add_option($key, $value, '', false); }
    }

    private static function roles(): void {
        add_role('pgw_member', 'Membro do Portal', ['read'=>true,'pgw_submit_groups'=>true,'pgw_edit_own_groups'=>true,'pgw_delete_own_groups'=>true,'pgw_view_own_groups'=>true,'pgw_manage_own_profile'=>true]);
        add_role('pgw_pending_member', 'Membro pendente', ['read'=>true]);
        $admin = get_role('administrator');
        if ($admin) foreach (['pgw_manage_groups','pgw_moderate_groups','pgw_manage_settings','pgw_manage_featured','pgw_manage_reports','pgw_view_metrics','pgw_manage_categories','pgw_manage_users','pgw_manage_emails','pgw_manage_demo'] as $cap) $admin->add_cap($cap);
    }

    private static function seed_terms(): void {
        foreach (['Grupo','Canal','Comunidade'] as $name) if (!term_exists($name, self::TYPE)) wp_insert_term($name, self::TYPE);
        $cats = ['Amizade','Amor e Romance','Artesanato','Carros e Motos','Cidades','Compra e Venda','Concursos','Design','Educação','Empregos','Empreendedorismo','Esportes','Futebol','Games e Jogos','Marketing','Memes e Humor','Música','Notícias','Outros','Pets','Receitas','Religião','Sustentabilidade','Tecnologia','TV','Viagem e Turismo'];
        foreach ($cats as $name) if (!term_exists($name, self::CAT)) wp_insert_term($name, self::CAT);
    }

    private static function create_tables(): void {
        global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php'; $charset = $wpdb->get_charset_collate();
        $events = $wpdb->prefix . 'pgw_events'; $reports = $wpdb->prefix . 'pgw_reports'; $challenges = $wpdb->prefix . 'pgw_auth_challenges'; $limits = $wpdb->prefix . 'pgw_rate_limits'; $audit = $wpdb->prefix . 'pgw_audit_log';
        dbDelta("CREATE TABLE {$events} (id bigint unsigned NOT NULL AUTO_INCREMENT,event_type varchar(40) NOT NULL,group_id bigint unsigned NULL,user_id bigint unsigned NULL,session_hash char(64) NULL,source varchar(40) NULL,created_at datetime NOT NULL,PRIMARY KEY(id),KEY group_event(group_id,event_type),KEY created_at(created_at)) {$charset};");
        dbDelta("CREATE TABLE {$reports} (id bigint unsigned NOT NULL AUTO_INCREMENT,group_id bigint unsigned NOT NULL,reporter_user_id bigint unsigned NULL,reason varchar(60) NOT NULL,details text NULL,status varchar(20) NOT NULL DEFAULT 'open',created_at datetime NOT NULL,updated_at datetime NOT NULL,PRIMARY KEY(id),KEY group_status(group_id,status),KEY status_date(status,created_at)) {$charset};");
        dbDelta("CREATE TABLE {$challenges} (id bigint unsigned NOT NULL AUTO_INCREMENT,selector char(24) NOT NULL,token_hash char(64) NOT NULL,code_hash varchar(255) NOT NULL,user_id bigint unsigned NOT NULL,purpose varchar(40) NOT NULL,email_hash char(64) NULL,attempts smallint unsigned NOT NULL DEFAULT 0,max_attempts smallint unsigned NOT NULL DEFAULT 5,expires_at datetime NOT NULL,consumed_at datetime NULL,created_at datetime NOT NULL,ip_hash char(64) NULL,user_agent_hash char(64) NULL,metadata longtext NULL,PRIMARY KEY(id),UNIQUE KEY selector(selector),KEY user_purpose(user_id,purpose),KEY expires_at(expires_at)) {$charset};");
        dbDelta("CREATE TABLE {$limits} (id bigint unsigned NOT NULL AUTO_INCREMENT,action_key varchar(64) NOT NULL,subject_hash char(64) NOT NULL,window_start datetime NOT NULL,count int unsigned NOT NULL DEFAULT 0,blocked_until datetime NULL,updated_at datetime NOT NULL,PRIMARY KEY(id),UNIQUE KEY action_subject(action_key,subject_hash),KEY blocked_until(blocked_until)) {$charset};");
        dbDelta("CREATE TABLE {$audit} (id bigint unsigned NOT NULL AUTO_INCREMENT,actor_user_id bigint unsigned NULL,action varchar(80) NOT NULL,object_type varchar(40) NOT NULL,object_id bigint unsigned NULL,before_data longtext NULL,after_data longtext NULL,created_at datetime NOT NULL,ip_hash char(64) NULL,PRIMARY KEY(id),KEY object_lookup(object_type,object_id),KEY created_at(created_at)) {$charset};");
    }

    public static function register_content(): void {
        register_post_type(self::CPT, ['labels'=>['name'=>'Grupos','singular_name'=>'Grupo','add_new_item'=>'Adicionar grupo','edit_item'=>'Editar grupo'], 'public'=>true,'show_in_rest'=>true,'menu_icon'=>'dashicons-groups','rewrite'=>['slug'=>'grupo','with_front'=>false],'supports'=>['title','editor','thumbnail','author','revisions'],'capability_type'=>['pgw_group','pgw_groups'],'map_meta_cap'=>true]);
        register_taxonomy(self::CAT,self::CPT,['labels'=>['name'=>'Categorias','singular_name'=>'Categoria'],'public'=>true,'show_in_rest'=>true,'hierarchical'=>true,'rewrite'=>['slug'=>'categoria']]);
        register_taxonomy(self::TYPE,self::CPT,['labels'=>['name'=>'Tipos','singular_name'=>'Tipo'],'public'=>true,'show_in_rest'=>true,'hierarchical'=>false,'rewrite'=>['slug'=>'tipo']]);
        register_taxonomy(self::LOCATION,self::CPT,['labels'=>['name'=>'Localizações','singular_name'=>'Localização'],'public'=>true,'show_in_rest'=>true,'hierarchical'=>false,'rewrite'=>['slug'=>'localizacao']]);
        $fields = ['pgw_invite_url','pgw_invite_hash','pgw_owner_id','pgw_status','pgw_rejection_reason','pgw_correction_request','pgw_rules','pgw_card_excerpt','pgw_link_status','pgw_source','pgw_focal_x','pgw_focal_y','pgw_featured_start','pgw_featured_end','pgw_last_link_check','pgw_next_link_check','pgw_last_approved_at','pgw_publication_notes'];
        foreach ($fields as $field) register_post_meta(self::CPT,$field,['type'=>'string','single'=>true,'show_in_rest'=>false,'sanitize_callback'=>'sanitize_text_field','auth_callback'=>fn()=>current_user_can('edit_posts')]);
        foreach (['pgw_featured','pgw_featured_priority','pgw_click_count','pgw_impression_count','pgw_link_failure_count','pgw_demo','pgw_original_image_id','pgw_square_image_id','pgw_hero_image_id'] as $field) register_post_meta(self::CPT,$field,['type'=>'integer','single'=>true,'show_in_rest'=>false,'sanitize_callback'=>'absint','auth_callback'=>fn()=>current_user_can('edit_posts')]);
    }

    public static function register_rewrites(): void { add_rewrite_rule('^ir/([^/]+)/?$', 'index.php?pgw_redirect=$matches[1]', 'top'); add_rewrite_tag('%pgw_redirect%','([^&]+)'); }

    public static function sanitize_invite($value): string {
        $url = esc_url_raw((string)$value); $parts = wp_parse_url($url); $allowed = ['chat.whatsapp.com','whatsapp.com','www.whatsapp.com'];
        $host = strtolower((string)($parts['host'] ?? '')); if (($parts['scheme'] ?? '') !== 'https' || !in_array($host,$allowed,true) || !empty($parts['user']) || !empty($parts['pass'])) return '';
        return remove_query_arg(['utm_source','utm_medium','utm_campaign','fbclid'], $url);
    }

    private static function trim_text(string $text, int $limit): string { $text = trim(wp_strip_all_tags($text)); if (function_exists('mb_strlen') && mb_strlen($text) <= $limit) return $text; if (strlen($text) <= $limit) return $text; $cut = function_exists('mb_substr') ? mb_substr($text,0,$limit-1) : substr($text,0,$limit-1); return preg_replace('/\s+\S*$/u','',$cut) . '…'; }
    private static function status(int $id): string { return (string)(get_post_meta($id,'pgw_status',true) ?: (get_post_status($id)==='publish'?'approved':'pending')); }
    private static function public_query_args(array $extra=[]): array { return array_merge(['post_type'=>self::CPT,'post_status'=>'publish','posts_per_page'=>30,'meta_query'=>[['key'=>'pgw_demo','compare'=>'NOT EXISTS']], 'orderby'=>'date','order'=>'DESC'], $extra); }

    public static function assets(): void {
        wp_register_style('pgw-style',false,[],self::VERSION); wp_enqueue_style('pgw-style');
        $css = ':root{--pgw-primary:#1f8236;--pgw-secondary:#03d0ae;--pgw-tertiary:#26d768;--pgw-ink:#102d19;--pgw-muted:#68756d;--pgw-border:rgba(31,130,54,.14)}.pgw-wrap{font-family:"Maven Pro",system-ui,sans-serif;background:radial-gradient(circle at 8% 0%,rgba(3,208,174,.1),transparent 30%),radial-gradient(circle at 92% 8%,rgba(38,215,104,.09),transparent 28%),#fff;color:var(--pgw-ink);padding:28px 0}.pgw-showcase-shell{max-width:1180px;width:calc(100% - 32px);margin:0 auto 34px;padding:34px 42px;border-radius:34px;background:rgba(247,249,247,.84);border:2px solid rgba(255,255,255,.94);box-shadow:0 34px 76px rgba(12,75,47,.22),0 10px 30px rgba(16,45,25,.08)}.pgw-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px;max-width:1180px;width:calc(100% - 32px);margin:auto}.pgw-card{display:flex;flex-direction:column;min-height:400px;padding:20px;border:1px solid var(--pgw-border);border-radius:27px;background:rgba(255,255,255,.8);box-shadow:0 24px 60px rgba(16,45,25,.12);text-align:center}.pgw-card--featured{background:linear-gradient(155deg,rgba(3,208,174,.94),rgba(31,130,54,.96));color:#fff;transform:translateY(-18px);box-shadow:0 34px 76px rgba(12,75,47,.22)}.pgw-card img,.pgw-placeholder{width:158px;height:158px;border-radius:27px;object-fit:cover;margin:16px auto;box-shadow:0 18px 34px rgba(16,45,25,.16);border:4px solid rgba(255,255,255,.86)}.pgw-placeholder{background:linear-gradient(135deg,var(--pgw-secondary),var(--pgw-primary));display:grid;place-items:center;color:#fff;font-size:42px;font-weight:800}.pgw-card h3{font-size:19px;line-height:1.15;margin:4px 0;height:44px;overflow:hidden}.pgw-card p{font-size:14px;line-height:1.5;color:var(--pgw-muted);height:63px;overflow:hidden;margin:8px 0}.pgw-card--featured p{color:rgba(255,255,255,.86)}.pgw-button{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:0 26px;border:0;border-radius:999px;background:var(--pgw-primary);color:#fff;font-weight:800;text-decoration:none;margin-top:auto;cursor:pointer}.pgw-card--featured .pgw-button{background:#fff;color:var(--pgw-primary)}.pgw-badge{font-weight:800;font-size:12px;color:var(--pgw-primary)}.pgw-card--featured .pgw-badge{color:#fff}.pgw-search{max-width:1180px;width:calc(100% - 32px);margin:0 auto 24px;display:flex;gap:8px}.pgw-search input,.pgw-wrap input,.pgw-wrap textarea,.pgw-wrap select{max-width:100%;border:1px solid var(--pgw-border);border-radius:12px;padding:12px}.pgw-search input{flex:1;border-radius:999px}.pgw-empty{text-align:center;padding:40px;color:var(--pgw-muted)}.pgw-categories ul{display:flex;flex-wrap:wrap;gap:10px;list-style:none;padding:0;max-width:1180px;width:calc(100% - 32px);margin:0 auto 28px}.pgw-categories a{display:block;padding:10px 16px;border-radius:999px;background:#edf4ef;color:var(--pgw-ink);text-decoration:none;font-weight:700}.pgw-auth,.pgw-submit-form{max-width:640px;margin:auto;padding:26px;border-radius:24px;background:#fff;box-shadow:0 20px 50px rgba(16,45,25,.1)}.pgw-auth label,.pgw-submit-form label{display:block;font-weight:700}.pgw-auth input:not([type=checkbox]),.pgw-submit-form input,.pgw-submit-form textarea{width:100%;box-sizing:border-box}.pgw-load-more{display:block;margin:28px auto}.pgw-admin .pgw-admin-card{display:inline-block;vertical-align:top;width:180px;margin:8px;padding:18px;border-radius:18px;background:#fff;box-shadow:0 8px 24px rgba(16,45,25,.1)}@media(max-width:900px){.pgw-grid{grid-template-columns:repeat(2,1fr)}.pgw-card--featured{transform:none}.pgw-showcase-shell{padding:26px}}@media(max-width:600px){.pgw-grid{grid-template-columns:1fr;width:calc(100% - 24px)}.pgw-search{width:calc(100% - 24px)}.pgw-card{min-height:380px}.pgw-card--featured{order:-1}.pgw-showcase-shell{width:calc(100% - 24px);padding:18px;border-radius:24px}} .pgw-header,.pgw-footer{background:linear-gradient(120deg,#0e2d19,#174f27 55%,#1f8236);color:#fff;padding:18px max(16px,calc((100% - 1180px)/2));display:flex;align-items:center;justify-content:space-between;gap:20px}.pgw-header img{display:block;max-width:180px;width:auto}.pgw-header nav{display:flex;align-items:center;gap:16px}.pgw-header a,.pgw-footer a{color:#fff;font-weight:800;text-decoration:none}.pgw-footer{display:block;margin-top:32px}.pgw-account-head{display:flex;align-items:center;gap:18px;padding-bottom:20px;border-bottom:1px solid var(--pgw-border)}.pgw-avatar{width:88px;height:88px;border-radius:28px;object-fit:cover;border:4px solid #fff;box-shadow:0 12px 26px rgba(16,45,25,.16)}.pgw-avatar--initials{display:grid;place-items:center;background:linear-gradient(135deg,var(--pgw-secondary),var(--pgw-primary));color:#fff;font-size:32px;font-weight:800}.pgw-account-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px}.pgw-status{display:inline-flex;padding:5px 10px;border-radius:999px;background:#edf4ef;color:var(--pgw-primary);font-weight:800;font-size:12px}.pgw-group-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:auto}.pgw-button--secondary{background:#edf4ef;color:var(--pgw-primary)}.pgw-danger{background:#c93d4c}.pgw-card form{margin:0}.pgw-card small{display:block;min-height:22px;color:var(--pgw-muted)}@media(max-width:600px){.pgw-header{align-items:flex-start}.pgw-header nav{flex-wrap:wrap;justify-content:flex-end}.pgw-account-head{align-items:flex-start}.pgw-avatar{width:68px;height:68px;border-radius:22px}}@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}';
        wp_add_inline_style('pgw-style',$css);
    }

    public static function admin_assets(string $hook): void { if (strpos($hook,'pgw')===false) return; wp_enqueue_style('pgw-style'); }

    public static function frontend_runtime(): void {
        if (!is_singular() && !is_page()) return;
        wp_register_script('pgw-runtime', false, [], self::VERSION, true);
        wp_enqueue_script('pgw-runtime');
        wp_localize_script('pgw-runtime', 'PGW_DATA', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'loadNonce' => wp_create_nonce('pgw_load_groups'),
            'reportNonce' => wp_create_nonce('pgw_report'),
            'otpNonce' => wp_create_nonce('pgw_resend_otp'),
            'i18n' => ['loading' => 'Carregando…', 'empty' => 'Não há mais grupos.'],
        ]);
        wp_add_inline_script('pgw-runtime', '(function(){"use strict";document.addEventListener("click",function(e){var b=e.target.closest(".pgw-load-more");if(!b)return;e.preventDefault();if(b.dataset.loading)return;b.dataset.loading="1";var data=new URLSearchParams({action:"pgw_load_groups",nonce:PGW_DATA.loadNonce,page:b.dataset.page||"2",amount:b.dataset.amount||"15",search:new URLSearchParams(location.search).get("pgw_q")||""});b.setAttribute("aria-busy","true");fetch(PGW_DATA.ajaxUrl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:data}).then(function(r){return r.json()}).then(function(r){if(r.success&&r.data.html){var grid=document.querySelector("[data-pgw-grid]");if(grid)grid.insertAdjacentHTML("beforeend",r.data.html);b.dataset.page=String(Number(b.dataset.page||2)+1);if(!r.data.has_more)b.remove();}else if(!r.data.has_more)b.remove();}).catch(function(){b.removeAttribute("data-loading");}).finally(function(){b.removeAttribute("aria-busy");delete b.dataset.loading;});});})();');
    }

    public static function sync_business_status(int $post_id, \WP_Post $post, bool $update): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== self::CPT || wp_is_post_revision($post_id)) return;
        $native = $post->post_status;
        $business = get_post_meta($post_id, 'pgw_status', true);
        if ($native === 'publish' && $business !== 'approved') update_post_meta($post_id, 'pgw_status', 'approved');
        if ($native === 'pending' && !$business) update_post_meta($post_id, 'pgw_status', 'pending');
    }

    public static function audit_status_transition(string $new_status, string $old_status, \WP_Post $post): void {
        if ($post->post_type !== self::CPT || $new_status === $old_status) return;
        global $wpdb;
        $table = $wpdb->prefix . 'pgw_events';
        $wpdb->insert($table, ['event_type'=>'status_change','group_id'=>$post->ID,'user_id'=>get_current_user_id() ?: null,'source'=>$old_status.'>'.$new_status,'created_at'=>current_time('mysql', true)], ['%s','%d','%d','%s','%s']);
    }

    public static function register_shortcodes(): void {
        $map=['pgw_mostrar_grupos'=>'catalog','pgw_busca'=>'search','pgw_enviar_grupo'=>'submit_form','pgw_header'=>'header','pgw_footer'=>'footer','pgw_categorias'=>'categories','pgw_entrar'=>'login_form','pgw_criar_conta'=>'register_form','pgw_minha_conta'=>'account','pgw_meus_grupos'=>'my_groups','pgw_recuperar_senha'=>'password_reset','pgw_confirmar_codigo'=>'verify_code','pgw_perfil_resumido'=>'profile_summary']; foreach($map as $tag=>$method) add_shortcode($tag,[self::class,$method]);
    }

    public static function header(): string { $logo=esc_url((string)get_option('pgw_logo_url')); $user=is_user_logged_in()?wp_get_current_user():null; $label=$user?esc_html($user->display_name):'Entrar'; return '<header class="pgw-header"><a href="'.esc_url(home_url('/')).'"><img src="'.$logo.'" alt="Portal Grupos WhatsApp" height="48"></a><nav><a href="'.esc_url(home_url('/grupos/')).'">Grupos</a><a href="'.esc_url(home_url('/categorias/')).'">Categorias</a><a href="'.esc_url($user?home_url('/minha-conta/'):home_url('/entrar/')).'">'.$label.'</a></nav></header>'; }
    public static function footer(): string { return '<footer class="pgw-footer"><p>Plataforma independente. Não possui vínculo, patrocínio ou associação oficial com WhatsApp LLC ou Meta Platforms, Inc.</p></footer>'; }
    public static function search(): string { return '<form class="pgw-search" method="get" action="'.esc_url(home_url('/grupos/')).'"><label class="screen-reader-text" for="pgw-search">Buscar um grupo</label><input id="pgw-search" name="pgw_q" type="search" placeholder="Buscar um grupo..." value="'.esc_attr(sanitize_text_field((string)($_GET['pgw_q']??''))).'"><button class="pgw-button" type="submit">Buscar</button></form>'; }

    public static function catalog(array $atts=[]): string {
        $atts=shortcode_atts(['limit'=>get_option('pgw_initial_limit',30),'columns'=>3,'featured_first'=>1,'showcase'=>1,'load_more'=>1,'load_amount'=>15],$atts,'pgw_mostrar_grupos'); $q=sanitize_text_field((string)($_GET['pgw_q']??''));
        $query=new \WP_Query(self::public_query_args(['posts_per_page'=>max(1,min(100,(int)$atts['limit'])),'s'=>$q,'meta_key'=>(!empty($atts['featured_first'])?'pgw_featured_priority':''),'orderby'=>(!empty($atts['featured_first'])?'meta_value_num date':'date'),'order'=>'ASC']));
        if(!$query->have_posts()) return '<div class="pgw-wrap"><p class="pgw-empty">Nenhum grupo encontrado.</p></div>';
        $featured=[]; $normal=[]; while($query->have_posts()){ $query->the_post(); $id=get_the_ID(); ((bool)get_post_meta($id,'pgw_featured',true)?$featured:$normal)[]=$id; } wp_reset_postdata();
        $html='<section class="pgw-wrap">'; $show=array_slice($featured,0,3); $used=[];
        if(!empty($atts['showcase'])&&count($show)>0){ $html.='<div class="pgw-showcase-shell"><div class="pgw-grid">'; foreach($show as $id){$used[]=$id;$html.=self::card($id,true);} $html.='</div></div>'; }
        $ids=array_values(array_unique(array_merge(array_diff($featured,$used),$normal))); $html.='<div class="pgw-grid" data-pgw-grid>'; foreach($ids as $id)$html.=self::card($id,false); $html.='</div>';
        if(!empty($atts['load_more'])&&$query->found_posts>(int)$atts['limit']) $html.='<button class="pgw-button pgw-load-more" type="button" data-page="2" data-amount="'.absint((int)$atts['load_amount']).'">Carregar mais grupos</button>';
        return $html.'</section>';
    }

    private static function card(int $id,bool $showcase=false): string { $title=self::trim_text(get_the_title($id),(int)get_option('pgw_card_title_limit',52)); $desc=self::trim_text(get_post_field('post_content',$id),(int)get_option('pgw_card_description_limit',145)); $terms=get_the_terms($id,self::CAT);$cat=$terms&&!is_wp_error($terms)?$terms[0]->name:'Comunidade';$url=get_post_meta($id,'pgw_invite_url',true);$featured=(bool)get_post_meta($id,'pgw_featured',true);$image=has_post_thumbnail($id)?get_the_post_thumbnail($id,'medium',['loading'=>'lazy','alt'=>esc_attr($title)]):'<div class="pgw-placeholder" aria-hidden="true">'.esc_html(mb_strtoupper(mb_substr($title,0,1))).'</div>'; return '<article class="pgw-card '.($featured?'pgw-card--featured ':'').'pgw-group-card--'.($showcase?'showcase':'catalog').'" data-group-id="'.absint($id).'"> <span class="pgw-badge">'.($featured?'Destaque · ':'').esc_html($cat).'</span>'.$image.'<h3>'.esc_html($title).'</h3><p>'.esc_html($desc).'</p><small>'.esc_html(get_the_date('d/m/Y',$id)).'</small><a class="pgw-button" href="'.esc_url(home_url('/ir/'.get_post_field('post_name',$id).'/')).'" rel="nofollow noopener noreferrer">Entrar no Grupo</a></article>'; }

    public static function categories(array $atts=[]): string { $atts=shortcode_atts(['limit'=>10,'count'=>0],$atts,'pgw_categorias');$terms=get_terms(['taxonomy'=>self::CAT,'hide_empty'=>true,'number'=>max(1,min(100,(int)$atts['limit'])),'orderby'=>'name']);if(is_wp_error($terms)||!$terms)return '<div class="pgw-empty">Nenhuma categoria disponível.</div>';$html='<nav class="pgw-categories" aria-label="Categorias"><ul>';foreach($terms as $term){$url=get_term_link($term);if(!is_wp_error($url))$html.='<li><a href="'.esc_url($url).'">'.esc_html($term->name).(!empty($atts['count'])?' <span>('.absint($term->count).')</span>':'').'</a></li>';}return $html.'</ul></nav>'; }

    public static function submit_form(): string { if(!is_user_logged_in())return '<div class="pgw-empty">Para enviar um grupo, <a href="'.esc_url(home_url('/entrar/')).'">entre</a> ou <a href="'.esc_url(home_url('/criar-conta/')).'">crie uma conta</a>.</div>'; $message=''; $categories=get_terms(['taxonomy'=>self::CAT,'hide_empty'=>false]); $types=get_terms(['taxonomy'=>self::TYPE,'hide_empty'=>false]); if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['pgw_submit_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pgw_submit_nonce'])),'pgw_submit')){ if(!self::rate_limit('group_submit','user_'.get_current_user_id(),8,DAY_IN_SECONDS))$message='<p role="alert">Limite de envios atingido. Tente novamente mais tarde.</p>';else{$title=self::trim_text(sanitize_text_field(wp_unslash($_POST['pgw_title']??'')),100);$url=self::sanitize_invite(wp_unslash($_POST['pgw_url']??''));$desc=self::trim_text(sanitize_textarea_field(wp_unslash($_POST['pgw_description']??'')),1000);$cat=absint($_POST['pgw_category']??0);$type=absint($_POST['pgw_type']??0);$rules=self::trim_text(sanitize_textarea_field(wp_unslash($_POST['pgw_rules']??'')),1000);if(!$title||!$url||!$desc||!$cat||!$type||empty($_POST['pgw_terms']))$message='<p role="alert">Preencha os campos obrigatórios e confirme que possui autorização para divulgar o link.</p>';else{$dup=new \WP_Query(['post_type'=>self::CPT,'post_status'=>'any','posts_per_page'=>1,'meta_key'=>'pgw_invite_url','meta_value'=>$url,'fields'=>'ids']);if($dup->have_posts())$message='<p role="alert">Este link já foi enviado.</p>';else{$id=wp_insert_post(['post_type'=>self::CPT,'post_status'=>'pending','post_title'=>$title,'post_content'=>$desc,'post_author'=>get_current_user_id()],true);if(!is_wp_error($id)){update_post_meta($id,'pgw_invite_url',$url);update_post_meta($id,'pgw_invite_hash',hash_hmac('sha256',$url,wp_salt('auth')));update_post_meta($id,'pgw_owner_id',get_current_user_id());update_post_meta($id,'pgw_status','pending');update_post_meta($id,'pgw_rules',$rules);wp_set_object_terms($id,[$cat],self::CAT);wp_set_object_terms($id,[$type],self::TYPE);self::attach_group_image('pgw_group_image',$id);self::audit('group_submitted','group',$id,[],['status'=>'pending']);$message='<p role="status">Seu grupo foi enviado para moderação.</p>';}}}}} $cat_options='<option value="">Selecione</option>';foreach($categories as $term)$cat_options.='<option value="'.absint($term->term_id).'">'.esc_html($term->name).'</option>'; $type_options='<option value="">Selecione</option>';foreach($types as $term)$type_options.='<option value="'.absint($term->term_id).'">'.esc_html($term->name).'</option>'; return '<div class="pgw-wrap"><form method="post" enctype="multipart/form-data" class="pgw-submit-form"><h2>Enviar grupo</h2><p>Todo envio passa por moderação antes de ser publicado.</p>'.$message.wp_nonce_field('pgw_submit','pgw_submit_nonce',true,false).'<p><label>Nome<input name="pgw_title" maxlength="100" required></label></p><p><label>Link HTTPS do WhatsApp<input type="url" name="pgw_url" required></label></p><p><label>Categoria<select name="pgw_category" required>'.$cat_options.'</select></label></p><p><label>Tipo<select name="pgw_type" required>'.$type_options.'</select></label></p><p><label>Imagem quadrada (JPEG, PNG ou WebP; até 5 MB)<input type="file" name="pgw_group_image" accept="image/jpeg,image/png,image/webp"></label></p><p><label>Descrição<textarea name="pgw_description" maxlength="1000" required></textarea></label></p><p><label>Regras do grupo <textarea name="pgw_rules" maxlength="1000"></textarea></label></p><p><label><input type="checkbox" name="pgw_terms" value="1" required> Confirmo que sou proprietário ou possuo autorização para divulgar este grupo.</label></p><button class="pgw-button" type="submit">Enviar para análise</button></form></div>'; }

    public static function login_form(array $atts=[]): string { if(is_user_logged_in())return '<p>Você já está conectado. <a href="'.esc_url(home_url('/minha-conta/')).'">Acessar minha conta</a></p>'; $message='';if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['pgw_login_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pgw_login_nonce'])),'pgw_login')){$email=sanitize_email(wp_unslash($_POST['pgw_email']??''));$pass=(string)wp_unslash($_POST['pgw_password']??'');$user=$email?get_user_by('email',$email):false;if($user&&wp_check_password($pass,$user->user_pass,$user->ID)&& (string) get_user_meta($user->ID,'pgw_account_status',true) !== 'suspended'){update_user_meta($user->ID,'pgw_pending_login',1);self::send_otp((int)$user->ID,'login');wp_safe_redirect(add_query_arg(['pgw_verify_user'=>(int)$user->ID,'pgw_verify_purpose'=>'login','pgw_remember'=>!empty($_POST['pgw_remember'])?1:0],home_url('/confirmar-codigo/')));exit;}$message='<p role="alert">E-mail ou senha inválidos.</p>';}return '<div class="pgw-wrap"><form method="post" class="pgw-auth"><h2>Entrar</h2>'.$message.wp_nonce_field('pgw_login','pgw_login_nonce',true,false).'<p><label>E-mail<input type="email" name="pgw_email" autocomplete="email" required></label></p><p><label>Senha<input type="password" name="pgw_password" autocomplete="current-password" required></label></p><p><label><input type="checkbox" name="pgw_remember" value="1"> Lembrar de mim</label></p><button class="pgw-button" type="submit">Entrar</button></form></div>'; }

    public static function register_form(): string { if(is_user_logged_in())return '<p>Você já possui uma conta.</p>'; $message='';if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['pgw_register_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pgw_register_nonce'])),'pgw_register')){$email=sanitize_email(wp_unslash($_POST['pgw_email']??''));$pass=(string)wp_unslash($_POST['pgw_password']??'');$name=sanitize_text_field(wp_unslash($_POST['pgw_name']??''));if(!is_email($email)||email_exists($email)||strlen($pass)<10||!$name)$message='<p role="alert">Confira os dados. A senha deve possuir pelo menos 10 caracteres.</p>';else{$base=sanitize_user(strstr($email,'@',true),true)?:'pgw_user';$username=$base;$suffix=1;while(username_exists($username))$username=$base.$suffix++;$id=wp_insert_user(['user_login'=>$username,'user_pass'=>$pass,'user_email'=>$email,'display_name'=>$name,'first_name'=>$name,'role'=>'pgw_pending_member']);if(!is_wp_error($id)){update_user_meta($id,'pgw_account_status','pending_email');update_user_meta($id,'pgw_registered_at',current_time('mysql',true));update_user_meta($id,'pgw_pending_login',1);$verification_token=bin2hex(random_bytes(32));update_user_meta($id,'pgw_verification_token',hash('sha256',$verification_token));if(!self::send_otp((int)$id,'registration')){wp_delete_user((int)$id);$message='<p role="alert">Não foi possível enviar o código de confirmação. Tente novamente.</p>';}else{wp_safe_redirect(add_query_arg(['pgw_verify_user'=>(int)$id,'pgw_verify_purpose'=>'registration','pgw_verification_token'=>$verification_token],home_url('/confirmar-codigo/')));exit;}}$message='<p role="alert">Não foi possível criar a conta.</p>';}}return '<div class="pgw-wrap"><form method="post" class="pgw-auth"><h2>Criar conta</h2>'.$message.wp_nonce_field('pgw_register','pgw_register_nonce',true,false).'<p><label>Nome<input name="pgw_name" maxlength="100" autocomplete="name" required></label></p><p><label>E-mail<input type="email" name="pgw_email" autocomplete="email" required></label></p><p><label>Senha<input type="password" name="pgw_password" minlength="10" autocomplete="new-password" required></label></p><button class="pgw-button" type="submit">Criar conta</button></form></div>'; }

    public static function account(): string { if(!is_user_logged_in())return '<div class="pgw-empty">Entre para acessar sua conta.</div>'; $u=wp_get_current_user();$message='';if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['pgw_profile_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pgw_profile_nonce'])),'pgw_profile')){$name=sanitize_text_field(wp_unslash($_POST['pgw_name']??''));$last=sanitize_text_field(wp_unslash($_POST['pgw_last_name']??''));$phone=preg_replace('/[^+0-9]/','',(string)wp_unslash($_POST['pgw_phone']??''));if(!$name)$message='<p role="alert">Informe seu nome.</p>';else{wp_update_user(['ID'=>$u->ID,'display_name'=>$name,'first_name'=>$name,'last_name'=>$last]);update_user_meta($u->ID,'pgw_phone',$phone);$message='<p role="status">Perfil atualizado.</p>';$u=wp_get_current_user();}}$providers=(array)get_user_meta($u->ID,'pgw_auth_providers',true);$verified=get_user_meta($u->ID,'pgw_email_verified',true);$avatar=self::avatar_html($u->ID,96);$notice=sanitize_key((string)($_GET['pgw_notice']??''));if($notice==='email_changed')$message.='<p role="status">Seu e-mail foi alterado com sucesso.</p>';if($notice==='avatar')$message.='<p role="status">Sua foto foi atualizada.</p>';return '<section class="pgw-wrap"><div class="pgw-auth"><div class="pgw-account-head">'.$avatar.'<p><strong>'.esc_html($u->display_name).'</strong><br>'.esc_html($u->user_email).'<br><span class="pgw-status">'.($verified?'E-mail verificado':'E-mail pendente').'</span><br><small>Provedores: '.esc_html(implode(', ', $providers)).'<br>Último acesso: '.esc_html((string)get_user_meta($u->ID,'pgw_last_login_at',true)).'</small></p></div><h2>Perfil</h2>'.$message.'<form method="post">'.wp_nonce_field('pgw_profile','pgw_profile_nonce',true,false).'<p><label>Nome<input name="pgw_name" value="'.esc_attr($u->first_name ?: $u->display_name).'" required></label></p><p><label>Sobrenome<input name="pgw_last_name" value="'.esc_attr($u->last_name).'"></label></p><p><label>Telefone<input name="pgw_phone" inputmode="tel" autocomplete="tel" value="'.esc_attr((string)get_user_meta($u->ID,'pgw_phone',true)).'"></label></p><button class="pgw-button" type="submit">Salvar perfil</button></form><hr><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><h3>Foto do perfil</h3><input type="hidden" name="action" value="pgw_upload_avatar">'.wp_nonce_field('pgw_avatar_upload','pgw_avatar_nonce',true,false).'<p><label>Nova foto (JPEG, PNG ou WebP; até 5 MB)<input type="file" name="pgw_avatar" accept="image/jpeg,image/png,image/webp" required></label></p><button class="pgw-button">Alterar foto</button></form><hr><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><h3>Segurança</h3><input type="hidden" name="action" value="pgw_update_password">'.wp_nonce_field('pgw_update_password','pgw_security_nonce',true,false).'<p><label>Senha atual<input type="password" name="current_password" autocomplete="current-password"></label></p><p><label>Nova senha<input type="password" name="new_password" minlength="10" autocomplete="new-password" required></label></p><button class="pgw-button">Alterar senha</button></form><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><h3>Alterar e-mail</h3><input type="hidden" name="action" value="pgw_update_email">'.wp_nonce_field('pgw_update_email','pgw_email_nonce',true,false).'<p><label>Novo e-mail<input type="email" name="new_email" autocomplete="email" required></label></p><button class="pgw-button">Enviar confirmação</button></form><hr><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><h3>Excluir conta</h3><input type="hidden" name="action" value="pgw_delete_account_request">'.wp_nonce_field('pgw_delete_account','pgw_delete_account_nonce',true,false).'<p><label>Digite EXCLUIR<input name="confirmation" required></label></p><p><label>Senha atual<input type="password" name="current_password" autocomplete="current-password" required></label></p><button class="pgw-button pgw-danger">Solicitar exclusão</button></form><p class="pgw-account-actions"><a class="pgw-button pgw-button--secondary" href="'.esc_url(home_url('/meus-grupos/')).'">Meus grupos</a><a class="pgw-button" href="'.esc_url(wp_logout_url(home_url('/'))).'">Sair</a></p></div></section>'; }

    public static function my_groups(): string { if(!is_user_logged_in())return '<div class="pgw-empty">Entre para visualizar seus grupos.</div>'; $statuses=['approved'=>'Aprovados','pending'=>'Pendentes','correction_requested'=>'Correções','rejected'=>'Rejeitados','inactive'=>'Inativos'];$counts=array_fill_keys(array_keys($statuses),0);$all=get_posts(['post_type'=>self::CPT,'post_status'=>'any','author'=>get_current_user_id(),'numberposts'=>-1,'fields'=>'ids']);foreach($all as $id){$status=self::status($id);if(isset($counts[$status]))$counts[$status]++;}$q=new \WP_Query(['post_type'=>self::CPT,'post_status'=>['publish','pending','draft','private'],'author'=>get_current_user_id(),'posts_per_page'=>20]);$summary='<div class="pgw-account-actions">';foreach($statuses as $key=>$label)$summary.='<span class="pgw-status">'.esc_html($label).': '.absint($counts[$key]).'</span>';$summary.='</div>';if(!$q->have_posts())return '<div class="pgw-wrap"><h2>Meus grupos</h2>'.$summary.'<p class="pgw-empty">Você ainda não enviou grupos.</p><p><a class="pgw-button" href="'.esc_url(home_url('/enviar-grupo/')).'">Enviar grupo</a></p></div>';$html='<section class="pgw-wrap"><h2>Meus grupos</h2>'.$summary.'<p><a class="pgw-button" href="'.esc_url(home_url('/enviar-grupo/')).'">Enviar grupo</a></p><div class="pgw-grid">';while($q->have_posts()){$q->the_post();$id=get_the_ID();$status=self::status($id);$clicks=absint(get_post_meta($id,'pgw_click_count',true));$reason=(string)get_post_meta($id,'pgw_rejection_reason',true);$html.='<article class="pgw-card"><h3>'.esc_html(get_the_title()).'</h3><p><span class="pgw-status">'.esc_html($status).'</span><br>Cliques: '.absint($clicks).($reason?'<br><strong>Motivo:</strong> '.esc_html($reason):'').'</p><div class="pgw-group-actions"><a class="pgw-button pgw-button--secondary" href="'.esc_url(get_permalink()).'">Visualizar</a><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pgw_delete_group"><input type="hidden" name="group_id" value="'.absint($id).'">'.wp_nonce_field('pgw_delete_group','_wpnonce',true,false).'<button class="pgw-button pgw-danger" type="submit">Excluir</button></form></div></article>';}wp_reset_postdata();return $html.'</div></section>'; }

    public static function password_reset(): string { return '<div class="pgw-wrap"><form class="pgw-auth" method="post" action="'.esc_url(wp_lostpassword_url()).'"><h2>Recuperar senha</h2><p>Informe seu e-mail para receber instruções.</p><p><label>E-mail<input type="email" name="user_login" required></label></p><button class="pgw-button">Enviar instruções</button></form></div>'; }
    public static function verify_code(): string {
        $user_id = absint($_REQUEST['pgw_verify_user'] ?? $_POST['pgw_user_id'] ?? 0);
        $purpose = sanitize_key((string) ($_REQUEST['pgw_verify_purpose'] ?? $_POST['pgw_verify_purpose'] ?? ''));
        $allowed = ['login', 'registration', 'email_change', 'password_change', 'account_deletion'];
        $message = '';
        if (!$user_id || !in_array($purpose, $allowed, true) || !get_userdata($user_id)) return '<div class="pgw-empty">Solicitação de confirmação inválida.</div>';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nonce = sanitize_text_field(wp_unslash($_POST['pgw_verify_nonce'] ?? ''));
            $posted_purpose = sanitize_key((string) wp_unslash($_POST['pgw_verify_purpose'] ?? ''));
            $code = preg_replace('/\\D+/', '', (string) wp_unslash($_POST['pgw_code'] ?? ''));
            if (!wp_verify_nonce($nonce, 'pgw_verify') || $posted_purpose !== $purpose || strlen($code) !== 6) {
                $message = '<p role="alert">Não foi possível confirmar esta solicitação.</p>';
            } elseif (!self::verify_otp($user_id, $code, $purpose)) {
                $message = '<p role="alert">Código inválido, expirado ou já utilizado.</p>';
            } elseif ($purpose === 'registration') {
                $user = new \WP_User($user_id); $user->set_role('pgw_member');
                update_user_meta($user_id, 'pgw_account_status', 'active'); update_user_meta($user_id, 'pgw_email_verified', 1);
                delete_user_meta($user_id, 'pgw_pending_login'); delete_user_meta($user_id, 'pgw_verification_token');
                self::audit('registration_verified', 'user', $user_id);
                wp_set_auth_cookie($user_id, true, is_ssl()); wp_safe_redirect(home_url('/minha-conta/')); exit;
            } elseif ($purpose === 'login') {
                if ((string) get_user_meta($user_id, 'pgw_account_status', true) !== 'active') $message = '<p role="alert">Esta conta não está disponível para acesso.</p>';
                else { wp_set_auth_cookie($user_id, !empty($_POST['pgw_remember']), is_ssl()); delete_user_meta($user_id, 'pgw_pending_login'); update_user_meta($user_id, 'pgw_last_login_at', current_time('mysql', true)); self::audit('login_verified', 'user', $user_id); wp_safe_redirect(home_url('/minha-conta/')); exit; }
            } elseif ($purpose === 'email_change') {
                $email = sanitize_email((string) get_user_meta($user_id, 'pgw_pending_email', true));
                if (!$email || email_exists($email)) $message = '<p role="alert">O novo e-mail não está mais disponível.</p>';
                else { $old_email = (string) get_userdata($user_id)->user_email; wp_update_user(['ID' => $user_id, 'user_email' => $email]); delete_user_meta($user_id, 'pgw_pending_email'); delete_user_meta($user_id, 'pgw_pending_email_requested_at'); update_user_meta($user_id, 'pgw_email_verified', 1); self::audit('email_changed', 'user', $user_id, ['email_hash' => hash_hmac('sha256', $old_email, wp_salt('auth'))], ['email_hash' => hash_hmac('sha256', $email, wp_salt('auth'))]); wp_mail($old_email, 'E-mail da conta alterado', 'O e-mail de acesso da sua conta foi alterado. Se não foi você, contate o suporte imediatamente.'); wp_safe_redirect(add_query_arg('pgw_notice', 'email_changed', home_url('/minha-conta/'))); exit; }
            elseif ($purpose === 'account_deletion') { update_user_meta($user_id, 'pgw_deletion_confirmed_at', current_time('mysql', true)); self::audit('account_deletion_confirmed', 'user', $user_id); wp_safe_redirect(add_query_arg('pgw_notice', 'deletion_requested', home_url('/minha-conta/'))); exit; }
        }
        $resend = '<p><button class="pgw-button pgw-resend-otp" type="button" data-user="'.absint($user_id).'" data-purpose="'.esc_attr($purpose).'">Reenviar código</button><span class="pgw-otp-status" aria-live="polite"></span></p>';
        return '<div class="pgw-wrap"><form method="post" class="pgw-auth"><h2>Confirmar código</h2>'.$message.wp_nonce_field('pgw_verify','pgw_verify_nonce',true,false).'<input type="hidden" name="pgw_user_id" value="'.absint($user_id).'"><input type="hidden" name="pgw_verify_purpose" value="'.esc_attr($purpose).'"><p><label>Código de seis dígitos<input inputmode="numeric" autocomplete="one-time-code" name="pgw_code" maxlength="6" pattern="[0-9]{6}" required></label></p><button class="pgw-button" type="submit">Confirmar</button>'.$resend.'</form></div>';
    }

    public static function profile_summary(): string {
        if (!is_user_logged_in()) return '<a class="pgw-button" href="'.esc_url(home_url('/entrar/')).'">Entrar</a>';
        $user = wp_get_current_user();
        return '<div class="pgw-profile-summary">'.self::avatar_html($user->ID, 48, 'pgw-avatar').' <span>'.esc_html($user->display_name).'</span><a href="'.esc_url(home_url('/minha-conta/')).'">Minha Conta</a></div>';
    }

    public static function admin_menu(): void {
        add_menu_page('Portal Grupos','Portal Grupos','pgw_manage_groups','pgw-dashboard',[self::class,'dashboard'],'dashicons-groups',26);
        add_submenu_page('pgw-dashboard','Todos os Grupos','Todos os Grupos','pgw_manage_groups','edit.php?post_type='.self::CPT);
        add_submenu_page('pgw-dashboard','Destaques','Destaques','pgw_manage_featured','pgw-featured',[self::class,'featured_page']);
        add_submenu_page('pgw-dashboard','Categorias','Categorias','pgw_manage_categories','edit-tags.php?taxonomy='.self::CAT.'&post_type='.self::CPT);
        add_submenu_page('pgw-dashboard','Denúncias','Denúncias','pgw_manage_reports','pgw-reports',[self::class,'reports_page']);
        add_submenu_page('pgw-dashboard','Métricas','Métricas','pgw_view_metrics','pgw-metrics',[self::class,'metrics_page']);
        add_submenu_page('pgw-dashboard','Conteúdo Demo','Conteúdo Demo','pgw_manage_demo','pgw-demo',[self::class,'demo_page']);
        add_submenu_page('pgw-dashboard','Configurações','Configurações','pgw_manage_settings','pgw-settings',[self::class,'settings_page']);
    }

    public static function featured_page(): void {
        if (!current_user_can('pgw_manage_featured')) return;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('pgw_featured_save');
            $ids = array_map('absint', (array) ($_POST['pgw_featured_ids'] ?? []));
            $priority = 1;
            foreach ($ids as $id) {
                if (get_post_type($id) !== self::CPT) continue;
                update_post_meta($id, 'pgw_featured', !empty($_POST['pgw_featured'][$id]) ? 1 : 0);
                if (!empty($_POST['pgw_featured'][$id])) update_post_meta($id, 'pgw_featured_priority', $priority++);
            }
            echo '<div class="notice notice-success"><p>Destaques atualizados.</p></div>';
        }
        $groups = get_posts(['post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>100,'orderby'=>'meta_value_num date','meta_key'=>'pgw_featured_priority','order'=>'ASC']);
        echo '<div class="wrap pgw-admin"><h1>Destaques</h1><p>Prioridade 1 ocupa o centro do showcase; 2 fica à esquerda e 3 à direita.</p><form method="post">'; wp_nonce_field('pgw_featured_save'); echo '<table class="widefat striped"><thead><tr><th>Destaque</th><th>Grupo</th><th>Prioridade</th></tr></thead><tbody>';
        foreach ($groups as $index=>$group) { $checked=(int)get_post_meta($group->ID,'pgw_featured',true)===1?' checked':''; $value=absint(get_post_meta($group->ID,'pgw_featured_priority',true)); echo '<tr><td><input type="hidden" name="pgw_featured_ids[]" value="'.absint($group->ID).'"><input type="checkbox" name="pgw_featured['.absint($group->ID).']" value="1"'.$checked.'></td><td><a href="'.esc_url(get_edit_post_link($group->ID)).'">'.esc_html($group->post_title).'</a></td><td>'.($value ?: '—').'</td></tr>'; }
        echo '</tbody></table>'; submit_button('Salvar destaques'); echo '</form></div>';
    }

    public static function metrics_page(): void {
        if (!current_user_can('pgw_view_metrics')) return;
        global $wpdb; $events=$wpdb->prefix.'pgw_events';
        $clicks=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$events} WHERE event_type='click'");
        $recent=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$events} WHERE event_type='click' AND created_at >= %s", gmdate('Y-m-d H:i:s', time()-30*DAY_IN_SECONDS)));
        $top=$wpdb->get_results("SELECT group_id, COUNT(*) total FROM {$events} WHERE event_type='click' AND group_id IS NOT NULL GROUP BY group_id ORDER BY total DESC LIMIT 10");
        echo '<div class="wrap pgw-admin"><h1>Métricas</h1><div class="pgw-admin-card"><strong>Cliques totais</strong><br>'.absint($clicks).'</div><div class="pgw-admin-card"><strong>Cliques em 30 dias</strong><br>'.absint($recent).'</div><h2>Grupos mais clicados</h2><table class="widefat"><thead><tr><th>Grupo</th><th>Cliques</th></tr></thead><tbody>';
        foreach($top as $row) echo '<tr><td>'.esc_html(get_the_title((int)$row->group_id) ?: 'Grupo removido').'</td><td>'.absint($row->total).'</td></tr>';
        echo '</tbody></table></div>';
    }

    public static function demo_page(): void {
        if (!current_user_can('pgw_manage_demo')) return;
        if ($_SERVER['REQUEST_METHOD']==='POST') { check_admin_referer('pgw_demo_content'); $mode=sanitize_key((string)($_POST['pgw_demo_mode']??''));
            if ($mode==='remove') { $ids=get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids','meta_key'=>'pgw_demo','meta_value'=>1]); foreach($ids as $id) wp_delete_post($id,true); echo '<div class="notice notice-success"><p>Conteúdo demonstrativo removido.</p></div>'; }
            if ($mode==='install') { self::install_demo_content(); echo '<div class="notice notice-success"><p>Conteúdo demonstrativo instalado.</p></div>'; }
        }
        $total=count(get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids','meta_key'=>'pgw_demo','meta_value'=>1]));
        echo '<div class="wrap pgw-admin"><h1>Conteúdo Demo</h1><p>Registros demonstrativos não são indexados e não redirecionam para links externos. Atualmente: '.absint($total).'.</p><form method="post">';wp_nonce_field('pgw_demo_content');echo '<button class="button button-primary" name="pgw_demo_mode" value="install">Instalar conteúdo demo</button> <button class="button" name="pgw_demo_mode" value="remove">Remover conteúdo demo</button></form></div>';
    }

    private static function install_demo_content(): void {
        $items=[['Tecnologia e Inovação Brasil','Tecnologia'],['Vagas Remotas e Freelancers','Empregos'],['Concursos Públicos em Foco','Concursos'],['Inglês na Prática Diária','Educação'],['Programadores Front-end BR','Tecnologia'],['Empreendedores em Ação','Empreendedorismo'],['Achadinhos e Ofertas Online','Compra e Venda'],['Receitas Rápidas em Família','Receitas'],['Viagens Econômicas Brasil','Viagem e Turismo'],['Fotografia Mobile Criativa','Outros'],['Clube de Leitura Atual','Outros'],['Cinema e Séries em Debate','Outros'],['Pets, Cuidados e Adoção','Pets'],['Corrida e Vida Ativa','Esportes'],['Futebol entre Amigos','Futebol'],['Música Brasileira Independente','Música'],['Design e Criatividade Digital','Design'],['Marketing para Pequenos Negócios','Marketing'],['Networking Profissional Brasil','Outros'],['Carros, Manutenção e Dicas','Carros e Motos'],['Motos e Estradas do Brasil','Carros e Motos'],['Jardinagem Dentro de Casa','Sustentabilidade'],['Sustentabilidade no Dia a Dia','Sustentabilidade'],['Pais e Mães Conectados','Outros'],['Comunidade Local São Paulo','Cidades'],['Games Cooperativos Brasil','Games e Jogos'],['Artesanato e Produtos Autorais','Artesanato'],['Finanças Pessoais Organizadas','Educação'],['Estudos Universitários em Rede','Educação'],['Notícias de Tecnologia e Apps','Tecnologia']];
        foreach($items as $item){ if(get_page_by_title($item[0],OBJECT,self::CPT)) continue; $id=wp_insert_post(['post_type'=>self::CPT,'post_status'=>'publish','post_title'=>$item[0],'post_content'=>'Conteúdo demonstrativo criado para apresentar o catálogo, as categorias e a experiência visual do Portal Grupos WhatsApp.']); if(is_wp_error($id))continue; $term=get_term_by('name',$item[1],self::CAT);if($term)wp_set_object_terms($id,[(int)$term->term_id],self::CAT); $type=get_term_by('name','Grupo',self::TYPE);if($type)wp_set_object_terms($id,[(int)$type->term_id],self::TYPE); update_post_meta($id,'pgw_demo',1);update_post_meta($id,'pgw_status','approved');update_post_meta($id,'pgw_invite_url','https://chat.whatsapp.com/DEMONSTRACAO'); }
    }
    public static function settings(): void { register_setting('pgw_settings','pgw_logo_url',['sanitize_callback'=>'esc_url_raw']);foreach(['pgw_initial_limit','pgw_load_amount','pgw_card_title_limit','pgw_card_description_limit'] as $key)register_setting('pgw_settings',$key,['sanitize_callback'=>'absint']);add_settings_section('pgw_main','Catálogo e aparência','__return_false','pgw-settings');foreach(['pgw_logo_url'=>'URL do logo','pgw_initial_limit'=>'Limite inicial','pgw_load_amount'=>'Quantidade ao carregar','pgw_card_title_limit'=>'Limite do título','pgw_card_description_limit'=>'Limite da descrição'] as $key=>$label)add_settings_field($key,$label,[self::class,'field'],'pgw-settings','pgw_main',['key'=>$key]); }
    public static function field(array $args): void { $key=$args['key'];echo '<input class="regular-text" name="'.esc_attr($key).'" value="'.esc_attr((string)get_option($key)).'">'; }
    public static function dashboard(): void { if(!current_user_can('pgw_manage_groups'))return;$counts=[];foreach(['publish'=>'Aprovados','pending'=>'Pendentes','draft'=>'Rascunhos'] as $status=>$label)$counts[]='<div class="pgw-admin-card"><strong>'.esc_html($label).'</strong><br>'.absint(wp_count_posts(self::CPT)->$status??0).'</div>';echo '<div class="wrap pgw-admin"><h1>Portal Grupos</h1>'.implode('',$counts).'<p><a class="button button-primary" href="'.esc_url(admin_url('post-new.php?post_type='.self::CPT)).'">Adicionar grupo</a></p></div>'; }
    public static function settings_page(): void { if(!current_user_can('pgw_manage_settings'))return;echo '<div class="wrap"><h1>Configurações do Portal</h1><form method="post" action="options.php">';settings_fields('pgw_settings');do_settings_sections('pgw-settings');submit_button();echo '</form></div>'; }
    public static function reports_page(): void { if(!current_user_can('pgw_manage_reports'))return;global $wpdb;$table=$wpdb->prefix.'pgw_reports';$rows=$wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100");echo '<div class="wrap"><h1>Denúncias</h1><table class="widefat"><thead><tr><th>Grupo</th><th>Motivo</th><th>Status</th><th>Data</th></tr></thead><tbody>';foreach($rows as $row)echo '<tr><td>'.absint($row->group_id).'</td><td>'.esc_html($row->reason).'</td><td>'.esc_html($row->status).'</td><td>'.esc_html($row->created_at).'</td></tr>';echo '</tbody></table></div>'; }
    public static function handle_submit(): void { auth_redirect(); }
    public static function handle_profile(): void { auth_redirect(); }
    public static function handle_email_change(): void { auth_redirect(); check_admin_referer('pgw_update_email','pgw_email_nonce'); $email=sanitize_email(wp_unslash($_POST['new_email']??'')); $user=wp_get_current_user(); if(!is_email($email)||email_exists($email)){wp_safe_redirect(add_query_arg('pgw_error','email',home_url('/minha-conta/')));exit;} update_user_meta($user->ID,'pgw_pending_email',$email); update_user_meta($user->ID,'pgw_pending_email_requested_at',current_time('mysql',true)); self::send_otp($user->ID,'email_change'); self::audit('email_change_requested','user',$user->ID,[],['email_hash'=>hash_hmac('sha256',$email,wp_salt('auth'))]); wp_safe_redirect(add_query_arg(['pgw_verify_user'=>$user->ID,'pgw_verify_purpose'=>'email_change'],home_url('/confirmar-codigo/')));exit; }
    public static function handle_password_change(): void { auth_redirect(); check_admin_referer('pgw_update_password','pgw_security_nonce'); $user=wp_get_current_user();$current=(string)wp_unslash($_POST['current_password']??'');$new=(string)wp_unslash($_POST['new_password']??'');if(strlen($new)<10||($user->user_pass&&!wp_check_password($current,$user->user_pass,$user->ID))){wp_safe_redirect(add_query_arg('pgw_error','password',home_url('/minha-conta/')));exit;} wp_set_password($new,$user->ID); wp_destroy_all_sessions($user->ID); wp_set_auth_cookie($user->ID,true,is_ssl());self::audit('password_changed','user',$user->ID,[],[]);wp_safe_redirect(add_query_arg('pgw_notice','password',home_url('/minha-conta/')));exit; }
    private static function audit(string $action,string $object_type,int $object_id,array $before=[],array $after=[]): void { global $wpdb;$wpdb->insert($wpdb->prefix.'pgw_audit_log',['actor_user_id'=>get_current_user_id()?:null,'action'=>sanitize_key($action),'object_type'=>sanitize_key($object_type),'object_id'=>$object_id,'before_data'=>wp_json_encode($before),'after_data'=>wp_json_encode($after),'created_at'=>current_time('mysql',true),'ip_hash'=>self::request_ip_hash()]); }
    public static function handle_delete_group(): void { auth_redirect();check_admin_referer('pgw_delete_group');$id=absint($_POST['group_id']??0);$post=get_post($id);if(!$post||$post->post_type!==self::CPT||((int)$post->post_author!==get_current_user_id()&&!current_user_can('pgw_manage_groups')))wp_die('Sem permissão.');self::audit('group_deleted','group',$id,['status'=>self::status($id)],[]);wp_trash_post($id);wp_safe_redirect(home_url('/meus-grupos/'));exit; }
    public static function handle_update_group(): void { auth_redirect();check_admin_referer('pgw_update_group');$id=absint($_POST['group_id']??0);$post=get_post($id);if(!$post||$post->post_type!==self::CPT||(int)$post->post_author!==get_current_user_id())wp_die('Sem permissão.');$title=self::trim_text(sanitize_text_field(wp_unslash($_POST['pgw_title']??'')),100);$description=self::trim_text(sanitize_textarea_field(wp_unslash($_POST['pgw_description']??'')),1000);$url=self::sanitize_invite(wp_unslash($_POST['pgw_url']??''));if(!$title||!$description||!$url)wp_die('Dados inválidos.');wp_update_post(['ID'=>$id,'post_title'=>$title,'post_content'=>$description,'post_status'=>'pending']);update_post_meta($id,'pgw_invite_url',$url);update_post_meta($id,'pgw_status','pending');self::audit('group_resubmitted','group',$id,[],['status'=>'pending']);wp_safe_redirect(home_url('/meus-grupos/'));exit; }
    public static function handle_moderation(): void { if(!current_user_can('pgw_moderate_groups'))wp_die('Sem permissão.');check_admin_referer('pgw_moderate_group');$id=absint($_POST['group_id']??0);$status=sanitize_key($_POST['status']??'');if($id&&in_array($status,['approved','rejected','correction_requested','inactive'],true)){update_post_meta($id,'pgw_status',$status);wp_update_post(['ID'=>$id,'post_status'=>$status==='approved'?'publish':'pending']);}wp_safe_redirect(wp_get_referer()?:admin_url());exit; }
    public static function handle_logout(): void { check_admin_referer('pgw_logout');wp_logout();wp_safe_redirect(home_url('/'));exit; }
    public static function require_login(): void { auth_redirect(); }
    public static function ajax_resend_otp(): void {
        check_ajax_referer('pgw_resend_otp', 'nonce');
        $user_id = absint($_POST['user_id'] ?? 0); $purpose = sanitize_key((string) ($_POST['purpose'] ?? ''));
        if (!$user_id || !in_array($purpose, ['login', 'registration', 'email_change', 'password_change', 'account_deletion'], true) || !get_userdata($user_id)) wp_send_json_error(['message' => 'Solicitação inválida.'], 400);
        $current = get_current_user_id();
        if (in_array($purpose, ['email_change', 'password_change', 'account_deletion'], true) && $current !== $user_id) wp_send_json_error(['message' => 'Solicitação inválida.'], 403);
        if ($purpose === 'login' && !get_user_meta($user_id, 'pgw_pending_login', true)) wp_send_json_error(['message' => 'Solicitação inválida.'], 403);
        if ($purpose === 'registration' && (string) get_user_meta($user_id, 'pgw_account_status', true) !== 'pending_email') wp_send_json_error(['message' => 'Solicitação inválida.'], 403);
        if (self::send_otp($user_id, $purpose)) wp_send_json_success(['message' => 'Um novo código foi enviado.']);
        wp_send_json_error(['message' => 'Aguarde antes de solicitar outro código.'], 429);
    }

    public static function ajax_groups(): void { check_ajax_referer('pgw_load_groups','nonce');
        $subject = is_user_logged_in() ? 'user_' . get_current_user_id() : 'ip_' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (!self::rate_limit('load_groups', $subject, 120, HOUR_IN_SECONDS)) wp_send_json_error(['message'=>'Muitas solicitações.'],429);
        $page=max(1,absint($_POST['page']??2));$amount=max(1,min(50,absint($_POST['amount']??15)));$q=new \WP_Query(self::public_query_args(['posts_per_page'=>$amount,'paged'=>$page,'s'=>sanitize_text_field((string)($_POST['search']??''))]));$html='';while($q->have_posts()){$q->the_post();$html.=self::card(get_the_ID(),false);}wp_reset_postdata();wp_send_json_success(['html'=>$html,'has_more'=>$page<$q->max_num_pages]); }
    public static function ajax_report(): void { check_ajax_referer('pgw_report','nonce');
        $subject = is_user_logged_in() ? 'user_' . get_current_user_id() : 'ip_' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (!self::rate_limit('report', $subject, 5, DAY_IN_SECONDS)) wp_send_json_error(['message'=>'Limite de denúncias atingido.'],429);
        $id=absint($_POST['group_id']??0);$reason=sanitize_text_field(wp_unslash($_POST['reason']??'other'));if(!$id||get_post_type($id)!==self::CPT)wp_send_json_error(['message'=>'Grupo inválido'],400);global $wpdb;$table=$wpdb->prefix.'pgw_reports';$wpdb->insert($table,['group_id'=>$id,'reporter_user_id'=>get_current_user_id()?:null,'reason'=>$reason,'details'=>sanitize_textarea_field(wp_unslash($_POST['details']??'')),'status'=>'open','created_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)],['%d','%d','%s','%s','%s','%s']);wp_send_json_success(['message'=>'Denúncia registrada.']); }
    public static function handle_avatar_upload(): void { auth_redirect(); check_admin_referer('pgw_avatar_upload', 'pgw_avatar_nonce'); $user_id = get_current_user_id(); if (empty($_FILES['pgw_avatar']) || !self::allowed_image_upload($_FILES['pgw_avatar'])) { wp_safe_redirect(add_query_arg('pgw_error', 'avatar', home_url('/minha-conta/'))); exit; } require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php'; $attachment_id = media_handle_upload('pgw_avatar', 0, [], ['test_form' => false, 'mimes' => ['jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp']]); if (is_wp_error($attachment_id)) { wp_safe_redirect(add_query_arg('pgw_error', 'avatar', home_url('/minha-conta/'))); exit; } $old = absint(get_user_meta($user_id, 'pgw_avatar_id', true)); update_user_meta($user_id, 'pgw_avatar_id', $attachment_id); update_user_meta($user_id, 'pgw_avatar_source', 'custom'); update_user_meta($user_id, 'pgw_avatar_updated_at', current_time('mysql', true)); if ($old && $old !== $attachment_id) wp_delete_attachment($old, true); self::audit('avatar_updated', 'user', $user_id); wp_safe_redirect(add_query_arg('pgw_notice', 'avatar', home_url('/minha-conta/'))); exit; }
    public static function handle_account_deletion_request(): void { auth_redirect(); check_admin_referer('pgw_delete_account', 'pgw_delete_account_nonce'); $user = wp_get_current_user(); $confirmation = sanitize_text_field(wp_unslash($_POST['confirmation'] ?? '')); $password = (string) wp_unslash($_POST['current_password'] ?? ''); if ($confirmation !== 'EXCLUIR' || !wp_check_password($password, $user->user_pass, $user->ID)) { wp_safe_redirect(add_query_arg('pgw_error', 'deletion', home_url('/minha-conta/'))); exit; } if (!self::send_otp($user->ID, 'account_deletion')) { wp_safe_redirect(add_query_arg('pgw_error', 'otp', home_url('/minha-conta/'))); exit; } wp_safe_redirect(add_query_arg(['pgw_verify_user'=>$user->ID,'pgw_verify_purpose'=>'account_deletion'], home_url('/confirmar-codigo/'))); exit; }
    public static function redirect_group(): void { $slug=get_query_var('pgw_redirect');if(!$slug)return;$post=get_page_by_path(sanitize_title($slug),OBJECT,self::CPT);if(!$post||get_post_status($post)!=='publish'){wp_safe_redirect(home_url('/grupos/'),302);exit;}$url=self::sanitize_invite(get_post_meta($post->ID,'pgw_invite_url',true));if(!$url){wp_safe_redirect(get_permalink($post),302);exit;}if((int)get_post_meta($post->ID,'pgw_demo',true)===1){wp_safe_redirect(add_query_arg('pgw_demo_notice',1,get_permalink($post)),302);exit;}global $wpdb;$wpdb->insert($wpdb->prefix.'pgw_events',['event_type'=>'click','group_id'=>$post->ID,'user_id'=>get_current_user_id()?:null,'session_hash'=>hash('sha256',wp_get_session_token()),'source'=>'redirect','created_at'=>current_time('mysql',true)],['%s','%d','%d','%s','%s','%s']);update_post_meta($post->ID,'pgw_click_count',absint(get_post_meta($post->ID,'pgw_click_count',true))+1);wp_safe_redirect($url,302);exit; }
    public static function single_content(string $content): string { if(!is_singular(self::CPT)||!in_the_loop()||!is_main_query())return $content;$id=get_the_ID();if(get_post_status($id)!=='publish')return is_user_logged_in()&&current_user_can('edit_post',$id)?$content:'<p>Este grupo ainda não está disponível.</p>';$terms=get_the_terms($id,self::CAT);$type=get_the_terms($id,self::TYPE);$category=$terms&&!is_wp_error($terms)?$terms[0]->name:'';$kind=$type&&!is_wp_error($type)?$type[0]->name:'Grupo';$rules=trim((string)get_post_meta($id,'pgw_rules',true));$state=(string)get_post_meta($id,'pgw_link_status',true);$image=has_post_thumbnail($id)?get_the_post_thumbnail($id,'large',['loading'=>'eager','fetchpriority'=>'high','alt'=>get_the_title($id)]):'';$demo_notice=!empty($_GET['pgw_demo_notice'])?'<p role="status">Este é um conteúdo demonstrativo; nenhum link externo foi aberto.</p>':'';$rules_html=$rules?'<section><h2>Regras</h2><p>'.nl2br(esc_html($rules)).'</p></section>':'';return '<article class="pgw-wrap"><div class="pgw-auth">'.$demo_notice.$image.'<p><span class="pgw-badge">'.esc_html($kind.($category?' · '.$category:'')).'</span></p>'.$content.$rules_html.'<p><small>Atualizado em '.esc_html(get_the_modified_date('d/m/Y',$id)).($state?' · Estado do link: '.esc_html($state):'').'</small></p><p><a class="pgw-button" href="'.esc_url(home_url('/ir/'.get_post_field('post_name',$id).'/')).'" rel="nofollow noopener noreferrer external">Entrar no Grupo</a></p><p>Plataforma independente e sem vínculo oficial com WhatsApp ou Meta. Não compartilhe dados pessoais nem envie valores a desconhecidos.</p></div></article>'; }
    public static function seo_title(string $title): string { return is_singular(self::CPT)?get_the_title().' — Portal Grupos WhatsApp':$title; }
    public static function seo_meta(): void { if(!is_singular(self::CPT)) return; $id=get_queried_object_id(); if((int)get_post_meta($id,'pgw_demo',true)===1){echo '<meta name="robots" content="noindex,nofollow">';return;} $description=self::trim_text(wp_strip_all_tags(get_post_field('post_content',$id)),155);$image=get_the_post_thumbnail_url($id,'large'); echo '<meta name="description" content="'.esc_attr($description).'">';echo '<link rel="canonical" href="'.esc_url(get_permalink($id)).'">';echo '<meta property="og:type" content="website"><meta property="og:title" content="'.esc_attr(get_the_title($id)).'"><meta property="og:description" content="'.esc_attr($description).'">';if($image)echo '<meta property="og:image" content="'.esc_url($image).'">'; }

    public static function register_privacy_exporter(array $exporters): array { $exporters['portal-grupos-whatsapp']=['exporter_friendly_name'=>'Portal Grupos WhatsApp','callback'=>[self::class,'privacy_exporter']]; return $exporters; }
    public static function privacy_exporter(string $email_address, int $page = 1): array { $user=get_user_by('email',$email_address);if(!$user)return ['data'=>[],'done'=>true];$items=[];$meta=['pgw_phone'=>'Telefone','pgw_account_status'=>'Status da conta','pgw_auth_providers'=>'Provedores de acesso','pgw_registered_at'=>'Data de cadastro','pgw_last_login_at'=>'Último acesso'];foreach($meta as $key=>$label){$value=get_user_meta($user->ID,$key,true);if($value!==''&&$value!==[])$items[]=['name'=>$label,'value'=>is_scalar($value)?(string)$value:wp_json_encode($value)];}$groups=get_posts(['post_type'=>self::CPT,'author'=>$user->ID,'post_status'=>'any','numberposts'=>-1]);foreach($groups as $group)$items[]=['name'=>'Grupo enviado','value'=>$group->post_title.' — '.self::status($group->ID)];return ['data'=>[['group_id'=>'pgw-account-'.$user->ID,'group_label'=>'Conta do Portal Grupos WhatsApp','item_id'=>'pgw-user-'.$user->ID,'data'=>$items]],'done'=>true]; }
    public static function register_privacy_eraser(array $erasers): array { $erasers['portal-grupos-whatsapp']=['eraser_friendly_name'=>'Portal Grupos WhatsApp','callback'=>[self::class,'privacy_eraser']]; return $erasers; }
    public static function privacy_eraser(string $email_address, int $page = 1): array { $user=get_user_by('email',$email_address);if(!$user)return ['items_removed'=>false,'items_retained'=>false,'messages'=>[],'done'=>true];delete_user_meta($user->ID,'pgw_phone');$avatar=absint(get_user_meta($user->ID,'pgw_avatar_id',true));delete_user_meta($user->ID,'pgw_avatar_id');delete_user_meta($user->ID,'pgw_avatar_source');if($avatar)wp_delete_attachment($avatar,true);return ['items_removed'=>true,'items_retained'=>true,'messages'=>['Os grupos enviados foram mantidos para revisão administrativa.'],'done'=>true]; }
    public static function admin_notices(): void { if(!current_user_can('pgw_manage_settings'))return;if(!defined('PGW_GOOGLE_CLIENT_ID')&&!get_option('pgw_google_client_id'))echo '<div class="notice notice-info"><p><strong>Portal Grupos WhatsApp:</strong> o login com Google está desativado até que um Client ID e Client Secret sejam configurados.</p></div>'; }
}

register_activation_hook(__FILE__, [Plugin::class,'activate']); register_deactivation_hook(__FILE__, [Plugin::class,'deactivate']); Plugin::boot();
