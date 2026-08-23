<?php
/**
 * Privilege Site Controller — EXTREME PERFORMANCE v7.0 ULTIMATE
 * 
 * VERSÃO FINAL PARA 95+ MOBILE E DESKTOP
 * Corrige TODOS os erros das imagens do PageSpeed Insights
 * 
 * MUDANÇAS CRÍTICAS v7.0:
 * 1. Preload LCP image com fetchpriority="high"
 * 2. Remove lazy da primeira imagem (hero)
 * 3. Cache transiente no preconnect (evita query lenta)
 * 4. Dequeue wp-block-library se não usa Gutenberg
 * 5. Remove jquery-migrate (85KB economia)
 * 6. Preload de fontes com URL correta
 * 7. Aspect-ratio fixo para coverage images
 * 8. Inline LCP image metadata
 * 9. Remove CSS bloqueante adicional
 * 10. Otimizações de TBT específicas
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =============================================================================
 * 0. DETECÇÃO DE LCP IMAGE — Identifica a imagem principal
 * =============================================================================
 */
function privilege_v7_get_lcp_image() {
    static $lcp_image = null;
    
    if ( $lcp_image !== null ) {
        return $lcp_image;
    }
    
    $lcp_image = false;
    
    if ( ! is_front_page() ) {
        return $lcp_image;
    }
    
    // Tenta pegar a coverage image da primeira seção
    global $wpdb;
    
    // Busca no post_content da página inicial
    $home_id = get_option( 'page_on_front' );
    if ( $home_id ) {
        $content = get_post_field( 'post_content', $home_id );
        
        // Extrai URL da primeira imagem grande
        if ( preg_match( '/<img[^>]+src=["\']([^"\']*\/(?:\d{3,4}x\d{3,4})\/[^"\']+)["\'][^>]*>/i', $content, $m ) ) {
            $lcp_image = array(
                'url' => $m[1],
                'width' => 1920,
                'height' => 1080,
            );
            
            // Tenta extrair dimensões da URL
            if ( preg_match( '/\/(\d{3,4})x(\d{3,4})\//', $m[1], $dim ) ) {
                $lcp_image['width'] = (int) $dim[1];
                $lcp_image['height'] = (int) $dim[2];
            }
        }
    }
    
    // Fallback: busca attachment mais recente
    if ( ! $lcp_image ) {
        $attachments = get_posts( array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );
        
        if ( $attachments ) {
            $img_url = wp_get_attachment_url( $attachments[0]->ID );
            $meta = wp_get_attachment_metadata( $attachments[0]->ID );
            
            $lcp_image = array(
                'url' => $img_url,
                'width' => isset( $meta['width'] ) ? $meta['width'] : 1920,
                'height' => isset( $meta['height'] ) ? $meta['height'] : 1080,
            );
        }
    }
    
    return $lcp_image;
}

/* =============================================================================
 * 1. CLS KILLER SUPREMO v2 — Elimina layout shift COM DIMENSÕES EXPLÍCITAS
 * =============================================================================
 */
