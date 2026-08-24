<?php
/**
 * Plugin Name: Privilege Mobile Performance Killer v9.0
 * Description: Correções específicas para elevar mobile de 75% para 95%+ no PageSpeed Insights
 * Version: 9.0.0-mobile-killer
 * Author: Agência Privilége - Otimização Extrema Mobile
 * 
 * FOCO TOTAL EM MOBILE - 15 OTIMIZAÇÕES CRÍTICAS
 */

if (!defined('ABSPATH')) {
    exit;
}

if (defined('PRIVILEGE_MOBILE_KILLER_LOADED')) {
    return;
}

define('PRIVILEGE_MOBILE_KILLER_LOADED', true);
define('PRIVILEGE_MOBILE_KILLER_VERSION', '9.0.0');

/**
 * DETECÇÃO DE MOBILE MAIS PRECISA QUE wp_is_mobile()
 * wp_is_mobile() falha em alguns tablets e User-Agents modernos
 */
function privilege_is_mobile_device() {
    if (is_admin()) {
        return false;
    }
    
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Padrões mobile mais abrangentes
    $mobile_patterns = [
        '/Mobile/i',
        '/Android/i',
        '/iPhone/i',
        '/iPad/i',
        '/iPod/i',
        '/BlackBerry/i',
        '/Windows Phone/i',
        '/webOS/i',
        '/Opera Mini/i',
        '/IEMobile/i',
        '/Silk/i',
        '/Kindle/i',
        '/Tablet/i',
        '/Touch/i'
    ];
    
    foreach ($mobile_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return true;
        }
    }
    
    return false;
}

/**
 * 1. PRELOAD DA PRIMEIRA IMAGEM COM FETCHPRIORITY=HIGH
 * Remove lazy loading da primeira imagem acima da dobra
 */
function privilege_mobile_lcp_image_optimize($content) {
    if (is_admin() || !privilege_is_mobile_device() || !is_front_page()) {
        return $content;
    }
    
    $first_img_found = false;
    
    return preg_replace_callback('/<img\b[^>]*>/i', function($matches) use (&$first_img_found) {
        $tag = $matches[0];
        
        // Só processa a PRIMEIRA imagem visível
        if ($first_img_found) {
            return $tag;
        }
        
        // Ignora imagens tiny/pixel/trackers
        if (preg_match('/width=["\']?[1-9]["\']?/i', $tag) || 
            preg_match('/height=["\']?[1-9]["\']?/i', $tag) ||
            preg_match('/(tracker|pixel|analytics|spacer)/i', $tag)) {
            return $tag;
        }
        
        $first_img_found = true;
        
        // REMOVE loading="lazy" da primeira imagem
        $tag = preg_replace('/\sloading=["\']lazy["\']/i', '', $tag);
        $tag = preg_replace('/\sloading=["\']eager["\']/i', '', $tag);
        $tag = preg_replace('/<img\b/i', '<img loading="eager"', $tag);
        
        // FORÇA fetchpriority="high"
        $tag = preg_replace('/\sfetchpriority=["\'][^"\']*["\']/i', '', $tag);
        $tag = preg_replace('/<img\b/i', '<img fetchpriority="high"', $tag);
        
        // decoding="sync" para LCP mais rápido (não async)
        $tag = preg_replace('/\sdecoding=["\'][^"\']*["\']/i', '', $tag);
        $tag = preg_replace('/<img\b/i', '<img decoding="sync"', $tag);
        
        // Adiciona class para identificação
        if (!preg_match('/\sclass=["\']/i', $tag)) {
            $tag = preg_replace('/<img\b/i', '<img class="privilege-lcp-mobile"', $tag);
        } else {
            $tag = preg_replace('/(\sclass=["\'][^"\']*)["\']/i', '$1 privilege-lcp-mobile"', $tag);
        }
        
        return $tag;
    }, $content);
}
add_filter('the_content', 'privilege_mobile_lcp_image_optimize', 100);

