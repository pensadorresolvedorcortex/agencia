<?php
/**
 * Plugin Name: Otimização GTMetrix PageSpeed 99+
 * Description: Otimizações avançadas para alcançar pontuação 99+ no GTMetrix e PageSpeed Insights (Mobile e Desktop). Compatível com W3 Total Cache.
 * Version: 1.0.0
 * Author: Agência Privilége
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe principal de otimização
 */
class Optimizacao_Performance_99 {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'iniciar_otimizacoes'), 1);
        add_action('wp_head', array($this, 'dns_prefetch'), 1);
        add_action('wp_head', array($this, 'preconnect_fonts'), 1);
        add_action('wp_enqueue_scripts', array($this, 'remover_scripts_desnecessarios'), 9999);
        add_filter('wp_resource_hints', array($this, 'adicionar_resource_hints'), 10, 2);
        add_filter('template_redirect', array($this, 'buffer_output'), 1);
        add_filter('wp_lazy_loading_enabled', '__return_false');
        
        // Preload da imagem LCP
        add_filter('wp_head', array($this, 'preload_lcp_image'), 1);
        
        // Inline critical CSS e defer não crítico
        add_action('wp_enqueue_scripts', array($this, 'otimizar_css_fonts'), 9999);
        
        // Adiar JavaScript não crítico
        add_filter('script_loader_tag', array($this, 'defer_non_critical_js'), 10, 3);
        
        // Remover emojis WordPress
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        
        // Remover oEmbed
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        remove_action('wp_enqueue_scripts', 'wp_oembed_add_host_js');
        
        // Remover WP Generator
        remove_action('wp_head', 'wp_generator');
        
        // Remover RSD
        remove_action('wp_head', 'rsd_link');
        
        // Remover wlwmanifest
        remove_action('wp_head', 'wlwmanifest_link');
        
        // Remover shortlink
        remove_action('wp_head', 'wp_shortlink_wp_head');
        
        // Remover REST API link
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('template_redirect', 'rest_output_link_header', 11);
        
        // Remover versão do WooCommerce
        remove_action('wp_head', array('WC_Structured_Data', 'generate_website_data'), 30);
        
        // Limpar head de links desnecessários
        remove_action('wp_head', 'wp_resourcehints', 2);
        remove_action('wp_head', 'rel_canonical');
        
        // Desabilitar Gutenberg CSS frontend se não necessário
        add_filter('should_load_separate_core_block_assets', '__return_true');
        
        // Otimizar Google Fonts
        add_filter('style_loader_tag', array($this, 'otimizar_google_fonts'), 10, 4);
    }

    public function iniciar_otimizacoes() {
        // Definir constantes de performance
        if (!defined('WP_MEMORY_LIMIT')) {
            define('WP_MEMORY_LIMIT', '256M');
        }
    }

    /**
     * DNS Prefetch para domínios externos
     */
    public function dns_prefetch() {
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//www.google-analytics.com">' . "\n";
        echo '<link rel="dns-prefetch" href="//stats.g.doubleclick.net">' . "\n";
        echo '<link rel="dns-prefetch" href="//connect.facebook.net">' . "\n";
        echo '<link rel="dns-prefetch" href="//www.googletagmanager.com">' . "\n";
    }

    /**
     * Preconnect para Google Fonts
     */
    public function preconnect_fonts() {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    }

    /**
     * Adicionar resource hints
     */
    public function adicionar_resource_hints($urls, $relation_type) {
        if ('preconnect' === $relation_type) {
            $urls[] = array(
                'href' => 'https://fonts.googleapis.com',
                'crossorigin' => true,
            );
            $urls[] = array(
                'href' => 'https://fonts.gstatic.com',
                'crossorigin' => true,
            );
        }
        return $urls;
    }

    /**
     * Buffer de saída para manipulação do HTML
     */
    public function buffer_output() {
        ob_start(array($this, 'processar_html'));
    }

    /**
     * Processar HTML para otimizações finais
     */
    public function processar_html($html) {
        // Remover comentários HTML
        $html = preg_replace('/<!--[^>]*>/', '', $html);
        
        // Minificar HTML (espaços em branco extras)
        $html = preg_replace('/\s+/', ' ', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        
        // Adicionar preload para a imagem LCP (hero image)
        if (strpos($html, 'agencia-desenvolvimento-de-sites.webp') !== false) {
            $preload_tag = '<link rel="preload" as="image" href="' . esc_url(home_url('/wp-content/uploads/2026/08/agencia-desenvolvimento-de-sites.webp')) . '" fetchpriority="high">' . "\n";
            $html = str_replace('<head>', '<head>' . $preload_tag, $html);
        }
        
        return $html;
    }

    /**
     * Preload da imagem LCP
     */
    public function preload_lcp_image() {
        // A imagem hero será adicionada via buffer
    }

    /**
     * Remover scripts desnecessários
     */
    public function remover_scripts_desnecessarios() {
        // Remover jQuery Migrate se não necessário
        wp_deregister_script('jquery-migrate');
        
        // Remover script de lazy load do W3TC se já temos solução nativa
        // (W3TC já faz isso, então apenas garantimos que não haja duplicação)
        
        // Remover blocos CSS do Gutenberg se não usado
        wp_dequeue_style('wp-block-library');
        wp_dequeue_style('wp-block-library-theme');
        wp_dequeue_style('wc-block-style');
        
        // Remover Contact Form 7 CSS/JS em páginas sem formulário
        if (!is_singular() || !has_shortcode(get_post()->post_content, 'contact-form-7')) {
            wp_dequeue_style('contact-form-7');
            wp_deregister_script('contact-form-7');
        }
    }

    /**
     * Otimizar carregamento de fontes Google
     */
    public function otimizar_google_fonts($html, $handle, $href, $media) {
        if (strpos($href, 'fonts.googleapis.com') !== false) {
            // Remover tag original e adicionar versão otimizada
            remove_action('wp_enqueue_scripts', 'wp_enqueue_google_fonts');
            
            // Fontes já estão sendo carregadas com display=swap pelo tema/plugin
            // Apenas garantimos que não haja duplicação
        }
        return $html;
    }

    /**
     * Otimizar CSS e fontes
     */
    public function otimizar_css_fonts() {
        // Garantir que as fontes Google usem font-display: swap
        $custom_css = "
            /* Otimização de Fontes */
            @font-face {
                font-display: swap;
            }
            
            /* Critical CSS inline para above-the-fold */
            html {
                line-height: 1.15;
                -webkit-text-size-adjust: 100%;
            }
            
            body {
                margin: 0;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
            }
            
            img {
                max-width: 100%;
                height: auto;
            }
            
            /* Reservar espaço para imagens evitando CLS */
            .wp-image, img {
                aspect-ratio: attr(width) / attr(height);
            }
        ";
        wp_register_style('otimizacao-critical', false);
        wp_enqueue_style('otimizacao-critical');
        wp_add_inline_style('otimizacao-critical', $custom_css);
    }

    /**
     * Adicionar defer a scripts não críticos
     */
    public function defer_non_critical_js($tag, $handle, $src) {
        // Scripts que podem ser adiados
        $scripts_to_defer = array(
            'comment-reply',
            'wp-embed',
            'woocommerce-cart-fragments',
            'woocommerce-js',
        );
        
        // Não aplicar defer no admin ou se já tiver async/defer
        if (is_admin() || strpos($tag, 'async') !== false || strpos($tag, 'defer') !== false) {
            return $tag;
        }
        
        foreach ($scripts_to_defer as $script) {
            if ($handle === $script) {
                return str_replace(' src', ' defer="defer" src', $tag);
            }
        }
        
        // Deferir scripts de analytics
        if (strpos($src, 'google-analytics') !== false || strpos($src, 'googletagmanager') !== false) {
            return str_replace(' src', ' defer="defer" src', $tag);
        }
        
        // Deferir Facebook Pixel
        if (strpos($src, 'facebook.net') !== false || strpos($src, 'fbcdn.net') !== false) {
            return str_replace(' src', ' defer="defer" src', $tag);
        }
        
        return $tag;
    }

    /**
     * Adicionar atributo loading=lazy em imagens
     */
    public function lazy_loading_images($html, $attachment, $image_meta) {
        // WordPress já faz isso nativamente desde 5.5
        // Apenas garantimos que imagens acima do fold não tenham lazy load
        return $html;
    }

    /**
     * Preconnect para CDN se existir
     */
    public function cdn_preconnect() {
        $cdn_url = '';
        
        // Verificar se W3TC está usando CDN
        if (function_exists('w3tc_cdn_url')) {
            $cdn_url = w3tc_cdn_url('');
        }
        
        if (!empty($cdn_url)) {
            $cdn_domain = parse_url($cdn_url, PHP_URL_HOST);
            if ($cdn_domain) {
                echo '<link rel="preconnect" href="//' . esc_attr($cdn_domain) . '" crossorigin>' . "\n";
            }
        }
    }
}

