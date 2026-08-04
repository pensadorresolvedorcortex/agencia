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

require_once __DIR__ . '/src/Security/InviteUrlValidator.php';
require_once __DIR__ . '/src/Security/FormProof.php';
require_once __DIR__ . '/src/Featured/FeaturedOrder.php';
require_once __DIR__ . '/src/Featured/FeaturedSchedule.php';
require_once __DIR__ . '/src/Search/PaginationWindow.php';
require_once __DIR__ . '/src/Search/CatalogFilters.php';
require_once __DIR__ . '/src/Search/CatalogPagePolicy.php';
require_once __DIR__ . '/src/Search/PublicStatusPolicy.php';
require_once __DIR__ . '/src/Search/CatalogQueryContract.php';
require_once __DIR__ . '/src/Auth/FlowToken.php';
require_once __DIR__ . '/src/Auth/PasswordPolicy.php';
require_once __DIR__ . '/src/Auth/GoogleClaims.php';
require_once __DIR__ . '/src/Auth/ProviderPolicy.php';
require_once __DIR__ . '/src/Accounts/DeletionPolicy.php';
require_once __DIR__ . '/src/Accounts/DeletionRequestPolicy.php';
require_once __DIR__ . '/src/Accounts/SessionPolicy.php';
require_once __DIR__ . '/src/Accounts/PasswordResetLink.php';
require_once __DIR__ . '/src/Accounts/RegistrationPolicy.php';
require_once __DIR__ . '/src/Accounts/ProfilePolicy.php';
require_once __DIR__ . '/src/Accounts/EmailChangePolicy.php';
require_once __DIR__ . '/src/Reports/ReportPolicy.php';
require_once __DIR__ . '/src/Groups/LinkCheckPolicy.php';
require_once __DIR__ . '/src/Groups/SubmissionPolicy.php';
require_once __DIR__ . '/src/Moderation/ModerationPolicy.php';
require_once __DIR__ . '/src/Images/UploadPolicy.php';
require_once __DIR__ . '/src/Images/GooglePicturePolicy.php';
require_once __DIR__ . '/src/Support/GroupMeta.php';
require_once __DIR__ . '/src/Support/Diagnostics.php';
require_once __DIR__ . '/src/Privacy/AccountExportPolicy.php';
require_once __DIR__ . '/src/Activation/PageBlueprints.php';
require_once __DIR__ . '/src/SEO/RobotsPolicy.php';
require_once __DIR__ . '/src/Demo/ArtworkPalette.php';
require_once __DIR__ . '/src/Taxonomy/CategoryColor.php';

final class Plugin {
    private const VERSION = '1.0.0';
    private const CPT = 'pgw_group';
    private const CAT = 'pgw_category';
    private const TYPE = 'pgw_group_type';
    private const LOCATION = 'pgw_location';
    private const OPTION_DB = 'pgw_db_version';
    private static bool $auth_modal_rendered = false;