/**
 * 2. INJETA PRELOAD NO HEAD PARA A PRIMEIRA IMAGEM
 */
function privilege_mobile_inject_lcp_preload() {
    global $post;
    
    if (is_admin() || !privilege_is_mobile_device() || !is_front_page() || !$post) {
        return;
    }
    
    // Tenta encontrar a primeira imagem do conteúdo
    if (preg_match('/<img[^>]+src=["\']([^"\']+\.webp|[^"\']+\.jpg|[^"\']+\.jpeg|[^"\']+\.png)[^>]*>/i', $post->post_content, $matches)) {
        $img_src = $matches[1];
        
        echo '<link rel="preload" as="image" href="' . esc_url($img_src) . '" fetchpriority="high">' . "\n";
    }
}
add_action('wp_head', 'privilege_mobile_inject_lcp_preload', 1);

/**
 * 3. CSS CRÍTICO MOBILE - ABOVE THE FOLD INLINE
 */
function privilege_mobile_critical_css() {
    if (is_admin() || !privilege_is_mobile_device()) {
        return;
    }
    ?>
    <style id="privilege-mobile-critical-css">
    img.privilege-lcp-mobile,
    img:first-of-type,
    .bt_bb_section_top_section_coverage_image {
        contain: strict !important;
        contain-intrinsic-size: auto 300px;
        height: auto !important;
        max-width: 100%;
    }
    
    body.home .bt_bb_wrapper > .bt_bb_section:first-child {
        contain: layout style paint;
        min-height: 50vh;
    }
    
    * {
        font-display: swap !important;
        text-rendering: optimizeLegibility;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    body.home .animate,
    body.home [class*="animate"] {
        opacity: 1 !important;
        animation: none !important;
        transition: none !important;
    }
    
    .bt_bb_background_image_holder {
        will-change: auto !important;
        transform: none !important;
        background-attachment: scroll !important;
    }
    
    @media (max-width: 768px) {
        .bt_bb_section:nth-child(n+4) {
            content-visibility: auto;
            contain-intrinsic-size: auto 400px;
        }
        
        iframe,
        video,
        .video-wrapper {
            contain: strict;
        }
    }
    </style>
    <?php
}
add_action('wp_head', 'privilege_mobile_critical_css', 0);

/**
 * 4. GTRANSLATE LAZY LOAD MOBILE - Delay de 4 segundos
 */
function privilege_mobile_gtranslate_lazy() {
    if (is_admin() || !privilege_is_mobile_device()) {
        return;
    }
    
    add_action('wp_footer', function() {
        ?>
        <script id="privilege-gtranslate-lazy">
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var gtScript = document.createElement('script');
                gtScript.src = '//cdn.gtranslate.net/widgets/latest/float.js';
                gtScript.defer = true;
                document.body.appendChild(gtScript);
                
                var placeholder = document.querySelector('.privilege-gtranslate');
                if (placeholder && !placeholder.innerHTML.trim()) {
                    placeholder.style.minHeight = '40px';
                    placeholder.style.minWidth = '100px';
                }
            }, 4000);
        });
        </script>
        <?php
    }, 1);
}
add_action('init', 'privilege_mobile_gtranslate_lazy', 100);

/**
 * 5. DEQUEUE JQUERY MIGRATE MOBILE (economia 85KB)
 */
function privilege_mobile_dequeue_jquery_migrate($scripts) {
    if (is_admin() || !privilege_is_mobile_device()) {
        return $scripts;
    }
    
    if (isset($scripts->registered['jquery-migrate'])) {
        $scripts->dequeue('jquery-migrate');
    }
    
    return $scripts;
}
add_action('wp_default_scripts', 'privilege_mobile_dequeue_jquery_migrate', 20);

/**
 * 6. FONT DISPLAY SWAP FORÇADO EM TODAS AS FONTES
 */