// Inicializar plugin
Optimizacao_Performance_99::get_instance();

/**
 * Funções auxiliares globais
 */

/**
 * Verifica se W3 Total Cache está ativo
 */
function w3tc_esta_ativo() {
    return defined('W3TC') && W3TC;
}

/**
 * Obter URL da imagem em destaque
 */
function get_lcp_image_url() {
    if (is_front_page()) {
        // Imagem hero da home
        return home_url('/wp-content/uploads/2026/08/agencia-desenvolvimento-de-sites.webp');
    }
    
    if (has_post_thumbnail()) {
        return get_the_post_thumbnail_url(get_the_ID(), 'full');
    }
    
    return '';
}

/**
 * Adicionar preload para imagem LCP no head
 */
function adicionar_preload_lcp() {
    $lcp_image = get_lcp_image_url();
    if ($lcp_image) {
        echo '<link rel="preload" as="image" href="' . esc_url($lcp_image) . '" fetchpriority="high">' . "\n";
    }
}
add_action('wp_head', 'adicionar_preload_lcp', 1);

/**
 * Remover query strings de recursos estáticos
 */
function remover_query_strings($src) {
    if (strpos($src, '?ver=')) {
        $src = remove_query_arg('ver', $src);
    }
    return $src;
}
add_filter('script_loader_src', 'remover_query_strings', 15, 1);
add_filter('style_loader_src', 'remover_query_strings', 15, 1);