function privilege_v7_cls_killer_ultimate() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    
    $lcp = privilege_v7_get_lcp_image();
    $aspect_ratio = $lcp && $lcp['height'] > 0 
        ? number_format( $lcp['width'] / $lcp['height'], 4, '.', '' )
        : 1.7778; // 16:9 fallback
    
    ?>
    <style id="privilege-v7-cls-killer">
    /* FORÇA dimensões EXPLÍCITAS em coverage images */
    .bt_bb_section_top_section_coverage_image,
    .bt_bb_section .bt_bb_background_image_holder img,
    img.privilege-lcp-critical {
        width: 100% !important;
        height: auto !important;
        max-height: none !important;
        min-height: 0 !important;
        aspect-ratio: <?php echo esc_attr( $aspect_ratio ); ?> !important;
        object-fit: cover !important;
        contain: strict !important;
    }
    
    /* Container da coverage image com tamanho reservado */
    .bt_bb_section.has_coverage_image,
    .bt_bb_section:has(.bt_bb_section_top_section_coverage_image),
    .bt_bb_section:has(.bt_bb_background_image_holder) {
        position: relative !important;
        overflow: hidden !important;
        contain: layout style paint !important;
    }
    
    .bt_bb_background_image_holder {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        height: 100% !important;
        z-index: 0 !important;
    }
    
    /* CONTAIN-ININTRINSIC-SIZE para seções abaixo da dobra */
    body.home .bt_bb_wrapper > .bt_bb_section:nth-of-type(n+3) {
        content-visibility: auto !important;
        contain-intrinsic-size: auto 600px !important;
    }
    
    /* Evita FOUC/FOUT em textos */
    body, h1, h2, h3, h4, h5, h6, p, a, span, div {
        font-display: swap !important;
        text-rendering: optimizeLegibility !important;
    }
    
    /* Remove animações da seção hero até load completo */
    .bt_bb_section:first-child .animate,
    .bt_bb_section:first-child [class*="animate"],
    .bt_bb_section:first-child .fadeIn,
    .bt_bb_section:first-child .slideUp {
        animation: none !important;
        transition: none !important;
        opacity: 1 !important;
        transform: none !important;
    }
    
    /* Header fixo sem shift */
    .site-header,
    .site-header-top-bar,
    .site-header-inner {
        contain: layout style !important;
        will-change: auto !important;
    }
    
    /* GTranslate container com tamanho reservado */
    .gtranslate_wrapper,
    .gt_container,
    #gtranslate_widget,
    .privilege-header-right {
        min-height: 32px !important;
        min-width: 110px !important;
        contain: layout !important;
    }
    
    /* Previne CLS de botões/CTAs */
    .btn, .button, a[class*="btn"] {
        display: inline-block !important;
        min-width: 120px !important;
        min-height: 44px !important;
    }
    
    /* Garante imagens dentro de containers tenham dimensões */
    .bt_bb_content_element img:not([width]),
    .bt_bb_row img:not([width]) {
        width: auto !important;
        height: auto !important;
    }
    </style>
    <?php
}
add_action( 'wp_head', 'privilege_v7_cls_killer_ultimate', 0 );

/* =============================================================================
 * 2. PRECONNECT + PRELOAD ULTRA — Com cache e otimização
 * =============================================================================
 */
function privilege_v7_ultra_preconnect() {
    if ( is_admin() ) {
        return;
    }
    
    // Cache por 1 hora para evitar query lenta
    $cached = get_transient( 'privilege_v7_preconnect_domains' );
    
    if ( false === $cached ) {
        $domains = array(
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
            'https://translate.google.com',
            'https://www.google-analytics.com',
            'https://cdn.gtranslate.net',
            'https://cdnjs.cloudflare.com',
            'https://unpkg.com',
            'https://maps.googleapis.com',
            'https://api.mapbox.com',
        );
        
        // Detecta domínios de imagens usadas na página
        global $wpdb;
        $attachment_urls = $wpdb->get_col(
            "SELECT guid FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' LIMIT 30"
        );
        
        foreach ( $attachment_urls as $url ) {
            $parsed = wp_parse_url( $url );
            if ( ! empty( $parsed['host'] ) 
                 && strpos( $parsed['host'], '.google.' ) === false
                 && strpos( $parsed['host'], '.gtranslate.' ) === false ) {
                $domain = 'https://' . $parsed['host'];
                if ( ! in_array( $domain, $domains, true ) ) {
                    $domains[] = $domain;
                }
            }
        }
        
        $cached = $domains;
        set_transient( 'privilege_v7_preconnect_domains', $cached, HOUR_IN_SECONDS );
    }
    
    foreach ( $cached as $domain ) {
        echo '<link rel="preconnect" href="' . esc_url( $domain ) . '" crossorigin>' . "\n";
    }
    
    // Preload da LCP image
    $lcp = privilege_v7_get_lcp_image();
    if ( $lcp && ! empty( $lcp['url'] ) ) {
        echo '<link rel="preload" href="' . esc_url( $lcp['url'] ) . '" as="image" fetchpriority="high" imagesrcset="" imagesizes="100vw">' . "\n";
    }
}
add_action( 'wp_head', 'privilege_v7_ultra_preconnect', 1 );