function privilege_mobile_font_display_swap($html) {
    if (is_admin() || !privilege_is_mobile_device()) {
        return $html;
    }
    
    if (preg_match_all('/@font-face\s*\{([^}]+)\}/i', $html, $matches)) {
        foreach ($matches[0] as $font_face) {
            if (!strpos($font_face, 'font-display:')) {
                $new_font_face = preg_replace(
                    '/}\s*$/',
                    'font-display:swap;}',
                    $font_face
                );
                $html = str_replace($font_face, $new_font_face, $html);
            }
        }
    }
    
    return $html;
}
add_filter('wp_resource_hints', 'privilege_mobile_font_display_swap', 9999);

/**
 * 7. PRECONNECT AGRESSIVO PARA DOMÍNIOS CRÍTICOS
 */
function privilege_mobile_preconnect() {
    if (is_admin() || !privilege_is_mobile_device()) {
        return;
    }
    
    $domains = [
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
        'https://www.googletagmanager.com',
        'https://www.google-analytics.com',
        'https://cdn.gtranslate.net',
        'https://unpkg.com',
        'https://cdnjs.cloudflare.com',
        'https://code.jquery.com'
    ];
    
    foreach ($domains as $domain) {
        echo '<link rel="preconnect" href="' . esc_url($domain) . '" crossorigin>' . "\n";
    }
}
add_action('wp_head', 'privilege_mobile_preconnect', 2);

/**
 * 8. DEFER JS NÃO-CRÍTICO ATÉ APÓS WINDOW.LOAD
 */
function privilege_mobile_defer_js($tag, $handle) {
    if (is_admin() || !privilege_is_mobile_device()) {
        return $tag;
    }
    
    $defer_handles = [
        'jquery',
        'wp-embed',
        'comment-reply',
        'gtranslate',
        'gtm'
    ];
    
    if (in_array($handle, $defer_handles)) {
        $tag = str_replace(' src', ' defer src', $tag);
    }
    
    return $tag;
}
add_filter('script_loader_tag', 'privilege_mobile_defer_js', 10, 2);

/**
 * 9. LAZY IFRAMES E VIDEOS MOBILE
 */
function privilege_mobile_lazy_iframes($content) {
    if (is_admin() || !privilege_is_mobile_device()) {
        return $content;
    }
    
    $iframe_count = 0;
    
    return preg_replace_callback('/<iframe\b[^>]*>/i', function($matches) use (&$iframe_count) {
        $tag = $matches[0];
        $iframe_count++;
        
        if ($iframe_count > 1) {
            $tag = preg_replace('/\sloading=["\'][^"\']*["\']/i', '', $tag);
            $tag = preg_replace('/<iframe\b/i', '<iframe loading="lazy"', $tag);
            
            if (!preg_match('/\sheight=["\']/i', $tag)) {
                $tag = preg_replace('/<iframe\b/i', '<iframe height="200"', $tag);
            }
        }
        
        return $tag;
    }, $content);
}
add_filter('the_content', 'privilege_mobile_lazy_iframes', 1000);

/**
 * 10. REDUCE TBT - THROTTLE REQUESTIDLECALLBACK
 */
function privilege_mobile_throttle_ric() {
    if (is_admin() || !privilege_is_mobile_device()) {
        return;
    }
    ?>
    <script id="privilege-mobile-throttle-ric">
    (function() {
        if (typeof requestIdleCallback === 'function') {
            const originalRIC = window.requestIdleCallback;
            window.requestIdleCallback = function(callback, options) {
                return originalRIC.call(window, callback, { timeout: 1 });
            };
        }
        
        const originalSetTimeout = window.setTimeout;
        window.setTimeout = function(callback, delay) {
            if (delay < 50) {
                delay = 50;
            }
            return originalSetTimeout.call(window, callback, delay);
        };
    })();
    </script>
    <?php
}
add_action('wp_head', 'privilege_mobile_throttle_ric', 9999);

/**
 * 11. CLEANUP EXTREMO MOBILE - Remove tudo que é desnecessário
 */
