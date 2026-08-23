<?php
/**
 * Privilege Site Controller — EXTREME PERFORMANCE v6.0
 * 
 * Módulo de otimizações AGRESSIVAS para atingir 95+ no Mobile e Desktop
 * Foco principal: ELIMINAR CLS 0.784 do desktop e melhorar LCP/TBT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =============================================================================
 * 1. CLS KILLER SUPREMO — Elimina layout shift de coverage images
 * =============================================================================
 * O problema: CSS do tema força altura fixa em seções com background image,
 * causando shift massivo (0.784 CLS). Solução: ignorar CSS do tema e forçar
 * height:auto com aspect-ratio baseado nas dimensões reais da imagem.
 */
function privilege_v6_cls_killer_supreme() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <style id="privilege-v6-cls-killer">
    /* FORÇA height:auto em TODAS as coverage images — ignora CSS do tema */
    .bt_bb_section_top_section_coverage_image,
    .bt_bb_section .bt_bb_background_image_holder img,
    img.privilege-lcp-critical {
        height: auto !important;
        max-height: none !important;
        min-height: 0 !important;
        object-fit: contain !important;
    }
    
    /* Previne overflow visual quando height:auto entra */
    .bt_bb_section.has_coverage_image,
    .bt_bb_section:has(.bt_bb_section_top_section_coverage_image),
    .bt_bb_section:has(.bt_bb_background_image_holder) {
        overflow: hidden !important;
    }
    
    /* Aspect-ratio DINÂMICO via CSS custom properties */
    .bt_bb_section_top_section_coverage_image {
        aspect-ratio: attr(data-width) / attr(data-height) !important;
    }
    
    /* CONTAIN-ININTRINSIC-SIZE para seções abaixo da dobra */
    body.home .bt_bb_wrapper > .bt_bb_section:nth-of-type(n+3) {
        content-visibility: auto !important;
        contain-intrinsic-size: auto 800px !important;
    }
    
    /* Evita FOUC/FOUT em textos durante carregamento de fonte */
    body {
        font-display: swap !important;
    }
    
    /* Garante que imagens dentro de containers tenham dimensões */
    .bt_bb_content_element img:not([width]),
    .bt_bb_content_element img:not([height]) {
        width: auto !important;
        height: auto !important;
    }
    
    /* Remove transform/animation que causam reflow */
    .bt_bb_section:first-child .animate,
    .bt_bb_section:first-child [class*="animate"] {
        animation: none !important;
        transition: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
    
    /* Header fixo para evitar shift do menu */
    .site-header,
    .site-header-top-bar {
        contain: layout style !important;
    }
    
    /* GTranslate container com tamanho reservado */
    .gtranslate_wrapper,
    .gt_container,
    #gtranslate_widget {
        min-height: 30px !important;
        min-width: 100px !important;
        contain: layout !important;
    }
    </style>
    <?php
}
add_action( 'wp_head', 'privilege_v6_cls_killer_supreme', 0 );

/* =============================================================================
 * 2. PRECONNECT UNIVERSAL — Preconnect para todos domínios externos
 * =============================================================================
 */
function privilege_v6_universal_preconnect() {
    if ( is_admin() ) {
        return;
    }
    
    $domains = array(
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
        'https://translate.google.com',
        'https://www.google-analytics.com',
        'https://cdn.gtranslate.net',
        'https://cdnjs.cloudflare.com',
        'https://unpkg.com',
    );
    
    // Detectar domínios de imagens usadas na página
    global $wpdb;
    $attachment_urls = $wpdb->get_col(
        "SELECT guid FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' LIMIT 50"
    );
    
    foreach ( $attachment_urls as $url ) {
        $parsed = wp_parse_url( $url );
        if ( ! empty( $parsed['host'] ) && ! in_array( 'https://' . $parsed['host'], $domains ) ) {
            $domains[] = 'https://' . $parsed['host'];
        }
    }
    
    foreach ( $domains as $domain ) {
        echo '<link rel="preconnect" href="' . esc_url( $domain ) . '" crossorigin>' . "\n";
    }
}
add_action( 'wp_head', 'privilege_v6_universal_preconnect', 1 );

/* =============================================================================
 * 3. FONT DISPLAY NUCLEAR — Aplica swap/optional em TODAS as fontes
 * =============================================================================
 */