    public static function boot(): void {
        add_action('init', [self::class, 'register_content']);
        add_action('init', [self::class, 'register_image_sizes']);
        add_action('init', [self::class, 'register_shortcodes']);
        add_action('init', [self::class, 'register_rewrites']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_action('admin_menu', [self::class, 'admin_menu']);
        add_action('admin_init', [self::class, 'settings']);
        add_action(self::CAT.'_add_form_fields', [self::class, 'category_add_color_field']);
        add_action(self::CAT.'_edit_form_fields', [self::class, 'category_edit_color_field']);
        add_action('created_'.self::CAT, [self::class, 'save_category_color']);
        add_action('edited_'.self::CAT, [self::class, 'save_category_color']);
        add_action('admin_post_pgw_submit_group', [self::class, 'handle_submit']);
        add_action('admin_post_nopriv_pgw_submit_group', [self::class, 'require_login']);
        add_action('admin_post_pgw_update_profile', [self::class, 'handle_profile']);
        add_action('admin_post_pgw_moderate_group', [self::class, 'handle_moderation']);
        add_action('admin_post_pgw_logout', [self::class, 'handle_logout']);
        add_action('admin_post_nopriv_pgw_google_begin',[self::class,'google_begin']);
        add_action('admin_post_pgw_google_begin',[self::class,'google_begin']);
        add_action('admin_post_pgw_google_link_begin',[self::class,'google_begin']);
        add_action('admin_post_pgw_google_unlink_request',[self::class,'google_unlink_request']);
        add_action('admin_post_nopriv_pgw_google_callback',[self::class,'google_callback']);
        add_action('admin_post_pgw_google_callback',[self::class,'google_callback']);
        add_action('admin_post_pgw_update_email', [self::class, 'handle_email_change']);
        add_action('admin_post_pgw_update_password', [self::class, 'handle_password_change']);
        add_action('admin_post_pgw_complete_password_change', [self::class, 'handle_complete_password_change']);
        add_action('admin_post_pgw_logout_other_sessions', [self::class, 'handle_logout_other_sessions']);
        add_action('admin_post_pgw_logout_all_sessions', [self::class, 'handle_logout_all_sessions']);
        add_action('admin_post_pgw_delete_group', [self::class, 'handle_delete_group']);
        add_action('admin_post_pgw_update_group', [self::class, 'handle_update_group']);
        add_action('admin_post_pgw_upload_avatar', [self::class, 'handle_avatar_upload']);
        add_action('admin_post_pgw_delete_account_request', [self::class, 'handle_account_deletion_request']);
        add_action('admin_post_pgw_export_account', [self::class, 'handle_account_export']);
        add_action('admin_post_pgw_create_pages', [self::class, 'handle_create_pages']);
        add_action('admin_post_pgw_unlock_rate_limit', [self::class, 'handle_unlock_rate_limit']);
        add_action('admin_post_pgw_resolve_report', [self::class, 'handle_resolve_report']);
        add_action('wp_ajax_pgw_load_groups', [self::class, 'ajax_groups']);
        add_action('wp_ajax_pgw_resend_otp', [self::class, 'ajax_resend_otp']);
        add_action('wp_ajax_nopriv_pgw_resend_otp', [self::class, 'ajax_resend_otp']);
        add_action('wp_ajax_nopriv_pgw_load_groups', [self::class, 'ajax_groups']);
        add_action('wp_ajax_pgw_report_group', [self::class, 'ajax_report']);
        add_action('wp_enqueue_scripts', [self::class, 'frontend_runtime'], 20);
        add_action('wp_footer', [self::class, 'render_global_auth_modal'], 30);
        add_action('save_post_' . self::CPT, [self::class, 'sync_business_status'], 20, 3);
        add_action('save_post_page', [self::class, 'sync_catalog_page_cache'], 20, 3);
        add_action('elementor/editor/after_save', [self::class, 'sync_elementor_catalog_cache'], 20, 2);
        add_action('transition_post_status', [self::class, 'audit_status_transition'], 10, 3);
        add_action('pre_get_posts', [self::class, 'enforce_catalog_query'], PHP_INT_MAX);
        add_filter('posts_results', [self::class, 'enforce_catalog_results'], PHP_INT_MAX, 2);
        add_action('wp_ajax_nopriv_pgw_report_group', [self::class, 'ajax_report']);
        add_action('template_redirect', [self::class, 'mark_dynamic_catalog_page'], 0);
        add_action('template_redirect', [self::class, 'process_auth_submission'], 1);
        add_action('template_redirect', [self::class, 'redirect_group']);
        add_filter('the_content', [self::class, 'single_content']);
        add_filter('post_thumbnail_html', [self::class, 'suppress_theme_group_thumbnail'], 20, 5);
        add_filter('pre_get_document_title', [self::class, 'seo_title']);
        add_filter('wp_robots', [self::class, 'seo_robots']);
        add_action('wp_head', [self::class, 'seo_meta'], 1);
        add_action('pgw_cleanup_cron', [self::class, 'cron_cleanup']);
        add_action('pgw_link_check_cron', [self::class, 'cron_check_links']);
        add_action('user_register', [self::class, 'on_user_register'], 10, 1);
        add_action('delete_user', [self::class, 'on_user_delete'], 10, 1);
        add_filter('retrieve_password_message', [self::class, 'password_reset_email'], 10, 4);
        add_filter('get_avatar', [self::class, 'filter_avatar'], 10, 6);
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'register_privacy_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'register_privacy_eraser']);
        add_action('admin_notices', [self::class, 'admin_notices']);
        add_action('plugins_loaded', [self::class, 'maybe_upgrade']);
        add_action('admin_init', [self::class, 'restrict_member_admin']);
        add_filter('show_admin_bar', [self::class, 'member_admin_bar']);
        add_filter('wp_sitemaps_posts_query_args', [self::class, 'sitemap_query_args'], 10, 2);
    }

    public static function activate(): void {
        self::register_content(); self::roles(); self::defaults(); self::seed_terms(); self::create_tables(); self::migrate_group_meta();
        update_option(self::OPTION_DB, 4, false);
        if (!wp_next_scheduled('pgw_cleanup_cron')) wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'pgw_cleanup_cron');
        if (!wp_next_scheduled('pgw_link_check_cron')) wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'twicedaily', 'pgw_link_check_cron');
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('pgw_cleanup_cron');
        wp_clear_scheduled_hook('pgw_link_check_cron');
        flush_rewrite_rules();
    }

    public static function maybe_upgrade(): void {
        if ((int) get_option(self::OPTION_DB, 0) >= 4) return;
        self::roles();
        self::create_tables();
        self::migrate_group_meta();
        update_option(self::OPTION_DB, 4, false);
    }

    private static function migrate_group_meta(): void { $ids=get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids']);foreach($ids as $id){foreach(Support\GroupMeta::legacyMap() as $legacy=>$canonical){if(!metadata_exists('post',$id,$legacy)||metadata_exists('post',$id,$canonical))continue;$value=get_post_meta($id,$legacy,true);update_post_meta($id,$canonical,$value);delete_post_meta($id,$legacy);}} }

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

    private static function import_google_picture(int $user_id): void {
        $url = (new Images\GooglePicturePolicy())->normalize((string) get_user_meta($user_id, 'pgw_pending_google_picture', true));
        delete_user_meta($user_id, 'pgw_pending_google_picture');
        if (!$url) return;
        update_user_meta($user_id, 'pgw_google_picture_source', $url);
        if ((string) get_user_meta($user_id, 'pgw_avatar_source', true) === 'custom') return;
        $max_bytes = min(5242880, max(1024, (int) get_option('pgw_max_image_bytes', 5242880)));
        $temporary = wp_tempnam('pgw-google-avatar');
        if (!$temporary) return;
        $response = wp_safe_remote_get($url, ['timeout'=>10, 'redirection'=>2, 'stream'=>true, 'filename'=>$temporary, 'limit_response_size'=>$max_bytes + 1]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200 || !is_file($temporary) || filesize($temporary) > $max_bytes) { @unlink($temporary); return; }
        $mime = function_exists('wp_get_image_mime') ? (string) wp_get_image_mime($temporary) : '';
        $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        if (!isset($extensions[$mime]) || !(new Images\UploadPolicy())->accepts($mime, (int) filesize($temporary), $max_bytes)) { @unlink($temporary); return; }
        require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_handle_sideload(['name'=>'google-avatar.'.$extensions[$mime], 'tmp_name'=>$temporary, 'size'=>(int) filesize($temporary), 'error'=>0], 0, null, ['test_form'=>false]);
        if (is_wp_error($attachment_id)) { @unlink($temporary); return; }
        $old = absint(get_user_meta($user_id, 'pgw_avatar_id', true)); update_user_meta($user_id, 'pgw_avatar_id', $attachment_id); update_user_meta($user_id, 'pgw_avatar_source', 'google'); update_user_meta($user_id, 'pgw_avatar_updated_at', current_time('mysql', true));
        if ($old && $old !== $attachment_id) wp_delete_attachment($old, true);
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

    public static function register_image_sizes(): void { add_image_size('pgw-square',800,800,true);add_image_size('pgw-hero',1000,500,true);add_image_size('pgw-avatar',512,512,true); }

    private static function allowed_image_upload(array $file, int $max_bytes = 5242880): bool {
        $max_bytes=(int)get_option('pgw_max_image_bytes',$max_bytes);if(empty($file['tmp_name'])||!is_uploaded_file((string)$file['tmp_name']))return false;
        $checked=wp_check_filetype_and_ext((string)$file['tmp_name'],(string)($file['name']??''),['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp']);
        return !empty($checked['type'])&&(new Images\UploadPolicy())->accepts((string)$checked['type'],(int)($file['size']??0),$max_bytes);
    }

    private static function attach_group_image(string $field, int $group_id): int {
        if (empty($_FILES[$field]) || !self::allowed_image_upload($_FILES[$field])) return 0;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_id = media_handle_upload($field, $group_id, [], ['test_form' => false, 'mimes' => ['jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']]);
        if (is_wp_error($attachment_id)) return 0;
        set_post_thumbnail($group_id, $attachment_id);
        update_post_meta($group_id, '_pgw_original_image_id', $attachment_id);
        update_post_meta($group_id, '_pgw_square_image_id', $attachment_id);update_post_meta($group_id,'_pgw_hero_image_id',$attachment_id);update_post_meta($group_id,'_pgw_focal_x',50);update_post_meta($group_id,'_pgw_focal_y',50);
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

    private static function google_client_id(): string { return defined('PGW_GOOGLE_CLIENT_ID')?(string)PGW_GOOGLE_CLIENT_ID:(string)get_option('pgw_google_client_id',''); }
    private static function google_client_secret(): string { return defined('PGW_GOOGLE_CLIENT_SECRET')?(string)PGW_GOOGLE_CLIENT_SECRET:(string)get_option('pgw_google_client_secret',''); }
    private static function google_redirect_uri(): string { return admin_url('admin-post.php?action=pgw_google_callback'); }
    private static function base64url(string $value): string { return rtrim(strtr(base64_encode($value),'+/','-_'),'='); }

    private static function auth_flow_url(int $user_id, string $purpose): string {
        $token = (new Auth\FlowToken(wp_salt('auth')))->issue($user_id, $purpose, 15 * MINUTE_IN_SECONDS);
        $confirmation=get_page_by_path('confirmar-codigo',OBJECT,'page');$base=$confirmation?get_permalink($confirmation):home_url('/confirmar-codigo/');
        return add_query_arg('pgw_flow', $token, $base);
    }

    private static function frontend_redirect(string $url): void {
        if(!headers_sent()&&wp_safe_redirect($url))exit;
        $safe=wp_json_encode(wp_validate_redirect($url,home_url('/')));
        $validated=wp_validate_redirect($url,home_url('/'));echo '<script>window.location.replace('.$safe.');</script><noscript><meta http-equiv="refresh" content="0;url='.esc_attr($validated).'"></noscript><p class="pgw-empty"><a class="pgw-button" href="'.esc_url($validated).'">Continuar para confirmar o código</a></p>';
        exit;
    }

    private static function current_frontend_url(): string {
        $scheme=is_ssl()?'https':'http';$host=sanitize_text_field((string)($_SERVER['HTTP_HOST']??''));$uri=(string)($_SERVER['REQUEST_URI']??'/');$url=wp_validate_redirect($scheme.'://'.$host.$uri,home_url('/'));return remove_query_arg(['pgw_auth_error','pgw_flow'],$url);
    }

    private static function requested_redirect(): string {
        return wp_validate_redirect((string)wp_unslash($_POST['pgw_redirect_to']??''),home_url('/minha-conta/'));
    }

    private static function consume_pending_redirect(int $user_id): string {
        $url=wp_validate_redirect((string)get_user_meta($user_id,'pgw_pending_redirect',true),home_url('/minha-conta/'));delete_user_meta($user_id,'pgw_pending_redirect');return $url;
    }

    public static function render_global_auth_modal(): void {
        if(is_user_logged_in()||self::$auth_modal_rendered)return;echo self::auth_modal('login','','',true);
    }

    public static function process_auth_submission(): void {
        if($_SERVER['REQUEST_METHOD']!=='POST'||is_admin())return;$fallback=wp_validate_redirect((string)wp_get_referer(),home_url('/entrar/'));
        if(isset($_POST['pgw_login_nonce'])){$nonce=sanitize_text_field(wp_unslash((string)$_POST['pgw_login_nonce']));if(!wp_verify_nonce($nonce,'pgw_login')||!self::verify_form_proof('login',1))self::frontend_redirect(add_query_arg('pgw_auth_error','login',$fallback));$email=sanitize_email(wp_unslash((string)($_POST['pgw_email']??'')));$pass=(string)wp_unslash($_POST['pgw_password']??'');$identity=hash_hmac('sha256',strtolower($email?:'invalid'),wp_salt('nonce'));$allowed=self::rate_limit('login_ip','ip_'.self::request_ip_hash(),15,15*MINUTE_IN_SECONDS)&&self::rate_limit('login_email',$identity,8,15*MINUTE_IN_SECONDS);$user=$allowed&&$email?get_user_by('email',$email):false;if($user&&wp_check_password($pass,$user->user_pass,$user->ID)&&(string)get_user_meta($user->ID,'pgw_account_status',true)!=='suspended'){update_user_meta($user->ID,'pgw_pending_login',1);update_user_meta($user->ID,'pgw_pending_redirect',self::requested_redirect());update_user_meta($user->ID,'pgw_pending_remember',!empty($_POST['pgw_remember'])?1:0);if(self::send_otp((int)$user->ID,'login')){self::audit('login_otp_requested','user',(int)$user->ID);self::frontend_redirect(self::auth_flow_url((int)$user->ID,'login'));}delete_user_meta($user->ID,'pgw_pending_login');delete_user_meta($user->ID,'pgw_pending_remember');}self::frontend_redirect(add_query_arg('pgw_auth_error','login',$fallback));}
        if(isset($_POST['pgw_register_nonce'])){$nonce=sanitize_text_field(wp_unslash((string)$_POST['pgw_register_nonce']));if(!wp_verify_nonce($nonce,'pgw_register')||!self::verify_form_proof('register',2))self::frontend_redirect(add_query_arg('pgw_auth_error','register',$fallback));$policy=new Accounts\RegistrationPolicy();$data=$policy->normalize(['first_name'=>wp_unslash((string)($_POST['pgw_first_name']??'')),'last_name'=>wp_unslash((string)($_POST['pgw_last_name']??'')),'email'=>wp_unslash((string)($_POST['pgw_email']??'')),'phone'=>wp_unslash((string)($_POST['pgw_phone']??'')),'password'=>wp_unslash((string)($_POST['pgw_password']??'')),'confirmation'=>wp_unslash((string)($_POST['pgw_password_confirmation']??'')),'terms'=>!empty($_POST['pgw_terms']),'privacy'=>!empty($_POST['pgw_privacy'])]);$identity=hash_hmac('sha256',$data['email']?:'invalid',wp_salt('nonce'));$allowed=self::rate_limit('register_ip','ip_'.self::request_ip_hash(),5,HOUR_IN_SECONDS)&&self::rate_limit('register_email',$identity,3,DAY_IN_SECONDS);if(!$allowed||!$data['valid'])self::frontend_redirect(add_query_arg('pgw_auth_error','register',$fallback));$existing_id=(int)email_exists($data['email']);if($existing_id){$existing=get_userdata($existing_id);$pending=$existing&&in_array('pgw_pending_member',(array)$existing->roles,true)&&(string)get_user_meta($existing_id,'pgw_account_status',true)==='pending_email';if(!$pending)self::frontend_redirect(add_query_arg('pgw_auth_error','register',$fallback));update_user_meta($existing_id,'pgw_pending_redirect',self::requested_redirect());self::send_otp($existing_id,'registration',null,true);self::frontend_redirect(self::auth_flow_url($existing_id,'registration'));}$base=sanitize_user(strstr($data['email'],'@',true),true)?:'pgw_user';$username=$base;$suffix=1;while(username_exists($username))$username=$base.$suffix++;$display=trim($data['firstName'].' '.$data['lastName']);$id=wp_insert_user(['user_login'=>$username,'user_pass'=>$data['password'],'user_email'=>$data['email'],'display_name'=>$display,'first_name'=>$data['firstName'],'last_name'=>$data['lastName'],'role'=>'pgw_pending_member']);if(is_wp_error($id))self::frontend_redirect(add_query_arg('pgw_auth_error','register',$fallback));update_user_meta($id,'pgw_phone',$data['phone']);update_user_meta($id,'pgw_account_status','pending_email');update_user_meta($id,'pgw_registered_at',current_time('mysql',true));update_user_meta($id,'pgw_terms_accepted_at',current_time('mysql',true));update_user_meta($id,'pgw_privacy_accepted_at',current_time('mysql',true));update_user_meta($id,'pgw_pending_login',1);update_user_meta($id,'pgw_pending_redirect',self::requested_redirect());if(!self::send_otp((int)$id,'registration',null,true))self::frontend_redirect(add_query_arg('pgw_auth_error','otp',$fallback));self::audit('registration_requested','user',(int)$id);self::frontend_redirect(self::auth_flow_url((int)$id,'registration'));}
    }

    private static function form_proof_fields(string $purpose): string {
        $proof = (new Security\FormProof(wp_salt('nonce')))->issue($purpose);
        return '<input type="hidden" name="_pgw_started" value="'.absint($proof['started']).'"><input type="hidden" name="_pgw_form_id" value="'.esc_attr($proof['identifier']).'"><input type="hidden" name="_pgw_proof" value="'.esc_attr($proof['proof']).'"><p class="pgw-honeypot" aria-hidden="true"><label>Não preencha este campo<input name="'.esc_attr($proof['honeypot']).'" tabindex="-1" autocomplete="off"></label></p>';
    }

    private static function verify_form_proof(string $purpose, int $minimum_age): bool {
        $proof = new Security\FormProof(wp_salt('nonce'));
        $honeypot = $proof->issue($purpose)['honeypot'];
        $valid = $proof->verify($purpose, $_POST['_pgw_started'] ?? null, $_POST['_pgw_form_id'] ?? null, $_POST['_pgw_proof'] ?? null, $_POST[$honeypot] ?? '', $minimum_age);
        if (!$valid) return false;
        global $wpdb;
        $identifier = sanitize_key((string) wp_unslash($_POST['_pgw_form_id'] ?? ''));
        $table = $wpdb->prefix . 'pgw_rate_limits';
        $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$table} (action_key,subject_hash,window_start,count,updated_at) VALUES (%s,%s,%s,1,%s)", 'form_proof', hash_hmac('sha256', $purpose . '|' . $identifier, wp_salt('auth')), current_time('mysql', true), current_time('mysql', true)));
        return $inserted === 1;
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

    private static function send_otp(int $user_id, string $purpose, ?string $recipient = null, bool $preserve_account = false): bool {
        $user = get_userdata($user_id); $recipient = sanitize_email($recipient ?: ($user ? $user->user_email : '')); if (!$user || !is_email($recipient)) return false;
        if (!self::rate_limit('otp_' . $purpose, strtolower($recipient), 5, HOUR_IN_SECONDS)) return false;
        $last = (int) get_user_meta($user_id, 'pgw_otp_sent_at', true);
        if ($last && (time() - $last) < 60) return false;
        $code = self::create_otp($user_id, $purpose);
        update_user_meta($user_id, 'pgw_otp_sent_at', time());
        $sent = wp_mail($recipient, 'Seu código de segurança — Portal Grupos WhatsApp', "Seu código é: {$code}\n\nEle expira em 10 minutos. Se você não solicitou este código, ignore esta mensagem.");
        if (!$sent) update_user_meta($user_id, 'pgw_otp_delivery_failed', time());
        else delete_user_meta($user_id, 'pgw_otp_delivery_failed');
        return $sent || $preserve_account;
    }

    public static function on_user_delete(int $user_id): void {
        $groups = get_posts(['post_type' => self::CPT, 'author' => $user_id, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']);
        foreach ($groups as $group_id) {
            update_post_meta($group_id, 'pgw_previous_owner_id', $user_id);
            wp_update_post(['ID' => $group_id, 'post_author' => 0, 'post_status' => 'draft']);
            update_post_meta($group_id, '_pgw_status', 'inactive');
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
        $scheduled=get_posts(['post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>100,'fields'=>'ids','meta_key'=>'_pgw_featured','meta_value'=>1]);foreach($scheduled as $group_id){$active=self::is_featured_active((int)$group_id);update_post_meta($group_id,'pgw_featured_active',$active?1:0);if(!$active&&get_post_meta($group_id,'_pgw_featured_end',true)&&strtotime((string)get_post_meta($group_id,'_pgw_featured_end',true))<=time()){update_post_meta($group_id,'_pgw_featured',0);self::audit('featured_expired','group',(int)$group_id);}}
    }

    public static function cron_check_links(): void {
        $groups=get_posts(['post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>25,'orderby'=>'date','order'=>'ASC','meta_query'=>['relation'=>'AND',['key'=>'_pgw_status','value'=>'approved'],['relation'=>'OR',['key'=>'_pgw_next_link_check','compare'=>'NOT EXISTS'],['key'=>'_pgw_next_link_check','value'=>current_time('mysql',true),'compare'=>'<=','type'=>'DATETIME']]]]);
        $policy=new Groups\LinkCheckPolicy();
        foreach($groups as $group){$url=self::sanitize_invite(get_post_meta($group->ID,'_pgw_invite_url',true));$failures=absint(get_post_meta($group->ID,'_pgw_link_failure_count',true));if(!$url){$result=['status'=>'unconfirmed','failures'=>$failures+1,'confirmed'=>false];}else{$response=wp_safe_remote_head($url,['timeout'=>5,'redirection'=>2,'user-agent'=>'PGW-LinkChecker/1.0']);$error=is_wp_error($response);$code=$error?null:(int)wp_remote_retrieve_response_code($response);if($code===405){$response=wp_safe_remote_get($url,['timeout'=>5,'redirection'=>2,'limit_response_size'=>1024,'user-agent'=>'PGW-LinkChecker/1.0']);$error=is_wp_error($response);$code=$error?null:(int)wp_remote_retrieve_response_code($response);}$result=$policy->evaluate($code,$failures,$error);}
            update_post_meta($group->ID,'_pgw_link_status',$result['status']);update_post_meta($group->ID,'_pgw_link_failure_count',$result['failures']);update_post_meta($group->ID,'_pgw_last_link_check',current_time('mysql',true));update_post_meta($group->ID,'_pgw_next_link_check',gmdate('Y-m-d H:i:s',time()+($result['status']==='temporary_error'?DAY_IN_SECONDS:3*DAY_IN_SECONDS)));if($result['confirmed'])update_post_meta($group->ID,'pgw_link_verified_at',current_time('mysql',true));self::audit('link_checked','group',$group->ID,[],['status'=>$result['status'],'failures'=>$result['failures']]);
        }
    }

    private static function defaults(): void {
        $defaults = [
            'pgw_logo_url' => 'https://portalgruposwhatsapp.com.br/wp-content/uploads/2026/08/logo.png',
            'pgw_initial_limit' => 30, 'pgw_load_amount' => 15, 'pgw_require_approval' => 1,
            'pgw_email_otp_enabled' => 1, 'pgw_otp_expiry' => 10, 'pgw_allow_location' => 1,
            'pgw_card_title_limit' => 52, 'pgw_card_description_limit' => 145, 'pgw_max_image_bytes' => 5242880,
        ];
        foreach ($defaults as $key => $value) { add_option($key, $value, '', false); }
    }

    private static function roles(): void {
        add_role('pgw_member', 'Membro do Portal', ['read'=>true,'pgw_submit_groups'=>true,'pgw_edit_own_groups'=>true,'pgw_delete_own_groups'=>true,'pgw_view_own_groups'=>true,'pgw_manage_own_profile'=>true]);
        add_role('pgw_pending_member', 'Membro pendente', ['read'=>true]);
        $admin = get_role('administrator');
        if ($admin) foreach (['pgw_manage_groups','pgw_moderate_groups','pgw_manage_settings','pgw_manage_featured','pgw_manage_reports','pgw_view_metrics','pgw_manage_categories','pgw_manage_users','pgw_manage_emails','pgw_manage_demo','edit_pgw_group','read_pgw_group','delete_pgw_group','edit_pgw_groups','edit_others_pgw_groups','publish_pgw_groups','read_private_pgw_groups','delete_pgw_groups','delete_private_pgw_groups','delete_published_pgw_groups','delete_others_pgw_groups','edit_private_pgw_groups','edit_published_pgw_groups','create_pgw_groups'] as $cap) $admin->add_cap($cap);
    }

    private static function seed_terms(): void {
        foreach (['Grupo','Canal','Comunidade'] as $name) if (!term_exists($name, self::TYPE)) wp_insert_term($name, self::TYPE);
        $cats = ['Amizade','Amor e Romance','Artesanato','Carros e Motos','Cidades','Compra e Venda','Concursos','Design','Desenhos e Animes','Divulgação','Educação','Educação Financeira','Empregos','Empreendedorismo','Esportes','Eventos','Fãs','Figurinhas e Stickers','Filmes e Séries','Fotografia','Frases e Mensagens','Futebol','Games e Jogos','Imobiliária','Livros','Marketing','Memes e Humor','Moda e Beleza','Música','Negócios','Notícias','Outros','Pets','Política','Profissões','Receitas','Redes Sociais','Religião','Sustentabilidade','Tecnologia','TV','Viagem e Turismo','Vídeos'];
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
        register_term_meta(self::CAT,'_pgw_color',['type'=>'string','single'=>true,'show_in_rest'=>true,'sanitize_callback'=>static fn($value)=>(new Taxonomy\CategoryColor())->normalize($value),'auth_callback'=>static fn()=>current_user_can('manage_categories')]);
        $fields = ['_pgw_invite_url','_pgw_invite_hash','_pgw_owner_id','_pgw_status','_pgw_rejection_reason','_pgw_correction_request','_pgw_rules','_pgw_card_excerpt','_pgw_link_status','_pgw_source','_pgw_focal_x','_pgw_focal_y','_pgw_featured_start','_pgw_featured_end','_pgw_last_link_check','_pgw_next_link_check','_pgw_last_approved_at','_pgw_previous_status','_pgw_publication_notes','_pgw_link_verified_at'];
        foreach ($fields as $field) register_post_meta(self::CPT,$field,['type'=>'string','single'=>true,'show_in_rest'=>false,'sanitize_callback'=>'sanitize_text_field','auth_callback'=>fn()=>current_user_can('edit_posts')]);
        foreach (['_pgw_featured','_pgw_featured_priority','_pgw_click_count','_pgw_impression_count','_pgw_link_failure_count','_pgw_demo','_pgw_original_image_id','_pgw_square_image_id','_pgw_hero_image_id'] as $field) register_post_meta(self::CPT,$field,['type'=>'integer','single'=>true,'show_in_rest'=>false,'sanitize_callback'=>'absint','auth_callback'=>fn()=>current_user_can('edit_posts')]);
    }

    public static function register_rewrites(): void { add_rewrite_rule('^ir/([^/]+)/?$', 'index.php?pgw_redirect=$matches[1]', 'top'); add_rewrite_tag('%pgw_redirect%','([^&]+)'); }

    public static function sanitize_invite($value): string {
        $validator = new Security\InviteUrlValidator();
        return $validator->normalize((string) $value);
    }

    public static function restrict_member_admin(): void {
        if (!is_user_logged_in() || current_user_can('manage_options') || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_CLI') && WP_CLI)) return;
        $user = wp_get_current_user();
        if (array_intersect(['pgw_member', 'pgw_pending_member'], (array) $user->roles)) {
            wp_safe_redirect(home_url('/minha-conta/'));
            exit;
        }
    }

    public static function member_admin_bar(bool $show): bool {
        if (!is_user_logged_in() || current_user_can('manage_options')) return $show;
        return array_intersect(['pgw_member', 'pgw_pending_member'], (array) wp_get_current_user()->roles) ? false : $show;
    }

    public static function sitemap_query_args(array $args, string $post_type): array {
        if ($post_type !== self::CPT) return $args;
        $args['meta_query'] = [
            'relation' => 'AND',
            ['key' => '_pgw_status', 'value' => 'approved'],
            ['key' => '_pgw_demo', 'compare' => 'NOT EXISTS'],
        ];
        return $args;
    }

    public static function enforce_catalog_query(\WP_Query $query): void {
        if(!(new Search\CatalogQueryContract())->owns($query->get(Search\CatalogQueryContract::FLAG)))return;$query->set('post_type',self::CPT);$query->set('post_status','publish');$query->set('ignore_sticky_posts',true);
    }

    public static function enforce_catalog_results(array $posts,\WP_Query $query): array {
        if(!(new Search\CatalogQueryContract())->owns($query->get(Search\CatalogQueryContract::FLAG)))return $posts;return array_values(array_filter($posts,static fn($post):bool=>$post instanceof \WP_Post&&$post->post_type===self::CPT));
    }

    private static function trim_text(string $text, int $limit): string { $text = trim(wp_strip_all_tags($text)); if (function_exists('mb_strlen') && mb_strlen($text) <= $limit) return $text; if (strlen($text) <= $limit) return $text; $cut = function_exists('mb_substr') ? mb_substr($text,0,$limit-1) : substr($text,0,$limit-1); return preg_replace('/\s+\S*$/u','',$cut) . '…'; }
    private static function status(int $id): string { return (string)(get_post_meta($id,'_pgw_status',true) ?: (get_post_status($id)==='publish'?'approved':'pending')); }
    private static function is_featured_active(int $id): bool { $schedule=new Featured\FeaturedSchedule();$start=get_post_meta($id,'_pgw_featured_start',true);$end=get_post_meta($id,'_pgw_featured_end',true);return $schedule->active((bool)get_post_meta($id,'_pgw_featured',true),$start?strtotime((string)$start):null,$end?strtotime((string)$end):null); }
    private static function public_query_args(array $extra=[]): array { return array_merge((new Search\CatalogQueryContract())->base(self::CPT),['posts_per_page'=>30,'meta_query'=>[(new Search\PublicStatusPolicy())->metaQuery()],'orderby'=>['date'=>'DESC','ID'=>'DESC']],$extra); }
    private static function catalog_query_args(array $input,array $extra=[]): array { $f=(new Search\CatalogFilters())->normalize($input);$args=self::public_query_args($extra);$args['s']=$f['search'];$tax=[];foreach([[self::CAT,$f['category']],[self::TYPE,$f['type']],[self::LOCATION,$f['location']]] as [$taxonomy,$slug])if($slug)$tax[]=['taxonomy'=>$taxonomy,'field'=>'slug','terms'=>$slug];if($tax)$args['tax_query']=array_merge(['relation'=>'AND'],$tax);if($f['featured'])$args['meta_query'][]=['key'=>'_pgw_featured_active','value'=>1,'type'=>'NUMERIC'];if($f['link'])$args['meta_query'][]=['key'=>'_pgw_link_status','value'=>$f['link']];if($f['order']==='updated')$args['orderby']=['modified'=>'DESC','ID'=>'DESC'];elseif($f['order']==='accessed'){$args['meta_key']='_pgw_click_count';$args['orderby']=['meta_value_num'=>'DESC','date'=>'DESC'];}elseif($f['order']==='az'){$args['orderby']=['title'=>'ASC','ID'=>'ASC'];}elseif($f['order']==='featured')$args['orderby']=['date'=>'DESC','ID'=>'DESC'];return $args; }

    public static function assets(): void {
        wp_enqueue_style('pgw-style',plugins_url('assets/dist/frontend.css',__FILE__),[],self::VERSION);
    }

    public static function mark_dynamic_catalog_page(): void {
        if(!is_page())return;$page=get_queried_object();if(!$page instanceof \WP_Post)return;$source=(string)$page->post_content.' '.(string)get_post_meta($page->ID,'_elementor_data',true);if(!(new Search\CatalogPagePolicy())->containsCatalog($source))return;if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);nocache_headers();
    }

    public static function sync_catalog_page_cache(int $post_id,\WP_Post $post,bool $update): void {
        if((defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||wp_is_post_revision($post_id)||$post->post_type!=='page')return;$source=(string)$post->post_content.' '.(string)get_post_meta($post_id,'_elementor_data',true);if((new Search\CatalogPagePolicy())->containsCatalog($source))self::invalidate_catalog_cache();
    }

    public static function sync_elementor_catalog_cache(int $post_id,mixed $editor_data): void {
        $source=is_string($editor_data)?$editor_data:wp_json_encode($editor_data);if((new Search\CatalogPagePolicy())->containsCatalog((string)$source))self::invalidate_catalog_cache();
    }

    public static function admin_assets(string $hook): void { if (strpos($hook,'pgw')===false) return; wp_enqueue_style('pgw-style',plugins_url('assets/dist/frontend.css',__FILE__),[],self::VERSION); }

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
        wp_add_inline_script('pgw-runtime', '(function(){"use strict";document.addEventListener("click",function(e){var b=e.target.closest(".pgw-load-more");if(!b)return;e.preventDefault();if(b.dataset.loading)return;b.dataset.loading="1";var data=new URLSearchParams({action:"pgw_load_groups",nonce:PGW_DATA.loadNonce,offset:b.dataset.offset||"0",amount:b.dataset.amount||"15",search:new URLSearchParams(location.search).get("pgw_q")||""});["pgw_category","pgw_type","pgw_location","pgw_featured","pgw_link","pgw_order"].forEach(function(k){var v=new URLSearchParams(location.search).get(k);if(v)data.append(k,v);});b.setAttribute("aria-busy","true");fetch(PGW_DATA.ajaxUrl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:data}).then(function(r){return r.json()}).then(function(r){if(r.success&&r.data.html){var grid=document.querySelector("[data-pgw-grid]");if(grid)grid.insertAdjacentHTML("beforeend",r.data.html);b.dataset.offset=String(r.data.next_offset);if(!r.data.has_more)b.remove();}else if(!r.data.has_more)b.remove();}).catch(function(){b.removeAttribute("data-loading");}).finally(function(){b.removeAttribute("aria-busy");delete b.dataset.loading;});});})();');
        wp_add_inline_script('pgw-runtime', '(function(){"use strict";document.addEventListener("click",function(e){var b=e.target.closest(".pgw-resend-otp");if(!b)return;e.preventDefault();if(b.disabled)return;var status=b.parentNode.querySelector(".pgw-otp-status");b.disabled=true;b.setAttribute("aria-busy","true");fetch(PGW_DATA.ajaxUrl,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded; charset=UTF-8"},body:new URLSearchParams({action:"pgw_resend_otp",nonce:PGW_DATA.otpNonce,flow:b.dataset.flow||""})}).then(function(r){return r.json()}).then(function(r){if(status)status.textContent=r.data&&r.data.message?r.data.message:"Solicitação concluída.";}).catch(function(){if(status)status.textContent="Não foi possível reenviar agora.";}).finally(function(){b.removeAttribute("aria-busy");window.setTimeout(function(){b.disabled=false;},60000);});});})();');
        wp_add_inline_script('pgw-runtime', '(function(){"use strict";document.addEventListener("submit",function(e){var f=e.target.closest(".pgw-report-form");if(!f)return;e.preventDefault();if(f.dataset.loading)return;f.dataset.loading="1";var status=f.querySelector(".pgw-report-status"),button=f.querySelector("button[type=submit]"),data=new FormData(f);data.append("action","pgw_report_group");data.append("nonce",PGW_DATA.reportNonce);button.disabled=true;button.setAttribute("aria-busy","true");fetch(PGW_DATA.ajaxUrl,{method:"POST",body:data}).then(function(r){return r.json()}).then(function(r){if(status)status.textContent=r.data&&r.data.message?r.data.message:"Denúncia registrada.";if(r.success)f.reset();}).catch(function(){if(status)status.textContent="Não foi possível registrar agora.";}).finally(function(){button.disabled=false;button.removeAttribute("aria-busy");delete f.dataset.loading;});});})();');
        wp_add_inline_script('pgw-runtime','(function(){"use strict";function select(modal,target){modal.querySelectorAll("[data-pgw-auth-tab]").forEach(function(item){item.setAttribute("aria-selected",String(item.dataset.pgwAuthTab===target));});modal.querySelectorAll("[data-pgw-auth-panel]").forEach(function(panel){panel.hidden=panel.dataset.pgwAuthPanel!==target;});}function close(modal){modal.hidden=true;document.documentElement.classList.remove("pgw-modal-open");}document.addEventListener("click",function(e){var tab=e.target.closest("[data-pgw-auth-tab]");if(tab){e.preventDefault();select(tab.closest(".pgw-auth-modal"),tab.dataset.pgwAuthTab);return;}var opener=e.target.closest(".pgw-open-auth,.pgw-open-login,.pgw-open-register");if(opener){var modal=document.querySelector(".pgw-auth-modal");if(!modal)return;e.preventDefault();select(modal,opener.classList.contains("pgw-open-register")?"register":"login");var redirect=opener.dataset.pgwAuthRedirect;if(redirect)modal.querySelectorAll("input[name=pgw_redirect_to]").forEach(function(input){input.value=redirect;});modal.hidden=false;document.documentElement.classList.add("pgw-modal-open");var heading=modal.querySelector("[data-pgw-auth-panel]:not([hidden]) h2");if(heading)heading.setAttribute("tabindex","-1"),heading.focus({preventScroll:true});return;}var closer=e.target.closest("[data-pgw-auth-close]");if(closer){var current=closer.closest(".pgw-auth-modal");if(current){e.preventDefault();close(current);}}});document.addEventListener("keydown",function(e){if(e.key!=="Escape")return;var modal=document.querySelector(".pgw-auth-modal:not([hidden])");if(modal)close(modal);});})();');
    }

    public static function sync_business_status(int $post_id, \WP_Post $post, bool $update): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== self::CPT || wp_is_post_revision($post_id)) return;
        $native = $post->post_status;
        $business = get_post_meta($post_id, '_pgw_status', true);
        if ($native === 'publish' && $business !== 'approved') update_post_meta($post_id, '_pgw_status', 'approved');
        if ($native === 'pending' && !$business) update_post_meta($post_id, '_pgw_status', 'pending');
        self::invalidate_catalog_cache();
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
        $atts=shortcode_atts(['limit'=>get_option('pgw_initial_limit',30),'columns'=>3,'featured_first'=>1,'showcase'=>1,'load_more'=>1,'load_amount'=>15],$atts,'pgw_mostrar_grupos');$filters=(new Search\CatalogFilters())->normalize(wp_unslash($_GET));
        $query=new \WP_Query(self::catalog_query_args(wp_unslash($_GET),['posts_per_page'=>max(1,min(100,(int)$atts['limit']))]));
        if(!$query->have_posts()) return '<div class="pgw-wrap"><p class="pgw-empty">Nenhum grupo encontrado.</p></div>';
        $featured=[]; $normal=[]; while($query->have_posts()){ $query->the_post(); $id=get_the_ID();if(get_post_type($id)!==self::CPT)continue; if (self::is_featured_active($id)) $featured[]=$id; else $normal[]=$id; } wp_reset_postdata();
        $html='<section class="pgw-wrap"><form class="pgw-search pgw-filters" method="get"><input type="search" name="pgw_q" value="'.esc_attr($filters['search']).'" placeholder="Buscar grupos"><input name="pgw_category" value="'.esc_attr($filters['category']).'" placeholder="Categoria"><select name="pgw_order"><option value="featured">Destaques</option><option value="recent"'.selected($filters['order'],'recent',false).'>Recentes</option><option value="updated"'.selected($filters['order'],'updated',false).'>Atualizados</option><option value="accessed"'.selected($filters['order'],'accessed',false).'>Mais acessados</option><option value="az"'.selected($filters['order'],'az',false).'>A–Z</option></select><button class="pgw-button">Filtrar</button></form>'; $priorities=[]; foreach($featured as $featured_id) $priorities[$featured_id]=absint(get_post_meta($featured_id,'_pgw_featured_priority',true)); $featured=(new Featured\FeaturedOrder())->order($priorities); $show=array_slice($featured,0,3); $used=[];
        if(!empty($atts['showcase'])&&count($show)>0){ $html.='<div class="pgw-showcase-shell"><div class="pgw-grid">'; foreach($show as $id){$used[]=$id;$html.=self::card($id,true);} $html.='</div></div>'; }
        $ids=array_values(array_unique(array_merge(array_diff($featured,$used),$normal))); $html.='<div class="pgw-grid" data-pgw-grid>'; foreach($ids as $id)$html.=self::card($id,false); $html.='</div>';
        if(!empty($atts['load_more'])&&$query->found_posts>(int)$atts['limit']) $html.='<button class="pgw-button pgw-load-more" type="button" data-offset="'.absint((int)$atts['limit']).'" data-amount="'.absint((int)$atts['load_amount']).'">Carregar mais grupos</button>';
        return $html.'</section>';
    }

    private static function card(int $id,bool $showcase=false): string { $title=self::trim_text(get_the_title($id),(int)get_option('pgw_card_title_limit',52));$desc=self::trim_text(get_post_field('post_content',$id),(int)get_option('pgw_card_description_limit',145));$terms=get_the_terms($id,self::CAT);$term=$terms&&!is_wp_error($terms)?$terms[0]:null;$cat=$term?$term->name:'Comunidade';$category_style=$term?self::category_color_style($term):'';$types=get_the_terms($id,self::TYPE);$type=$types&&!is_wp_error($types)?$types[0]->name:'Grupo';$featured=self::is_featured_active($id);$priority=$featured?max(1,min(3,absint(get_post_meta($id,'_pgw_featured_priority',true))?:3)):0;$verified=(string)get_post_meta($id,'_pgw_link_status',true)==='active';$focal_x=(new Images\UploadPolicy())->focal(get_post_meta($id,'_pgw_focal_x',true));$focal_y=(new Images\UploadPolicy())->focal(get_post_meta($id,'_pgw_focal_y',true));$image=has_post_thumbnail($id)?get_the_post_thumbnail($id,'pgw-square',['loading'=>'lazy','decoding'=>'async','alt'=>esc_attr($title),'style'=>'object-position:'.$focal_x.'% '.$focal_y.'%']):'<div class="pgw-placeholder" aria-hidden="true">'.esc_html(mb_strtoupper(mb_substr($title,0,1))).'</div>';$classes=['pgw-card','pgw-group-card--'.($showcase?'showcase':'catalog')];if($featured){$classes[]='pgw-card--featured';$classes[]='pgw-card--priority-'.$priority;}return '<article class="'.esc_attr(implode(' ',$classes)).'" data-group-id="'.absint($id).'"><header class="pgw-card__header"><span class="pgw-card__category"'.$category_style.'>'.esc_html($cat).'</span>'.($featured?'<span class="pgw-badge">Destaque</span>':'').'</header><div class="pgw-card__visual"><span class="pgw-card__backplate" aria-hidden="true"></span><div class="pgw-card__media">'.$image.'</div></div><div class="pgw-card__body"><h3>'.esc_html($title).'</h3><p>'.esc_html($desc).'</p><div class="pgw-card__meta"><span>'.esc_html($type).' <i aria-hidden="true"></i> '.esc_html($cat).'</span>'.($verified?'<span class="pgw-card__verified">Link verificado</span>':'').'</div></div>'.self::group_join_button($id,'pgw-card__button').'</article>'; }

    private static function group_join_button(int $id,string $extra_class=''): string { $redirect=home_url('/ir/'.get_post_field('post_name',$id).'/');$classes=trim('pgw-button '.$extra_class);if(is_user_logged_in())return '<a class="'.esc_attr($classes).'" href="'.esc_url($redirect).'" rel="nofollow noopener noreferrer external">Entrar no Grupo</a>';$login=add_query_arg('pgw_redirect_to',$redirect,home_url('/entrar/'));return '<a class="'.esc_attr($classes.' pgw-open-login').'" href="'.esc_url($login).'" data-pgw-auth-redirect="'.esc_url($redirect).'" rel="nofollow">Entrar no Grupo</a>'; }

    private static function category_color_style(\WP_Term $term): string { $policy=new Taxonomy\CategoryColor();$color=$policy->normalize(get_term_meta($term->term_id,'_pgw_color',true))?:$policy->fallback((int)$term->term_id,(string)$term->slug);return ' style="--pgw-category-color:'.esc_attr($color).';--pgw-category-ink:'.esc_attr($policy->ink($color)).'"'; }

    public static function categories(array $atts=[]): string { $atts=shortcode_atts(['limit'=>10,'count'=>0,'show_more'=>0],$atts,'pgw_categorias');$limit=max(1,min(100,(int)$atts['limit']));$terms=get_terms(['taxonomy'=>self::CAT,'hide_empty'=>true,'number'=>$limit,'orderby'=>'name']);if(is_wp_error($terms)||!$terms)return '<div class="pgw-empty">Nenhuma categoria disponível.</div>';$html='<nav class="pgw-categories" aria-label="Categorias"><ul>';foreach($terms as $term){$url=get_term_link($term);if(!is_wp_error($url))$html.='<li><a href="'.esc_url($url).'"'.self::category_color_style($term).'>'.esc_html($term->name).(!empty($atts['count'])?' <span>('.absint($term->count).')</span>':'').'</a></li>';}return $html.'</ul></nav>'; }

    public static function category_add_color_field(): void { $color=(new Taxonomy\CategoryColor())->fallback(0,'nova-categoria');echo '<div class="form-field"><label for="pgw-category-color">Cor da categoria</label><input id="pgw-category-color" type="color" name="pgw_category_color" value="'.esc_attr($color).'"><p>Escolha a cor neon usada no seletor e nos cards desta categoria.</p></div>'; }

    public static function category_edit_color_field(\WP_Term $term): void { $policy=new Taxonomy\CategoryColor();$color=$policy->normalize(get_term_meta($term->term_id,'_pgw_color',true))?:$policy->fallback((int)$term->term_id,(string)$term->slug);echo '<tr class="form-field"><th scope="row"><label for="pgw-category-color">Cor da categoria</label></th><td><input id="pgw-category-color" type="color" name="pgw_category_color" value="'.esc_attr($color).'"> <code>'.esc_html($color).'</code><p class="description">Escolha a cor neon usada no seletor e nos cards desta categoria.</p></td></tr>'; }

    public static function save_category_color(int $term_id): void { if(!current_user_can('manage_categories')||!isset($_POST['pgw_category_color']))return;$color=(new Taxonomy\CategoryColor())->normalize(wp_unslash($_POST['pgw_category_color']));if($color)update_term_meta($term_id,'_pgw_color',$color);else delete_term_meta($term_id,'_pgw_color'); }

    public static function submit_form(): string { if(!is_user_logged_in())return '<div class="pgw-empty">Para enviar um grupo, <a href="'.esc_url(home_url('/entrar/')).'">entre</a> ou <a href="'.esc_url(home_url('/criar-conta/')).'">crie uma conta</a>.</div>'; $message=''; $categories=get_terms(['taxonomy'=>self::CAT,'hide_empty'=>false]); $types=get_terms(['taxonomy'=>self::TYPE,'hide_empty'=>false]); if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['pgw_submit_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pgw_submit_nonce'])),'pgw_submit')&&self::verify_form_proof('submit',3)){ if(!self::rate_limit('group_submit','user_'.get_current_user_id(),8,DAY_IN_SECONDS))$message='<p role="alert">Limite de envios atingido. Tente novamente mais tarde.</p>';else{$title=self::trim_text(sanitize_text_field(wp_unslash($_POST['pgw_title']??'')),100);$url=self::sanitize_invite(wp_unslash($_POST['pgw_url']??''));$desc=self::trim_text(sanitize_textarea_field(wp_unslash($_POST['pgw_description']??'')),1000);$cat=absint($_POST['pgw_category']??0);$type=absint($_POST['pgw_type']??0);$rules=self::trim_text(sanitize_textarea_field(wp_unslash($_POST['pgw_rules']??'')),1000);if(!$title||!$url||!$desc||!$cat||!$type||empty($_POST['pgw_terms']))$message='<p role="alert">Preencha os campos obrigatórios e confirme que possui autorização para divulgar o link.</p>';else{$dup=new \WP_Query(['post_type'=>self::CPT,'post_status'=>'any','posts_per_page'=>1,'meta_key'=>'_pgw_invite_url','meta_value'=>$url,'fields'=>'ids']);if($dup->have_posts())$message='<p role="alert">Este link já foi enviado.</p>';else{$id=wp_insert_post(['post_type'=>self::CPT,'post_status'=>'pending','post_title'=>$title,'post_content'=>$desc,'post_author'=>get_current_user_id()],true);if(!is_wp_error($id)){update_post_meta($id,'_pgw_invite_url',$url);update_post_meta($id,'_pgw_invite_hash',hash_hmac('sha256',$url,wp_salt('auth')));update_post_meta($id,'_pgw_owner_id',get_current_user_id());update_post_meta($id,'_pgw_status','pending');update_post_meta($id,'_pgw_rules',$rules);wp_set_object_terms($id,[$cat],self::CAT);wp_set_object_terms($id,[$type],self::TYPE);self::attach_group_image('pgw_group_image',$id);self::audit('group_submitted','group',$id,[],['status'=>'pending']);$message='<p role="status">Seu grupo foi enviado para moderação.</p>';}}}}} $cat_options='<option value="">Selecione</option>';foreach($categories as $term)$cat_options.='<option value="'.absint($term->term_id).'">'.esc_html($term->name).'</option>'; $type_options='<option value="">Selecione</option>';foreach($types as $term)$type_options.='<option value="'.absint($term->term_id).'">'.esc_html($term->name).'</option>'; return '<div class="pgw-wrap"><form method="post" enctype="multipart/form-data" class="pgw-submit-form"><h2>Enviar grupo</h2><p>Todo envio passa por moderação antes de ser publicado.</p>'.$message.wp_nonce_field('pgw_submit','pgw_submit_nonce',true,false).self::form_proof_fields('submit').'<p><label>Nome<input name="pgw_title" maxlength="100" required></label></p><p><label>Link HTTPS do WhatsApp<input type="url" name="pgw_url" required></label></p><p><label>Categoria<select name="pgw_category" required>'.$cat_options.'</select></label></p><p><label>Tipo<select name="pgw_type" required>'.$type_options.'</select></label></p><p><label>Imagem quadrada (JPEG, PNG ou WebP; até 5 MB)<input type="file" name="pgw_group_image" accept="image/jpeg,image/png,image/webp"></label></p><p><label>Descrição<textarea name="pgw_description" maxlength="1000" required></textarea></label></p><p><label>Regras do grupo <textarea name="pgw_rules" maxlength="1000"></textarea></label></p><p><label><input type="checkbox" name="pgw_terms" value="1" required> Confirmo que sou proprietário ou possuo autorização para divulgar este grupo.</label></p><button class="pgw-button" type="submit">Enviar para análise</button></form></div>'; }

    public static function login_form(array $atts=[]): string {
        if(is_user_logged_in())return '<p>Você já está conectado. <a href="'.esc_url(home_url('/minha-conta/')).'">Acessar minha conta</a></p>';
        $error=sanitize_key((string)($_GET['pgw_auth_error']??''));$register_error=in_array($error,['register','otp'],true);$login_message=$error&&!$register_error?'<p role="alert">Não foi possível entrar. Confira os dados ou tente novamente em instantes.</p>':'';$register_message=$register_error?'<p role="alert">Não foi possível concluir o cadastro. Confira os dados ou solicite um novo código.</p>':'';
        return self::auth_modal($register_error?'register':'login',$login_message,$register_message);
    }

    public static function register_form(): string {
        if(is_user_logged_in())return '<p>Você já possui uma conta.</p>';
        $error=sanitize_key((string)($_GET['pgw_auth_error']??''));$message=$error?'<p role="alert">Não foi possível concluir o cadastro. Confira os dados ou tente novamente em instantes.</p>':'';
        return self::auth_modal('register','',$message);
    }

    private static function auth_modal(string $active,string $login_message,string $register_message,bool $hidden=false): string {
        $active=$active==='register'?'register':'login';self::$auth_modal_rendered=true;$terms=home_url('/termos/');$privacy=home_url('/privacidade/');$google=self::google_client_id()&&self::google_client_secret();$redirect=wp_validate_redirect((string)($_GET['pgw_redirect_to']??self::current_frontend_url()),home_url('/minha-conta/'));$redirect_field='<input type="hidden" name="pgw_redirect_to" value="'.esc_attr($redirect).'">';
        $login='<form method="post" class="pgw-auth-form" data-pgw-auth-panel="login"'.($active==='login'?'':' hidden').'><h2>Bem-vindo de volta</h2><p class="pgw-auth-lead">Entre para administrar seus grupos.</p>'.$login_message.$redirect_field.wp_nonce_field('pgw_login','pgw_login_nonce',true,false).self::form_proof_fields('login').'<p><label>E-mail<input type="email" name="pgw_email" autocomplete="email" required></label></p><p><label>Senha<input type="password" name="pgw_password" autocomplete="current-password" required></label></p><div class="pgw-auth-row"><label><input type="checkbox" name="pgw_remember" value="1"> Lembrar de mim</label><a href="'.esc_url(home_url('/recuperar-senha/')).'">Esqueci a senha</a></div><button class="pgw-button pgw-auth-submit" type="submit">Entrar</button>'.($google?'<a class="pgw-button pgw-button--secondary pgw-auth-google" href="'.esc_url(admin_url('admin-post.php?action=pgw_google_begin')).'">Entrar com Google</a>':'').'</form>';
        $register='<form method="post" class="pgw-auth-form" data-pgw-auth-panel="register"'.($active==='register'?'':' hidden').'><h2>Crie sua conta</h2><p class="pgw-auth-lead">Cadastre-se para enviar e acompanhar grupos.</p>'.$register_message.$redirect_field.wp_nonce_field('pgw_register','pgw_register_nonce',true,false).self::form_proof_fields('register').'<div class="pgw-auth-fields"><p><label>Nome<input name="pgw_first_name" maxlength="100" autocomplete="given-name" required></label></p><p><label>Sobrenome <small>(opcional)</small><input name="pgw_last_name" maxlength="100" autocomplete="family-name"></label></p></div><p><label>E-mail<input type="email" name="pgw_email" autocomplete="email" required></label></p><p><label>Telefone <small>(opcional)</small><input type="tel" name="pgw_phone" autocomplete="tel" inputmode="tel"></label></p><div class="pgw-auth-fields"><p><label>Senha<input type="password" name="pgw_password" minlength="10" autocomplete="new-password" required></label></p><p><label>Confirmar senha<input type="password" name="pgw_password_confirmation" minlength="10" autocomplete="new-password" required></label></p></div><p class="pgw-auth-consent"><label><input type="checkbox" name="pgw_terms" value="1" required> Aceito os <a href="'.esc_url($terms).'" target="_blank" rel="noopener">Termos</a>.</label><label><input type="checkbox" name="pgw_privacy" value="1" required> Aceito a <a href="'.esc_url($privacy).'" target="_blank" rel="noopener">Privacidade</a>.</label></p><button class="pgw-button pgw-auth-submit" type="submit">Cadastrar e confirmar e-mail</button>'.($google?'<a class="pgw-button pgw-button--secondary pgw-auth-google" href="'.esc_url(admin_url('admin-post.php?action=pgw_google_begin')).'">Continuar com Google</a>':'').'</form>';
        return '<div class="pgw-auth-modal"'.($hidden?' hidden':'').' role="dialog" aria-modal="true" aria-labelledby="pgw-auth-title"><a class="pgw-auth-backdrop" data-pgw-auth-close href="'.esc_url(home_url('/')).'" aria-label="Fechar"></a><section class="pgw-auth-dialog"><a class="pgw-auth-close" data-pgw-auth-close href="'.esc_url(home_url('/')).'" aria-label="Fechar">&times;</a><div class="pgw-auth-content"><h1 id="pgw-auth-title" class="screen-reader-text">Acesso ao Portal Grupos WhatsApp</h1><nav class="pgw-auth-tabs" aria-label="Acesso"><button type="button" data-pgw-auth-tab="login" aria-selected="'.($active==='login'?'true':'false').'">Entrar</button><button type="button" data-pgw-auth-tab="register" aria-selected="'.($active==='register'?'true':'false').'">Cadastre-se</button></nav>'.$login.$register.'</div></section></div>';
    }


    public static function account(): string { if(!is_user_logged_in())return '<div class="pgw-empty">Entre para acessar sua conta.</div>'; $u=wp_get_current_user();$message='';if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['pgw_profile_nonce'])&&wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['pgw_profile_nonce'])),'pgw_profile')){$profile=(new Accounts\ProfilePolicy())->normalize(['first_name'=>wp_unslash((string)($_POST['pgw_name']??'')),'last_name'=>wp_unslash((string)($_POST['pgw_last_name']??'')),'display_name'=>wp_unslash((string)($_POST['pgw_display_name']??'')),'bio'=>wp_unslash((string)($_POST['pgw_bio']??'')),'phone'=>wp_unslash((string)($_POST['pgw_phone']??''))]);if(!$profile['valid'])$message='<p role="alert">Confira nome, nome público, telefone e biografia.</p>';else{wp_update_user(['ID'=>$u->ID,'display_name'=>$profile['displayName'],'first_name'=>$profile['firstName'],'last_name'=>$profile['lastName'],'description'=>$profile['bio']]);update_user_meta($u->ID,'pgw_phone',$profile['phone']);self::audit('profile_updated','user',(int)$u->ID);$message='<p role="status">Perfil atualizado.</p>';$u=get_userdata($u->ID);}}$providers=(array)get_user_meta($u->ID,'pgw_auth_providers',true);$verified=get_user_meta($u->ID,'pgw_email_verified',true);$avatar=self::avatar_html($u->ID,96);$notice=sanitize_key((string)($_GET['pgw_notice']??''));if($notice==='email_changed')$message.='<p role="status">Seu e-mail foi alterado com sucesso.</p>';if($notice==='avatar')$message.='<p role="status">Sua foto foi atualizada.</p>';if($notice==='google_linked')$message.='<p role="status">Conta Google vinculada.</p>';if($notice==='google_unlinked')$message.='<p role="status">Conta Google desvinculada.</p>';
        $authorization=sanitize_text_field(wp_unslash((string)($_GET['pgw_password_authorization']??'')));$authorization_valid=$authorization&&preg_match('/^[a-f0-9]{64}$/D',$authorization)&&hash_equals((string)get_user_meta($u->ID,'pgw_password_authorization_hash',true),hash_hmac('sha256',$authorization,wp_salt('auth')))&&(int)get_user_meta($u->ID,'pgw_password_authorization_expires',true)>=time();
        $session_manager=\WP_Session_Tokens::get_instance($u->ID);$session_summary=(new Accounts\SessionPolicy())->summarize($session_manager->get_all());$session_controls='<section class="pgw-sessions"><h3>Sessões</h3><p><strong>'.absint($session_summary['active']).'</strong> sessão(ões) ativa(s).'.($session_summary['next_expiration']?'<br><small>Próxima expiração: '.esc_html(wp_date('d/m/Y H:i',$session_summary['next_expiration'])).'</small>':'').'</p><div class="pgw-account-actions"><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pgw_logout_other_sessions">'.wp_nonce_field('pgw_logout_other_sessions','pgw_sessions_nonce',true,false).'<button class="pgw-button pgw-button--secondary">Sair das outras sessões</button></form><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pgw_logout_all_sessions">'.wp_nonce_field('pgw_logout_all_sessions','pgw_sessions_nonce',true,false).'<button class="pgw-button pgw-danger">Sair de todas</button></form></div></section>';
        $provider_policy=new Auth\ProviderPolicy();$providers=$provider_policy->normalize($providers);$google_linked=$provider_policy->linked($providers,'google');$google_controls='<section class="pgw-google-account"><h3>Google</h3>';if(!self::google_client_id()||!self::google_client_secret())$google_controls.='<p>Integração Google não configurada pelo administrador.</p>';elseif(!$google_linked)$google_controls.='<p><a class="pgw-button pgw-button--secondary" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=pgw_google_link_begin'),'pgw_google_link')).'">Vincular Google</a></p>';elseif($provider_policy->canUnlink($providers,'google'))$google_controls.='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pgw_google_unlink_request">'.wp_nonce_field('pgw_google_unlink','pgw_google_nonce',true,false).'<p>Google está vinculado à sua conta.</p><button class="pgw-button pgw-button--secondary">Desvincular Google</button></form>';else $google_controls.='<p>Google é seu único método de acesso. Defina outro método antes de desvincular.</p>';$google_controls.='</section>';
        $google_only=$providers===['google'];
        if($authorization_valid)$password_form='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><h3>Definir nova senha</h3><input type="hidden" name="action" value="pgw_complete_password_change"><input type="hidden" name="authorization" value="'.esc_attr($authorization).'">'.wp_nonce_field('pgw_complete_password_change','pgw_security_nonce',true,false).'<p><label>Nova senha<input type="password" name="new_password" minlength="10" autocomplete="new-password" required></label></p><p><label>Confirmar nova senha<input type="password" name="confirm_password" minlength="10" autocomplete="new-password" required></label></p><button class="pgw-button">Salvar nova senha</button></form>';else{$password_form='<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><h3>'.($google_only?'Criar senha':'Alterar senha').'</h3><input type="hidden" name="action" value="pgw_update_password">'.wp_nonce_field('pgw_update_password','pgw_security_nonce',true,false).($google_only?'<p>Confirme por código para adicionar acesso por e-mail e senha.</p>':'<p><label>Senha atual<input type="password" name="current_password" autocomplete="current-password" required></label></p>').'<button class="pgw-button">Enviar código de segurança</button></form>';}
        $deletion_password_field=$google_only?'<p>Como o Google é seu único provedor, a confirmação será feita pelo código enviado ao e-mail.</p>':'<p><label>Senha atual<input type="password" name="current_password" autocomplete="current-password" required></label></p>';
        return '<section class="pgw-wrap"><div class="pgw-auth"><div class="pgw-account-head">'.$avatar.'<p><strong>'.esc_html($u->display_name).'</strong><br>'.esc_html($u->user_email).'<br><span class="pgw-status">'.($verified?'E-mail verificado':'E-mail pendente').'</span><br><small>Provedores: '.esc_html(implode(', ', $providers)).'<br>Último acesso: '.esc_html((string)get_user_meta($u->ID,'pgw_last_login_at',true)).'</small></p></div><h2>Perfil</h2>'.$message.'<form method="post">'.wp_nonce_field('pgw_profile','pgw_profile_nonce',true,false).'<p><label>Nome<input name="pgw_name" value="'.esc_attr($u->first_name ?: $u->display_name).'" required></label></p><p><label>Sobrenome<input name="pgw_last_name" maxlength="100" value="'.esc_attr($u->last_name).'"></label></p><p><label>Nome público<input name="pgw_display_name" maxlength="100" value="'.esc_attr($u->display_name).'" required></label></p><p><label>Telefone<input name="pgw_phone" inputmode="tel" autocomplete="tel" value="'.esc_attr((string)get_user_meta($u->ID,'pgw_phone',true)).'"></label></p><p><label>Biografia curta<textarea name="pgw_bio" maxlength="300">'.esc_textarea((string)$u->description).'</textarea></label></p><button class="pgw-button" type="submit">Salvar perfil</button></form><hr><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><h3>Foto do perfil</h3><input type="hidden" name="action" value="pgw_upload_avatar">'.wp_nonce_field('pgw_avatar_upload','pgw_avatar_nonce',true,false).'<p><label>Nova foto (JPEG, PNG ou WebP; até 5 MB)<input type="file" name="pgw_avatar" accept="image/jpeg,image/png,image/webp" required></label></p><button class="pgw-button">Alterar foto</button></form><hr><h3>Segurança</h3>'.$password_form.$google_controls.$session_controls.'<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><h3>Alterar e-mail</h3><input type="hidden" name="action" value="pgw_update_email">'.wp_nonce_field('pgw_update_email','pgw_email_nonce',true,false).'<p><label>Novo e-mail<input type="email" name="new_email" autocomplete="email" required></label></p><button class="pgw-button">Enviar confirmação</button></form><hr><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><h3>Exportar meus dados</h3><input type="hidden" name="action" value="pgw_export_account">'.wp_nonce_field('pgw_export_account','pgw_export_nonce',true,false).'<p>Baixe uma cópia JSON dos dados da conta e dos grupos enviados.</p><button class="pgw-button pgw-button--secondary">Baixar meus dados</button></form><hr><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><h3>Excluir conta</h3><input type="hidden" name="action" value="pgw_delete_account_request">'.wp_nonce_field('pgw_delete_account','pgw_delete_account_nonce',true,false).'<p><label>Digite EXCLUIR<input name="confirmation" required></label></p>'.$deletion_password_field.'<button class="pgw-button pgw-danger">Solicitar exclusão</button></form><p class="pgw-account-actions"><a class="pgw-button pgw-button--secondary" href="'.esc_url(home_url('/meus-grupos/')).'">Meus grupos</a><a class="pgw-button" href="'.esc_url(wp_logout_url(home_url('/'))).'">Sair</a></p></div></section>'; }

    public static function my_groups(): string { if(!is_user_logged_in())return self::auth_modal('login','<p role="status">Entre ou cadastre-se para visualizar seus grupos.</p>',''); $statuses=['approved'=>'Aprovados','pending'=>'Pendentes','correction_requested'=>'Correções','rejected'=>'Rejeitados','inactive'=>'Inativos'];$counts=array_fill_keys(array_keys($statuses),0);$all=get_posts(['post_type'=>self::CPT,'post_status'=>'any','author'=>get_current_user_id(),'numberposts'=>-1,'fields'=>'ids']);foreach($all as $id){$status=self::status($id);if(isset($counts[$status]))$counts[$status]++;}$categories=get_terms(['taxonomy'=>self::CAT,'hide_empty'=>false]);$types=get_terms(['taxonomy'=>self::TYPE,'hide_empty'=>false]);$q=new \WP_Query(['post_type'=>self::CPT,'post_status'=>['publish','pending','draft','private'],'author'=>get_current_user_id(),'posts_per_page'=>20]);$summary='<div class="pgw-account-actions">';foreach($statuses as $key=>$label)$summary.='<span class="pgw-status">'.esc_html($label).': '.absint($counts[$key]).'</span>';$summary.='</div>';if(!$q->have_posts())return '<div class="pgw-wrap"><h2>Meus grupos</h2>'.$summary.'<p class="pgw-empty">Você ainda não enviou grupos.</p><p><a class="pgw-button" href="'.esc_url(home_url('/enviar-grupo/')).'">Enviar grupo</a></p></div>';$html='<section class="pgw-wrap"><h2>Meus grupos</h2>'.$summary.'<p><a class="pgw-button" href="'.esc_url(home_url('/enviar-grupo/')).'">Enviar grupo</a></p><div class="pgw-grid">';while($q->have_posts()){$q->the_post();$id=get_the_ID();$status=self::status($id);$clicks=absint(get_post_meta($id,'_pgw_click_count',true));$reason=(string)get_post_meta($id,'_pgw_rejection_reason',true);$current_categories=wp_get_object_terms($id,self::CAT,['fields'=>'ids']);$current_types=wp_get_object_terms($id,self::TYPE,['fields'=>'ids']);$category_options='';foreach($categories as $term)$category_options.='<option value="'.absint($term->term_id).'"'.selected(in_array((int)$term->term_id,$current_categories,true),true,false).'>'.esc_html($term->name).'</option>';$type_options='';foreach($types as $term)$type_options.='<option value="'.absint($term->term_id).'"'.selected(in_array((int)$term->term_id,$current_types,true),true,false).'>'.esc_html($term->name).'</option>';$html.='<article class="pgw-card"><h3>'.esc_html(get_the_title()).'</h3><p><span class="pgw-status">'.esc_html($status).'</span><br>Cliques: '.absint($clicks).($reason?'<br><strong>Motivo:</strong> '.esc_html($reason):'').'</p><div class="pgw-group-actions"><a class="pgw-button pgw-button--secondary" href="'.esc_url(get_permalink()).'">Visualizar</a><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pgw_delete_group"><input type="hidden" name="group_id" value="'.absint($id).'">'.wp_nonce_field('pgw_delete_group','_wpnonce',true,false).'<button class="pgw-button pgw-danger" type="submit">Excluir</button></form></div><details><summary>Editar e reenviar</summary><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pgw_update_group"><input type="hidden" name="group_id" value="'.absint($id).'">'.wp_nonce_field('pgw_update_group_'.$id,'pgw_update_nonce',true,false).'<p><label>Nome<input name="pgw_title" maxlength="100" value="'.esc_attr(get_the_title($id)).'" required></label></p><p><label>Link<input type="url" name="pgw_url" value="'.esc_attr((string)get_post_meta($id,'_pgw_invite_url',true)).'" required></label></p><p><label>Categoria<select name="pgw_category" required>'.$category_options.'</select></label></p><p><label>Tipo<select name="pgw_type" required>'.$type_options.'</select></label></p><p><label>Descrição<textarea name="pgw_description" maxlength="1000" required>'.esc_textarea((string)get_post_field('post_content',$id)).'</textarea></label></p><p><label>Regras<textarea name="pgw_rules" maxlength="1000">'.esc_textarea((string)get_post_meta($id,'_pgw_rules',true)).'</textarea></label></p><p><label>Nova imagem<input type="file" name="pgw_group_image" accept="image/jpeg,image/png,image/webp"></label></p><button class="pgw-button" type="submit">Salvar e reenviar</button></form></details></article>';}wp_reset_postdata();return $html.'</div></section>'; }

    public static function password_reset(): string {
        if (is_user_logged_in()) return '<div class="pgw-empty">Você já está conectado.</div>';
        $key=sanitize_text_field(wp_unslash((string)($_REQUEST['key']??''))); $login=sanitize_text_field(wp_unslash((string)($_REQUEST['login']??''))); $message='';
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['pgw_reset_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash((string)$_POST['pgw_reset_nonce'])),'pgw_password_reset')) {
            $action=sanitize_key((string)($_POST['pgw_reset_action']??'request'));
            if (!self::verify_form_proof('password_reset',1)) $message='<p role="alert">Não foi possível processar a solicitação.</p>';
            elseif ($action==='complete') {
                $user=check_password_reset_key($key,$login); $password=(string)wp_unslash($_POST['new_password']??''); $confirmation=(string)wp_unslash($_POST['confirm_password']??'');
                if (is_wp_error($user) || !(new Auth\PasswordPolicy())->accepts($password,$confirmation,10)) $message='<p role="alert">O link expirou ou as senhas não atendem aos requisitos.</p>';
                else { reset_password($user,$password); $providers=(new Auth\ProviderPolicy())->normalize(get_user_meta($user->ID,'pgw_auth_providers',true)); if(!in_array('email',$providers,true))$providers[]='email'; update_user_meta($user->ID,'pgw_auth_providers',(new Auth\ProviderPolicy())->normalize($providers)); self::audit('password_reset_completed','user',(int)$user->ID); wp_mail($user->user_email,'Senha redefinida — Portal Grupos WhatsApp','Sua senha foi redefinida e as sessões anteriores foram encerradas. Se não foi você, contate o suporte.'); wp_safe_redirect(add_query_arg('pgw_notice','password_reset',home_url('/entrar/'))); exit; }
            } else {
                $email=sanitize_email(wp_unslash((string)($_POST['pgw_email']??''))); $identity=$email?hash_hmac('sha256',strtolower($email),wp_salt('nonce')):'invalid';
                if(self::rate_limit('password_reset','ip_'.self::request_ip_hash(),5,HOUR_IN_SECONDS)&&self::rate_limit('password_reset_email',$identity,3,HOUR_IN_SECONDS)&&is_email($email)) retrieve_password($email);
                $message='<p role="status">Se existir uma conta para esse e-mail, enviaremos as instruções de recuperação.</p>';
            }
        }
        if ($key && $login) {
            $user=check_password_reset_key($key,$login); if(is_wp_error($user)) return '<div class="pgw-empty">Este link de recuperação é inválido ou expirou. Solicite um novo.</div>';
            return '<div class="pgw-wrap"><form class="pgw-auth" method="post"><h2>Definir nova senha</h2>'.$message.wp_nonce_field('pgw_password_reset','pgw_reset_nonce',true,false).self::form_proof_fields('password_reset').'<input type="hidden" name="pgw_reset_action" value="complete"><input type="hidden" name="key" value="'.esc_attr($key).'"><input type="hidden" name="login" value="'.esc_attr($login).'"><p><label>Nova senha<input type="password" name="new_password" minlength="10" autocomplete="new-password" required></label></p><p><label>Confirmar senha<input type="password" name="confirm_password" minlength="10" autocomplete="new-password" required></label></p><button class="pgw-button">Redefinir senha</button></form></div>';
        }
        return '<div class="pgw-wrap"><form class="pgw-auth" method="post"><h2>Recuperar senha</h2>'.$message.wp_nonce_field('pgw_password_reset','pgw_reset_nonce',true,false).self::form_proof_fields('password_reset').'<input type="hidden" name="pgw_reset_action" value="request"><p>Informe seu e-mail para receber instruções.</p><p><label>E-mail<input type="email" name="pgw_email" autocomplete="email" required></label></p><button class="pgw-button">Enviar instruções</button></form></div>';
    }

    public static function password_reset_email(string $message, string $key, string $user_login, \WP_User $user): string {
        $url=(new Accounts\PasswordResetLink())->build(home_url('/recuperar-senha/'),$key,$user_login);
        if(!$url)return $message;
        return "Olá ".$user->display_name.",\n\nRecebemos uma solicitação para redefinir sua senha no Portal Grupos WhatsApp.\n\nAcesse o link abaixo para criar uma nova senha:\n".$url."\n\nSe você não solicitou, ignore esta mensagem. O link expira automaticamente.\n";
    }
    public static function verify_code(): string {
        $allowed = ['login', 'registration', 'google_login', 'google_link', 'google_unlink', 'email_change', 'password_change', 'account_deletion'];
        $flow_token = sanitize_text_field(wp_unslash((string) ($_REQUEST['pgw_flow'] ?? '')));
        $context = (new Auth\FlowToken(wp_salt('auth')))->verify($flow_token, $allowed);
        $user_id = (int) ($context['user_id'] ?? 0);
        $purpose = (string) ($context['purpose'] ?? '');
        $message = '';
        if (!$user_id || !in_array($purpose, $allowed, true) || !get_userdata($user_id) || (in_array($purpose, ['google_unlink','email_change','password_change','account_deletion'], true) && get_current_user_id() !== $user_id)) return '<div class="pgw-empty">Solicitação de confirmação inválida.</div>';
        if ((int)get_user_meta($user_id,'pgw_otp_delivery_failed',true) > 0) $message='<p role="alert">A conta foi criada, mas o servidor de e-mail não confirmou o envio. Use “Reenviar código” ou confira a configuração SMTP.</p>';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nonce = sanitize_text_field(wp_unslash($_POST['pgw_verify_nonce'] ?? ''));
            $posted_purpose = $purpose;
            $code = preg_replace('/\\D+/', '', (string) wp_unslash($_POST['pgw_code'] ?? ''));
            if (!wp_verify_nonce($nonce, 'pgw_verify') || $posted_purpose !== $purpose || strlen($code) !== 6) {
                $message = '<p role="alert">Não foi possível confirmar esta solicitação.</p>';
            } elseif (!self::verify_otp($user_id, $code, $purpose)) {
                $message = '<p role="alert">Código inválido, expirado ou já utilizado.</p>';
            } elseif ($purpose === 'registration') {
                $user = new \WP_User($user_id); $user->set_role('pgw_member');
                update_user_meta($user_id, 'pgw_account_status', 'active'); update_user_meta($user_id, 'pgw_email_verified', 1);
                delete_user_meta($user_id, 'pgw_pending_login');
                self::audit('registration_verified', 'user', $user_id);
                wp_set_auth_cookie($user_id, true, is_ssl()); wp_safe_redirect(self::consume_pending_redirect($user_id)); exit;
            } elseif (in_array($purpose,['google_login','google_link'],true)) {
                if((string)get_user_meta($user_id,'pgw_account_status',true)==='suspended')return '<div class="pgw-empty">Esta conta não está disponível.</div>';
                if($purpose==='google_link'){$sub=(string)get_user_meta($user_id,'pgw_pending_google_sub',true);$google_email=sanitize_email((string)get_user_meta($user_id,'pgw_pending_google_email',true));if(!$sub)return '<div class="pgw-empty">Vinculação inválida.</div>';update_user_meta($user_id,'pgw_google_sub',$sub);update_user_meta($user_id,'pgw_google_email',$google_email);update_user_meta($user_id,'pgw_google_linked_at',current_time('mysql',true));delete_user_meta($user_id,'pgw_pending_google_sub');delete_user_meta($user_id,'pgw_pending_google_email');}
                $google_user=new \WP_User($user_id);if(in_array('pgw_pending_member',(array)$google_user->roles,true))$google_user->set_role('pgw_member');$providers=(new Auth\ProviderPolicy())->normalize(get_user_meta($user_id,'pgw_auth_providers',true));if(!in_array('google',$providers,true))$providers[]='google';update_user_meta($user_id,'pgw_account_status','active');update_user_meta($user_id,'pgw_email_verified',1);update_user_meta($user_id,'pgw_auth_providers',(new Auth\ProviderPolicy())->normalize($providers));self::import_google_picture($user_id);wp_set_auth_cookie($user_id,true,is_ssl());self::audit($purpose==='google_link'?'google_linked':'google_login_verified','user',$user_id);wp_safe_redirect(add_query_arg('pgw_notice',$purpose==='google_link'?'google_linked':'google_login',home_url('/minha-conta/')));exit;
            } elseif ($purpose === 'login') {
                if ((string) get_user_meta($user_id, 'pgw_account_status', true) !== 'active') $message = '<p role="alert">Esta conta não está disponível para acesso.</p>';
                else { wp_set_auth_cookie($user_id, (bool)get_user_meta($user_id,'pgw_pending_remember',true), is_ssl()); delete_user_meta($user_id, 'pgw_pending_login'); delete_user_meta($user_id,'pgw_pending_remember'); update_user_meta($user_id, 'pgw_last_login_at', current_time('mysql', true)); self::audit('login_verified', 'user', $user_id); wp_safe_redirect(self::consume_pending_redirect($user_id)); exit; }
            } elseif ($purpose === 'email_change') {
                $email = sanitize_email((string) get_user_meta($user_id, 'pgw_pending_email', true)); $requested_at=(int)get_user_meta($user_id,'pgw_pending_email_requested_at',true); $current_user=get_userdata($user_id); $existing=$email?email_exists($email):false; $change=$current_user?(new Accounts\EmailChangePolicy())->request($email,(string)$current_user->user_email,$existing?(int)$existing:null,$user_id):['valid'=>false];
                if (empty($change['valid']) || !(new Accounts\EmailChangePolicy())->fresh($requested_at,time())) { delete_user_meta($user_id,'pgw_pending_email');delete_user_meta($user_id,'pgw_pending_email_requested_at');$message = '<p role="alert">O novo e-mail não está mais disponível ou a solicitação expirou.</p>'; }
                else { $old_email = (string) $current_user->user_email; wp_update_user(['ID' => $user_id, 'user_email' => $email]); delete_user_meta($user_id, 'pgw_pending_email'); delete_user_meta($user_id, 'pgw_pending_email_requested_at'); update_user_meta($user_id, 'pgw_email_verified', 1); self::audit('email_changed', 'user', $user_id, ['email_hash' => hash_hmac('sha256', $old_email, wp_salt('auth'))], ['email_hash' => hash_hmac('sha256', $email, wp_salt('auth'))]); \WP_Session_Tokens::get_instance($user_id)->destroy_all();wp_set_auth_cookie($user_id,false,is_ssl()); wp_mail($old_email, 'E-mail da conta alterado', 'O e-mail de acesso da sua conta foi alterado e as sessões anteriores foram encerradas. Se não foi você, contate o suporte imediatamente.'); wp_mail($email,'E-mail confirmado — Portal Grupos WhatsApp','Este e-mail foi confirmado como novo endereço de acesso da sua conta.'); wp_safe_redirect(add_query_arg('pgw_notice', 'email_changed', home_url('/minha-conta/'))); exit; }
            } elseif ($purpose === 'google_unlink') {
                $providers = (new Auth\ProviderPolicy())->normalize(get_user_meta($user_id, 'pgw_auth_providers', true));
                if (!(new Auth\ProviderPolicy())->canUnlink($providers, 'google')) $message = '<p role="alert">Mantenha ao menos um método de acesso à conta.</p>';
                else { $google_avatar=(string)get_user_meta($user_id,'pgw_avatar_source',true)==='google'?absint(get_user_meta($user_id,'pgw_avatar_id',true)):0; delete_user_meta($user_id, 'pgw_google_sub'); delete_user_meta($user_id, 'pgw_google_email'); delete_user_meta($user_id, 'pgw_google_linked_at'); delete_user_meta($user_id, 'pgw_google_picture_source'); delete_user_meta($user_id, 'pgw_pending_google_picture'); if($google_avatar){delete_user_meta($user_id,'pgw_avatar_id');delete_user_meta($user_id,'pgw_avatar_source');wp_delete_attachment($google_avatar,true);} update_user_meta($user_id, 'pgw_auth_providers', (new Auth\ProviderPolicy())->without($providers, 'google')); self::audit('google_unlinked', 'user', $user_id); wp_safe_redirect(add_query_arg('pgw_notice', 'google_unlinked', home_url('/minha-conta/'))); exit; }
            } elseif ($purpose === 'password_change') {
                $authorization = bin2hex(random_bytes(32));
                update_user_meta($user_id,'pgw_password_authorization_hash',hash_hmac('sha256',$authorization,wp_salt('auth')));
                update_user_meta($user_id,'pgw_password_authorization_expires',time()+10*MINUTE_IN_SECONDS);
                self::audit('password_change_otp_verified','user',$user_id);
                wp_safe_redirect(add_query_arg('pgw_password_authorization',$authorization,home_url('/minha-conta/'))); exit;
            }
            elseif ($purpose === 'account_deletion') {
                $deleted_user = get_userdata($user_id); $email = $deleted_user ? (string) $deleted_user->user_email : '';
                $groups = get_posts(['post_type'=>self::CPT,'post_status'=>'any','author'=>$user_id,'numberposts'=>-1,'fields'=>'ids']);
                $policy = new Accounts\DeletionPolicy();
                foreach ($groups as $group_id) { $transition=$policy->transition(self::status((int)$group_id)); update_post_meta($group_id,'_pgw_previous_status',$transition['previous_status']); update_post_meta($group_id,'_pgw_status',$transition['status']); wp_update_post(['ID'=>(int)$group_id,'post_status'=>$transition['post_status']]); }
                self::audit('account_deleted', 'user', $user_id, ['owned_groups'=>count($groups)], ['groups_deactivated'=>count($groups)]);
                \WP_Session_Tokens::get_instance($user_id)->destroy_all(); wp_clear_auth_cookie();
                require_once ABSPATH . 'wp-admin/includes/user.php';
                $administrators = get_users(['role'=>'administrator','number'=>1,'fields'=>'ids','orderby'=>'ID','order'=>'ASC']);
                $reassign_to = !empty($administrators[0]) ? (int) $administrators[0] : null;
                $deleted = $reassign_to ? wp_delete_user($user_id, $reassign_to) : false;
                if (!$deleted) { update_user_meta($user_id,'pgw_account_status','deletion_pending'); wp_safe_redirect(add_query_arg('pgw_error','deletion_pending',home_url('/minha-conta/'))); exit; }
                if ($deleted && is_email($email)) wp_mail($email, 'Conta excluída — Portal Grupos WhatsApp', 'Sua conta foi excluída. Os grupos enviados foram desativados e mantidos somente para revisão administrativa.');
                wp_safe_redirect(add_query_arg('pgw_notice','account_deleted',home_url('/'))); exit;
            }
        }
        $resend = '<p><button class="pgw-button pgw-resend-otp" type="button" data-flow="'.esc_attr($flow_token).'">Reenviar código</button><span class="pgw-otp-status" aria-live="polite"></span></p>';
        return '<div class="pgw-wrap"><form method="post" class="pgw-auth"><h2>Confirmar código</h2>'.$message.wp_nonce_field('pgw_verify','pgw_verify_nonce',true,false).'<input type="hidden" name="pgw_flow" value="'.esc_attr($flow_token).'"><p><label>Código de seis dígitos<input inputmode="numeric" autocomplete="one-time-code" name="pgw_code" maxlength="6" pattern="[0-9]{6}" required></label></p><button class="pgw-button" type="submit">Confirmar</button>'.$resend.'</form></div>';
    }

    public static function profile_summary(): string {
        if (!is_user_logged_in()) return '<a class="pgw-button" href="'.esc_url(home_url('/entrar/')).'">Entrar</a>';
        $user = wp_get_current_user();
        return '<div class="pgw-profile-summary">'.self::avatar_html($user->ID, 48, 'pgw-avatar').' <span>'.esc_html($user->display_name).'</span><a href="'.esc_url(home_url('/minha-conta/')).'">Minha Conta</a></div>';
    }

    public static function admin_menu(): void {
        add_menu_page('Portal Grupos','Portal Grupos','pgw_manage_groups','pgw-dashboard',[self::class,'dashboard'],'dashicons-groups',26);
        add_submenu_page('pgw-dashboard','Todos os Grupos','Todos os Grupos','pgw_manage_groups','edit.php?post_type='.self::CPT);
        add_submenu_page('pgw-dashboard','Moderação','Pendentes e Correções','pgw_moderate_groups','pgw-moderation',[self::class,'moderation_page']);
        add_submenu_page('pgw-dashboard','Destaques','Destaques','pgw_manage_featured','pgw-featured',[self::class,'featured_page']);
        add_submenu_page('pgw-dashboard','Categorias','Categorias','pgw_manage_categories','edit-tags.php?taxonomy='.self::CAT.'&post_type='.self::CPT);
        add_submenu_page('pgw-dashboard','Denúncias','Denúncias','pgw_manage_reports','pgw-reports',[self::class,'reports_page']);
        add_submenu_page('pgw-dashboard','Métricas','Métricas','pgw_view_metrics','pgw-metrics',[self::class,'metrics_page']);
        add_submenu_page('pgw-dashboard','Conteúdo Demo','Conteúdo Demo','pgw_manage_demo','pgw-demo',[self::class,'demo_page']);
        add_submenu_page('pgw-dashboard','Assistente de páginas','Assistente','pgw_manage_settings','pgw-setup',[self::class,'setup_page']);
        add_submenu_page('pgw-dashboard','Diagnóstico','Diagnóstico','pgw_manage_settings','pgw-diagnostics',[self::class,'diagnostics_page']);
        add_submenu_page('pgw-dashboard','Configurações','Configurações','pgw_manage_settings','pgw-settings',[self::class,'settings_page']);
    }

    public static function setup_page(): void {
        if(!current_user_can('pgw_manage_settings'))return;$pages=(new Activation\PageBlueprints())->all();$stored=(array)get_option('pgw_page_ids',[]);$notice=sanitize_key((string)($_GET['pgw_notice']??''));
        echo '<div class="wrap pgw-admin"><h1>Assistente de páginas</h1>'.($notice==='pages_created'?'<div class="notice notice-success"><p>Páginas verificadas e criadas sem sobrescrever conteúdo existente.</p></div>':'').'<p>Nenhuma página é criada automaticamente. Selecione e confirme somente as interfaces que deseja publicar.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pgw_create_pages">'.wp_nonce_field('pgw_create_pages','pgw_pages_nonce',true,false).'<table class="widefat striped"><thead><tr><th>Criar/verificar</th><th>Página</th><th>Slug</th><th>Shortcode</th><th>Situação</th></tr></thead><tbody>';
        foreach($pages as $slug=>$page){$existing=get_page_by_path($slug,OBJECT,'page');$id=$existing?(int)$existing->ID:absint($stored[$slug]??0);echo '<tr><td><input type="checkbox" name="pages[]" value="'.esc_attr($slug).'"'.checked(!$existing,true,false).'></td><td>'.esc_html($page['title']).'</td><td><code>/'.esc_html($slug).'/</code></td><td><code>'.esc_html($page['content']).'</code></td><td>'.($id?'<a href="'.esc_url(get_permalink($id)).'">Existente</a>':'Não criada').'</td></tr>';}
        echo '</tbody></table>';submit_button('Confirmar páginas selecionadas');echo '</form></div>';
    }

    public static function diagnostics_page(): void {
        if(!current_user_can('pgw_manage_settings'))return;global $wpdb,$wp_version;$tables=['pgw_auth_challenges','pgw_rate_limits','pgw_events','pgw_reports','pgw_audit_log'];$all_tables=true;foreach($tables as $suffix){$name=$wpdb->prefix.$suffix;if((string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($name)))!==$name){$all_tables=false;break;}}$checks=(new Support\Diagnostics())->evaluate(['php'=>PHP_VERSION,'wordpress'=>$wp_version,'https'=>is_ssl(),'cleanup_cron'=>wp_next_scheduled('pgw_cleanup_cron'),'link_cron'=>wp_next_scheduled('pgw_link_check_cron'),'tables'=>$all_tables,'image_editor'=>function_exists('wp_image_editor_supports')&&wp_image_editor_supports(['mime_type'=>'image/png'])]);$notice=sanitize_key((string)($_GET['pgw_notice']??''));$labels=['php'=>'PHP 8.1+','wordpress'=>'WordPress 6.4+','https'=>'HTTPS ativo','cron'=>'Agendamentos do plugin','database'=>'Tabelas do banco','images'=>'Editor de imagens PNG'];
        echo '<div class="wrap pgw-admin"><h1>Diagnóstico</h1>'.($notice==='unlocked'?'<div class="notice notice-success"><p>O limite correspondente foi removido sem expor seu identificador armazenado.</p></div>':'').'<table class="widefat striped"><thead><tr><th>Verificação</th><th>Estado</th></tr></thead><tbody>';foreach($labels as $key=>$label)echo '<tr><td>'.esc_html($label).'</td><td><strong style="color:'.($checks[$key]?'#1f8236':'#c93d4c').'">'.($checks[$key]?'OK':'Atenção').'</strong></td></tr>';echo '<tr><td>Versão do banco</td><td>'.absint(get_option(self::OPTION_DB,0)).'</td></tr><tr><td>Google OIDC</td><td>'.(self::google_client_id()&&self::google_client_secret()?'Configurado':'Desativado até configurar credenciais').'</td></tr></tbody></table><h2>Desbloqueio pontual</h2><p>Informe exatamente a ação e o identificador usados pelo limite, por exemplo <code>login_email</code> e o e-mail, ou <code>group_submit</code> e <code>user_123</code>. Nenhum hash armazenado é exibido.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pgw_unlock_rate_limit">'.wp_nonce_field('pgw_unlock_rate_limit','pgw_unlock_nonce',true,false).'<p><label>Ação <input name="limit_action" maxlength="64" required></label></p><p><label>Identificador <input name="limit_subject" maxlength="200" required></label></p><button class="button button-secondary">Remover bloqueio correspondente</button></form></div>';
    }

    public static function moderation_page(): void {
        if(!current_user_can('pgw_moderate_groups'))return;$groups=get_posts(['post_type'=>self::CPT,'post_status'=>['pending','draft'],'numberposts'=>100,'orderby'=>'date','order'=>'ASC','meta_query'=>[['key'=>'_pgw_status','value'=>['pending','correction_requested'],'compare'=>'IN']]]);
        echo '<div class="wrap pgw-admin"><h1>Pendentes e Correções</h1><table class="widefat striped"><thead><tr><th>Grupo</th><th>Proprietário</th><th>Estado</th><th>Enviado</th><th>Decisão</th></tr></thead><tbody>';if(!$groups)echo '<tr><td colspan="5">Nenhum grupo aguardando moderação.</td></tr>';
        foreach($groups as $group){$owner=get_userdata((int)$group->post_author);echo '<tr><td><a href="'.esc_url(get_edit_post_link($group->ID)).'">'.esc_html($group->post_title).'</a></td><td>'.esc_html($owner?$owner->display_name:'—').'</td><td>'.esc_html(self::status($group->ID)).'</td><td>'.esc_html(get_the_date('d/m/Y H:i',$group)).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pgw_moderate_group"><input type="hidden" name="group_id" value="'.absint($group->ID).'">'.wp_nonce_field('pgw_moderate_group_'.$group->ID,'pgw_moderation_nonce',true,false).'<select name="status"><option value="approved">Aprovar</option><option value="correction_requested">Solicitar correção</option><option value="rejected">Rejeitar</option><option value="inactive">Desativar</option></select><input name="reason" maxlength="1000" placeholder="Motivo para rejeição/correção"> <button class="button button-primary">Aplicar</button></form></td></tr>';}
        echo '</tbody></table></div>';
    }

    public static function featured_page(): void {
        if(!current_user_can('pgw_manage_featured'))return;$schedule=new Featured\FeaturedSchedule();
        if($_SERVER['REQUEST_METHOD']==='POST'){check_admin_referer('pgw_featured_save');$ids=array_map('absint',(array)($_POST['pgw_featured_ids']??[]));foreach($ids as $id){if(get_post_type($id)!==self::CPT)continue;$enabled=!empty($_POST['_pgw_featured'][$id]);$priority=$schedule->priority($_POST['_pgw_featured_priority'][$id]??999);$start=sanitize_text_field(wp_unslash((string)($_POST['_pgw_featured_start'][$id]??'')));$end=sanitize_text_field(wp_unslash((string)($_POST['_pgw_featured_end'][$id]??'')));$window=$schedule->window($start?:null,$end?:null);if(!$window['valid'])continue;update_post_meta($id,'_pgw_featured',$enabled?1:0);update_post_meta($id,'_pgw_featured_priority',$priority);update_post_meta($id,'_pgw_featured_start',$start?gmdate('Y-m-d H:i:s',$window['start']):'');update_post_meta($id,'_pgw_featured_end',$end?gmdate('Y-m-d H:i:s',$window['end']):'');update_post_meta($id,'_pgw_featured_active',self::is_featured_active($id)?1:0);self::audit('featured_updated','group',$id,[],['enabled'=>$enabled,'priority'=>$priority,'start'=>$start,'end'=>$end]);}echo '<div class="notice notice-success"><p>Destaques atualizados.</p></div>';}
        $groups=get_posts(['post_type'=>self::CPT,'post_status'=>'publish','numberposts'=>100,'orderby'=>['date'=>'DESC','ID'=>'DESC'],'meta_query'=>[['key'=>'_pgw_status','value'=>'approved']]]);echo '<div class="wrap pgw-admin"><h1>Destaques</h1><p>Prioridade 1 ocupa o centro, 2 a esquerda e 3 a direita. Datas usam o fuso configurado no WordPress.</p><form method="post">';wp_nonce_field('pgw_featured_save');echo '<table class="widefat striped"><thead><tr><th>Ativo</th><th>Grupo</th><th>Prioridade</th><th>Início</th><th>Fim</th><th>Agora</th></tr></thead><tbody>';
        foreach($groups as $group){$id=(int)$group->ID;$checked=(int)get_post_meta($id,'_pgw_featured',true)===1?' checked':'';$priority=absint(get_post_meta($id,'_pgw_featured_priority',true))?:999;$start=(string)get_post_meta($id,'_pgw_featured_start',true);$end=(string)get_post_meta($id,'_pgw_featured_end',true);echo '<tr><td><input type="hidden" name="pgw_featured_ids[]" value="'.absint($id).'"><input type="checkbox" name="pgw_featured['.absint($id).']" value="1"'.$checked.'></td><td><a href="'.esc_url(get_edit_post_link($id)).'">'.esc_html($group->post_title).'</a></td><td><input type="number" min="1" max="999" name="pgw_featured_priority['.absint($id).']" value="'.absint($priority).'" class="small-text"></td><td><input type="datetime-local" name="pgw_featured_start['.absint($id).']" value="'.esc_attr($start?gmdate('Y-m-d\TH:i',strtotime($start)):'').'"></td><td><input type="datetime-local" name="pgw_featured_end['.absint($id).']" value="'.esc_attr($end?gmdate('Y-m-d\TH:i',strtotime($end)):'').'"></td><td>'.(self::is_featured_active($id)?'Ativo':'Inativo').'</td></tr>';}
        echo '</tbody></table>';submit_button('Salvar destaques');echo '</form></div>';
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
            if ($mode==='remove') { $ids=get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids','meta_key'=>'_pgw_demo','meta_value'=>1]); foreach($ids as $id){$attachments=array_unique([absint(get_post_meta($id,'_pgw_square_image_id',true)),absint(get_post_meta($id,'_pgw_hero_image_id',true))]);foreach(array_filter($attachments) as $attachment)wp_delete_attachment($attachment,true);wp_delete_post($id,true);}foreach(array_map('absint',(array)get_option('pgw_demo_page_ids',[])) as $page_id)if((int)get_post_meta($page_id,'_pgw_demo_page',true)===1)wp_delete_post($page_id,true);delete_option('pgw_demo_page_ids');self::invalidate_catalog_cache();echo '<div class="notice notice-success"><p>Conteúdo demonstrativo e páginas demo removidos; caches públicos foram limpos.</p></div>'; }
            if ($mode==='install') { self::install_demo_content();$pages=self::install_demo_pages();$generated=self::regenerate_demo_images(false);self::invalidate_catalog_cache();echo '<div class="notice notice-success"><p>Conteúdo demonstrativo instalado: 40 cards, '.absint($pages).' páginas novas e '.absint($generated).' imagens geradas. O cache público foi limpo.</p></div>'; }
            if ($mode==='images') { $generated=self::regenerate_demo_images(true);self::invalidate_catalog_cache();echo '<div class="notice notice-success"><p>Imagens demonstrativas recriadas: '.absint($generated).'. O cache público foi limpo.</p></div>'; }
            if ($mode==='sync') { self::invalidate_catalog_cache();echo '<div class="notice notice-success"><p>Frontend sincronizado. Agora salve o widget no Elementor e publique/atualize a página.</p></div>'; }
        }
        $total=count(get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids','meta_key'=>'_pgw_demo','meta_value'=>1]));$page_total=count(array_filter(array_map('absint',(array)get_option('pgw_demo_page_ids',[])),'get_post'));$public=new \WP_Query(self::public_query_args(['posts_per_page'=>1,'fields'=>'ids']));$front_id=absint(get_option('page_on_front'));$front=$front_id?get_post($front_id):null;$front_source=$front?(string)$front->post_content.' '.(string)get_post_meta($front_id,'_elementor_data',true):'';$front_has_catalog=(new Search\CatalogPagePolicy())->containsCatalog($front_source);$front_ready=$front&&$front->post_status==='publish'&&$front_has_catalog;
        echo '<div class="wrap pgw-admin"><h1>Conteúdo Demo</h1><p>Instale os 40 cards e as páginas prontas para todos os shortcodes públicos. Demos não são indexados e não abrem links externos.</p><p><strong>Instalados:</strong> '.absint($total).' cards e '.absint($page_total).' páginas demo. <strong>Disponíveis para o shortcode:</strong> '.absint($public->found_posts).'.</p><div class="notice '.($front_ready?'notice-success':'notice-warning').' inline"><p><strong>Homepage:</strong> '.($front?esc_html($front->post_title).' — '.esc_html($front->post_status):'não definida como página estática').' · shortcode salvo: '.($front_has_catalog?'sim':'não').'.'.(!$front_ready?' No Elementor, clique primeiro em <strong>Aplicar</strong> no widget e depois em <strong>Publicar/Atualizar</strong> no topo.':' Pronta para o frontend.').'</p></div><p><a class="button" href="'.esc_url(home_url('/grupos/')).'" target="_blank" rel="noopener">Abrir catálogo público</a></p><form method="post">';wp_nonce_field('pgw_demo_content');echo '<button class="button button-primary" name="pgw_demo_mode" value="install">Importar 40 cards e páginas</button> <button class="button" name="pgw_demo_mode" value="images">Recriar imagens</button> <button class="button" name="pgw_demo_mode" value="sync">Sincronizar frontend</button> <button class="button" name="pgw_demo_mode" value="remove">Remover conteúdo demo</button></form></div>';
    }

    private static function install_demo_content(): void {
        $items = require __DIR__ . '/data/demo-groups.php';
        foreach ($items as $item) {
            $existing=get_page_by_title($item['title'],OBJECT,self::CPT);if($existing&&(int)get_post_meta($existing->ID,'_pgw_demo',true)!==1)continue;$id=$existing?$existing->ID:wp_insert_post(['post_type'=>self::CPT,'post_status'=>'publish','post_title'=>$item['title'],'post_content'=>$item['description']]);if(is_wp_error($id))continue;
            $term = get_term_by('name', $item['category'], self::CAT);
            if ($term) wp_set_object_terms($id, [(int) $term->term_id], self::CAT);
            $type = get_term_by('name', 'Grupo', self::TYPE);
            if ($type) wp_set_object_terms($id, [(int) $type->term_id], self::TYPE);
            update_post_meta($id, '_pgw_demo', 1);
            update_post_meta($id, '_pgw_status', 'approved');
            update_post_meta($id, '_pgw_link_status', 'not_confirmed');
            $position=array_search($item,$items,true);if($position!==false&&$position<3){update_post_meta($id,'_pgw_featured',1);update_post_meta($id,'_pgw_featured_priority',$position+1);}
        }
    }
    private static function invalidate_catalog_cache(): void {
        update_option('pgw_catalog_revision',(string)microtime(true),false);wp_cache_flush();if(class_exists('Elementor\\Plugin')&&isset(\Elementor\Plugin::$instance->files_manager))\Elementor\Plugin::$instance->files_manager->clear_cache();if(function_exists('rocket_clean_domain'))rocket_clean_domain();if(function_exists('w3tc_flush_all'))w3tc_flush_all();if(function_exists('wp_cache_clear_cache'))wp_cache_clear_cache();if(function_exists('wpfc_clear_all_cache'))wpfc_clear_all_cache();if(function_exists('sg_cachepress_purge_cache'))sg_cachepress_purge_cache();do_action('litespeed_purge_all');do_action('pgw_catalog_cache_cleared');
    }
    private static function install_demo_pages(): int {
        $created=0;$stored=(array)get_option('pgw_demo_page_ids',[]);foreach((new Activation\PageBlueprints())->all() as $slug=>$page){$existing=get_page_by_path($slug,OBJECT,'page');if($existing)continue;$id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_name'=>$slug,'post_title'=>$page['title'],'post_content'=>$page['content'],'comment_status'=>'closed'],true);if(is_wp_error($id))continue;update_post_meta($id,'_pgw_demo_page',1);$stored[$slug]=(int)$id;$created++;self::audit('demo_page_created','page',(int)$id,[],['slug'=>$slug]);}update_option('pgw_demo_page_ids',$stored,false);flush_rewrite_rules(false);return $created;
    }
    private static function demo_image_data(string $seed,int $width,int $height): string {
        if(!function_exists('imagecreatetruecolor'))return '';$image=imagecreatetruecolor($width,$height);$colors=(new Demo\ArtworkPalette())->for($seed);$allocate=static function($image,string $hex): int { return imagecolorallocate($image,hexdec(substr($hex,1,2)),hexdec(substr($hex,3,2)),hexdec(substr($hex,5,2)));};$background=$allocate($image,$colors[0]);$accent=$allocate($image,$colors[1]);$deep=$allocate($image,$colors[2]);$white=imagecolorallocatealpha($image,255,255,255,70);imagefilledrectangle($image,0,0,$width,$height,$background);$unit=min($width,$height);imagefilledellipse($image,(int)($width*.22),(int)($height*.18),(int)($unit*.72),(int)($unit*.72),$accent);imagefilledellipse($image,(int)($width*.82),(int)($height*.76),(int)($unit*.92),(int)($unit*.92),$deep);imagefilledellipse($image,(int)($width*.58),(int)($height*.42),(int)($unit*.42),(int)($unit*.42),$white);ob_start();imagepng($image,null,8);$data=(string)ob_get_clean();imagedestroy($image);return $data;
    }

    private static function create_demo_attachment(int $group_id,string $kind,int $width,int $height,string $seed): int {
        $data=self::demo_image_data($seed.'|'.$kind,$width,$height);if($data==='')return 0;$upload=wp_upload_bits('pgw-demo-'.$group_id.'-'.$kind.'.png',null,$data);if(!empty($upload['error']))return 0;require_once ABSPATH.'wp-admin/includes/image.php';$attachment=wp_insert_attachment(['post_mime_type'=>'image/png','post_title'=>'Arte demonstrativa '.$kind,'post_status'=>'inherit'],$upload['file'],$group_id,true);if(is_wp_error($attachment)){wp_delete_file($upload['file']);return 0;}$metadata=wp_generate_attachment_metadata($attachment,$upload['file']);if($metadata)wp_update_attachment_metadata($attachment,$metadata);return (int)$attachment;
    }

    private static function regenerate_demo_images(bool $replace): int {
        $ids=get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>-1,'fields'=>'ids','meta_key'=>'_pgw_demo','meta_value'=>1]);$generated=0;foreach($ids as $id){$square=absint(get_post_meta($id,'_pgw_square_image_id',true));$hero=absint(get_post_meta($id,'_pgw_hero_image_id',true));if($replace){if($square)wp_delete_attachment($square,true);if($hero&&$hero!==$square)wp_delete_attachment($hero,true);delete_post_thumbnail($id);delete_post_meta($id,'_pgw_square_image_id');delete_post_meta($id,'_pgw_hero_image_id');delete_post_meta($id,'_pgw_original_image_id');$square=$hero=0;}if(!$square){$square=self::create_demo_attachment((int)$id,'square',800,800,get_the_title($id));if($square){update_post_meta($id,'_pgw_square_image_id',$square);update_post_meta($id,'_pgw_original_image_id',$square);set_post_thumbnail($id,$square);$generated++;}}if(!$hero){$hero=self::create_demo_attachment((int)$id,'hero',1000,500,get_the_title($id));if($hero){update_post_meta($id,'_pgw_hero_image_id',$hero);$generated++;}}}return $generated;
    }

    public static function settings(): void { register_setting('pgw_settings','pgw_logo_url',['sanitize_callback'=>'esc_url_raw']);foreach(['pgw_initial_limit','pgw_load_amount','pgw_card_title_limit','pgw_card_description_limit','pgw_max_image_bytes'] as $key)register_setting('pgw_settings',$key,['sanitize_callback'=>'absint']);register_setting('pgw_settings','pgw_google_client_id',['sanitize_callback'=>'sanitize_text_field']);register_setting('pgw_settings','pgw_google_client_secret',['sanitize_callback'=>[self::class,'sanitize_google_secret']]);add_settings_section('pgw_main','Catálogo e aparência','__return_false','pgw-settings');foreach(['pgw_logo_url'=>'URL do logo','pgw_initial_limit'=>'Limite inicial','pgw_load_amount'=>'Quantidade ao carregar','pgw_card_title_limit'=>'Limite do título','pgw_card_description_limit'=>'Limite da descrição','pgw_max_image_bytes'=>'Limite de imagem (bytes)','pgw_google_client_id'=>'Google Client ID','pgw_google_client_secret'=>'Google Client Secret'] as $key=>$label)add_settings_field($key,$label,[self::class,'field'],'pgw-settings','pgw_main',['key'=>$key]); }
    public static function sanitize_google_secret(mixed $value): string { $value=trim(sanitize_text_field((string)$value));return $value!==''?$value:(string)get_option('pgw_google_client_secret',''); }
    public static function field(array $args): void { $key=$args['key'];if($key==='pgw_google_client_secret'){echo '<input type="password" class="regular-text" autocomplete="new-password" name="pgw_google_client_secret" value="" placeholder="'.(get_option($key)?'Configurado — deixe vazio para manter':'Informe o secret').'">';return;}echo '<input class="regular-text" name="'.esc_attr($key).'" value="'.esc_attr((string)get_option($key)).'">'; }
    public static function dashboard(): void { if(!current_user_can('pgw_manage_groups'))return;$post_counts=wp_count_posts(self::CPT);global $wpdb;$new_reports=absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}pgw_reports WHERE status = 'new'"));$metrics=[['Aprovados',absint($post_counts->publish??0),'Conteúdo público'],['Pendentes',absint($post_counts->pending??0),'Aguardando análise'],['Rascunhos',absint($post_counts->draft??0),'Em preparação'],['Denúncias',$new_reports,'Novas ocorrências']];$modules=[['Todos os grupos','Cadastre, edite e acompanhe todo o diretório.','edit.php?post_type='.self::CPT,'Gerenciar'],['Moderação','Analise pendências, correções e segurança.','admin.php?page=pgw-moderation','Revisar'],['Destaques','Organize o showcase e suas prioridades.','admin.php?page=pgw-featured','Configurar'],['Categorias','Estruture a descoberta do catálogo.','edit-tags.php?taxonomy='.self::CAT.'&post_type='.self::CPT,'Organizar'],['Denúncias','Investigue ocorrências enviadas pelo público.','admin.php?page=pgw-reports','Analisar'],['Métricas','Acompanhe cliques e grupos mais acessados.','admin.php?page=pgw-metrics','Visualizar'],['Conteúdo demo','Importe cards, páginas e imagens de avaliação.','admin.php?page=pgw-demo','Importar'],['Diagnóstico','Valide requisitos, cron, banco e integrações.','admin.php?page=pgw-diagnostics','Verificar'],['Configurações','Ajuste identidade, autenticação e integrações.','admin.php?page=pgw-settings','Personalizar']];echo '<div class="wrap pgw-admin"><section class="pgw-admin-hero"><div><span class="pgw-admin-eyebrow">Portal Grupos WhatsApp</span><h1>Seu diretório, sob controle.</h1><p>Moderação, crescimento e segurança em uma experiência operacional premium.</p></div><a class="pgw-admin-primary" href="'.esc_url(admin_url('post-new.php?post_type='.self::CPT)).'">Adicionar grupo</a></section><section class="pgw-admin-metrics" aria-label="Resumo">';foreach($metrics as [$label,$value,$description])echo '<article class="pgw-admin-metric"><span>'.esc_html($label).'</span><strong>'.absint($value).'</strong><small>'.esc_html($description).'</small></article>';echo '</section><div class="pgw-admin-section-head"><div><span class="pgw-admin-eyebrow">Workspace</span><h2>Módulos do plugin</h2></div></div><section class="pgw-admin-modules">';foreach($modules as [$title,$description,$path,$action])echo '<article class="pgw-admin-module"><span class="pgw-admin-module-icon" aria-hidden="true"></span><h3>'.esc_html($title).'</h3><p>'.esc_html($description).'</p><a href="'.esc_url(admin_url($path)).'">'.esc_html($action).' <span aria-hidden="true">→</span></a></article>';echo '</section></div>'; }
    public static function settings_page(): void { if(!current_user_can('pgw_manage_settings'))return;echo '<div class="wrap"><h1>Configurações do Portal</h1><form method="post" action="options.php">';settings_fields('pgw_settings');do_settings_sections('pgw-settings');submit_button();echo '</form></div>'; }
    public static function reports_page(): void {
        if(!current_user_can('pgw_manage_reports'))return;
        global $wpdb;$table=$wpdb->prefix.'pgw_reports';$status=(new Reports\ReportPolicy())->normalizeStatus(sanitize_key((string)($_GET['report_status']??'open')));
        $rows=$wpdb->get_results($wpdb->prepare("SELECT id,group_id,reason,details,status,created_at FROM {$table} WHERE status=%s ORDER BY created_at DESC,id DESC LIMIT 100",$status));
        echo '<div class="wrap pgw-admin"><h1>Denúncias</h1><nav class="nav-tab-wrapper">';foreach(Reports\ReportPolicy::STATUSES as $filter)echo '<a class="nav-tab '.($status===$filter?'nav-tab-active':'').'" href="'.esc_url(add_query_arg(['page'=>'pgw-reports','report_status'=>$filter],admin_url('admin.php'))).'">'.esc_html($filter).'</a>';echo '</nav><table class="widefat striped"><thead><tr><th>Grupo</th><th>Motivo e detalhes</th><th>Status</th><th>Data</th><th>Ações</th></tr></thead><tbody>';
        if(!$rows)echo '<tr><td colspan="5">Nenhuma denúncia neste estado.</td></tr>';
        foreach($rows as $row){$group=get_post((int)$row->group_id);echo '<tr><td>'.($group?'<a href="'.esc_url(get_edit_post_link($group->ID)).'">'.esc_html($group->post_title).'</a>':'#'.absint($row->group_id)).'</td><td><strong>'.esc_html($row->reason).'</strong>'.($row->details?'<br>'.esc_html($row->details):'').'</td><td>'.esc_html($row->status).'</td><td>'.esc_html($row->created_at).'</td><td><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="pgw_resolve_report"><input type="hidden" name="report_id" value="'.absint($row->id).'">'.wp_nonce_field('pgw_resolve_report_'.$row->id,'pgw_report_nonce',true,false).'<select name="report_status"><option value="in_review">Em análise</option><option value="resolved">Resolvida</option><option value="improper">Improcedente</option></select> <button class="button">Atualizar</button></form></td></tr>';}
        echo '</tbody></table></div>';
    }
    public static function handle_resolve_report(): void {
        if(!current_user_can('pgw_manage_reports'))wp_die('Sem permissão.');$id=absint($_POST['report_id']??0);check_admin_referer('pgw_resolve_report_'.$id,'pgw_report_nonce');$policy=new Reports\ReportPolicy();$status=$policy->normalizeStatus(sanitize_key((string)($_POST['report_status']??'')));if(!$id||!in_array($status,['in_review','resolved','improper'],true))wp_die('Dados inválidos.');global $wpdb;$table=$wpdb->prefix.'pgw_reports';$before=$wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE id=%d",$id));if($before===null)wp_die('Denúncia não encontrada.');$wpdb->update($table,['status'=>$status,'updated_at'=>current_time('mysql',true)],['id'=>$id],['%s','%s'],['%d']);self::audit('report_status_changed','report',$id,['status'=>$before],['status'=>$status]);wp_safe_redirect(add_query_arg(['page'=>'pgw-reports','report_status'=>$status],admin_url('admin.php')));exit;
    }
    public static function handle_submit(): void { auth_redirect(); }
    public static function handle_profile(): void { auth_redirect(); }
    public static function handle_email_change(): void {
        auth_redirect(); check_admin_referer('pgw_update_email','pgw_email_nonce'); $user=wp_get_current_user(); $candidate=sanitize_email(wp_unslash((string)($_POST['new_email']??''))); $existing=$candidate?email_exists($candidate):false;
        $request=(new Accounts\EmailChangePolicy())->request($candidate,(string)$user->user_email,$existing?(int)$existing:null,(int)$user->ID);
        if(!$request['valid']||!self::rate_limit('email_change','user_'.$user->ID,3,DAY_IN_SECONDS)){wp_safe_redirect(add_query_arg('pgw_error','email',home_url('/minha-conta/')));exit;}
        update_user_meta($user->ID,'pgw_pending_email',$request['email']); update_user_meta($user->ID,'pgw_pending_email_requested_at',time());
        if(!self::send_otp((int)$user->ID,'email_change',$request['email'])){delete_user_meta($user->ID,'pgw_pending_email');delete_user_meta($user->ID,'pgw_pending_email_requested_at');wp_safe_redirect(add_query_arg('pgw_error','otp',home_url('/minha-conta/')));exit;}
        self::audit('email_change_requested','user',$user->ID,[],['email_hash'=>hash_hmac('sha256',$request['email'],wp_salt('auth'))]); wp_safe_redirect(self::auth_flow_url((int)$user->ID,'email_change'));exit;
    }

    public static function handle_password_change(): void { auth_redirect(); check_admin_referer('pgw_update_password','pgw_security_nonce'); $user=wp_get_current_user();$current=(string)wp_unslash($_POST['current_password']??'');$providers=(new Auth\ProviderPolicy())->normalize(get_user_meta($user->ID,'pgw_auth_providers',true));$google_only=$providers===['google'];if((!$google_only&&!wp_check_password($current,$user->user_pass,$user->ID))||!self::rate_limit('password_change','user_'.$user->ID,5,HOUR_IN_SECONDS)){wp_safe_redirect(add_query_arg('pgw_error','password',home_url('/minha-conta/')));exit;}if(!self::send_otp($user->ID,'password_change')){wp_safe_redirect(add_query_arg('pgw_error','otp',home_url('/minha-conta/')));exit;}self::audit('password_change_requested','user',$user->ID);wp_safe_redirect(self::auth_flow_url((int)$user->ID,'password_change'));exit; }
    public static function handle_complete_password_change(): void { auth_redirect();check_admin_referer('pgw_complete_password_change','pgw_security_nonce');$user=wp_get_current_user();$authorization=sanitize_text_field(wp_unslash((string)($_POST['authorization']??'')));$expected=(string)get_user_meta($user->ID,'pgw_password_authorization_hash',true);$expires=(int)get_user_meta($user->ID,'pgw_password_authorization_expires',true);$new=(string)wp_unslash($_POST['new_password']??'');$confirm=(string)wp_unslash($_POST['confirm_password']??'');$valid=preg_match('/^[a-f0-9]{64}$/D',$authorization)&&$expected&&hash_equals($expected,hash_hmac('sha256',$authorization,wp_salt('auth')))&&$expires>=time()&&(new Auth\PasswordPolicy())->accepts($new,$confirm,10);delete_user_meta($user->ID,'pgw_password_authorization_hash');delete_user_meta($user->ID,'pgw_password_authorization_expires');if(!$valid){wp_safe_redirect(add_query_arg('pgw_error','password_authorization',home_url('/minha-conta/')));exit;}wp_set_password($new,$user->ID);$providers=(new Auth\ProviderPolicy())->normalize(get_user_meta($user->ID,'pgw_auth_providers',true));if(!in_array('email',$providers,true))$providers[]='email';update_user_meta($user->ID,'pgw_auth_providers',(new Auth\ProviderPolicy())->normalize($providers));\WP_Session_Tokens::get_instance($user->ID)->destroy_all();wp_set_auth_cookie($user->ID,false,is_ssl());self::audit('password_changed','user',$user->ID);wp_mail($user->user_email,'Senha alterada — Portal Grupos WhatsApp','Sua senha foi alterada. Se não foi você, contate o suporte imediatamente.');wp_safe_redirect(add_query_arg('pgw_notice','password_changed',home_url('/minha-conta/')));exit; }
    public static function handle_logout_other_sessions(): void { auth_redirect();check_admin_referer('pgw_logout_other_sessions','pgw_sessions_nonce');$user_id=get_current_user_id();if(!self::rate_limit('sessions','user_'.$user_id,10,HOUR_IN_SECONDS)){wp_safe_redirect(add_query_arg('pgw_error','sessions',home_url('/minha-conta/')));exit;}\WP_Session_Tokens::get_instance($user_id)->destroy_others(wp_get_session_token());self::audit('other_sessions_revoked','user',$user_id);wp_safe_redirect(add_query_arg('pgw_notice','sessions_revoked',home_url('/minha-conta/')));exit; }
    public static function handle_logout_all_sessions(): void { auth_redirect();check_admin_referer('pgw_logout_all_sessions','pgw_sessions_nonce');$user_id=get_current_user_id();\WP_Session_Tokens::get_instance($user_id)->destroy_all();self::audit('all_sessions_revoked','user',$user_id);wp_clear_auth_cookie();wp_safe_redirect(home_url('/entrar/'));exit; }
    private static function audit(string $action,string $object_type,int $object_id,array $before=[],array $after=[]): void { global $wpdb;$wpdb->insert($wpdb->prefix.'pgw_audit_log',['actor_user_id'=>get_current_user_id()?:null,'action'=>sanitize_key($action),'object_type'=>sanitize_key($object_type),'object_id'=>$object_id,'before_data'=>wp_json_encode($before),'after_data'=>wp_json_encode($after),'created_at'=>current_time('mysql',true),'ip_hash'=>self::request_ip_hash()]); }
    public static function handle_delete_group(): void { auth_redirect();check_admin_referer('pgw_delete_group');$id=absint($_POST['group_id']??0);$post=get_post($id);if(!$post||$post->post_type!==self::CPT||((int)$post->post_author!==get_current_user_id()&&!current_user_can('pgw_manage_groups')))wp_die('Sem permissão.');self::audit('group_deleted','group',$id,['status'=>self::status($id)],[]);wp_trash_post($id);wp_safe_redirect(home_url('/meus-grupos/'));exit; }
    public static function handle_update_group(): void { auth_redirect();$id=absint($_POST['group_id']??0);check_admin_referer('pgw_update_group_'.$id,'pgw_update_nonce');$post=get_post($id);if(!$post||$post->post_type!==self::CPT||(int)$post->post_author!==get_current_user_id())wp_die('Sem permissão.');if(!self::rate_limit('group_update','user_'.get_current_user_id(),20,DAY_IN_SECONDS))wp_die('Limite de alterações atingido.');$policy=new Groups\SubmissionPolicy();$data=$policy->normalize(sanitize_text_field(wp_unslash((string)($_POST['pgw_title']??''))),sanitize_textarea_field(wp_unslash((string)($_POST['pgw_description']??''))),sanitize_textarea_field(wp_unslash((string)($_POST['pgw_rules']??''))));$url=self::sanitize_invite(wp_unslash((string)($_POST['pgw_url']??'')));$category=absint($_POST['pgw_category']??0);$type=absint($_POST['pgw_type']??0);if(!$data['valid']||!$url||!term_exists($category,self::CAT)||!term_exists($type,self::TYPE))wp_die('Dados inválidos.');$hash=$policy->inviteHash($url,wp_salt('auth'));$duplicate=get_posts(['post_type'=>self::CPT,'post_status'=>'any','numberposts'=>1,'fields'=>'ids','post__not_in'=>[$id],'meta_key'=>'_pgw_invite_hash','meta_value'=>$hash]);if($duplicate)wp_die('Este convite já pertence a outro cadastro.');$before=['status'=>self::status($id),'title'=>$post->post_title,'invite_hash'=>(string)get_post_meta($id,'_pgw_invite_hash',true)];wp_update_post(['ID'=>$id,'post_title'=>$data['title'],'post_content'=>$data['description'],'post_status'=>'pending']);update_post_meta($id,'_pgw_invite_url',$url);update_post_meta($id,'_pgw_invite_hash',$hash);update_post_meta($id,'_pgw_rules',$data['rules']);update_post_meta($id,'_pgw_previous_status',$before['status']);update_post_meta($id,'_pgw_status','pending');wp_set_object_terms($id,[$category],self::CAT);wp_set_object_terms($id,[$type],self::TYPE);delete_post_meta($id,'_pgw_rejection_reason');delete_post_meta($id,'_pgw_correction_request');self::attach_group_image('pgw_group_image',$id);self::audit('group_resubmitted','group',$id,$before,['status'=>'pending','title'=>$data['title'],'invite_hash'=>$hash]);wp_safe_redirect(add_query_arg('pgw_notice','group_resubmitted',home_url('/meus-grupos/')));exit; }
    public static function handle_moderation(): void { if(!current_user_can('pgw_moderate_groups'))wp_die('Sem permissão.');$id=absint($_POST['group_id']??0);check_admin_referer('pgw_moderate_group_'.$id,'pgw_moderation_nonce');$post=get_post($id);if(!$post||$post->post_type!==self::CPT)wp_die('Grupo inválido.');$policy=new Moderation\ModerationPolicy();$transition=$policy->transition(sanitize_key((string)($_POST['status']??'')),sanitize_textarea_field(wp_unslash((string)($_POST['reason']??''))));if(!$transition['valid'])wp_die('Informe uma decisão válida e o motivo quando obrigatório.');$before=['status'=>self::status($id),'post_status'=>$post->post_status];update_post_meta($id,'_pgw_previous_status',$before['status']);update_post_meta($id,'_pgw_status',$transition['status']);if($transition['status']==='rejected')update_post_meta($id,'_pgw_rejection_reason',$transition['reason']);else delete_post_meta($id,'_pgw_rejection_reason');if($transition['status']==='correction_requested')update_post_meta($id,'_pgw_correction_request',$transition['reason']);else delete_post_meta($id,'_pgw_correction_request');if($transition['status']==='approved')update_post_meta($id,'_pgw_last_approved_at',current_time('mysql',true));wp_update_post(['ID'=>$id,'post_status'=>$transition['post_status']]);self::audit('group_moderated','group',$id,$before,['status'=>$transition['status'],'post_status'=>$transition['post_status'],'reason'=>$transition['reason']]);self::invalidate_catalog_cache();$owner=get_userdata((int)$post->post_author);if($owner)wp_mail($owner->user_email,'Atualização da moderação — Portal Grupos WhatsApp','O grupo "'.$post->post_title.'" recebeu o estado: '.$transition['status'].($transition['reason']?' — '.$transition['reason']:''));wp_safe_redirect(admin_url('admin.php?page=pgw-moderation'));exit; }
    public static function google_begin(): void {
        if (!self::google_client_id() || !self::google_client_secret()) wp_die('Login Google não configurado.');
        $link_user_id = 0;
        if (sanitize_key((string) ($_REQUEST['action'] ?? '')) === 'pgw_google_link_begin') {
            auth_redirect();
            check_admin_referer('pgw_google_link');
            $link_user_id = get_current_user_id();
        }
        if (!self::rate_limit('google_begin', 'ip_'.self::request_ip_hash(), 20, HOUR_IN_SECONDS)) wp_die('Tente novamente mais tarde.');
        $state = bin2hex(random_bytes(24)); $nonce = bin2hex(random_bytes(24)); $verifier = self::base64url(random_bytes(48));
        set_transient('pgw_google_'.hash('sha256', $state), ['nonce'=>$nonce, 'verifier'=>$verifier, 'link_user_id'=>$link_user_id], 10*MINUTE_IN_SECONDS);
        $url = add_query_arg(['client_id'=>self::google_client_id(), 'redirect_uri'=>self::google_redirect_uri(), 'response_type'=>'code', 'scope'=>'openid email profile', 'state'=>$state, 'nonce'=>$nonce, 'code_challenge'=>self::base64url(hash('sha256', $verifier, true)), 'code_challenge_method'=>'S256', 'prompt'=>'select_account'], 'https://accounts.google.com/o/oauth2/v2/auth');
        wp_redirect(esc_url_raw($url)); exit;
    }

    public static function google_callback(): void {
        $state=sanitize_text_field(wp_unslash((string)($_GET['state']??''))); $code=sanitize_text_field(wp_unslash((string)($_GET['code']??'')));
        if(!preg_match('/^[a-f0-9]{48}$/D',$state)||!$code) wp_die('Solicitação Google inválida.');
        $key='pgw_google_'.hash('sha256',$state); $context=get_transient($key); delete_transient($key);
        if(!is_array($context)||empty($context['nonce'])||empty($context['verifier'])) wp_die('Solicitação Google expirada.');
        $response=wp_safe_remote_post('https://oauth2.googleapis.com/token',['timeout'=>10,'body'=>['code'=>$code,'client_id'=>self::google_client_id(),'client_secret'=>self::google_client_secret(),'redirect_uri'=>self::google_redirect_uri(),'grant_type'=>'authorization_code','code_verifier'=>$context['verifier']]]);
        if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200) wp_die('Não foi possível concluir o login Google.');
        $tokens=json_decode(wp_remote_retrieve_body($response),true); $id_token=is_array($tokens)?(string)($tokens['id_token']??''):'';
        if(!$id_token) wp_die('Resposta Google inválida.');
        $verified=wp_safe_remote_get('https://oauth2.googleapis.com/tokeninfo?id_token='.rawurlencode($id_token),['timeout'=>10]); $claims=!is_wp_error($verified)?json_decode(wp_remote_retrieve_body($verified),true):null;
        if(!is_array($claims)||!(new Auth\GoogleClaims())->validate($claims,self::google_client_id(),(string)$context['nonce'])) wp_die('Identidade Google inválida.');
        $sub=(string)$claims['sub']; $email=sanitize_email((string)$claims['email']); $linked=get_users(['meta_key'=>'pgw_google_sub','meta_value'=>$sub,'number'=>1]); $purpose='google_login';
        $link_user_id=(int)($context['link_user_id']??0);
        if($link_user_id){
            if(get_current_user_id()!==$link_user_id) wp_die('A sessão usada para vincular o Google expirou.');
            if($linked&&(int)$linked[0]->ID!==$link_user_id) wp_die('Esta conta Google já está vinculada a outro usuário.');
            $user=get_userdata($link_user_id); update_user_meta($link_user_id,'pgw_pending_google_sub',$sub); update_user_meta($link_user_id,'pgw_pending_google_email',$email); $purpose='google_link';
        } elseif($linked) $user=$linked[0];
        else {
            $user=get_user_by('email',$email);
            if($user){ update_user_meta($user->ID,'pgw_pending_google_sub',$sub); update_user_meta($user->ID,'pgw_pending_google_email',$email); $purpose='google_link'; }
            else { $base=sanitize_user(strstr($email,'@',true),true)?:'pgw_google'; $login=$base; $i=1; while(username_exists($login))$login=$base.$i++; $id=wp_insert_user(['user_login'=>$login,'user_email'=>$email,'user_pass'=>wp_generate_password(32,true,true),'display_name'=>sanitize_text_field((string)($claims['name']??$base)),'role'=>'pgw_pending_member']); if(is_wp_error($id))wp_die('Não foi possível criar a conta.'); $user=get_userdata($id); update_user_meta($id,'pgw_google_sub',$sub); update_user_meta($id,'pgw_google_email',$email); update_user_meta($id,'pgw_google_linked_at',current_time('mysql',true)); update_user_meta($id,'pgw_auth_providers',['google']); update_user_meta($id,'pgw_account_status','pending_google'); }
        }
        if(!$user||(string)get_user_meta($user->ID,'pgw_account_status',true)==='suspended') wp_die('Não foi possível concluir o acesso.');
        $picture=(new Images\GooglePicturePolicy())->normalize((string)($claims['picture']??'')); if($picture)update_user_meta($user->ID,'pgw_pending_google_picture',$picture);
        if(!self::send_otp((int)$user->ID,$purpose)) wp_die('Não foi possível enviar o código de segurança.');
        update_user_meta($user->ID,'pgw_pending_login',1); wp_safe_redirect(self::auth_flow_url((int)$user->ID,$purpose)); exit;
    }

    public static function google_unlink_request(): void {
        auth_redirect(); check_admin_referer('pgw_google_unlink','pgw_google_nonce'); $user=wp_get_current_user();
        $providers=(new Auth\ProviderPolicy())->normalize(get_user_meta($user->ID,'pgw_auth_providers',true));
        if(!(new Auth\ProviderPolicy())->canUnlink($providers,'google')||!self::rate_limit('google_unlink','user_'.$user->ID,3,HOUR_IN_SECONDS)||!self::send_otp($user->ID,'google_unlink')) { wp_safe_redirect(add_query_arg('pgw_error','google_unlink',home_url('/minha-conta/'))); exit; }
        self::audit('google_unlink_requested','user',$user->ID); wp_safe_redirect(self::auth_flow_url((int)$user->ID,'google_unlink')); exit;
    }

    public static function handle_logout(): void { check_admin_referer('pgw_logout'); wp_logout(); wp_safe_redirect(home_url('/')); exit; }
    public static function require_login(): void { auth_redirect(); }

    public static function ajax_resend_otp(): void {
        check_ajax_referer('pgw_resend_otp', 'nonce');
        $allowed = ['login', 'registration', 'google_login', 'google_link', 'google_unlink', 'email_change', 'password_change', 'account_deletion'];
        $flow = sanitize_text_field(wp_unslash((string) ($_POST['flow'] ?? '')));
        $context = (new Auth\FlowToken(wp_salt('auth')))->verify($flow, $allowed);
        $user_id = (int) ($context['user_id'] ?? 0); $purpose = (string) ($context['purpose'] ?? '');
        if (!$user_id || !get_userdata($user_id)) wp_send_json_error(['message' => 'Solicitação inválida.'], 400);
        $current = get_current_user_id();
        if (in_array($purpose, ['google_unlink', 'email_change', 'password_change', 'account_deletion'], true) && $current !== $user_id) wp_send_json_error(['message' => 'Solicitação inválida.'], 403);
        if ($purpose === 'login' && !get_user_meta($user_id, 'pgw_pending_login', true)) wp_send_json_error(['message' => 'Solicitação inválida.'], 403);
        if ($purpose === 'registration' && (string) get_user_meta($user_id, 'pgw_account_status', true) !== 'pending_email') wp_send_json_error(['message' => 'Solicitação inválida.'], 403);
        if ($purpose === 'email_change' && !(new Accounts\EmailChangePolicy())->fresh((int)get_user_meta($user_id,'pgw_pending_email_requested_at',true),time())) wp_send_json_error(['message'=>'A solicitação expirou. Inicie uma nova troca de e-mail.'],410);
        $recipient=$purpose==='email_change'?sanitize_email((string)get_user_meta($user_id,'pgw_pending_email',true)):null;
        if (self::send_otp($user_id, $purpose, $recipient)) wp_send_json_success(['message' => 'Um novo código foi enviado.']);
        wp_send_json_error(['message' => 'Aguarde antes de solicitar outro código.'], 429);
    }

    public static function ajax_groups(): void { check_ajax_referer('pgw_load_groups','nonce');
        $subject = is_user_logged_in() ? 'user_' . get_current_user_id() : 'ip_' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (!self::rate_limit('load_groups', $subject, 120, HOUR_IN_SECONDS)) wp_send_json_error(['message'=>'Muitas solicitações.'],429);
        $window=(new Search\PaginationWindow())->fromInput($_POST['offset']??0,$_POST['amount']??15);$input=wp_unslash($_POST);$input['pgw_q']=$input['search']??'';$q=new \WP_Query(self::catalog_query_args($input,['posts_per_page'=>$window['amount'],'offset'=>$window['offset']]));$html='';while($q->have_posts()){$q->the_post();$id=get_the_ID();if(get_post_type($id)!==self::CPT)continue;$html.=self::card($id,false);}$returned=(int)$q->post_count;$has_more=(new Search\PaginationWindow())->hasMore($window['offset'],$returned,(int)$q->found_posts);wp_reset_postdata();wp_send_json_success(['html'=>$html,'has_more'=>$has_more,'next_offset'=>$window['offset']+$returned]); }
    public static function ajax_report(): void { check_ajax_referer('pgw_report','nonce');
        $subject = is_user_logged_in() ? 'user_' . get_current_user_id() : 'ip_' . hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (!self::rate_limit('report', $subject, 5, DAY_IN_SECONDS)) wp_send_json_error(['message'=>'Limite de denúncias atingido.'],429);
        $id=absint($_POST['group_id']??0);if(!self::verify_form_proof('report',2))wp_send_json_error(['message'=>'Solicitação inválida.'],400);if(!$id||get_post_type($id)!==self::CPT||get_post_status($id)!=='publish'||self::status($id)!=='approved')wp_send_json_error(['message'=>'Grupo inválido'],400);$policy=new Reports\ReportPolicy();$reason=$policy->normalizeReason(sanitize_key(wp_unslash((string)($_POST['reason']??'other'))));$details=$policy->normalizeDetails(sanitize_textarea_field(wp_unslash((string)($_POST['details']??''))));global $wpdb;$table=$wpdb->prefix.'pgw_reports';$inserted=$wpdb->insert($table,['group_id'=>$id,'reporter_user_id'=>get_current_user_id()?:null,'reason'=>$reason,'details'=>$details,'status'=>'open','created_at'=>current_time('mysql',true),'updated_at'=>current_time('mysql',true)],['%d','%d','%s','%s','%s','%s']);if($inserted===false)wp_send_json_error(['message'=>'Não foi possível registrar agora.'],500);self::audit('report_created','group',$id,[],['reason'=>$reason]);wp_send_json_success(['message'=>'Denúncia registrada.']); }
    public static function handle_account_export(): void {
        auth_redirect(); check_admin_referer('pgw_export_account','pgw_export_nonce'); $user=wp_get_current_user();
        if(!self::rate_limit('account_export','user_'.$user->ID,3,HOUR_IN_SECONDS))wp_die('Aguarde antes de gerar uma nova exportação.');
        $keys=['first_name','last_name','description','pgw_phone','pgw_email_verified','pgw_auth_providers','pgw_google_sub','pgw_google_email','pgw_registered_at','pgw_last_login_at','pgw_terms_accepted_at','pgw_privacy_accepted_at'];$meta=[];foreach($keys as $key)$meta[$key]=get_user_meta($user->ID,$key,true);
        $groups=[];$posts=get_posts(['post_type'=>self::CPT,'post_status'=>'any','author'=>$user->ID,'numberposts'=>-1,'orderby'=>'ID','order'=>'ASC']);foreach($posts as $post){$categories=wp_get_object_terms($post->ID,self::CAT,['fields'=>'names']);$types=wp_get_object_terms($post->ID,self::TYPE,['fields'=>'names']);$groups[]=['id'=>(int)$post->ID,'title'=>$post->post_title,'description'=>$post->post_content,'status'=>self::status((int)$post->ID),'invite_url'=>(string)get_post_meta($post->ID,'_pgw_invite_url',true),'rules'=>(string)get_post_meta($post->ID,'_pgw_rules',true),'created_at'=>get_post_time(DATE_ATOM,true,$post),'updated_at'=>get_post_modified_time(DATE_ATOM,true,$post),'categories'=>is_wp_error($categories)?[]:$categories,'types'=>is_wp_error($types)?[]:$types];}
        $payload=['schema'=>'portal-grupos-whatsapp/account-export/v1','exported_at'=>gmdate(DATE_ATOM),'account'=>['id'=>(int)$user->ID,'email'=>$user->user_email,'display_name'=>$user->display_name,'created_at'=>$user->user_registered,'meta'=>(new Privacy\AccountExportPolicy())->userMeta($meta)],'groups'=>$groups];
        self::audit('account_data_exported','user',(int)$user->ID,[],['groups'=>count($groups)]);nocache_headers();header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="portal-grupos-whatsapp-dados-'.gmdate('Y-m-d').'.json"');echo wp_json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }

    public static function handle_unlock_rate_limit(): void {
        if(!current_user_can('pgw_manage_settings'))wp_die('Sem permissão.');check_admin_referer('pgw_unlock_rate_limit','pgw_unlock_nonce');$action=sanitize_key((string)($_POST['limit_action']??''));$subject=trim(sanitize_text_field(wp_unslash((string)($_POST['limit_subject']??''))));if($action===''||$subject===''||strlen($subject)>200)wp_die('Dados inválidos.');if(in_array($action,['login_email','register_email','password_reset_email'],true)){$email=sanitize_email($subject);if(!$email)wp_die('E-mail inválido.');$subject=hash_hmac('sha256',strtolower($email),wp_salt('nonce'));}global $wpdb;$wpdb->delete($wpdb->prefix.'pgw_rate_limits',['action_key'=>$action,'subject_hash'=>hash_hmac('sha256',$subject,wp_salt('nonce'))],['%s','%s']);self::audit('rate_limit_unlocked','rate_limit',0,[],['action'=>$action]);wp_safe_redirect(add_query_arg('pgw_notice','unlocked',admin_url('admin.php?page=pgw-diagnostics')));exit;
    }

    public static function handle_create_pages(): void {
        if(!current_user_can('pgw_manage_settings'))wp_die('Sem permissão.');check_admin_referer('pgw_create_pages','pgw_pages_nonce');$selected=array_map('sanitize_title',(array)($_POST['pages']??[]));$pages=(new Activation\PageBlueprints())->selected($selected);$stored=(array)get_option('pgw_page_ids',[]);
        foreach($pages as $slug=>$page){$existing=get_page_by_path($slug,OBJECT,'page');if($existing){$stored[$slug]=(int)$existing->ID;continue;}$id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_name'=>$slug,'post_title'=>$page['title'],'post_content'=>$page['content'],'comment_status'=>'closed'],true);if(!is_wp_error($id)){$stored[$slug]=(int)$id;self::audit('setup_page_created','page',(int)$id,[],['slug'=>$slug]);}}
        update_option('pgw_page_ids',$stored,false);flush_rewrite_rules(false);wp_safe_redirect(add_query_arg('pgw_notice','pages_created',admin_url('admin.php?page=pgw-setup')));exit;
    }

    public static function handle_avatar_upload(): void { auth_redirect(); check_admin_referer('pgw_avatar_upload', 'pgw_avatar_nonce'); $user_id = get_current_user_id(); if (empty($_FILES['pgw_avatar']) || !self::allowed_image_upload($_FILES['pgw_avatar'])) { wp_safe_redirect(add_query_arg('pgw_error', 'avatar', home_url('/minha-conta/'))); exit; } require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php'; $attachment_id = media_handle_upload('pgw_avatar', 0, [], ['test_form' => false, 'mimes' => ['jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp']]); if (is_wp_error($attachment_id)) { wp_safe_redirect(add_query_arg('pgw_error', 'avatar', home_url('/minha-conta/'))); exit; } $old = absint(get_user_meta($user_id, 'pgw_avatar_id', true)); update_user_meta($user_id, 'pgw_avatar_id', $attachment_id); update_user_meta($user_id, 'pgw_avatar_source', 'custom'); update_user_meta($user_id, 'pgw_avatar_updated_at', current_time('mysql', true)); if ($old && $old !== $attachment_id) wp_delete_attachment($old, true); self::audit('avatar_updated', 'user', $user_id); wp_safe_redirect(add_query_arg('pgw_notice', 'avatar', home_url('/minha-conta/'))); exit; }
    public static function handle_account_deletion_request(): void { auth_redirect(); check_admin_referer('pgw_delete_account', 'pgw_delete_account_nonce'); $user=wp_get_current_user();$confirmation=sanitize_text_field(wp_unslash((string)($_POST['confirmation']??'')));$password=(string)wp_unslash($_POST['current_password']??'');$providers=(new Auth\ProviderPolicy())->normalize(get_user_meta($user->ID,'pgw_auth_providers',true));$password_verified=$password!==''&&wp_check_password($password,$user->user_pass,$user->ID);if(!(new Accounts\DeletionRequestPolicy())->allows($confirmation,$providers,$password_verified)||!self::rate_limit('account_deletion','user_'.$user->ID,3,DAY_IN_SECONDS)){wp_safe_redirect(add_query_arg('pgw_error','deletion',home_url('/minha-conta/')));exit;}if(!self::send_otp($user->ID,'account_deletion')){wp_safe_redirect(add_query_arg('pgw_error','otp',home_url('/minha-conta/')));exit;}self::audit('account_deletion_requested','user',(int)$user->ID);wp_safe_redirect(self::auth_flow_url((int)$user->ID,'account_deletion'));exit; }
    public static function redirect_group(): void { $slug=get_query_var('pgw_redirect');if(!$slug)return;$post=get_page_by_path(sanitize_title($slug),OBJECT,self::CPT);if(!$post||get_post_status($post)!=='publish'){wp_safe_redirect(home_url('/grupos/'),302);exit;}if(!is_user_logged_in()){$target=home_url('/ir/'.get_post_field('post_name',$post).'/');wp_safe_redirect(add_query_arg('pgw_redirect_to',$target,home_url('/entrar/')),302);exit;}if((int)get_post_meta($post->ID,'_pgw_demo',true)===1){wp_safe_redirect(add_query_arg('pgw_demo_notice',1,get_permalink($post)),302);exit;}$url=self::sanitize_invite(get_post_meta($post->ID,'_pgw_invite_url',true));if(!$url){wp_safe_redirect(get_permalink($post),302);exit;}global $wpdb;$wpdb->insert($wpdb->prefix.'pgw_events',['event_type'=>'click','group_id'=>$post->ID,'user_id'=>get_current_user_id()?:null,'session_hash'=>hash('sha256',wp_get_session_token()),'source'=>'redirect','created_at'=>current_time('mysql',true)],['%s','%d','%d','%s','%s','%s']);update_post_meta($post->ID,'_pgw_click_count',absint(get_post_meta($post->ID,'_pgw_click_count',true))+1);wp_safe_redirect($url,302);exit; }
    public static function suppress_theme_group_thumbnail(string $html,int $post_id): string { return is_singular(self::CPT)&&$post_id===get_queried_object_id()?'':$html; }
    public static function single_content(string $content): string { if(!is_singular(self::CPT)||!in_the_loop()||!is_main_query())return $content;$id=get_the_ID();if(get_post_status($id)!=='publish')return is_user_logged_in()&&current_user_can('edit_post',$id)?$content:'<p>Este grupo ainda não está disponível.</p>';$terms=get_the_terms($id,self::CAT);$type=get_the_terms($id,self::TYPE);$category=$terms&&!is_wp_error($terms)?$terms[0]->name:'';$kind=$type&&!is_wp_error($type)?$type[0]->name:'Grupo';$rules=trim((string)get_post_meta($id,'_pgw_rules',true));$state=(string)get_post_meta($id,'_pgw_link_status',true);$thumbnail_id=get_post_thumbnail_id($id);$image=$thumbnail_id?wp_get_attachment_image($thumbnail_id,'pgw-hero',false,['loading'=>'eager','fetchpriority'=>'high','alt'=>get_the_title($id),'class'=>'pgw-single__image']):'';$demo_notice=!empty($_GET['pgw_demo_notice'])?'<p role="status">Este é um conteúdo demonstrativo; nenhum link externo foi aberto.</p>':'';$rules_html=$rules?'<section><h2>Regras</h2><p>'.nl2br(esc_html($rules)).'</p></section>':'';$report_form='<details class="pgw-report"><summary>Denunciar este grupo</summary><form class="pgw-report-form">'.self::form_proof_fields('report').'<input type="hidden" name="group_id" value="'.absint($id).'"><p><label>Motivo<select name="reason" required><option value="expired_link">Link expirado</option><option value="missing_group">Grupo inexistente</option><option value="inappropriate">Conteúdo inadequado</option><option value="scam">Golpe</option><option value="fraud">Fraude</option><option value="spam">Spam</option><option value="duplicate">Duplicidade</option><option value="improper_image">Imagem indevida</option><option value="personal_data">Dados pessoais</option><option value="other">Outro</option></select></label></p><p><label>Detalhes<textarea name="details" maxlength="1000"></textarea></label></p><button class="pgw-button pgw-button--secondary" type="submit">Enviar denúncia</button><span class="pgw-report-status" role="status" aria-live="polite"></span></form></details>';return '<article class="pgw-wrap"><div class="pgw-auth">'.$demo_notice.$image.'<p><span class="pgw-badge">'.esc_html($kind.($category?' · '.$category:'')).'</span></p>'.$content.$rules_html.'<p><small>Atualizado em '.esc_html(get_the_modified_date('d/m/Y',$id)).($state?' · Estado do link: '.esc_html($state):'').'</small></p><p>'.self::group_join_button($id).'</p>'.$report_form.'<p>Plataforma independente e sem vínculo oficial com WhatsApp ou Meta. Não compartilhe dados pessoais nem envie valores a desconhecidos.</p></div></article>'; }
    public static function seo_title(string $title): string { return is_singular(self::CPT)?get_the_title().' — Portal Grupos WhatsApp':$title; }
    public static function seo_robots(array $robots): array { $object=get_queried_object();$path=$object instanceof \WP_Post&&$object->post_type==='page'?$object->post_name:trim((string)wp_parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH),'/');$group_id=is_singular(self::CPT)?get_queried_object_id():0;$status=$group_id?self::status($group_id):null;$demo=($group_id&&(int)get_post_meta($group_id,'_pgw_demo',true)===1)||($object instanceof \WP_Post&&(int)get_post_meta($object->ID,'_pgw_demo_page',true)===1);$redirect=(bool)get_query_var('pgw_redirect');if((new SEO\RobotsPolicy())->noindex($path,$status,$demo,$redirect)){unset($robots['index'],$robots['follow']);$robots['noindex']=true;$robots['nofollow']=true;$robots['noarchive']=true;}return $robots; }
    private static function external_seo_active(): bool { return defined('WPSEO_VERSION')||defined('RANK_MATH_VERSION')||defined('SEOPRESS_VERSION')||defined('SEOPRESS_PRO_VERSION'); }
    public static function seo_meta(): void { if(!is_singular(self::CPT)||self::external_seo_active())return;$id=get_queried_object_id();if(self::status($id)!=='approved'||(int)get_post_meta($id,'_pgw_demo',true)===1)return;$title=get_the_title($id);$description=self::trim_text(wp_strip_all_tags(get_post_field('post_content',$id)),155);$image=get_the_post_thumbnail_url($id,'pgw-hero');echo '<meta name="description" content="'.esc_attr($description).'">';echo '<link rel="canonical" href="'.esc_url(get_permalink($id)).'">';echo '<meta property="og:type" content="website"><meta property="og:title" content="'.esc_attr($title).'"><meta property="og:description" content="'.esc_attr($description).'">';if($image)echo '<meta property="og:image" content="'.esc_url($image).'">';echo '<meta name="twitter:card" content="'.($image?'summary_large_image':'summary').'"><meta name="twitter:title" content="'.esc_attr($title).'"><meta name="twitter:description" content="'.esc_attr($description).'">';if($image)echo '<meta name="twitter:image" content="'.esc_url($image).'">'; }

    public static function register_privacy_exporter(array $exporters): array { $exporters['portal-grupos-whatsapp']=['exporter_friendly_name'=>'Portal Grupos WhatsApp','callback'=>[self::class,'privacy_exporter']]; return $exporters; }
    public static function privacy_exporter(string $email_address, int $page = 1): array { $user=get_user_by('email',$email_address);if(!$user)return ['data'=>[],'done'=>true];$items=[];$meta=['pgw_phone'=>'Telefone','pgw_account_status'=>'Status da conta','pgw_auth_providers'=>'Provedores de acesso','pgw_registered_at'=>'Data de cadastro','pgw_last_login_at'=>'Último acesso'];foreach($meta as $key=>$label){$value=get_user_meta($user->ID,$key,true);if($value!==''&&$value!==[])$items[]=['name'=>$label,'value'=>is_scalar($value)?(string)$value:wp_json_encode($value)];}$groups=get_posts(['post_type'=>self::CPT,'author'=>$user->ID,'post_status'=>'any','numberposts'=>-1]);foreach($groups as $group)$items[]=['name'=>'Grupo enviado','value'=>$group->post_title.' — '.self::status($group->ID)];return ['data'=>[['group_id'=>'pgw-account-'.$user->ID,'group_label'=>'Conta do Portal Grupos WhatsApp','item_id'=>'pgw-user-'.$user->ID,'data'=>$items]],'done'=>true]; }
    public static function register_privacy_eraser(array $erasers): array { $erasers['portal-grupos-whatsapp']=['eraser_friendly_name'=>'Portal Grupos WhatsApp','callback'=>[self::class,'privacy_eraser']]; return $erasers; }
    public static function privacy_eraser(string $email_address, int $page = 1): array { $user=get_user_by('email',$email_address);if(!$user)return ['items_removed'=>false,'items_retained'=>false,'messages'=>[],'done'=>true];delete_user_meta($user->ID,'pgw_phone');foreach(['pgw_google_sub','pgw_google_email','pgw_google_linked_at','pgw_google_picture_source','pgw_pending_google_picture'] as $key)delete_user_meta($user->ID,$key);$avatar=absint(get_user_meta($user->ID,'pgw_avatar_id',true));delete_user_meta($user->ID,'pgw_avatar_id');delete_user_meta($user->ID,'pgw_avatar_source');if($avatar)wp_delete_attachment($avatar,true);return ['items_removed'=>true,'items_retained'=>true,'messages'=>['Os grupos enviados foram mantidos para revisão administrativa.'],'done'=>true]; }
    public static function admin_notices(): void { if(!current_user_can('pgw_manage_settings'))return;if(!defined('PGW_GOOGLE_CLIENT_ID')&&!get_option('pgw_google_client_id'))echo '<div class="notice notice-info"><p><strong>Portal Grupos WhatsApp:</strong> o login com Google está desativado até que um Client ID e Client Secret sejam configurados.</p></div>'; }
}

register_activation_hook(__FILE__, [Plugin::class,'activate']); register_deactivation_hook(__FILE__, [Plugin::class,'deactivate']); Plugin::boot();