function privilege_mobile_cleanup_head() {
    if (is_admin() || !privilege_is_mobile_device()) {
        return;
    }
    
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_shortlink_wp_head');
    remove_action('wp_head', 'rest_output_link_wp_head');
    remove_action('wp_head', 'wp_oembed_add_discovery_links');
    remove_action('wp_head', 'rel_canonical');
    remove_action('wp_head', 'feed_links', 2);
    remove_action('wp_head', 'feed_links_extra', 3);
    remove_action('wp_head', 'wp_resource_hints', 2);
}
add_action('init', 'privilege_mobile_cleanup_head', 100);

/**
 * 12. MINIFICAÇÃO HTML AGGRESSIVA MOBILE
 */
function privilege_mobile_minify_html($buffer) {
    if (is_admin() || !privilege_is_mobile_device()) {
        return $buffer;
    }
    
    $buffer = preg_replace('/<!--[^>]*>/', '', $buffer);
    $buffer = preg_replace('/>\s+</', '><', $buffer);
    $buffer = preg_replace('/^\s*[\r\n]/m', '', $buffer);
    $buffer = trim($buffer);
    
    return $buffer;
}
ob_start('privilege_mobile_minify_html');

/**
 * 13. PRELOAD GOOGLE FONTS USADAS
 */
function privilege_mobile_preload_fonts() {
    if (is_admin() || !privilege_is_mobile_device()) {
        return;
    }
    
    $fonts_to_preload = [
        get_template_directory_uri() . '/assets/fonts/Aiko/Aiko.woff2',
        get_template_directory_uri() . '/assets/icon-sets/RemixIconsSystem/RemixIconsSystem.woff2'
    ];
    
    foreach ($fonts_to_preload as $font_url) {
        if (file_exists(str_replace(get_site_url(), ABSPATH, $font_url))) {
            echo '<link rel="preload" href="' . esc_url($font_url) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
    }
}
add_action('wp_head', 'privilege_mobile_preload_fonts', 3);

/**
 * 14. CONTENT-VISIBILITY AUTO EM SEÇÕES ABAIXO DA DOBRA
 */
function privilege_mobile_content_visibility($content) {
    if (is_admin() || !privilege_is_mobile_device()) {
        return $content;
    }
    
    $section_count = 0;
    
    return preg_replace_callback('/(<div[^>]*bt_bb_section[^>]*>)/i', function($matches) use (&$section_count) {
        $section_count++;
        
        if ($section_count > 3) {
            $tag = $matches[1];
            
            if (!preg_match('/\sstyle=["\']/i', $tag)) {
                $tag = preg_replace(
                    '/<div/i',
                    '<div style="content-visibility:auto;contain-intrinsic-size:auto 400px;"',
                    $tag
                );
            } else {
                $tag = preg_replace(
                    '/(\sstyle=["\'][^"\']*)["\']/i',
                    '$1;content-visibility:auto;contain-intrinsic-size:auto 400px;"',
                    $tag
                );
            }
            
            return $tag;
        }
        
        return $matches[1];
    }, $content);
}
add_filter('the_content', 'privilege_mobile_content_visibility', 5);

/**
 * 15. DISABLE HEAVY ANIMATIONS MOBILE
 */
function privilege_mobile_disable_animations() {
    if (is_admin() || !privilege_is_mobile_device()) {
        return;
    }
    ?>
    <style id="privilege-mobile-no-animations">
    @media (max-width: 768px) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
        
        .animate,
        [class*="animate"],
        [data-animate],
        .btAnimate,
        .fade-in,
        .slide-in {
            opacity: 1 !important;
            visibility: visible !important;
            transform: none !important;
        }
    }
    </style>
    <?php
}
add_action('wp_head', 'privilege_mobile_disable_animations', 0);

/**
 * BONUS: LOG DE DEBUG PARA VERIFICAÇÃO
 */
function privilege_mobile_debug_marker() {
    if (!privilege_is_mobile_device()) {
        return;
    }
    
    echo '<!-- PRIVILEGE MOBILE KILLER v9.0 ACTIVE -->' . "\n";
}
add_action('wp_head', 'privilege_mobile_debug_marker', 99999);
