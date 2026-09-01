<?php
/**
 * Plugin Name: PlayBrand Performance
 * Description: Otimizações de performance seguras e complementares ao WP Rocket/LiteSpeed.
 * Version: 1.0.0
 * Requires at least: 6.3
 * Requires PHP: 8.1
 * Author: PlayBrand
 */
declare(strict_types=1);
namespace PlayBrand\Performance;
if (!defined('ABSPATH')) exit;
const PBPF_VERSION='1.0.0'; const PBPF_OPTION='pbpf_options';
require_once __DIR__.'/includes/Assets.php'; require_once __DIR__.'/includes/Fonts.php'; require_once __DIR__.'/includes/Media.php'; require_once __DIR__.'/includes/ThirdParty.php'; require_once __DIR__.'/includes/ResourceHints.php'; require_once __DIR__.'/includes/Admin.php'; require_once __DIR__.'/includes/Diagnostics.php';
final class Plugin { public static function boot(): void { if (is_admin() && !wp_doing_ajax()) Admin::init(); if (!self::safe_context()) return; Assets::init(); Fonts::init(); Media::init(); ThirdParty::init(); ResourceHints::init(); add_action('init',[self::class,'cleanup']); }
 public static function safe_context(): bool { return !is_admin() && !wp_doing_ajax() && !(defined('REST_REQUEST')&&REST_REQUEST) && !(defined('DOING_CRON')&&DOING_CRON) && !is_user_logged_in(); }
 public static function cleanup(): void { $o=get_option(PBPF_OPTION,[]); if (empty($o['cleanup'])) return; remove_action('wp_head','print_emoji_detection_script',7); remove_action('wp_print_styles','print_emoji_styles'); remove_action('wp_head','rsd_link'); remove_action('wp_head','wlwmanifest_link'); remove_action('wp_head','wp_oembed_add_discovery_links'); }
}
register_activation_hook(__FILE__, function(){ if(!get_option(PBPF_OPTION)) add_option(PBPF_OPTION,['enabled'=>1,'dry_run'=>1,'cleanup'=>1,'media'=>1,'fonts'=>1,'third_party'=>1]); });
Plugin::boot();