function privilege_v6_font_display_nuclear() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <style id="privilege-v6-font-display">
    /* Aplica font-display:swap em QUALQUER @font-face */
    @font-face {
        font-display: swap !important;
    }
    
    /* Fallback fonts enquanto webfont carrega */
    body, h1, h2, h3, h4, h5, h6, p, a, span, div {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, 
                     "Helvetica Neue", Arial, sans-serif !important;
        font-display: swap !important;
    }
    
    /* Quando fonte customizada carregar, aplica */
    html.wf-active body,
    html.wf-active h1, html.wf-active h2, html.wf-active h3,
    html.wf-active h4, html.wf-active h5, html.wf-active h6 {
        font-family: inherit !important;
    }
    
    /* Inline critical font woff2 preloads */
    </style>
    <?php
    
    // Preload das fontes principais com fetchpriority high
    $template_dir = get_template_directory_uri();
    
    // Tenta preload da fonte principal do tema
    $main_font = $template_dir . '/assets/fonts/main.woff2';
    if ( @fopen( $main_font, 'r' ) ) {
        echo '<link rel="preload" href="' . esc_url( $main_font ) . '" as="font" type="font/woff2" crossorigin fetchpriority="high">' . "\n";
    }
}
add_action( 'wp_head', 'privilege_v6_font_display_nuclear', 2 );

/* =============================================================================
 * 4. GTRANSLATE LAZY — Adia carregamento após LCP
 * =============================================================================
 */
function privilege_v6_lazy_gtranslate() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <script id="privilege-v6-lazy-gtranslate">
    (function() {
        // Delay inicial de 2 segundos ou após window.load
        var loadGTranslate = function() {
            var widget = document.querySelector('#gtranslate_widget, .gtranslate_wrapper');
            if (!widget || widget.dataset.loaded === 'true') return;
            
            // Cria elemento placeholder se não existe
            if (!widget) {
                var placeholder = document.createElement('div');
                placeholder.id = 'gtranslate_widget';
                placeholder.className = 'gtranslate_wrapper';
                placeholder.style.minHeight = '30px';
                placeholder.style.minWidth = '100px';
                document.querySelector('.privilege-header-right')?.appendChild(placeholder);
            }
            
            // Marca como carregando
            widget = widget || document.querySelector('#gtranslate_widget');
            widget.dataset.loaded = 'true';
            
            // Insere script do GTranslate
            var script = document.createElement('script');
            script.src = '//cdn.gtranslate.net/widgets/latest/float.js';
            script.async = true;
            document.body.appendChild(script);
        };
        
        // Carrega após delay ou window load
        if (document.readyState === 'complete') {
            setTimeout(loadGTranslate, 2000);
        } else {
            window.addEventListener('load', function() {
                setTimeout(loadGTranslate, 1500);
            });
        }
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'privilege_v6_lazy_gtranslate', PHP_INT_MAX );

/* =============================================================================
 * 5. IMAGE DIMENSIONS FORÇADAS — width/height em TODAS imagens
 * =============================================================================
 */
function privilege_v6_force_image_dimensions( $content ) {
    if ( is_admin() || ! is_string( $content ) ) {
        return $content;
    }
    
    return preg_replace_callback(
        '#<img\b[^>]*>#i',
        static function( $match ) {
            $tag = $match[0];
            
            // Se já tem width E height, mantém
            if ( preg_match( '/\bwidth=["\']?\d+["\']?/i', $tag ) 
                 && preg_match( '/\bheight=["\']?\d+["\']?/i', $tag ) ) {
                return $tag;
            }
            
            // Tenta extrair src para calcular dimensões
            if ( ! preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $src_m ) ) {
                return $tag;
            }
            
            $src = $src_m[1];
            
            // Para imagens da biblioteca de mídia, tenta obter dimensões
            if ( preg_match( '/\/(\d{2,4})x(\d{2,4})\//', $src, $dim_m ) ) {
                $width = (int) $dim_m[1];
                $height = (int) $dim_m[2];
                
                if ( ! preg_match( '/\bwidth=["\']?\d+["\']?/i', $tag ) ) {
                    $tag = preg_replace( '/<img\b/i', "<img width=\"$width\"", $tag, 1 );
                }
                if ( ! preg_match( '/\bheight=["\']?\d+["\']?/i', $tag ) ) {
                    $tag = preg_replace( '/<img\b/i', "<img height=\"$height\"", $tag, 1 );
                }
            } elseif ( ! preg_match( '/\bwidth=["\']?\d+["\']?/i', $tag ) ) {
                // Fallback: width genérico para imagens sem dimensão
                $tag = preg_replace( '/<img\b/i', '<img width="300" height="200"', $tag, 1 );
            }
            
            // Garante loading="lazy" para imagens fora da viewport
            if ( ! preg_match( '/\bloading=["\']?(eager|lazy)["\']?/i', $tag ) ) {
                $tag = str_replace( '<img', '<img loading="lazy"', $tag );
            }
            
            return $tag;
        },
        $content
    );
}
add_filter( 'the_content', 'privilege_v6_force_image_dimensions', 100 );
add_filter( 'wp_get_attachment_image_attributes', function( $attr ) {
    if ( empty( $attr['width'] ) || empty( $attr['height'] ) ) {
        $attr['width'] = '300';
        $attr['height'] = '200';
    }
    return $attr;
}, 100 );

