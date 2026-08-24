<?php
/**
 * Plugin Name: Otimização GTMetrix PageSpeed 99+
 * Description: Otimizações avançadas para alcançar pontuação 99+ no GTMetrix e PageSpeed Insights (Mobile e Desktop). Compatível com W3 Total Cache.
 * Version: 3.0.0
 * Author: Agência Privilége
 */

if (!defined('ABSPATH')) {
    exit;
}

class Optimizacao_Performance_99 {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hooks prioritários
        add_action('init', array($this, 'definir_constantes'), 0);
        add_action('wp_head', array($this, 'resource_hints'), 0);
        add_action('wp_head', array($this, 'preload_lcp'), 1);
        add_action('wp_enqueue_scripts', array($this, 'critical_css'), 0);
        add_action('wp_enqueue_scripts', array($this, 'limpar_scripts'), 9999);
        
        // Filtros
        add_filter('wp_lazy_loading_enabled', '__return_false');
        add_filter('script_loader_tag', array($this, 'defer_js'), 10, 3);
        add_filter('script_loader_src', array($this, 'remover_query_strings'), 15);
        add_filter('style_loader_src', array($this, 'remover_query_strings'), 15);
        add_filter('wp_resource_hints', array($this, 'preconnect_fonts'), 10, 2);
        
        // Cleanup
        $this->remover_emojis();
        $this->limpar_head();
        
        // Buffer output
        add_action('template_redirect', array($this, 'buffer_start'), 0);
    }

    public function definir_constantes() {
        if (!defined('WP_POST_REVISIONS')) define('WP_POST_REVISIONS', 5);
        if (!defined('EMPTY_TRASH_DAYS')) define('EMPTY_TRASH_DAYS', 7);
        add_filter('should_load_separate_core_block_assets', '__return_true');
    }

    private function remover_emojis() {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    }

    private function limpar_head() {
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('template_redirect', 'rest_output_link_header', 11);
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'rel_canonical');
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
        if (class_exists('WooCommerce')) {
            remove_action('wp_head', array('WC_Structured_Data', 'generate_website_data'), 30);
        }
    }

    public function resource_hints() {
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//www.google-analytics.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//connect.facebook.net">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        if (defined('W3TC') && function_exists('w3tc_cdn_url')) {
            $cdn = w3tc_cdn_url('');
            if ($cdn) {
                $host = parse_url($cdn, PHP_URL_HOST);
                if ($host) echo '<link rel="preconnect" href="//' . esc_attr($host) . '" crossorigin>' . "\n";
            }
        }
    }

    public function preconnect_fonts($urls, $relation_type) {
        if ('preconnect' === $relation_type) {
            $urls[] = ['href' => 'https://fonts.googleapis.com', 'crossorigin' => true];
            $urls[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin' => true];
        }
        return $urls;
    }

    public function preload_lcp() {
        $img = is_front_page() 
            ? home_url('/wp-content/uploads/2026/08/agencia-desenvolvimento-de-sites.webp')
            : (has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'full') : '');
        
        if ($img) {
            echo '<link rel="preload" as="image" href="' . esc_url($img) . '" fetchpriority="high">' . "\n";
        }
    }

    public function critical_css() {
        $css = "html{line-height:1.15;-webkit-text-size-adjust:100%}body{margin:0}img{max-width:100%;height:auto;display:block}*{box-sizing:border-box}";
        wp_register_style('perf-critical', false);
        wp_enqueue_style('perf-critical');
        wp_add_inline_style('perf-critical', $css);
    }

    public function limpar_scripts() {
        wp_deregister_script('jquery-migrate');
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-block-style');
        
        if (!is_singular() || !has_shortcode(get_post()->post_content ?? '', 'contact-form-7')) {
            wp_dequeue_style('contact-form-7');
            wp_deregister_script('contact-form-7');
        }
    }

    public function defer_js($tag, $handle, $src) {
        if (is_admin()) return $tag;
        if (strpos($tag, 'async') !== false || strpos($tag, 'defer') !== false) return $tag;
        
        $defer_handles = ['comment-reply', 'wp-embed', 'woocommerce-cart-fragments', 'woocommerce-js'];
        if (in_array($handle, $defer_handles)) {
            return str_replace(' src', ' defer="defer" src', $tag);
        }
        
        if (strpos($src, 'google-analytics') !== false || strpos($src, 'googletagmanager') !== false || strpos($src, 'facebook.net') !== false) {
            return str_replace(' src', ' defer="defer" src', $tag);
        }
        
        return $tag;
    }

    public function remover_query_strings($src) {
        return strpos($src, '?ver=') ? remove_query_arg('ver', $src) : $src;
    }

    public function buffer_start() {
        ob_start(function($html) {
            $html = preg_replace('/<!--[^>]*>/', '', $html);
            $html = preg_replace('/>\s+</', '><', $html);
            return trim($html);
        });
    }
}

Optimizacao_Performance_99::get_instance();