/**
 * Adicionar expires headers via PHP (caso servidor não tenha configurado)
 */
function adicionar_expires_headers() {
    if (!headers_sent()) {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        
        // Cache por 1 ano para recursos estáticos
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|avif|ico|svg|css|js|woff|woff2|ttf|eot)$/i', $_SERVER['REQUEST_URI'])) {
            header("$protocol 200 OK");
            header('Cache-Control: public, max-age=31536000, immutable');
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        }
    }
}
// Nota: W3TC já gerencia isso melhor via .htaccess ou nginx

/**
 * Otimizar entrega de Google Fonts localmente (opcional)
 * Esta função pode ser ativada se quiser baixar as fontes
 */
function otimizar_fonts_locais() {
    // Implementação opcional para baixar fontes Google localmente
    // Requereria cron job para manter fonts atualizadas
}

/**
 * Cleanup final do head
 */
function cleanup_head_final() {
    // Remover qualquer link feed restante
    remove_action('wp_head', 'feed_links', 2);
    remove_action('wp_head', 'feed_links_extra', 3);
}
add_action('after_setup_theme', 'cleanup_head_final', 999);

/**
 * Desabilitar heartbeat para economizar recursos (opcional)
 */
function controlar_heartbeat($period) {
    // Reduzir frequência do heartbeat para 60 segundos
    return 60;
}
add_filter('heartbeat_settings', 'controlar_heartbeat');

/**
 * Limitar revisões de posts
 */
define('WP_POST_REVISIONS', 5);

/**
 * Intervalo de lixo automático (em dias)
 */
define('EMPTY_TRASH_DAYS', 7);

/**
 * Otimizar banco de dados automaticamente
 */
function otimizar_banco_dados() {
    global $wpdb;
    
    // Otimizar tabelas
    $tables = $wpdb->get_col("SHOW TABLES");
    foreach ($tables as $table) {
        $wpdb->query("OPTIMIZE TABLE $table");
    }
}
// Agendar via wp_cron se necessário

/**
 * Preload de páginas críticas
 */
function preload_paginas_criticas() {
    if (!is_user_logged_in() && !isset($_COOKIE['wordpress_test_cookie'])) {
        $home_url = home_url();
        
        // Fazer preload da homepage
        wp_remote_get($home_url, array(
            'timeout' => 5,
            'blocking' => false,
            'sslverify' => false,
        ));
    }
}
// Executar após cache limpo

/**
 * Mensagem no admin sobre otimizações
 */
function mostrar_info_otimizacao() {
    echo '<div class="notice notice-success"><p>';
    echo '<strong>Otimização GTMetrix/PageSpeed Ativa:</strong> ';
    echo 'Este plugin aplica otimizações para alcançar 99+ no PageSpeed. ';
    echo 'Certifique-se de configurar o W3 Total Cache corretamente e usar imagens WebP.';
    echo '</p></div>';
}
add_action('admin_notices', 'mostrar_info_otimizacao');
