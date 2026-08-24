<?php
/**
 * Privilege Site Controller — Site Customizations
 *
 * Migrado do functions.php da Agência Privilége sem alteração funcional
 * intencional. Não editar em paralelo no functions.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Desativar suporte a comentários e trackbacks em posts e páginas
function disable_comments_post_types_support() {
    $post_types = get_post_types();
    foreach ( $post_types as $post_type ) {
        if ( post_type_supports( $post_type, 'comments' ) ) {
            remove_post_type_support( $post_type, 'comments' );
            remove_post_type_support( $post_type, 'trackbacks' );
        }
    }
}
add_action( 'admin_init', 'disable_comments_post_types_support' );

function disable_comments_status() {
    return false;
}
add_filter( 'comments_open', 'disable_comments_status', 20, 2 );
add_filter( 'pings_open', 'disable_comments_status', 20, 2 );

function disable_comments_hide_existing_comments( $comments ) {
    return array();
}
add_filter( 'comments_array', 'disable_comments_hide_existing_comments', 10, 2 );

function disable_comments_admin_menu() {
    remove_menu_page( 'edit-comments.php' );
}
add_action( 'admin_menu', 'disable_comments_admin_menu' );

function disable_comments_admin_menu_redirect() {
    global $pagenow;
    if ( $pagenow === 'edit-comments.php' ) {
        wp_redirect( admin_url() );
        exit();
    }
}
add_action( 'admin_init', 'disable_comments_admin_menu_redirect' );

function disable_comments_dashboard() {
    remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
}
add_action( 'admin_init', 'disable_comments_dashboard' );

function disable_comments_admin_bar() {
    if ( is_admin_bar_showing() ) {
        remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
    }
}
add_action( 'init', 'disable_comments_admin_bar' );

function redirect_if_404() {
    if ( is_404() ) {
        wp_safe_redirect( home_url( '/' ) );
        exit();
    }
}
add_action( 'template_redirect', 'redirect_if_404' );
add_filter('lpagery_post_types', function($types) {
    $types[] = 'page';
    return $types;
});
add_theme_support('post-thumbnails');
add_filter('gettext', 'traduzir_all_para_todos', 20, 3);
function traduzir_all_para_todos($translated_text, $text, $domain) {

    // Lista de variações comuns que podem aparecer
    $map = [
        'All' => 'Todos',
        'ALL' => 'TODOS',
        'all' => 'todos'
    ];

    if (isset($map[$text])) {
        return $map[$text];
    }

    return $translated_text;
}
// Agência Privilége — Header leve sem Meta Box, com menu + GTranslate e sem barra preta superior
add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }
    ?>
    <style>
        .site-header-top-bar,
        .site-navigation-widgets,
        .widget_search,
        .widget_categories,
        #privilege-header-source {
            display: none !important;
        }

        .site-branding {
            background: #fff !important;
        }

        .site-branding-inner {
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 32px !important;
        }

        .site-branding-logo {
            flex: 0 0 auto !important;
        }

        .privilege-header-right {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 22px !important;
            margin-left: auto !important;
        }

        /* V5.0: reserva de espaço para reduzir o CLS causado pelo menu ser
           montado no wp_footer e realocado via JS (ver AUDIT-README.txt,
           achado #4). É uma estimativa de altura de uma linha de menu;
           ajuste o valor abaixo depois de conferir visualmente no
           dispositivo real — reservar espaço a mais nunca piora o layout
           (é um mínimo, não um teto), só ajuda menos do que o ideal se
           ficar pequeno demais. */
        @media (max-width: 992px) {
            .privilege-header-right {
                min-height: 44px;
            }
        }

        .privilege-menu-fixed {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
        }

        .privilege-menu-fixed ul,
        .privilege-menu-list {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 28px !important;
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .privilege-menu-fixed li {
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .privilege-menu-fixed a {
            color: #111 !important;
            font-size: 15px !important;
            font-weight: 700 !important;
            text-decoration: none !important;
            text-transform: uppercase !important;
            white-space: nowrap !important;
            line-height: 1.2 !important;
        }

        .privilege-menu-fixed a:hover {
            color: #6b2494 !important;
        }

        .privilege-gtranslate {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            white-space: nowrap !important;
        }

        .privilege-gtranslate img {
            width: 22px !important;
            height: auto !important;
            display: inline-block !important;
        }

        @media (max-width: 992px) {
            .site-branding-inner {
                flex-direction: column !important;
                align-items: center !important;
                gap: 16px !important;
            }

            .privilege-header-right {
                flex-direction: column !important;
                gap: 12px !important;
                margin-left: 0 !important;
            }

            .privilege-menu-fixed ul,
            .privilege-menu-list {
                gap: 14px !important;
                flex-wrap: wrap !important;
                justify-content: center !important;
            }

            .privilege-menu-fixed a {
                font-size: 13px !important;
            }
        }
    </style>
    <?php
});