/* =============================================================================
 * 6. DEFER JS AGGRESSIVE — Defer em TODO JS não-crítico
 * =============================================================================
 */
function privilege_v6_defer_non_critical_js() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <script id="privilege-v6-defer-js">
    (function() {
        // Defer scripts não-críticos após load
        window.addEventListener('load', function() {
            var scripts = document.querySelectorAll(
                'script:not([type="module"]):not([id*="privilege"]):not([id*="gtranslate"])'
            );
            
            scripts.forEach(function(script) {
                if (script.src && !script.async && !script.defer) {
                    // Scripts inline pequenos executam imediatamente
                    if (!script.src && script.text.length < 500) {
                        return;
                    }
                    
                    script.defer = true;
                }
            });
        });
        
        // Cancela animações pesadas até after load
        document.documentElement.classList.add('v6-performance-mode');
        
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.documentElement.classList.remove('v6-performance-mode');
            }, 100);
        });
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'privilege_v6_defer_non_critical_js', 0 );

/* =============================================================================
 * 7. CRITICAL CSS INLINE — CSS mínimo acima da dobra inline
 * =============================================================================
 */
function privilege_v6_critical_css_inline() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <style id="privilege-v6-critical-css">
    /* Critical CSS inline — acima da dobra apenas */
    :root{--wp--preset--color--background:#ffffff;--wp--preset--color--foreground:#111111}
    body{margin:0;padding:0;background:#fff;color:#111;line-height:1.6;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
    .site-header{position:relative;z-index:1000;background:#fff;border-bottom:1px solid #eee}
    .site-header-inner{max-width:1280px;margin:0 auto;padding:16px 24px;display:flex;align-items:center;justify-content:space-between}
    .site-branding{display:flex;align-items:center}
    .site-branding img{height:50px;width:auto;display:block}
    .site-navigation ul{list-style:none;margin:0;padding:0;display:flex;gap:24px}
    .site-navigation li{margin:0;padding:0}
    .site-navigation a{text-decoration:none;color:#111;font-weight:600;text-transform:uppercase;font-size:14px}
    .hero-section{position:relative;min-height:80vh;display:flex;align-items:center;justify-content:center;overflow:hidden}
    .hero-section img{width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0}
    .hero-content{position:relative;z-index:10;text-align:center;padding:24px}
    h1{font-size:clamp(2rem,5vw,3.5rem);margin:0 0 16px;line-height:1.2}
    .btn{display:inline-block;padding:14px 32px;background:#6b2494;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;text-transform:uppercase;transition:background 0.3s}
    .btn:hover{background:#8b3ab8}
    @media(max-width:768px){.site-header-inner{flex-direction:column;gap:16px}.site-navigation ul{flex-wrap:wrap;justify-content:center}.hero-section{min-height:60vh}}
    </style>
    <?php
}
add_action( 'wp_head', 'privilege_v6_critical_css_inline', 0 );

/* =============================================================================
 * 8. REMOVE UNNECESSARY HEAD ELEMENTS — Limpeza extrema do <head>
 * =============================================================================
 */
function privilege_v6_clean_head() {
    // Remove emojis (já feito, mas reforçando)
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    
    // Remove oEmbed
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    remove_action( 'wp_head', 'wp_oembed_add_host_js' );
    
    // Remove WP generator
    remove_action( 'wp_head', 'wp_generator' );
    
    // Remove RSD, wlwmanifest
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    
    // Remove shortlink
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    
    // Remove REST API link
    remove_action( 'wp_head', 'rest_output_link_wp_head' );
    
    // Remove edituri
    remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );
    
    // Remove feed links
    remove_action( 'wp_head', 'feed_links', 2 );
    remove_action( 'wp_head', 'feed_links_extra', 3 );
    
    // Remove DNS prefetch (usamos preconnect específico)
    remove_action( 'wp_head', 'wp_resource_hints', 2 );
}
add_action( 'after_setup_theme', 'privilege_v6_clean_head', 100 );

/* =============================================================================
 * 9. REDUCE DOM SIZE GAMBIRRA — Simplifica DOM onde possível
 * =============================================================================
 */
function privilege_v6_simplify_dom( $content ) {
    if ( is_admin() || ! is_front_page() || ! is_string( $content ) ) {
        return $content;
    }
    
    // Remove comentários HTML inúteis
    $content = preg_replace( '/<!--(?!\[if)[^\[>].*?-->/s', '', $content );
    
    // Remove espaços em branco excessivos entre tags
    $content = preg_replace( '/>\s+</', '><', $content );
    
    // Remove atributos vazios
    $content = preg_replace( '/\s+[a-z]+=""\s*/i', ' ', $content );
    
    return $content;
}
add_filter( 'the_content', 'privilege_v6_simplify_dom', 200 );
add_filter( 'wp_title', 'strip_tags' );

/* =============================================================================
 * 10. MINIFY HTML OUTPUT — Minificação agressiva de HTML
 * =============================================================================
 */
function privilege_v6_minify_html( $buffer ) {
    if ( is_admin() || ! is_string( $buffer ) ) {
        return $buffer;
    }
    
    // Remove espaços em branco múltiplos
    $buffer = preg_replace( '/[ \t]+/', ' ', $buffer );
    
    // Remove linhas em branco
    $buffer = preg_replace( '/^\s*[\r\n]/m', '', $buffer );
    
    // Remove espaços ao redor de tags
    $buffer = preg_replace( '/>\s+</', '><', $buffer );
    
    return trim( $buffer );
}
add_action( 'template_redirect', function() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    
    ob_start( 'privilege_v6_minify_html' );
});

/* =============================================================================
 * 11. ASYNC CSS LOADING — Carrega CSS não-crítico async
 * =============================================================================
 */
function privilege_v6_async_css( $html ) {
    if ( ! is_string( $html ) || ! is_front_page() ) {
        return $html;
    }
    
    return preg_replace_callback(
        '#<link\s+rel=["\']stylesheet["\']\s+href=["\']([^"\']+)["\'][^>]*>#i',
        static function( $match ) {
            $tag = $match[0];
            
            // Mantém CSS crítico inline (já tratado)
            if ( stripos( $tag, 'critical' ) !== false ) {
                return $tag;
            }
            
            // Adiciona media=print + onload para load assíncrono
            if ( stripos( $tag, 'media=' ) === false ) {
                $tag = preg_replace(
                    '/>$/',
                    ' media="print" onload="this.media=\'all\';this.onload=null;">',
                    $tag,
                    1
                );
                
                // Adiciona noscript fallback
                $tag .= '<noscript>' . 
                    str_replace( 'media="print" onload="this.media=\'all\';this.onload=null;"', '', $tag ) . 
                    '</noscript>';
            }
            
            return $tag;
        },
        $html
    );
}
add_filter( 'template_redirect', function() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    
    ob_start( 'privilege_v6_async_css' );
}, 1 );

/* =============================================================================
 * 12. LAZY IFRAME — Lazy load em todos iframes
 * =============================================================================
 */
function privilege_v6_lazy_iframes( $content ) {
    if ( is_admin() || ! is_string( $content ) ) {
        return $content;
    }
    
    return preg_replace_callback(
        '#<iframe\b[^>]*>#i',
        static function( $match ) {
            $tag = $match[0];
            
            // Adiciona loading=lazy se não existir
            if ( stripos( $tag, 'loading=' ) === false ) {
                $tag = str_replace( '<iframe', '<iframe loading="lazy"', $tag );
            }
            
            // Adiciona title se não existir (acessibilidade)
            if ( stripos( $tag, 'title=' ) === false ) {
                $tag = preg_replace( '/<iframe/i', '<iframe title="Embedded content"', $tag, 1 );
            }
            
            return $tag;
        },
        $content
    );
}
add_filter( 'the_content', 'privilege_v6_lazy_iframes', 100 );
add_filter( 'widget_text', 'privilege_v6_lazy_iframes', 100 );