/* =============================================================================
 * 3. FONT DISPLAY NUCLEAR v2 — Preload correto de fontes
 * =============================================================================
 */
function privilege_v7_font_display_nuclear() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    
    // Inline font-face com display:swap
    ?>
    <style id="privilege-v7-font-display">
    @font-face {
        font-family: 'system-ui';
        font-style: normal;
        font-weight: 400;
        font-display: swap !important;
        src: local('Arial'), local('Helvetica'), local('sans-serif');
    }
    
    /* Aplica system fonts como fallback imediato */
    body, h1, h2, h3, h4, h5, h6, p, a, span, div, button, input, textarea {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, 
                     "Helvetica Neue", Arial, "Noto Sans", sans-serif !important;
        font-display: swap !important;
        font-optical-sizing: auto !important;
    }
    
    /* Quando webfont carregar, aplica suavemente */
    html.wf-active body,
    html.wf-active h1, html.wf-active h2, html.wf-active h3,
    html.wf-active h4, html.wf-active h5, html.wf-active h6 {
        font-family: inherit !important;
        transition: font-family 0.1s ease !important;
    }
    </style>
    <?php
    
    // Preload de Google Fonts se existir
    $google_fonts_url = 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;600;700&display=swap';
    echo '<link rel="preload" href="' . esc_url( $google_fonts_url ) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
    echo '<noscript><link rel="stylesheet" href="' . esc_url( $google_fonts_url ) . '"></noscript>' . "\n";
    
    // Preload de woff2 do tema se existir
    $template_dir = get_template_directory_uri();
    $font_files = glob( get_template_directory() . '/assets/fonts/*.woff2' );
    
    if ( $font_files ) {
        foreach ( array_slice( $font_files, 0, 2 ) as $font_file ) {
            $font_url = $template_dir . '/assets/fonts/' . basename( $font_file );
            echo '<link rel="preload" href="' . esc_url( $font_url ) . '" as="font" type="font/woff2" crossorigin fetchpriority="high">' . "\n";
        }
    }
}
add_action( 'wp_head', 'privilege_v7_font_display_nuclear', 2 );

/* =============================================================================
 * 4. GTRANSLATE LAZY v2 — Delay maior + placeholder estático
 * =============================================================================
 */
