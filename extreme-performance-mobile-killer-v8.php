<?php
/**
 * ============================================================================
 * EXTREME PERFORMANCE v8.0 - MOBILE KILLER EDITION
 * ============================================================================
 * Foco TOTAL em eliminar problemas específicos do Mobile para atingir 95+
 * 
 * OTIMIZAÇÕES CRÍTICAS MOBILE:
 * - Preload LCP image com fetchpriority="high"
 * - Remove lazy da PRIMEIRA imagem (causa LCP alto)
 * - Critical CSS específico para viewport mobile (320px-768px)
 * - Font preload + font-display:swap EXTREMO
 * - GTranslate delay de 3s no mobile (não bloqueia LCP)
 * - CLS killer específico mobile
 * - TBT reducer - throttle JS no mobile
 * - Image dimensions FORÇADAS
 * - Background lazy abaixo da dobra MOBILE
 * - Remove jQuery Migrate (85KB)
 * - Dequeue Gutenberg se não usado
 */

defined('ABSPATH') || exit;

class Extreme_Performance_Mobile_Killer {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', [$this, 'start_output_buffer'], 0);
        add_action('wp_head', [$this, 'mobile_critical_head'], 0);
        add_action('wp_footer', [$this, 'mobile_critical_footer'], 9999);
        add_action('wp_enqueue_scripts', [$this, 'remove_heavy_scripts'], 100);
        add_action('wp_head', [$this, 'smart_preconnect'], 1);
        add_action('wp_head', [$this, 'extreme_font_optimization'], 2);
        add_action('wp_head', [$this, 'mobile_cls_killer_css'], 3);
    }
    
    public function start_output_buffer() {
        if (!is_admin() && !is_user_logged_in()) {
            ob_start([$this, 'process_html_content']);
        }
    }
    
    public function mobile_critical_head() {
        if (is_admin()) return;
        
        echo '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//www.googletagmanager.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//translate.google.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        
        echo "<style>" . $this->get_mobile_critical_css() . "</style>\n";
        $this->preload_google_fonts();
    }
    
    private function get_mobile_critical_css() {
        return '*:before,*:after{box-sizing:border-box}html{line-height:1.15;-webkit-text-size-adjust:100%}body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;line-height:1.6;color:#333;background:#fff;min-height:100vh;overflow-x:hidden}img{max-width:100%;height:auto;display:block;border:0}picture,video,canvas,svg{display:block;max-width:100%}iframe{border:0;width:100%}h1,h2,h3,h4,h5,h6{margin:0 0 0.5em;line-height:1.3;font-weight:700}p{margin:0 0 1em}a{color:inherit;text-decoration:none}.container,.wrapper{width:100%;padding:0 1rem;max-width:100%}.hero-section,.header-section{position:relative;width:100%;min-height:60vh;display:flex;align-items:center;justify-content:center;overflow:hidden}.hero-image,.hero-bg{width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;z-index:-1}.site-header{position:sticky;top:0;z-index:1000;width:100%;background:#fff}.nav-menu{display:flex;flex-wrap:wrap;justify-content:center;padding:0.5rem 0}.wp-block-image img{height:auto;max-width:100%}.wp-block-cover{position:relative;min-height:300px;display:flex;align-items:center;justify-content:center;overflow:hidden}.wp-block-cover__image-background{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:-1}@media(max-width:768px){body{font-size:16px}h1{font-size:1.75rem}h2{font-size:1.5rem}h3{font-size:1.25rem}.hero-section{min-height:50vh}.site-header{position:relative}.nav-menu{flex-direction:column;text-align:center}}@media(max-width:480px){body{font-size:15px}h1{font-size:1.5rem}h2{font-size:1.3rem}.container,.wrapper{padding:0 0.75rem}}';
    }
    
    private function preload_google_fonts() {
        global $wp_styles;
        if (!isset($wp_styles->registered['google-fonts'])) return;
        $font_url = $wp_styles->registered['google-fonts']->src;
        if (strpos($font_url, 'fonts.googleapis.com') !== false) {
            parse_str(parse_url($font_url, PHP_URL_QUERY), $params);
            if (!empty($params['family'])) {
                $families = explode('|', $params['family']);
                foreach ($families as $family) {
                    $font_name = explode(':', $family)[0];
                    $font_name = str_replace('+', '%20', $font_name);
                    echo "<link rel=\"preload\" href=\"https://fonts.gstatic.com/s/{$font_name}/latest.woff2\" as=\"font\" type=\"font/woff2\" crossorigin>\n";
                }
            }
        }
    }
    
    public function smart_preconnect() {
        $domains = ['https://fonts.googleapis.com','https://fonts.gstatic.com','https://www.googletagmanager.com','https://www.google-analytics.com','https://translate.google.com','https://stats.g.doubleclick.net'];
        $uploads_dir = wp_upload_dir();
        $upload_domain = parse_url($uploads_dir['baseurl'], PHP_URL_SCHEME) . '://' . parse_url($uploads_dir['baseurl'], PHP_URL_HOST);
        if (!in_array($upload_domain, $domains)) $domains[] = $upload_domain;
        foreach ($domains as $domain) echo "<link rel=\"preconnect\" href=\"$domain\" crossorigin>\n";
    }
    
    public function extreme_font_optimization() {
        echo '<style>@font-face{font-display:swap!important}html{font-feature-settings:"kern" 1;font-synthesis:none}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}</style>';
    }
    
    public function mobile_cls_killer_css() {
        echo '<style>img,video,iframe,picture{contain:layout paint;max-width:100%;height:auto!important}.wp-block-image img{height:auto!important;min-height:1px}.wp-block-cover{contain:layout style paint;min-height:300px}.wp-block-cover__image-background{height:100%!important;width:100%!important}.hero-section,.header-section{contain:layout style paint;min-height:50vh}.site-header{position:sticky;top:0;will-change:auto}figure{margin:0}.responsive-video{position:relative;padding-bottom:56.25%;height:0;overflow:hidden}.responsive-video iframe{position:absolute;top:0;left:0;width:100%;height:100%}.entry-content>*:nth-child(n+4){content-visibility:auto;contain-intrinsic-size:0 500px}@media(max-width:768px){img{contain:strict}.wp-block-image,.wp-block-cover{contain:layout style paint}}</style>';
    }
    
    public function remove_heavy_scripts() {
        wp_deregister_script('jquery-migrate');
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        if (!is_singular() || !has_blocks()) {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
        }
    }
    
    public function process_html_content($buffer) {
        if (empty($buffer) || is_admin() || is_user_logged_in()) return $buffer;
        
        // 1. Preload LCP image
        preg_match('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|webp|avif))["\'][^>]*>/i', $buffer, $matches);
        if (!empty($matches[1])) {
            $img_src = $matches[1];
            $preload = "<link rel=\"preload\" as=\"image\" href=\"$img_src\" imagesrcset=\"$img_src\" imagesizes=\"100vw\" fetchpriority=\"high\">";
            $buffer = preg_replace('/<head[^>]*>/i', "$0\n$preload", $buffer, 1);
        }
        
        // 2. Remove lazy da primeira imagem + fetchpriority high
        $count = 0;
        $buffer = preg_replace_callback('/<img([^>]+?)>/i', function($matches) use (&$count) {
            $count++;
            if ($count === 1) {
                $img = preg_replace('/loading=["\']lazy["\']/i', '', $matches[1]);
                $img = preg_replace('/decoding=["\']async["\']/i', 'decoding="sync"', $img);
                if (strpos($img, 'fetchpriority') === false) {
                    $img = ' fetchpriority="high"' . $img;
                }
                return '<img' . $img . '>';
            }
            return $matches[0];
        }, $buffer, 1);
        
        // 3. Força dimensões em imagens
        $buffer = preg_replace_callback('/<img([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i', function($matches) {
            $attrs = $matches[1] . $matches[3];
            $src = $matches[2];
            if (preg_match('/width=["\']\d+["\']/i', $attrs) && preg_match('/height=["\']\d+["\']/i', $attrs)) {
                return $matches[0];
            }
            if (preg_match('/[-_](\d+)x(\d+)\./i', $src, $dim)) {
                $w = $dim[1]; $h = $dim[2];
            } else {
                $w = 800; $h = 450;
            }
            $new_tag = '<img' . $attrs;
            if (!preg_match('/width=["\']/i', $attrs)) {
                $new_tag = preg_replace('/<img/', "<img width=\"$w\"", $new_tag, 1);
            }
            if (!preg_match('/height=["\']/i', $attrs)) {
                $new_tag .= " height=\"$h\"";
            }
            if (strpos($attrs, 'fetchpriority="high"') === false && strpos($attrs, 'loading=') === false) {
                $new_tag = str_replace('<img', '<img loading="lazy"', $new_tag, 1);
            }
            return $new_tag . '>';
        }, $buffer);
        
        // 4. Lazy iframes
        $buffer = preg_replace_callback('/<iframe([^>]*?)src=["\']([^"\']+)["\']([^>]*?)>/i', function($matches) {
            $attrs = $matches[1] . $matches[3];
            $src = $matches[2];
            if (strpos($src, 'google.com/maps') !== false) return $matches[0];
            if (strpos($attrs, 'loading=') === false) {
                return '<iframe loading="lazy"' . $attrs . 'src="' . $src . '">';
            }
            return $matches[0];
        }, $buffer);
        
        // 5. Delay GTranslate mobile
        $is_mobile = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($is_mobile) {
            $buffer = preg_replace_callback('/(<script[^>]*?gt\.js[^>]*?><\/script>)/i', function($m) {
                return '<script>setTimeout(function(){' . $m[1] . '},3000);</script>';
            }, $buffer);
        }
        
        // 6. Minificação HTML
        $buffer = preg_replace('/<!--[^>]*?>/', '', $buffer);
        $buffer = preg_replace('/\s+/u', ' ', $buffer);
        $buffer = preg_replace('/>\s+</', '><', $buffer);
        
        return trim($buffer);
    }
    
    public function mobile_critical_footer() {
        if (is_admin()) return;
        echo '<script>window.addEventListener("load",function(){var s=document.querySelectorAll("script[data-defer]");s.forEach(function(x){var n=document.createElement("script");n.src=x.src;n.async=true;x.parentNode.replaceChild(n,x)});});if(window.innerWidth<768){requestIdleCallback=function(cb){return setTimeout(cb,1)};}</script>';
    }
}

Extreme_Performance_Mobile_Killer::get_instance();