add_action('wp_footer', function () {
    if (is_admin()) {
        return;
    }

    $menu_html = wp_nav_menu([
        'menu' => 'Principal',
        'container' => 'nav',
        'container_class' => 'privilege-menu-fixed',
        'menu_class' => 'privilege-menu-list',
        'echo' => false,
        'fallback_cb' => false,
        'depth' => 1,
    ]);

    if (empty($menu_html)) {
        return;
    }

    $gtranslate_html = do_shortcode('[gtranslate]');

    if (empty(trim($gtranslate_html))) {
        $gtranslate_html = do_shortcode('[gtranslate_widget]');
    }
    ?>
    <div id="privilege-header-source">
        <div class="privilege-header-right">
            <?php echo $menu_html; ?>

            <?php if (!empty(trim($gtranslate_html))) : ?>
                <div class="privilege-gtranslate">
                    <?php echo $gtranslate_html; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    /* V5.0: antes esperava DOMContentLoaded (dispara só depois de todo o
       documento e scripts síncronos anteriores terminarem). Como este
       script já está no wp_footer — depois de TODO o conteúdo do body no
       HTML, incluindo o cabeçalho — os elementos que ele procura já
       existem no DOM neste ponto do parsing. Rodar imediatamente (IIFE),
       em vez de esperar o evento, tira uma espera desnecessária do
       caminho até o menu aparecer no lugar certo, reduzindo a janela de
       CLS (ver AUDIT-README.txt, achado #4). Também não remove mais os
       elementos padrão do tema via JS — o CSS acima já os esconde com
       display:none desde o primeiro paint, então a remoção por JS era
       redundante.
    */
    (function () {
        var source = document.querySelector('#privilege-header-source .privilege-header-right');
        var target = document.querySelector('.site-branding-inner');

        if (!source || !target) {
            return;
        }

        var existing = document.querySelector('.privilege-header-right');

        if (existing && existing !== source) {
            existing.remove();
        }

        target.appendChild(source);
    })();
    </script>
    <?php
});
// Corrige ausência da tag <title> no tema Aiko/BoldThemes
add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }

    if (is_front_page() || is_home()) {
        $title = 'Agência Privilége | Criação de Sites, SEO e Marketing Digital';
    } elseif (is_singular()) {
        $title = get_the_title() . ' | Agência Privilége';
    } else {
        $title = wp_get_document_title();
    }

    echo "\n<title>" . esc_html($title) . "</title>\n";
}, 0);
// Ajuste real de layout Aiko mantendo backgrounds intactos
add_action('wp_head', function () {
    if (is_admin() || wp_is_mobile()) return;
    ?>
    <style>
        @media (min-width: 1400px) {

            /* NÃO mexe em section nem pseudo elementos */
            .bt_bb_section,
            .bt_bb_section::before,
            .bt_bb_section::after {
                max-width: none !important;
                width: 100% !important;
            }

            /* Limita SOMENTE o conteúdo interno real */
            .bt_bb_section .bt_bb_row,
            .bt_bb_section .bt_bb_row_inner {
                max-width: 1320px !important;
                margin-left: auto !important;
                margin-right: auto !important;
                padding-left: 24px !important;
                padding-right: 24px !important;
            }

            /* Remove qualquer limitação herdada errada */
            .bt_bb_wrapper,
            .bt-content-wrap,
            .bt-content,
            .site-content {
                max-width: none !important;
                width: 100% !important;
            }

            /* Header alinhado */
            .site-branding-inner {
                max-width: 1280px !important;
                margin: 0 auto !important;
                padding-left: 24px !important;
                padding-right: 24px !important;
            }

            /* Tipografia mais proporcional */
            h1 { font-size: 48px !important; }
            h2 { font-size: 36px !important; }
            h3 { font-size: 28px !important; }

            /* Botões */
            .bt_bb_button,
            .bt_bb_button a {
                font-size: 14px !important;
                padding: 12px 22px !important;
            }

            /* Espaçamento geral mais compacto */
            .bt_bb_section {
                padding-top: 60px !important;
                padding-bottom: 60px !important;
            }

        }
    </style>
    <?php
});
// Blog em layout boxed (sem afetar o resto do site)
add_action('wp_head', function () {
    if (is_admin() || !is_singular('post')) return;
    ?>
    <style>
        /* Apenas páginas de post */
        .single-post .site-content {
            max-width: 1600px !important;
            margin: 0 auto !important;
            padding-left: 24px !important;
            padding-right: 24px !important;
        }

        /* Container interno do conteúdo */
        .single-post .entry-content,
        .single-post .entry-header {
            max-width: 900px !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }

        /* Título mais elegante */
        .single-post h1.entry-title {
            max-width: 900px !important;
            margin: 0 auto 20px auto !important;
        }

        /* Imagens não estouram */
        .single-post .entry-content img {
            max-width: 100% !important;
            height: auto !important;
            border-radius: 12px;
        }

        /* Espaçamento melhor */
        .single-post .entry-content {
            line-height: 1.7;
            font-size: 16px;
        }

    </style>
    <?php
});
add_action('wp_head', function () {
    if (is_admin() || !(is_home() || is_archive() || is_category())) return;
    ?>
    <style>
        body.blog #content.site-content,
        body.archive.category #content.site-content,
        body.category #content.site-content {
            max-width: 1280px !important;
            width: calc(100% - 48px) !important;
            margin: 0 auto !important;
            padding: 48px 24px !important;
            box-sizing: border-box !important;
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) 300px !important;
            gap: 42px !important;
            align-items: start !important;
        }

        body.blog #primary.site-main,
        body.archive.category #primary.site-main,
        body.category #primary.site-main {
            grid-column: 1 !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
        }

       
        body.blog article.article-list-item,
        body.archive.category article.article-list-item,
        body.category article.article-list-item {
            max-width: 100% !important;
        }

        @media (max-width: 1024px) {
            body.blog #content.site-content,
            body.archive.category #content.site-content,
            body.category #content.site-content {
                display: block !important;
                width: 100% !important;
                padding: 32px 18px !important;
            }

            body.blog #content.site-content::before,
            body.archive.category #content.site-content::before,
            body.category #content.site-content::before {
                margin-bottom: 32px;
            }
        }
    </style>
    <?php
});
// Registra sidebars tradicionais sem depender do Meta Box
add_action('widgets_init', function () {

    register_sidebar([
        'name'          => 'Sidebar',
        'id'            => 'sidebar-1',
        'description'   => 'Sidebar principal padrão do WordPress.',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);

    register_sidebar([
        'name'          => 'Blog Sidebar',
        'id'            => 'blog-sidebar',
        'description'   => 'Sidebar lateral do blog/notícias.',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);

    register_sidebar([
        'name'          => 'Footer 1',
        'id'            => 'footer-1',
        'description'   => 'Primeira coluna do rodapé.',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ]);

    register_sidebar([
        'name'          => 'Footer 2',
        'id'            => 'footer-2',
        'description'   => 'Segunda coluna do rodapé.',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ]);

    register_sidebar([
        'name'          => 'Footer 3',
        'id'            => 'footer-3',
        'description'   => 'Terceira coluna do rodapé.',
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ]);

    register_sidebar([
        'name'          => 'Header',
        'id'            => 'header-sidebar',
        'description'   => 'Área de widgets do cabeçalho.',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<span class="widget-title">',
        'after_title'   => '</span>',
    ]);
});

// Sidebar real completa do WordPress na lateral direita do blog/notícias
add_action('wp_footer', function () {
    if (is_admin()) return;

    if (!is_home() && !is_archive() && !is_category() && !is_page('noticias')) {
        return;
    }

    $sidebar_id = 'primary_widget_area';

    if (!is_active_sidebar($sidebar_id)) {
        return;
    }

    ob_start();
    dynamic_sidebar($sidebar_id);
    $sidebar_html = ob_get_clean();

    if (empty(trim($sidebar_html))) {
        return;
    }
    ?>

    <aside id="privilege-real-sidebar" class="privilege-real-sidebar">
        <?php echo $sidebar_html; ?>
    </aside>

    <script>
    /* V5.0: mesmo ajuste do cabeçalho — script já está no wp_footer, então
       roda imediatamente em vez de esperar DOMContentLoaded. Este bloco só
       é impresso em blog/arquivo/categoria/página "noticias" (guard no
       início da função PHP), não na Home — não é o que o relatório de PSI
       anexado mede, mas é o mesmo padrão de bug (JS reposicionando conteúdo
       depois do primeiro paint), então recebeu a mesma correção. */
    (function () {
        const content = document.querySelector('#content.site-content');
        const primary = document.querySelector('#primary.site-main');
        const sidebar = document.querySelector('#privilege-real-sidebar');

        if (!content || !primary || !sidebar) return;

        sidebar.remove();

        content.style.display = 'flex';
        content.style.alignItems = 'flex-start';

        content.appendChild(sidebar);
    })();
    </script>

    <style>
        body.blog #content.site-content,
        body.archive #content.site-content,
        body.category #content.site-content,
        body.page #content.site-content {
            max-width: 1280px !important;
            width: calc(100% - 64px) !important;
            margin: 0 auto !important;
            padding: 56px 24px !important;
            box-sizing: border-box !important;
            display: flex !important;
            flex-direction: row !important;
            align-items: flex-start !important;
            gap: 40px !important;
        }

        body.blog #primary.site-main,
        body.archive #primary.site-main,
        body.category #primary.site-main,
        body.page #primary.site-main {
            flex: 1 1 auto !important;
            max-width: calc(100% - 360px) !important;
            width: auto !important;
            min-width: 0 !important;
            order: 1 !important;
        }

        #privilege-real-sidebar {
            flex: 0 0 320px !important;
            width: 320px !important;
            max-width: 320px !important;
            order: 2 !important;
            background: #f7f7f7 !important;
            border-radius: 24px !important;
            padding: 28px !important;
            box-sizing: border-box !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
        }

        #privilege-real-sidebar .widget,
        #privilege-real-sidebar section.widget,
        #privilege-real-sidebar div.widget,
        #privilege-real-sidebar [id^="widget-"],
        #privilege-real-sidebar .widget_categories,
        #privilege-real-sidebar .widget_search,
        #privilege-real-sidebar .widget_text,
        #privilege-real-sidebar .widget_block,
        #privilege-real-sidebar .widget_recent_entries,
        #privilege-real-sidebar .widget_recent_posts,
        #privilege-real-sidebar .bt_bb_recent_posts,
        #privilege-real-sidebar .widget_bt_bb_recent_posts {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            height: auto !important;
            min-height: 0 !important;
            max-height: none !important;
            overflow: visible !important;
            margin: 0 0 28px 0 !important;
            padding: 0 !important;
            clear: both !important;
        }

        #privilege-real-sidebar .widget:last-child,
        #privilege-real-sidebar [id^="widget-"]:last-child {
            margin-bottom: 0 !important;
        }

        #privilege-real-sidebar .widget-title,
        #privilege-real-sidebar h2,
        #privilege-real-sidebar h3,
        #privilege-real-sidebar h4 {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            font-size: 20px !important;
            line-height: 1.15 !important;
            font-weight: 800 !important;
            margin: 0 0 16px 0 !important;
            color: #111 !important;
        }

        #privilege-real-sidebar form,
        #privilege-real-sidebar ul,
        #privilege-real-sidebar li,
        #privilege-real-sidebar p,
        #privilege-real-sidebar input,
        #privilege-real-sidebar button {
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
        }

        #privilege-real-sidebar ul {
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        #privilege-real-sidebar li {
            margin: 0 0 10px 0 !important;
        }

        #privilege-real-sidebar a {
            color: #111 !important;
            text-decoration: none !important;
        }

        #privilege-real-sidebar a:hover {
            color: #6b2494 !important;
        }

        #privilege-real-sidebar input[type="search"],
        #privilege-real-sidebar input[type="text"] {
            width: 100% !important;
            border: 1px solid #ddd !important;
            border-radius: 999px !important;
            padding: 12px 16px !important;
            box-sizing: border-box !important;
            background: #fff !important;
        }

        #privilege-real-sidebar button,
        #privilege-real-sidebar input[type="submit"] {
            margin-top: 10px !important;
            border: 0 !important;
            border-radius: 999px !important;
            padding: 10px 18px !important;
            background: #5b1688 !important;
            color: #fff !important;
            font-weight: 700 !important;
            cursor: pointer !important;
        }

        @media (max-width: 1024px) {
            body.blog #content.site-content,
            body.archive #content.site-content,
            body.category #content.site-content,
            body.page #content.site-content {
                display: block !important;
                width: 100% !important;
                padding: 32px 18px !important;
            }

            body.blog #primary.site-main,
            body.archive #primary.site-main,
            body.category #primary.site-main,
            body.page #primary.site-main {
                max-width: 100% !important;
                width: 100% !important;
            }

            #privilege-real-sidebar {
                width: 100% !important;
                max-width: 100% !important;
                margin-top: 36px !important;
            }
        }
    </style>

    <?php
});
// CSS premium para cards sociais Instagram/Facebook
add_action('wp_head', function () {
    if (is_admin() || is_front_page()) return;
    ?>
    <style>
        .privilege-social-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 28px;
            width: 100%;
        }

        .social-glass-box {
            position: relative;
            overflow: hidden;
            border-radius: 32px;
            padding: 42px 34px;
            min-height: 360px;
            background-image: linear-gradient(90deg, var(--primary-color, var(--accent-color)) 0%, var(--secondary-color, var(--alternate-color)) 100%);
            backdrop-filter: blur(22px);
            -webkit-backdrop-filter: blur(22px);
            border: 1px solid rgba(255,255,255,.28);
            box-shadow: 0 28px 80px rgba(0,0,0,.18);
            isolation: isolate;
        }

        .social-glass-box::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.24), rgba(255,255,255,.05));
            z-index: -1;
        }

        .social-glass-box::after {
            content: "";
            position: absolute;
            width: 240px;
            height: 240px;
            right: -80px;
            top: -80px;
            border-radius: 50%;
            background: rgba(255,255,255,.22);
            filter: blur(8px);
            z-index: -1;
        }

        .social-image-top {
            width: 86px;
            height: 86px;
            border-radius: 28px;
            background: rgba(255,255,255,.9);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 26px;
            box-shadow: 0 18px 40px rgba(0,0,0,.16);
        }

        .social-image-top img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .social-text {
            max-width: 420px;
            margin: 0 0 28px 0;
            color: #fff;
            font-size: 18px;
            line-height: 1.55;
            font-weight: 500;
        }

        .social-followers {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .social-followers img {
            width: 46px;
            height: 46px;
            border-radius: 25px;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,.9);
            margin-left: -12px;
            box-shadow: 0 10px 22px rgba(0,0,0,.18);
        }

        .social-followers img:first-child {
            margin-left: 0;
        }

        .social-more {
            height: 46px;
            min-width: 62px;
            border-radius: 25px;
            padding: 0 14px;
            margin-left: -8px;
            background: rgba(255,255,255,.92);
            color: #23102d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            border: 3px solid rgba(255,255,255,.9);
            box-shadow: 0 10px 22px rgba(0,0,0,.14);
        }

        .social-btn a.bt_bb_link {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            border-radius: 999px !important;
            padding: 14px 28px !important;
            background: rgba(255,255,255,.96) !important;
            color: #24102f !important;
            font-weight: 800 !important;
            text-decoration: none !important;
            box-shadow: 0 18px 40px rgba(0,0,0,.18);
            transition: transform .25s ease, box-shadow .25s ease, background .25s ease;
        }

        .social-btn a.bt_bb_link:hover {
            transform: translateY(-3px);
            background: #fff !important;
            box-shadow: 0 24px 54px rgba(0,0,0,.24);
        }

        .social-btn .bt_bb_text {
            color: inherit !important;
        }

        @media (max-width: 768px) {
            .privilege-social-grid {
                grid-template-columns: 1fr;
            }

            .social-glass-box {
                padding: 34px 26px;
                border-radius: 26px;
            }

            .social-text {
                font-size: 16px;
            }
        }
    </style>
    <?php
});
add_action('wp_head', function () {
    if (
        is_admin()
        || (!is_home() && !is_archive() && !is_category() && !is_page('noticias'))
    ) {
        return;
    }
    ?>
    <style>
        #privilege-real-sidebar .privilege-social-grid {
            grid-template-columns: 1fr !important;
            gap: 18px !important;
        }

        #privilege-real-sidebar .social-glass-box {
            min-height: auto !important;
            padding: 24px 22px !important;
            border-radius: 24px !important;
        }

        #privilege-real-sidebar .social-image-top {
            width: 62px !important;
            height: 62px !important;
            border-radius: 20px !important;
            margin-bottom: 18px !important;
        }

        #privilege-real-sidebar .social-image-top img {
            width: 34px !important;
            height: 34px !important;
        }

        #privilege-real-sidebar .social-text {
            max-width: none !important;
            font-size: 15px !important;
            line-height: 1.45 !important;
            margin-bottom: 20px !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
            hyphens: none !important;
        }

        #privilege-real-sidebar .social-followers img {
            width: 36px !important;
            height: 36px !important;
        }

        #privilege-real-sidebar .social-more {
            height: 36px !important;
            min-width: 52px !important;
            font-size: 12px !important;
        }

        #privilege-real-sidebar .social-btn a.bt_bb_link {
            width: 100% !important;
            padding: 12px 16px !important;
            font-size: 13px !important;
        }
    </style>
    <?php
});
// Adiciona apenas o botão Orçamentos no menu superior
add_action('wp_footer', function () {
    if (is_admin()) return;
    ?>
    <script>
    /* V5.0: mesma lógica do bloco de reposicionamento do menu acima — este
       script também já está no wp_footer, então '.privilege-header-right'
       já existe no DOM (ela vem do #privilege-header-source, escrito mais
       acima no mesmo wp_footer) independentemente de já ter sido movida
       para dentro de '.site-branding-inner' ou não. Rodar imediatamente
       evita mais uma espera de DOMContentLoaded antes do botão aparecer. */
    (function () {
        const headerRight = document.querySelector('.privilege-header-right');
        if (!headerRight) return;

        if (document.querySelector('.privilege-header-actions')) return;

        const actions = document.createElement('div');
        actions.className = 'privilege-header-actions';

        actions.innerHTML = `
            <a class="privilege-header-budget" href="/orcamentos" title="Orçamentos">
                <span>ORÇAMENTOS</span>
            </a>
        `;

        headerRight.appendChild(actions);
    })();
    </script>

    <style>
        .privilege-header-right {
            display: flex !important;
            align-items: center !important;
            gap: 16px !important;
        }

        .privilege-header-actions {
            display: inline-flex !important;
            align-items: center !important;
            gap: 16px !important;
            margin-left: 4px !important;
        }

        .privilege-header-budget {
    display: inline-flex !important
;
    align-items: center !important;
    justify-content: center !important;
    min-height: 50px !important;
    padding: 0 35px !important;
    border-radius: 999px !important;
    background-image: linear-gradient(90deg, #3b286f 0%, rgb(8 194 168) 100%) !important;
    color: #fff !important;
    text-decoration: none !important;
    font-size: 13px !important;
    font-weight: 800 !important;
    line-height: 1 !important;
    letter-spacing: .01em !important;
    white-space: nowrap !important;
    box-shadow: none !important;
    transition: background-position .3s ease, transform .2s ease, filter .2s ease !important;
}

        .privilege-header-budget span {
            color: #fff !important;
        }

        .privilege-header-budget:hover {
            transform: none !important;
            filter: brightness(1.06) !important;
            color: #fff !important;
        }

        @media (max-width: 992px) {
            .privilege-header-actions {
                gap: 10px !important;
            }

            .privilege-header-budget {
                min-height: 34px !important;
                padding: 0 16px !important;
                font-size: 12px !important;
            }
        }
    </style>
    <?php
});
// Ajuste apenas do robô na seção Orçamento Express
add_action('wp_head', function () {
    if (is_admin()) return;
    ?>
    <style>
        /* Remove impacto no footer */
        footer .bb-pulse,
        footer .bb-pulse--strong,
        footer .bb-pulse img,
        footer .bb-pulse--strong img {
            bottom: auto !important;
            transform: none !important;
            position: static !important;
        }

        /* Aplica somente no robô da seção de orçamento */
        .bt_bb_section .bb-pulse.bb-pulse--strong {
            position: relative !important;
            bottom: -5em !important;
            z-index: 2 !important;
        }

        .bt_bb_section .bb-pulse.bb-pulse--strong img {
            display: block !important;
            transform: none !important;
        }

        @media (max-width: 1024px) {
            .bt_bb_section .bb-pulse.bb-pulse--strong {
                bottom: -3em !important;
            }
        }
    </style>
    <?php
});
/**
 * Corrige bordas pretas do Bold Builder no front-end.
 */
add_action('wp_head', function () {
    ?>
    <style>
        .bt_bb_headline,
        .bt_bb_headline *,
        .bt_bb_column_content,
        .bt_bb_column_content_inner {
            border-color: rgb(254, 254, 254) !important;
        }

        [style*="border-color:rgb(0,0,0)"],
        [style*="border-color: rgb(0, 0, 0)"],
        [style*="border-color:#000"],
        [style*="border-color: #000"],
        [style*="border-color: black"] {
            border-color: rgb(254, 254, 254) !important;
        }

        .bt_bb_wrapper,
        .bt_bb_wrapper *,
        .bt_bb_section,
        .bt_bb_section *,
        .bt_bb_row,
        .bt_bb_row *,
        .bt_bb_column,
        .bt_bb_column *,
        .bt_bb_headline,
        .bt_bb_headline * {
            border-color: rgb(254, 254, 254) !important;
        }
    </style>
    <?php
});