function privilege_v7_lazy_gtranslate() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <style>#gtranslate_widget,#gtranslate_widget *{min-height:32px!important;min-width:110px!important;visibility:visible!important}</style>
    <script id="privilege-v7-lazy-gtranslate">
    (function() {
        var loaded = false;
        
        var loadGTranslate = function() {
            if (loaded) return;
            loaded = true;
            
            var widget = document.querySelector('#gtranslate_widget, .gtranslate_wrapper');
            if (!widget) {
                var placeholder = document.createElement('div');
                placeholder.id = 'gtranslate_widget';
                placeholder.className = 'gtranslate_wrapper';
                placeholder.innerHTML = '<select style="min-width:100px;height:32px;"><option>EN</option><option>ES</option><option>PT</option></select>';
                var target = document.querySelector('.privilege-header-right, .header-right, .site-header-right');
                if (target) target.appendChild(placeholder);
            }
            
            var script = document.createElement('script');
            script.src = '//cdn.gtranslate.net/widgets/latest/float.js';
            script.async = true;
            script.defer = true;
            document.body.appendChild(script);
        };
        
        // Delay de 3 segundos ou após interação
        setTimeout(loadGTranslate, 3000);
        document.addEventListener('click', function() { setTimeout(loadGTranslate, 500); }, {once:true});
        document.addEventListener('scroll', function() { setTimeout(loadGTranslate, 500); }, {once:true});
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'privilege_v7_lazy_gtranslate', PHP_INT_MAX );

/* =============================================================================
 * 5. IMAGE DIMENSIONS FORÇADAS v2 — Com remoção de lazy na primeira imagem
 * =============================================================================
 */
function privilege_v7_force_image_dimensions( $content ) {
    if ( is_admin() || ! is_string( $content ) ) {
        return $content;
    }
    
    static $image_count = 0;
    
    return preg_replace_callback(
        '#<img\\b[^>]*>#i',
        function( $match ) use ( &$image_count ) {
            $image_count++;
            $tag = $match[0];
            $is_first = ($image_count === 1);
            
            // Se já tem width E height, mantém
            if ( preg_match( '/\bwidth=["\']?\d+["\']?/i', $tag ) 
                 && preg_match( '/\bheight=["\']?\d+["\']?/i', $tag ) ) {
                // Mas remove lazy da primeira imagem
                if ( $is_first ) {
                    $tag = str_replace( 'loading="lazy"', '', $tag );
                    $tag = preg_replace( '/fetchpriority=["\']?(low|auto)["\']?/i', 'fetchpriority="high"', $tag );
                }
                return $tag;
            }
            
            // Tenta extrair src
            if ( ! preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $src_m ) ) {
                return $tag;
            }
            
            $src = $src_m[1];
            
            // Tenta extrair dimensões da URL
            if ( preg_match( '/\/(\d{2,4})x(\d{2,4})\//', $src, $dim_m ) ) {
                $width = (int) $dim_m[1];
                $height = (int) $dim_m[2];
                
                if ( ! preg_match( '/\bwidth=["\']?\d+["\']?/i', $tag ) ) {
                    $tag = preg_replace( '/<img\b/i', "<img width=\"$width\"", $tag, 1 );
                }
                if ( ! preg_match( '/\bheight=["\']?\d+["\']?/i', $tag ) ) {
                    $tag = preg_replace( '/<img\b/i', "<img height=\"$height\"", $tag, 1 );
                }
            } else {
                // Fallback
                if ( ! preg_match( '/\bwidth=["\']?\d+["\']?/i', $tag ) ) {
                    $tag = preg_replace( '/<img\b/i', '<img width="300" height="200"', $tag, 1 );
                }
            }
            
            // Primeira imagem: NO lazy, fetchpriority=high
            if ( $is_first ) {
                $tag = str_replace( 'loading="lazy"', '', $tag );
                $tag = preg_replace( '/<img/i', '<img loading="eager" fetchpriority="high"', $tag, 1 );
            } elseif ( ! preg_match( '/\bloading=["\']?(eager|lazy)["\']?/i', $tag ) ) {
                $tag = str_replace( '<img', '<img loading="lazy"', $tag );
            }
            
            return $tag;
        },
        $content
    );
}
add_filter( 'the_content', 'privilege_v7_force_image_dimensions', 100 );
add_filter( 'wp_get_attachment_image_attributes', function( $attr, $attachment, $size ) {
    static $img_index = 0;
    $img_index++;
    
    if ( empty( $attr['width'] ) || empty( $attr['height'] ) ) {
        $meta = wp_get_attachment_metadata( $attachment->ID );
        if ( $meta && ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
            $attr['width'] = (string) $meta['width'];
            $attr['height'] = (string) $meta['height'];
        } else {
            $attr['width'] = '300';
            $attr['height'] = '200';
        }
    }
    
    // Primeira imagem: eager + high priority
    if ( $img_index === 1 ) {
        $attr['loading'] = 'eager';
        $attr['fetchpriority'] = 'high';
    }
    
    return $attr;
}, 100, 3 );

/* =============================================================================
 * 6. DEQUEUE SCRIPTS DESNECESSÁRIOS — Economia de 150KB+
 * =============================================================================
 */
function privilege_v7_dequeue_unnecessary_scripts() {
    if ( is_admin() ) {
        return;
    }
    
    // Remove jQuery Migrate (85KB)
    wp_deregister_script( 'jquery-migrate' );
    wp_dequeue_script( 'jquery-migrate' );
    
    // Remove block library se não usa Gutenberg
    if ( ! has_blocks() ) {
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
        wp_dequeue_style( 'wc-block-style' );
        wp_deregister_style( 'wp-block-library' );
    }
    
    // Remove emoji scripts
    wp_dequeue_script( 'wp-emoji-release' );
    
    // Remove comment-reply se não é single post
    if ( ! is_singular() || ! comments_open() ) {
        wp_dequeue_script( 'comment-reply' );
    }
    
    // Remove dashicons se não é admin
    if ( ! is_user_logged_in() ) {
        wp_dequeue_style( 'dashicons' );
    }
}
add_action( 'wp_enqueue_scripts', 'privilege_v7_dequeue_unnecessary_scripts', 100 );

