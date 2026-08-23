<?php
/**
 * Plugin Name: Privilege Site Controller
 * Description: Controlador MU das customizações e otimizações da Agência Privilége.
 * Version: 6.0.0-flyingpress-ultra
 * Author: Agência Privilége
 *
 * Stack de performance: FlyingPress + Otimizações Extremas v6.0
 *
 * V6.0 — MEGA OTIMIZAÇÃO PARA 95+ MOBILE E DESKTOP
 * ============================================================================
 * Foco total em eliminar CLS (desktop 0.784 → <0.1) e melhorar LCP/TBT
 * 
 * NOVIDADES CRÍTICAS:
 * 
 * 1. CLS KILLER: Força height:auto + overflow:hidden em TODAS seções com
 *    coverage images, ignorando CSS do tema que causa shift. Gambiarra
 *    agressiva mas visualmente segura.
 * 
 * 2. PRECONNECT UNIVERSAL: Preconnect para Google Fonts, GTranslate e
 *    qualquer domínio externo detectado.
 * 
 * 3. FONT DISPLAY NUCLEAR: font-display:swap + font-display:optional em
 *    TODAS as fontes, incluindo as do tema.
 * 
 * 4. GTRANSLATE LAZY: Adia carregamento do widget GTranslate após LCP.
 * 
 * 5. CRITICAL CSS INLINE: Inline do CSS crítico acima da dobra.
 * 
 * 6. DEFER JS AGGRESSIVE: Defer em TODO JS não-crítico.
 * 
 * 7. IMAGE DIMENSIONS FORÇADAS: width/height em TODAS imagens sem exceção.
 * 
 * 8. CONTAINER SIZE: contain-intrinsic-size em seções abaixo da dobra.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'PRIVILEGE_SITE_CONTROLLER_LOADED' ) ) {
    return;
}

define( 'PRIVILEGE_SITE_CONTROLLER_LOADED', true );
define( 'PRIVILEGE_SITE_CONTROLLER_VERSION', '6.0.0-flyingpress-ultra' );

$privilege_site_controller_modules = array(
    'site-customizations.php',
    'performance-flyingpress.php',
    'extreme-performance-v6.php',
    'admin-dashboard.php',
    'bot-whatsapp.php',
);

foreach ( $privilege_site_controller_modules as $privilege_site_controller_module ) {
    $privilege_site_controller_file =
        __DIR__ . '/privilege-site-controller/modules/' . $privilege_site_controller_module;

    if ( is_readable( $privilege_site_controller_file ) ) {
        require_once $privilege_site_controller_file;
    }
}
