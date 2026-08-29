<?php
/**
 * Plugin Name: Otimização GTMetrix PageSpeed 99+
 * Description: Otimizações EXTREMAS para alcançar pontuação 99+ no GTMetrix e PageSpeed Insights (Mobile e Desktop). Compatível com W3 Total Cache.
 * Version: 4.0.0
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
        // Hooks prioritários - CRÍTICO para mobile
        add_action('init', array($this, 'definir_constantes'), 0);
        add_action('wp_head', array($this, 'resource_hints'), 0);
        add_action('wp_head', array($this, 'preload_lcp'), 0);
        add_action('wp_enqueue_scripts', array($this, 'critical_css_inline'), 0);
        add_action('wp_enqueue_scripts', array($this, 'remover_tudo_inutil'), 0);
        add_action('wp_enqueue_scripts', array($this, 'otimizar_fontes'), 1);
        
        // Filtros agressivos
        add_filter('wp_lazy_loading_enabled', '__return_false', 9999);
        add_filter('script_loader_tag', array($this, 'defer_todos_scripts'), 9999, 3);
        add_filter('style_loader_tag', array($this, 'inline_css_critico'), 9999, 2);
        add_filter('script_loader_src', array($this, 'remover_query_strings'), 9999);
        add_filter('style_loader_src', array($this, 'remover_query_strings'), 9999);
        add_filter('wp_resource_hints', array($this, 'preconnect_fonts'), 9999, 2);
        add_filter('the_content', array($this, 'add_fetchpriority_hero'), 9999);
        
        // Cleanup total
        $this->remover_emojis_completo();
        $this->limpar_head_total();
        
        // Buffer output MINIFICADO
        add_action('template_redirect', array($this, 'buffer_start'), 0);
        
        // Desativa Gutenberg frontend se não usar blocos
        add_filter('should_load_separate_core_block_assets', '__return_true');
        add_action('wp_enqueue_scripts', function() {
            wp_dequeue_style('global-styles');
            wp_dequeue_style('wp-global-styles');
        }, 9999);
    }

    public function definir_constantes() {
        if (!defined('WP_POST_REVISIONS')) define('WP_POST_REVISIONS', 3);
        if (!defined('EMPTY_TRASH_DAYS')) define('EMPTY_TRASH_DAYS', 7);
        if (!defined('DISABLE_WP_CRON')) define('DISABLE_WP_CRON', true);
    }

    private function remover_emojis_completo() {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        remove_filter('wp_sitemaps_posts_entry', 'wp_staticize_emoji');
        remove_filter('wp_sitemaps_taxonomies_entry', 'wp_staticize_emoji');
    }

    private function limpar_head_total() {
        remove_action('wp_head', 'wp_generator', 1);
        remove_action('wp_head', 'rsd_link', 1);
        remove_action('wp_head', 'wlwmanifest_link', 1);
        remove_action('wp_head', 'wp_shortlink_wp_head', 10);
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('template_redirect', 'rest_output_link_header', 11);
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'rel_canonical', 10);
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
        remove_action('wp_head', 'wp_oembed_add_host_js');
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_head', 'wp_print_styles', 8);
        remove_action('wp_head', 'wp_custom_css_cb', 101);
        
        // Remove DNS prefetch do WordPress (usamos o nosso otimizado)
        remove_action('wp_head', 'wp_resource_hints', 2);
        
        if (class_exists('WooCommerce')) {
            remove_action('wp_head', array('WC_Structured_Data', 'generate_website_data'), 30);
        }
    }

    public function resource_hints() {
        // DNS Prefetch AGRESSIVO
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//www.google-analytics.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//connect.facebook.net">' . "\n";
        echo '<link rel="dns-prefetch" href="//static.xx.fbcdn.net">' . "\n";
        echo '<link rel="dns-prefetch" href="//maps.googleapis.com">' . "\n";
        
        // Preconnect CRÍTICO
        echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin="anonymous">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="anonymous">' . "\n";
        
        // CDN W3TC
        if (defined('W3TC') && function_exists('w3tc_cdn_url')) {
            $cdn = w3tc_cdn_url('');
            if ($cdn) {
                $host = parse_url($cdn, PHP_URL_HOST);
                if ($host) {
                    echo '<link rel="preconnect" href="//' . esc_attr($host) . '" crossorigin="anonymous">' . "\n";
                    echo '<link rel="dns-prefetch" href="//' . esc_attr($host) . '">' . "\n";
                }
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
        // Detecta imagem LCP de forma inteligente
        $lcp_image = '';
        
        if (is_front_page()) {
            // Homepage - usa imagem hero específica
            $lcp_image = home_url('/wp-content/uploads/2026/08/agencia-desenvolvimento-de-sites.webp');
        } elseif (has_post_thumbnail()) {
            // Posts/Páginas com featured image
            $lcp_image = get_the_post_thumbnail_url(get_the_ID(), 'full');
        }
        
        if ($lcp_image) {
            // Preload AGRESSIVO com fetchpriority high
            echo '<link rel="preload" as="image" href="' . esc_url($lcp_image) . '" fetchpriority="high" imagesrcset="' . esc_attr(wp_get_attachment_image_srcset(get_post_thumbnail_id(get_the_ID()), 'full') ?: '') . '" imagesizes="(max-width: 768px) 100vw, 100vw">' . "\n";
            
            // Preload da imagem em tamanhos menores para mobile
            $thumb_id = get_post_thumbnail_id(get_the_ID());
            if ($thumb_id) {
                $sizes = ['medium', 'large'];
                foreach ($sizes as $size) {
                    $img_data = wp_get_attachment_image_src($thumb_id, $size);
                    if ($img_data && $img_data[0] !== $lcp_image) {
                        echo '<link rel="preload" as="image" href="' . esc_url($img_data[0]) . '" media="(max-width: ' . ($size === 'medium' ? '768' : '1024') . 'px)">' . "\n";
                    }
                }
            }
        }
        
        // Preload de fontes CRÍTICAS
        echo '<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        echo '<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Open+Sans:wght@400;600&display=swap"></noscript>' . "\n";
    }

    public function critical_css_inline() {
        // Critical CSS COMPLETO para above-the-fold
        $critical_css = "
*,*::before,*::after{box-sizing:border-box}
html{line-height:1.15;-webkit-text-size-adjust:100%;font-size:16px}
body{margin:0;font-family:'Montserrat',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,sans-serif;font-weight:400;line-height:1.6;color:#333;background-color:#fff}
h1,h2,h3,h4,h5,h6{margin:0 0 1rem;font-weight:700;line-height:1.2}
h1{font-size:2.5rem}h2{font-size:2rem}h3{font-size:1.75rem}
p{margin:0 0 1rem}
a{color:#0066cc;text-decoration:none}a:hover{text-decoration:underline}
img{max-width:100%;height:auto;display:block;border-style:none}
img[width][height]{min-height:var(--aspect-ratio,auto)}
.container{width:100%;max-width:1200px;margin:0 auto;padding:0 20px}
.row{display:flex;flex-wrap:wrap;margin:0 -10px}
.col{flex:1;padding:0 10px}
.btn{display:inline-block;padding:12px 30px;background:#0066cc;color:#fff;border-radius:5px;font-weight:600;transition:all 0.3s}.btn:hover{background:#0052a3;color:#fff;text-decoration:none}
.hero{min-height:100vh;display:flex;align-items:center;background-size:cover;background-position:center;position:relative}
.hero-overlay{position:absolute;inset:0;background:rgba(0,0,0,0.5)}
.hero-content{position:relative;z-index:2;text-align:center;color:#fff}
.header{padding:20px 0;border-bottom:1px solid #eee}
.nav{display:flex;justify-content:space-between;align-items:center}
@media(max-width:768px){h1{font-size:2rem}h2{font-size:1.5rem}.hero{min-height:80vh}}
";
        
        wp_register_style('perf-critical', false);
        wp_enqueue_style('perf-critical');
        wp_add_inline_style('perf-critical', trim($critical_css));
    }

    public function otimizar_fontes() {
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('dashicons');
        add_filter('style_loader_tag', array($this, 'add_font_display_swap'), 9999, 2);
    }

    public function add_font_display_swap($html, $handle) {
        if (strpos($handle, 'google-fonts') !== false || strpos($html, 'fonts.googleapis') !== false) {
            $html = str_replace('<link rel=\'stylesheet\'', '<link rel=\'stylesheet\' media="print" onload="this.media=\'all\'"', $html);
        }
        return $html;
    }

    public function remover_tudo_inutil() {
        wp_deregister_script('jquery-migrate');
        wp_deregister_script('jquery');
        wp_register_script('jquery', false);
        
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-block-style');
        wp_dequeue_style('global-styles');
        wp_dequeue_style('wp-global-styles');
        wp_dequeue_script('wp-block-library');
        wp_dequeue_script('wp-i18n');
        wp_dequeue_script('wp-element');
        wp_dequeue_script('wp-html-entities');
        
        if (!is_singular() || !has_shortcode(get_post()->post_content ?? '', 'contact-form-7')) {
            wp_dequeue_style('contact-form-7');
            wp_deregister_script('contact-form-7');
            wp_deregister_script('wpcf7-recaptcha');
        }
        
        if (class_exists('WooCommerce')) {
            if (!is_product() && !is_cart() && !is_checkout() && !is_account_page()) {
                wp_dequeue_style('woocommerce-general');
                wp_dequeue_style('woocommerce-layout');
                wp_dequeue_style('woocommerce-smallscreen');
                wp_deregister_script('wc-cart-fragments');
                wp_deregister_script('woocommerce');
            }
        }
        
        wp_dequeue_style('wp-emoji-styles');
        
        if (!is_admin_bar_showing()) {
            wp_dequeue_style('dashicons');
        }
    }

    public function defer_todos_scripts($tag, $handle, $src) {
        if (is_admin() || is_preview()) return $tag;
        if (strpos($tag, 'async') !== false || strpos($tag, 'defer') !== false) return $tag;
        
        $always_defer = [
            'comment-reply', 'wp-embed', 'woocommerce-cart-fragments', 
            'woocommerce-js', 'wc-add-to-cart', 'wc-single-product',
            'google-analytics', 'ga-js', 'gtag', 'google-tag-manager',
            'facebook-jssdk', 'facebook-sdk', 'fb-async-init'
        ];
        
        $critical_handles = ['jquery-core', 'jquery'];
        
        if (in_array($handle, $critical_handles)) {
            return $tag;
        }
        
        if (in_array($handle, $always_defer) || 
            strpos($src, 'google-analytics') !== false || 
            strpos($src, 'googletagmanager') !== false || 
            strpos($src, 'facebook.net') !== false ||
            strpos($src, 'analytics.js') !== false ||
            strpos($src, 'gtag/js') !== false) {
            return str_replace(' src', ' defer="defer" src', $tag);
        }
        
        return str_replace(' src', ' defer="defer" src', $tag);
    }

    public function inline_css_critico($html, $handle) {
        if (strpos($handle, 'critical') !== false || strpos($handle, 'above-fold') !== false) {
            global $wp_styles;
            if (isset($wp_styles->registered[$handle]->src)) {
                $css_file = ABSPATH . str_replace(site_url(), '', $wp_styles->registered[$handle]->src);
                if (file_exists($css_file) && filesize($css_file) < 14336) {
                    $css_content = file_get_contents($css_file);
                    return "<style>\n" . trim($css_content) . "\n</style>";
                }
            }
        }
        return $html;
    }

    public function remover_query_strings($src) {
        if (strpos($src, '?ver=') !== false) {
            $src = remove_query_arg('ver', $src);
        }
        if (strpos($src, '?') !== false && strpos($src, 'googleapis') === false) {
            $src = strtok($src, '?');
        }
        return $src;
    }

    public function add_fetchpriority_hero($content) {
        if (is_admin() || !is_singular()) return $content;
        
        $pattern = '/<img([^>]+)>/i';
        $count = 0;
        
        $content = preg_replace_callback($pattern, function($matches) use (&$count) {
            $count++;
            if ($count === 1) {
                $img = $matches[0];
                if (strpos($img, 'fetchpriority') === false) {
                    $img = str_replace('<img', '<img fetchpriority="high" loading="eager"', $img);
                }
                if (strpos($img, 'width=') === false || strpos($img, 'height=') === false) {
                    $img = preg_replace('/<img/', '<img width="800" height="600"', $img, 1);
                }
                return $img;
            }
            return $matches[0];
        }, $content, 1);
        
        return $content;
    }

    public function buffer_start() {
        ob_start(function($html) {
            $html = preg_replace('/<!--[^>]*>/', '', $html);
            $html = preg_replace('/\s+/', ' ', $html);
            $html = preg_replace('/>\s+</', '><', $html);
            return trim($html);
        });
    }
}

Optimizacao_Performance_99::get_instance();