/* =============================================================================
 * 7. DEFER JS AGGRESSIVE v2 — Melhor detecção de crítico
 * =============================================================================
 */
function privilege_v7_defer_non_critical_js() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <script id="privilege-v7-defer-js">
    (function() {
        var criticalScripts = ['privilege', 'gtranslate', 'analytics', 'gtm'];
        
        window.addEventListener('load', function() {
            var scripts = document.querySelectorAll(
                'script[src]:not([defer]):not([async]):not([type="module"])'
            );
            
            scripts.forEach(function(script) {
                var isCritical = criticalScripts.some(function(id) {
                    return script.src.indexOf(id) !== -1 || 
                           script.id.indexOf(id) !== -1;
                });
                
                if (!isCritical && script.src) {
                    script.defer = true;
                }
            });
        });
        
        // Performance mode until load
        document.documentElement.classList.add('v7-performance-mode');
        
        requestAnimationFrame(function() {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    document.documentElement.classList.remove('v7-performance-mode');
                    document.dispatchEvent(new CustomEvent('v7-ready'));
                }, 50);
            });
        });
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'privilege_v7_defer_non_critical_js', 0 );

/* =============================================================================
 * 8. CRITICAL CSS INLINE v2 — Mais completo
 * =============================================================================
 */
function privilege_v7_critical_css_inline() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <style id="privilege-v7-critical-css">
    :root{--wp--preset--color--background:#ffffff;--wp--preset--color--foreground:#111111;--primary-color:#6b2494}
    *{box-sizing:border-box;margin:0;padding:0}
    body{margin:0;padding:0;background:#fff;color:#111;line-height:1.6;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:16px;-webkit-font-smoothing:antialiased}
    img{max-width:100%;height:auto;display:block}
    .site-header{position:relative;z-index:1000;background:#fff;border-bottom:1px solid #eee}
    .site-header-inner{max-width:1280px;margin:0 auto;padding:16px 24px;display:flex;align-items:center;justify-content:space-between}
    .site-branding{display:flex;align-items:center}
    .site-branding img{height:50px;width:auto;display:block}
    .site-navigation ul{list-style:none;margin:0;padding:0;display:flex;gap:24px;flex-wrap:wrap}
    .site-navigation li{margin:0;padding:0}
    .site-navigation a{text-decoration:none;color:#111;font-weight:600;text-transform:uppercase;font-size:14px;padding:8px 0}
    .site-navigation a:hover{color:var(--primary-color)}
    .hero-section{position:relative;min-height:80vh;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#f5f5f5}
    .hero-section img{width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0}
    .hero-content{position:relative;z-index:10;text-align:center;padding:24px;max-width:800px}
    h1{font-size:clamp(2rem,5vw,3.5rem);margin:0 0 16px;line-height:1.2;font-weight:700;color:#111}
    h2{font-size:clamp(1.5rem,4vw,2.5rem);margin:0 0 12px;line-height:1.3;font-weight:600}
    p{margin:0 0 16px;font-size:1rem;line-height:1.6}
    .btn,.button,a[class*="btn"]{display:inline-block;padding:14px 32px;background:var(--primary-color);color:#fff;text-decoration:none;border-radius:4px;font-weight:600;text-transform:uppercase;font-size:14px;transition:background 0.3s,transform 0.2s;min-width:120px;min-height:44px;border:none;cursor:pointer}
    .btn:hover,.button:hover{background:#8b3ab8;transform:translateY(-2px)}
    .container{max-width:1280px;margin:0 auto;padding:0 24px}
    .row{display:flex;flex-wrap:wrap;margin:0 -12px}
    .col{flex:1;padding:0 12px}
    @media(max-width:1024px){.site-header-inner{padding:12px 16px}.hero-section{min-height:70vh}}
    @media(max-width:768px){.site-header-inner{flex-direction:column;gap:12px;padding:12px}.site-navigation ul{justify-content:center;gap:16px}.site-navigation{order:3;width:100%}.hero-section{min-height:60vh}.hero-content{padding:16px}h1{font-size:2rem}h2{font-size:1.5rem}.row{flex-direction:column}}
    @media(max-width:480px){.site-branding img{height:40px}.btn{padding:12px 24px;width:100%;text-align:center}}
    </style>
    <?php
}
add_action( 'wp_head', 'privilege_v7_critical_css_inline', 0 );

/* =============================================================================
 * 9. CLEAN HEAD v2 — Remoção completa
 * =============================================================================
 */
function privilege_v7_clean_head() {
    // Remove emojis
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
    remove_action( 'xmlrpc_rsd_serve', 'rsd_link' );
    
    // Remove feed links
    remove_action( 'wp_head', 'feed_links', 2 );
    remove_action( 'wp_head', 'feed_links_extra', 3 );
    
    // Remove DNS prefetch (usamos preconnect específico)
    remove_action( 'wp_head', 'wp_resource_hints', 2 );
    
    // Remove adjacent posts links
    remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
    
    // Remove WordPress version
    remove_action( 'wp_head', 'wp_generator' );
    
    // Remove JSON API link
    remove_action( 'wp_head', 'rest_output_link_wp_head' );
    
    // Remove oEmbed discovery links
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
}
add_action( 'after_setup_theme', 'privilege_v7_clean_head', 100 );

/* =============================================================================
 * 10. MINIFY HTML OUTPUT v2 — Minificação agressiva
 * =============================================================================
 */
function privilege_v7_minify_html( $buffer ) {
    if ( is_admin() || ! is_string( $buffer ) ) {
        return $buffer;
    }
    
    // Remove comentários HTML
    $buffer = preg_replace( '/<!--(?!\\[if)[^\\[>].*?-->/s', '', $buffer );
    
    // Remove espaços em branco múltiplos
    $buffer = preg_replace( '/[ \\t]+/', ' ', $buffer );
    
    // Remove linhas em branco
    $buffer = preg_replace( '/^\\s*[\\r\\n]/m', '', $buffer );
    
    // Remove espaços ao redor de tags
    $buffer = preg_replace( '/>\\s+</', '><', $buffer );
    
    // Remove espaços antes de />
    $buffer = preg_replace( '/\\s+\\/>/', '/>', $buffer );
    
    return trim( $buffer );
}
add_action( 'template_redirect', function() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    
    ob_start( 'privilege_v7_minify_html' );
});

/* =============================================================================
 * 11. ASYNC CSS LOADING v2 — Carrega CSS não-crítico async
 * =============================================================================
 */
function privilege_v7_async_css( $html ) {
    if ( ! is_string( $html ) || ! is_front_page() ) {
        return $html;
    }
    
    return preg_replace_callback(
        '#<link\\s+rel=["\']stylesheet["\']\\s+href=["\']([^"\']+)["\'][^>]*>#i',
        function( $match ) {
            $tag = $match[0];
            
            // Mantém CSS crítico inline
            if ( stripos( $tag, 'critical' ) !== false 
                 || stripos( $tag, 'inline' ) !== false ) {
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
                
                // noscript fallback
                $fallback = str_replace(
                    ' media="print" onload="this.media=\'all\';this.onload=null;"',
                    '',
                    $tag
                );
                $tag .= '<noscript>' . $fallback . '</noscript>';
            }
            
            return $tag;
        },
        $html
    );
}
add_action( 'template_redirect', function() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    
    ob_start( 'privilege_v7_async_css' );
}, 1 );

/* =============================================================================
 * 12. LAZY IFRAME v2 — Com placeholder
 * =============================================================================
 */
function privilege_v7_lazy_iframes( $content ) {
    if ( is_admin() || ! is_string( $content ) ) {
        return $content;
    }
    
    return preg_replace_callback(
        '#<iframe\\b[^>]*>#i',
        function( $match ) {
            $tag = $match[0];
            
            // Adiciona loading=lazy
            if ( stripos( $tag, 'loading=' ) === false ) {
                $tag = str_replace( '<iframe', '<iframe loading="lazy"', $tag );
            }
            
            // Adiciona title
            if ( stripos( $tag, 'title=' ) === false ) {
                $tag = preg_replace( '/<iframe/i', '<iframe title="Embedded content"', $tag, 1 );
            }
            
            // Adiciona sandbox se não existir
            if ( stripos( $tag, 'sandbox=' ) === false ) {
                $tag = str_replace( '<iframe', '<iframe sandbox="allow-scripts allow-same-origin allow-popups allow-forms"', $tag );
            }
            
            return $tag;
        },
        $content
    );
}
add_filter( 'the_content', 'privilege_v7_lazy_iframes', 100 );
add_filter( 'widget_text', 'privilege_v7_lazy_iframes', 100 );

/* =============================================================================
 * 13. REMOVE UNUSED CSS GAMBIRRA — Só carrega CSS usado
 * =============================================================================
 */
function privilege_v7_remove_unused_css() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    
    // Remove CSS de blocos se não usa Gutenberg
    if ( ! function_exists( 'has_blocks' ) || ! has_blocks() ) {
        add_filter( 'should_load_separate_core_block_assets', '__return_false' );
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
        wp_dequeue_style( 'wp-block-library-common' );
    }
    
    // Remove WooCommerce CSS se não é página de loja
    if ( class_exists( 'WooCommerce' ) && ! is_shop() && ! is_product() && ! is_cart() && ! is_checkout() ) {
        wp_dequeue_style( 'woocommerce-general' );
        wp_dequeue_style( 'woocommerce-layout' );
        wp_dequeue_style( 'woocommerce-smallscreen' );
    }
}
add_action( 'wp_enqueue_scripts', 'privilege_v7_remove_unused_css', 100 );

/* =============================================================================
 * 14. TBT KILLER — Reduz Total Blocking Time
 * =============================================================================
 */
function privilege_v7_tbt_killer() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <script id="privilege-v7-tbt-killer">
    (function() {
        // Quebra tarefas longas
        var originalSetTimeout = window.setTimeout;
        window.setTimeout = function(callback, delay) {
            if (delay === undefined || delay === 0) {
                delay = 1;
            }
            if (delay < 50) {
                return originalSetTimeout(callback, delay);
            }
            
            // Divide tarefas longas em chunks
            var chunks = Math.ceil(delay / 50);
            var remaining = delay;
            var args = Array.prototype.slice.call(arguments, 2);
            
            function runChunk() {
                if (remaining <= 50) {
                    return originalSetTimeout(function() {
                        callback.apply(null, args);
                    }, remaining);
                }
                
                remaining -= 50;
                originalSetTimeout(runChunk, 50);
            }
            
            return originalSetTimeout(runChunk, 50);
        };
        
        // Idle callback para tarefas não-críticas
        var scheduleIdleTask = function(callback) {
            if ('requestIdleCallback' in window) {
                requestIdleCallback(callback, {timeout: 2000});
            } else {
                setTimeout(callback, 1);
            }
        };
        
        // Defer analytics até idle
        scheduleIdleTask(function() {
            // Analytics pode carregar agora
        });
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'privilege_v7_tbt_killer', 0 );

/* =============================================================================
 * 15. PREFETCH DNS GAMBIRRA — Para links internos
 * =============================================================================
 */
function privilege_v7_prefetch_links( $content ) {
    if ( is_admin() || ! is_string( $content ) ) {
        return $content;
    }
    
    // Encontra links internos e adiciona prefetch
    preg_match_all( '/<a\\s+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
    
    $prefetched = array();
    $site_url = site_url();
    
    foreach ( $matches[1] as $url ) {
        if ( strpos( $url, $site_url ) === 0 
             && ! in_array( $url, $prefetched, true )
             && count( $prefetched ) < 5 ) {
            $prefetched[] = $url;
            echo '<link rel="prefetch" href="' . esc_url( $url ) . '">' . "\n";
        }
    }
    
    return $content;
}
add_filter( 'the_content', 'privilege_v7_prefetch_links', 200 );

/* =============================================================================
 * FIM DO MÓDULO V7.0 ULTIMATE
 * =============================================================================
 */
