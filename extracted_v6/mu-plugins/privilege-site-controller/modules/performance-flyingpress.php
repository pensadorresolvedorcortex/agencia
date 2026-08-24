<?php
/**
 * Privilege Site Controller — FlyingPress Compatibility v5.0
 *
 * ============================================================================
 * V5.0 — reescrita pós-auditoria (ver AUDIT-README.txt para o relatório
 * completo, com as evidências de rede que embasam cada mudança abaixo).
 * ============================================================================
 *
 * A V4.10 acumulava 10 gerações de patches pontuais (V3 → V4.10), a maioria
 * hardcoded para um arquivo/imagem/heading específico. Quando o conteúdo da
 * Home mudava, o patch ficava obsoleto silenciosamente — sem erro, sem aviso,
 * só parava de fazer efeito (ou pior: continuava agindo sobre o alvo errado).
 * A auditoria cruzou o código com um HAR real (GTmetrix) e achou 3 bugs
 * concretos, comprovados por requisição de rede real, não suposição:
 *
 *   1) O preload de altíssima prioridade da fonte de ícones apontava para
 *      ".../_RemidiconsSystem/RemidiconsSystem.woff" (nome errado, "_" a
 *      mais). No HAR isso aparece como HTTP 302 redirecionando de volta
 *      para a própria Home — um preload inteiro desperdiçado. A fonte
 *      realmente usada pelo tema (".../RemixIconsSystem/RemixIconsSystem.woff",
 *      HTTP 200 no mesmo HAR) nunca tinha preload e era descoberta tarde —
 *      exatamente o que a "Árvore de dependência da rede" do PSI apontava
 *      como maior latência da cadeia.
 *
 *   2) O preload/promoção de LCP era hardcoded para
 *      "/2023/11/top_white_03_01.png" — uma imagem pequena (~2,8 KiB,
 *      confirmada no HAR) que é decorativa. A rotina ainda REMOVIA qualquer
 *      outro preload de imagem que aparecesse, inclusive um eventual preload
 *      da imagem de conteúdo real. Resultado: o slot de preload prioritário
 *      ia para o elemento errado, e o candidato real a LCP (a imagem grande
 *      de conteúdo, .../2026/08/t2.webp) não recebia preload nenhum.
 *
 *   3) A função que gerava uma variante 2x do Python.png (V4.9) checava a
 *      existência de "/2025/08/Python.png" — mas o HAR mostra que a Home
 *      atual referencia ".../2026/08/Python.webp". O conteúdo mudou; a
 *      checagem nunca mais bateu. Código morto, silenciosamente inofensivo,
 *      mas o mesmo padrão se repete em pelo menos mais dois pontos do V4.10.
 *
 * Filosofia da V5.0: onde existe um mecanismo nativo (WordPress core já
 * marca fetchpriority=high na imagem correta; FlyingPress já sabe fazer
 * Remove Unused CSS/otimização de imagem melhor do que regex), este módulo
 * PARA de reimplementar isso na mão e passa a reforçar/espelhar o
 * mecanismo nativo. Onde o hack manual continua sendo a opção certa
 * (preload de fonte, CSS pesado assíncrono, aria-label, heading levels),
 * ele foi mantido — só consolidado, sem duplicação entre versões.
 *
 * AÇÃO MANUAL RECOMENDADA (não é código — é um toggle no FlyingPress):
 * reative "Remove Unused CSS" em FlyingPress > Otimização. É o único ganho
 * capaz de atacar os "158 KiB de CSS não usado" apontados pelo PSI; nenhuma
 * técnica de carregamento assíncrono reduz o que É baixado, só adia QUANDO.
 * Ver AUDIT-README.txt para o passo a passo e o motivo.
 *
 * Este módulo continua responsável apenas pelo que NÃO compete com o
 * FlyingPress: cache/preload de página, minificação, Delay JS, lazy-load
 * padrão e Google Fonts continuam 100% a cargo do FlyingPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PRIVILEGE_FP_VERSION', '5.2.0' );

/* =============================================================================
 * ÍNDICE
 * 1. Emojis do WordPress (remoção)
 * 2. Home mobile: fast paint da 1ª seção
 * 3. Fonte de ícones RemixIconsSystem — BUG CORRIGIDO (preload + font-display)
 * 4. Helper genérico de atributos HTML
 * 5. the_content: promove a imagem de topo do Bold Builder a LCP candidate
 * 6. Buffer de HTML da Home: orquestra os passos abaixo
 *    6a. Preload dinâmico de LCP (substitui o hardcode de filename)
 *    6b. CSS pesado do Bold Builder assíncrono (+ fallback <noscript>)
 *    6c. Acessibilidade: aria-label e níveis de heading
 *    6d. WebP sob demanda para imagens locais sem srcset
 *    6e. Inline de CSS local pequeno
 *    6f. Logo com srcset real 1x/2x
 * 7. CSS principal do Aiko: cópia com font-display:swap
 * 8. Mobile: descarrega JS de floating-image (reduz forced reflow)
 * 9. CSS avulso (node logo, alvos de toque, content-visibility)
 * 10. /llms.txt virtual
 * 11. Resource hints (preconnect)
 * 12. Admin bar: cache dedicado da Home (purge/gerar)
 * 13. Marcador de debug
 * ============================================================================= */


/* =============================================================================
 * 1) Remove o pacote de emojis do WordPress — 1 JS + 1 CSS a menos em toda
 *    página. Inalterado desde a V3.
 * ============================================================================= */
function privilege_fp_remove_wp_emoji_assets() {
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'privilege_fp_remove_wp_emoji_assets', 5 );


/* =============================================================================
 * 2) Home mobile: desliga só a animação de entrada da 1ª seção (evita custo
 *    de opacity/transform no paint inicial). Escopo estreito, não compete
 *    com cache/RUCSS/lazy-load/Delay JS do FlyingPress. Inalterado desde a V3.
 * ============================================================================= */
function privilege_fp_home_mobile_fast_paint() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <style id="privilege-fp-home-fast-paint">
    @media (max-width:767px) {
        body.home .bt_bb_wrapper > .bt_bb_section:first-child .animate {
            opacity:1 !important;
            transform:none !important;
            transition:none !important;
            transition-delay:0s !important;
            animation:none !important;
        }

        body.home .bt_bb_wrapper > .bt_bb_section:first-child .bt_bb_background_image_holder {
            background-attachment:scroll !important;
            transform:none !important;
            will-change:auto !important;
        }
    }
    </style>
    <?php
}
add_action( 'wp_head', 'privilege_fp_home_mobile_fast_paint', 0 );


/* =============================================================================
 * 3) Fonte de ícones RemixIconsSystem — BUG CORRIGIDO NA V5.0
 *
 * O preload da V4.10 (wp_head, priority 99999) apontava para
 * ".../_RemidiconsSystem/RemidiconsSystem.woff" — nome errado. Confirmado
 * no HAR: HTTP 302 de volta para a Home, preload perdido. A V4.10 também
 * duplicava a declaração @font-face em 4 hooks diferentes (priorities 1, 2,
 * 3 e 99999) competindo entre si sem necessidade. Substituído por UM único
 * preload + UMA declaração @font-face, para o caminho que o HAR confirma
 * como HTTP 200 real.
 * ============================================================================= */
function privilege_fp_icon_font_optimize() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }

    $font_url = get_template_directory_uri() . '/assets/icon-sets/RemixIconsSystem/RemixIconsSystem.woff';

    echo "\n<link rel=\"preload\" href=\"" . esc_url( $font_url ) . "\" as=\"font\" type=\"font/woff\" crossorigin>\n";
    echo '<style id="privilege-fp-icon-font-swap">@font-face{font-family:\'RemixIconsSystem\';src:url(\'' . esc_url( $font_url ) . '\') format(\'woff\');font-style:normal;font-weight:400;font-display:swap;}</style>' . "\n";
}
add_action( 'wp_head', 'privilege_fp_icon_font_optimize', 5 );


/* =============================================================================
 * 4) Helper genérico para setar/substituir um atributo de uma tag HTML.
 *    A V4.10 tinha duas cópias quase idênticas desta função (uma para o
 *    the_content, outra para o buffer de HTML). Consolidado em uma só.
 * ============================================================================= */
function privilege_fp_set_attr( $tag, $name, $value ) {
    $pattern     = '/\s' . preg_quote( $name, '/' ) . '\s*=\s*(["\']).*?\1/i';
    $replacement = ' ' . $name . '="' . esc_attr( $value ) . '"';

    if ( preg_match( $pattern, $tag ) ) {
        return preg_replace( $pattern, $replacement, $tag, 1 );
    }

    return preg_replace( '/>$/', $replacement . '>', $tag, 1 );
}


/* =============================================================================
 * 5) Home: promove as duas primeiras imagens de topo do Bold Builder
 *    (bt_bb_section_top_section_coverage_image) a candidatas críticas.
 *    Mantido como reforço/defesa adicional: o WordPress core (6.3+) já
 *    detecta fetchpriority=high automaticamente na maioria dos casos
 *    (confirmado no PSI: a imagem de conteúdo real já chega com
 *    loading=eager/fetchpriority=high mesmo sem este filtro tocá-la) — mas
 *    esta classe específica do Bold Builder pode não ser reconhecida pela
 *    heurística nativa em todo layout, então o reforço explícito é mantido
 *    por segurança. É idempotente: se o core já marcou, isso só repete o
 *    mesmo atributo.
 *
 * V5.2 — achado do PSI de 23/08 (desktop): essa MESMA imagem (confirmada
 * pela classe privilege-lcp-critical, aplicada só aqui) é hoje o maior
 * item isolado de CLS do site: 0,767 de um total de 0,784 — mesmo com
 * width/height presentes no HTML. Isso é a assinatura clássica de um
 * "coverage image" cujo CSS o estica/corta (object-fit:cover) para
 * preencher uma seção cuja altura é definida por outra coisa, independente
 * da proporção real da imagem — nesse caso, o navegador reserva espaço
 * pela proporção do width/height (errada para o layout final) e corrige
 * depois, causando o salto.
 *
 * Correção aplicada aqui: aspect-ratio inline, calculado a partir do
 * width/height que a própria tag já traz (não é um número fixo — se a
 * imagem mudar de dimensão, acompanha). É uma correção PARCIAL e
 * conservadora de propósito: só entra em vigor para a dimensão que o CSS
 * do tema deixar como "auto"; se o tema força height a um valor fixo
 * (ex.: 100% de uma seção com altura própria), esta regra fica sem efeito
 * nessa dimensão — o que é intencional, porque forçar height:auto às
 * cegas poderia encolher visualmente uma seção desenhada para ser mais
 * alta que a proporção nativa da imagem, e isso eu não tenho como
 * conferir sem ver o CSS do tema ao vivo. O painel Administração >
 * Privilege Performance ganhou uma seção que extrai a regra CSS real do
 * tema para essa classe — ver AUDIT-README.txt.
 * ============================================================================= */
function privilege_fp_mark_front_page_lcp_images( $content ) {
    if ( is_admin() || ! is_front_page() || false === stripos( $content, 'bt_bb_section_top_section_coverage_image' ) ) {
        return $content;
    }

    $promoted = 0;

    return preg_replace_callback(
        '/<img\b[^>]*>/i',
        static function ( $match ) use ( &$promoted ) {
            $tag = $match[0];

            if ( $promoted >= 2 || false === stripos( $tag, 'bt_bb_section_top_section_coverage_image' ) ) {
                return $tag;
            }

            $promoted++;

            if ( preg_match( '/\sclass=(["\'])(.*?)\1/i', $tag, $class_match ) ) {
                $classes = trim( $class_match[2] . ' privilege-lcp-critical' );
                $tag     = preg_replace( '/\sclass=(["\'])(.*?)\1/i', ' class="' . esc_attr( $classes ) . '"', $tag, 1 );
            } else {
                $tag = preg_replace( '/<img\b/i', '<img class="privilege-lcp-critical"', $tag, 1 );
            }

            $tag = privilege_fp_set_attr( $tag, 'loading', 'eager' );
            $tag = privilege_fp_set_attr( $tag, 'fetchpriority', 'high' );
            $tag = privilege_fp_set_attr( $tag, 'decoding', 'async' );

            if (
                preg_match( '/\bwidth=(["\'])(\d+)\1/i', $tag, $w_m )
                && preg_match( '/\bheight=(["\'])(\d+)\1/i', $tag, $h_m )
                && (int) $h_m[2] > 0
            ) {
                $ratio_css = 'aspect-ratio:' . (int) $w_m[2] . '/' . (int) $h_m[2] . ' !important;';

                if ( preg_match( '/\sstyle=(["\'])(.*?)\1/i', $tag, $style_m ) ) {
                    $new_style = rtrim( $style_m[2] );
                    if ( '' !== $new_style && ';' !== substr( $new_style, -1 ) ) {
                        $new_style .= ';';
                    }
                    $new_style .= $ratio_css;
                    $tag        = preg_replace( '/\sstyle=(["\']).*?\1/i', ' style="' . esc_attr( $new_style ) . '"', $tag, 1 );
                } else {
                    $tag = preg_replace( '/>$/', ' style="' . esc_attr( $ratio_css ) . '">', $tag, 1 );
                }
            }

            return $tag;
        },
        $content
    );
}
add_filter( 'the_content', 'privilege_fp_mark_front_page_lcp_images', 50 );



/* =============================================================================
 * 6a) Preload dinâmico de LCP — SUBSTITUI o hardcode de filename da V4.10.
 *
 * Duas fontes, nenhuma delas um nome de arquivo fixo:
 *
 * 1) a PRIMEIRA imagem que o próprio WordPress/tema já marcou como
 *    fetchpriority="high" no HTML final (heurística nativa do core).
 *
 * 2) NOVO NA V5.1 — o hero com background-image inline do Bold Builder
 *    (div.bt_bb_background_image_holder, style="background-image:url(...)").
 *    Achado do PSI de 22/08 (desktop, seção "Descoberta de solicitações de
 *    LCP"): o elemento que o Lighthouse aponta como candidato a LCP É esse
 *    background-image, não uma tag <img> — por isso é INVISÍVEL para a
 *    heurística fetchpriority=high do WordPress, que só olha para <img>. A
 *    fonte #1 sozinha não cobria esse caso.
 *
 * As duas fontes podem coexistir (preferi cobrir as duas a apostar em qual
 * delas é a real em todo viewport/dispositivo); nenhuma aposta em nome de
 * arquivo — se a Home trocar de imagem ou de hero, a extração acompanha
 * sozinha, sem precisar de outro patch manual.
 * ============================================================================= */
function privilege_fp_find_lcp_background_urls( $html ) {
    $urls = array();

    if ( ! preg_match_all( '#<div\b[^>]*>#i', $html, $div_matches ) ) {
        return $urls;
    }

    foreach ( $div_matches[0] as $tag ) {
        if ( false === stripos( $tag, 'bt_bb_background_image_holder' ) ) {
            continue;
        }

        if ( ! preg_match( '/\bclass=(["\'])[^"\']*\bbt_bb_background_image_holder\b[^"\']*\1/i', $tag ) ) {
            continue;
        }

        if ( ! preg_match( '/\bstyle=(["\'])(.*?)\1/i', $tag, $style_m ) ) {
            continue;
        }

        $style = html_entity_decode( $style_m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        if ( preg_match( '/background-image\s*:\s*url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $style, $bg_m ) ) {
            $url = trim( $bg_m[1] );

            if ( '' !== $url ) {
                $urls[] = $url;
                break; // só a 1ª ocorrência: a seção mais acima é o candidato real.
            }
        }
    }

    return $urls;
}

function privilege_fp_insert_dynamic_lcp_preload( $html ) {
    if ( ! is_string( $html ) || '' === $html || stripos( $html, '<head' ) === false ) {
        return $html;
    }

    $preloads = array();

    if ( preg_match( '#<img\b[^>]*\bfetchpriority=(["\'])high\1[^>]*>#i', $html, $img_m ) ) {
        $tag = $img_m[0];

        if ( preg_match( '/\bsrc=(["\'])(.*?)\1/i', $tag, $src_m ) ) {
            $src   = $src_m[2];
            $entry = '<link rel="preload" as="image" href="' . esc_url( $src ) . '" fetchpriority="high"';

            if ( preg_match( '/\bsrcset=(["\'])(.*?)\1/i', $tag, $srcset_m ) ) {
                $entry .= ' imagesrcset="' . esc_attr( html_entity_decode( $srcset_m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) . '"';
            }

            if ( preg_match( '/\bsizes=(["\'])(.*?)\1/i', $tag, $sizes_m ) ) {
                $entry .= ' imagesizes="' . esc_attr( $sizes_m[2] ) . '"';
            }

            $preloads[ $src ] = $entry . '>';
        }
    }

    foreach ( privilege_fp_find_lcp_background_urls( $html ) as $bg_url ) {
        if ( ! isset( $preloads[ $bg_url ] ) ) {
            $preloads[ $bg_url ] = '<link rel="preload" as="image" href="' . esc_url( $bg_url ) . '" fetchpriority="high">';
        }
    }

    if ( empty( $preloads ) ) {
        return $html;
    }

    $to_insert = array();

    foreach ( $preloads as $url => $tag ) {
        // Já existe preload apontando para essa mesma URL em algum outro lugar
        // do documento? Não duplica.
        if ( false !== stripos( $html, 'rel="preload"' ) && false !== stripos( $html, $url ) ) {
            continue;
        }

        $to_insert[] = $tag;
    }

    if ( empty( $to_insert ) ) {
        return $html;
    }

    $block = implode( "\n", $to_insert ) . "\n";

    return preg_replace( '/<head\b([^>]*)>/i', '<head$1>' . "\n" . $block, $html, 1 );
}


/* =============================================================================
 * 6b) CSS pesado do Bold Builder: padrão "loadCSS" (media=print + onload)
 *     para tirar do caminho crítico sem depender do RUCSS estar ligado.
 *     Mantido da V4.1/V4.0. NOVO na V5.0: fallback <noscript> — a V4.10 não
 *     tinha, então com JS desabilitado esse CSS nunca chegava a aplicar.
 *     Continua batendo por nome de arquivo: se/quando o RUCSS for
 *     reativado no FlyingPress, os nomes gerados mudam e este passo vira
 *     automaticamente um no-op — não precisa remover nada na mão depois.
 * ============================================================================= */
function privilege_fp_async_heavy_css( $html ) {
    return preg_replace_callback(
        '#<link\b[^>]*\brel=(["\'])stylesheet\1[^>]*>#i',
        static function ( $match ) {
            $tag = $match[0];

            $should_async = (
                stripos( $tag, 'content_elements.crush.css' ) !== false
                || stripos( $tag, 'slick.css' ) !== false
                || stripos( $tag, 'frontend-script.css' ) !== false
            );

            if ( ! $should_async ) {
                return $tag;
            }

            if ( stripos( $tag, 'media="print"' ) !== false || stripos( $tag, "media='print'" ) !== false ) {
                return $tag;
            }

            $clean = preg_replace( '/\smedia=(["\']).*?\1/i', '', $tag );
            $clean = preg_replace( '/\sonload=(["\']).*?\1/i', '', $clean );

            $async_tag = preg_replace(
                '/>$/',
                ' media="print" onload="this.media=\'all\';this.onload=null;">',
                $clean,
                1
            );

            return $async_tag . '<noscript>' . $tag . '</noscript>';
        },
        $html
    );
}


/* =============================================================================
 * 6c) Acessibilidade — a lógica original é mantida, só some a duplicação
 *     que já existia (a V4.10 tinha DOIS blocos de aria-label quase
 *     idênticos e DOIS blocos de correção do mesmo heading h3→h2;
 *     consolidado em um de cada).
 *
 *     NOVO NA V5.1: o link do logo no DESKTOP usa class="logo" puro — um
 *     seletor diferente de responsive-logo/responsive-menu-logo/
 *     responsive-sticky-logo (que parecem ser específicos do menu mobile).
 *     Nenhuma versão anterior cobria esse caso porque toda auditoria até
 *     22/08 só tinha dados de Mobile; o PSI desktop (mesma data) apontou
 *     "Os links não têm um nome compreensível" exatamente nesse link.
 * ============================================================================= */
function privilege_fp_fix_accessible_names( $html ) {
    $html = preg_replace_callback(
        '#<a\b([^>]*)>#i',
        static function ( $match ) {
            $tag = $match[0];

            if ( preg_match( '/\baria-label=(["\']).*?\1/i', $tag ) ) {
                return $tag;
            }

            $classes = '';
            if ( preg_match( '/\bclass=(["\'])(.*?)\1/i', $tag, $class_m ) ) {
                $classes = ' ' . html_entity_decode( $class_m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . ' ';
            }

            $label = '';

            if (
                stripos( $tag, 'responsive-menu-logo' ) !== false
                || stripos( $tag, 'responsive-sticky-logo' ) !== false
                || stripos( $tag, 'responsive-logo' ) !== false
                || false !== stripos( $classes, ' logo ' )
            ) {
                $label = 'Agência Privilége - Página inicial';
            } elseif ( stripos( $tag, 'btCardLink' ) !== false ) {
                $label = 'Conheça nossos serviços';
            } elseif ( stripos( $tag, 'instagram.com' ) !== false ) {
                $label = 'Agência Privilége no Instagram';
            } elseif ( stripos( $tag, 'facebook.com' ) !== false ) {
                $label = 'Agência Privilége no Facebook';
            } elseif ( stripos( $tag, 'linkedin.com' ) !== false ) {
                $label = 'Agência Privilége no LinkedIn';
            } elseif ( stripos( $tag, 'whatsapp.com' ) !== false || stripos( $tag, 'wa.me' ) !== false ) {
                $label = 'Falar com a Agência Privilége pelo WhatsApp';
            }

            if ( '' === $label ) {
                return $tag;
            }

            return preg_replace( '/>$/', ' aria-label="' . esc_attr( $label ) . '">', $tag, 1 );
        },
        $html
    );

    // h5/h6 do Bold Builder que pulam nível → h4.
    $html = preg_replace_callback(
        '#<(h5|h6)([^>]*class=(["\'])[^"\']*bt_bb_headline_tag[^"\']*\3[^>]*)>(.*?)</\1>#is',
        static function ( $m ) {
            return '<h4' . $m[2] . '>' . $m[4] . '</h4>';
        },
        $html
    );

    // Heading específico apontado pelo Lighthouse: h3 → h2 (compara o texto
    // já sem tags/entidades, então funciona mesmo com spans/strong internos).
    $html = preg_replace_callback(
        '#<h3\b([^>]*class=(["\'])[^"\']*\bbt_bb_headline_tag\b[^"\']*\2[^>]*)>(.*?)</h3>#is',
        static function ( $m ) {
            $plain = trim( preg_replace(
                '/\s+/u',
                ' ',
                wp_strip_all_tags( html_entity_decode( $m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) )
            ) );

            $is_target = (
                stripos( $plain, 'Agência Privilége representa a escolha mais estratégica' ) !== false
                || stripos( $plain, 'Agencia Privilege representa a escolha mais estrategica' ) !== false
            );

            if ( ! $is_target ) {
                return $m[0];
            }

            return '<h2' . $m[1] . '>' . $m[3] . '</h2>';
        },
        $html
    );

    // Heading "Atendimento": h4 → h3.
    $html = preg_replace_callback(
        '#<h4\b([^>]*class=(["\'])[^"\']*\bbt_bb_headline_tag\b[^"\']*\2[^>]*)>(.*?)</h4>#is',
        static function ( $m ) {
            $plain = trim( preg_replace(
                '/\s+/u',
                ' ',
                wp_strip_all_tags( html_entity_decode( $m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) )
            ) );

            if ( 0 !== strcasecmp( $plain, 'Atendimento' ) ) {
                return $m[0];
            }

            return '<h3' . $m[1] . '>' . $m[3] . '</h3>';
        },
        $html,
        1
    );

    return $html;
}


/* =============================================================================
 * 6d) WebP sob demanda para imagens locais SEM srcset (badges/logos de
 *     tamanho fixo — ex.: os ícones de tecnologia da seção "Tecnologias e
 *     marcas"). Generaliza o que a V4.9/V4.10 faziam na mão, arquivo por
 *     arquivo (Python.png, logo.png) — inclusive um desses hardcodes já
 *     está morto hoje porque o arquivo de origem mudou (ver cabeçalho).
 *     Escopo deliberadamente conservador: só entra em imagens SEM srcset,
 *     para não colidir com imagens responsivas que o FlyingPress já trata.
 * ============================================================================= */
function privilege_fp_local_upload_path_from_url( $url ) {
    $uploads = wp_upload_dir();

    if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) ) {
        return '';
    }

    $base_url = $uploads['baseurl'];

    if ( 0 !== strpos( $url, $base_url ) ) {
        return '';
    }

    $relative = ltrim( substr( $url, strlen( $base_url ) ), '/' );
    $path     = trailingslashit( $uploads['basedir'] ) . $relative;
    $real     = realpath( $path );
    $real_base = realpath( $uploads['basedir'] );

    if ( ! $real || ! $real_base || 0 !== strpos( $real, $real_base ) ) {
        return '';
    }

    return $real;
}

function privilege_fp_path_to_upload_url( $path ) {
    $uploads = wp_upload_dir();

    if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
        return '';
    }

    $real_base = realpath( $uploads['basedir'] );
    $real_path = realpath( $path );

    if ( ! $real_base || ! $real_path || 0 !== strpos( $real_path, $real_base ) ) {
        return '';
    }

    $relative = ltrim( substr( $real_path, strlen( $real_base ) ), '/\\' );

    return trailingslashit( $uploads['baseurl'] ) . str_replace( '\\', '/', $relative );
}

function privilege_fp_get_or_create_webp( $source_path ) {
    if ( ! is_file( $source_path ) || ! is_readable( $source_path ) ) {
        return '';
    }

    // Limites de segurança: fora disso, não processa on-the-fly sem
    // verificação ao vivo (evita custo alto em imagem inesperadamente grande).
    if ( filesize( $source_path ) > 8 * MB_IN_BYTES ) {
        return '';
    }

    $info = @getimagesize( $source_path );
    if ( ! $info || $info[0] > 4000 || $info[1] > 4000 ) {
        return '';
    }

    $webp_path = preg_replace( '/\.(png|jpe?g)$/i', '.webp', $source_path );

    if ( $webp_path === $source_path ) {
        return '';
    }

    if ( is_file( $webp_path ) ) {
        return $webp_path;
    }

    $editor = wp_get_image_editor( $source_path );
    if ( is_wp_error( $editor ) ) {
        return '';
    }

    if ( method_exists( $editor, 'set_quality' ) ) {
        $editor->set_quality( 82 );
    }

    $saved = $editor->save( $webp_path, 'image/webp' );

    if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! is_file( $saved['path'] ) ) {
        return '';
    }

    return $saved['path'];
}

function privilege_fp_auto_webp_content_images( $html ) {
    if ( ! is_string( $html ) || '' === $html ) {
        return $html;
    }

    return preg_replace_callback(
        '#<img\b[^>]*>#i',
        static function ( $match ) {
            $tag = $match[0];

            if ( preg_match( '/\bsrcset=/i', $tag ) ) {
                return $tag;
            }

            if ( ! preg_match( '/\bsrc=(["\'])(.*?)\1/i', $tag, $src_m ) ) {
                return $tag;
            }

            $src = html_entity_decode( $src_m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );

            if ( ! preg_match( '/\.(png|jpe?g)(\?.*)?$/i', $src ) ) {
                return $tag;
            }

            $source_path = privilege_fp_local_upload_path_from_url( strtok( $src, '?' ) );
            if ( ! $source_path ) {
                return $tag;
            }

            $webp_path = privilege_fp_get_or_create_webp( $source_path );
            if ( ! $webp_path ) {
                return $tag;
            }

            $webp_url = privilege_fp_path_to_upload_url( $webp_path );
            if ( ! $webp_url ) {
                return $tag;
            }

            return preg_replace(
                '/\ssrc=(["\']).*?\1/i',
                ' src="' . esc_url( $webp_url ) . '"',
                $tag,
                1
            );
        },
        $html
    );
}


/* =============================================================================
 * 6e) Inline de CSS local pequeno (<= 8 KB) que o Lighthouse ainda acusa
 *     como render-blocking. Mantido da V4.10. Auto-desativa sozinho quando
 *     o RUCSS estiver ligado, porque os nomes de arquivo do FlyingPress
 *     mudam e as exclusões abaixo (aiko-v5-, content_elements.crush.css)
 *     deixam de casar com o link processado pelo RUCSS.
 * ============================================================================= */
function privilege_fp_local_url_to_path( $url ) {
    $home = wp_parse_url( home_url( '/' ) );
    $data = wp_parse_url( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

    if ( empty( $data['path'] ) ) {
        return '';
    }

    if ( ! empty( $data['host'] ) && ! empty( $home['host'] ) && strtolower( $data['host'] ) !== strtolower( $home['host'] ) ) {
        return '';
    }

    $path = ABSPATH . ltrim( rawurldecode( $data['path'] ), '/' );
    $real = realpath( $path );

    if ( ! $real || ! is_file( $real ) || ! is_readable( $real ) ) {
        return '';
    }

    $root = realpath( ABSPATH );
    if ( ! $root || 0 !== strpos( $real, $root ) ) {
        return '';
    }

    return $real;
}

function privilege_fp_inline_small_local_css( $html ) {
    if ( ! is_string( $html ) || '' === $html ) {
        return $html;
    }

    return preg_replace_callback(
        '#<link\b[^>]*\brel=(["\'])stylesheet\1[^>]*>#i',
        static function ( $m ) {
            $tag = $m[0];

            if (
                stripos( $tag, 'aiko-v5-' ) !== false
                || stripos( $tag, '/themes/aiko/style.css' ) !== false
                || stripos( $tag, 'content_elements.crush.css' ) !== false
            ) {
                return $tag;
            }

            if ( ! preg_match( '/\bhref=(["\'])(.*?)\1/i', $tag, $href_m ) ) {
                return $tag;
            }

            $path = privilege_fp_local_url_to_path( $href_m[2] );
            if ( ! $path ) {
                return $tag;
            }

            $size = filesize( $path );
            if ( false === $size || $size <= 0 || $size > 8192 ) {
                return $tag;
            }

            $css = file_get_contents( $path );
            if ( false === $css || '' === trim( $css ) ) {
                return $tag;
            }

            return '<style data-privilege-inline-css="1">' . $css . '</style>';
        },
        $html
    );
}


/* =============================================================================
 * 6f) Logo com srcset 1x/2x real + eager/high forçados (V5.1).
 *
 * BUG DA V4.10: convertia o logo para WebP mas APAGAVA o srcset
 * (`preg_replace('/\ssrcset=.../','',...)`), forçando TODO dispositivo —
 * inclusive telas comuns (1x) — a baixar sempre a variante de 600x200,
 * mesmo a imagem sendo exibida a 368x123. É exatamente o "imagem maior do
 * que precisa ser" que o PSI aponta. A V5.0 gera as duas variantes (368x123
 * para 1x, 600x200 para 2x/retina) e devolve o srcset em vez de removê-lo.
 *
 * V5.1: o PSI de 22/08 (desktop) revelou que esse <img> chegava com
 * loading="lazy"/fetchpriority="low" aplicados pelo próprio WordPress core
 * — maior item do relatório de layout shift do desktop. Ver a função
 * privilege_fp_optimize_logo_html() abaixo para a correção.
 * ============================================================================= */
function privilege_fp_get_logo_webp_urls() {
    static $cached = null;

    if ( null !== $cached ) {
        return $cached;
    }

    $cached  = array();
    $uploads = wp_upload_dir();

    if ( ! empty( $uploads['error'] ) ) {
        return $cached;
    }

    $source = trailingslashit( $uploads['basedir'] ) . '2025/08/logo.png';
    if ( ! is_file( $source ) || ! is_readable( $source ) ) {
        return $cached;
    }

    $dir = trailingslashit( $uploads['basedir'] ) . 'privilege-performance';
    if ( ! wp_mkdir_p( $dir ) ) {
        return $cached;
    }

    $variants = array(
        '1x' => array( 368, 123, 'logo-368x123-v5.webp' ),
        '2x' => array( 600, 200, 'logo-600x200-v5.webp' ),
    );

    foreach ( $variants as $key => $spec ) {
        $w        = $spec[0];
        $h        = $spec[1];
        $filename = $spec[2];
        $target   = trailingslashit( $dir ) . $filename;
        $url      = trailingslashit( $uploads['baseurl'] ) . 'privilege-performance/' . $filename;

        if ( ! is_file( $target ) ) {
            $editor = wp_get_image_editor( $source );

            if ( is_wp_error( $editor ) ) {
                continue;
            }

            $editor->resize( $w, $h, true );

            if ( method_exists( $editor, 'set_quality' ) ) {
                $editor->set_quality( 86 );
            }

            $saved = $editor->save( $target, 'image/webp' );

            if ( is_wp_error( $saved ) ) {
                continue;
            }
        }

        $cached[ $key ] = array(
            'url' => $url,
            'w'   => $w,
            'h'   => $h,
        );
    }

    return $cached;
}

function privilege_fp_optimize_logo_html( $html ) {
    $variants = privilege_fp_get_logo_webp_urls();

    if ( empty( $variants['1x'] ) || empty( $variants['2x'] ) || ! is_string( $html ) || '' === $html ) {
        return $html;
    }

    return preg_replace_callback(
        '#<img\b[^>]*src=(["\'])[^"\']*/2025/08/logo\.png[^"\']*\1[^>]*>#i',
        static function ( $m ) use ( $variants ) {
            $tag = $m[0];

            $tag = preg_replace(
                '/\ssrc=(["\']).*?\1/i',
                ' src="' . esc_url( $variants['1x']['url'] ) . '"',
                $tag,
                1
            );

            $srcset = esc_attr( $variants['1x']['url'] . ' 1x, ' . $variants['2x']['url'] . ' 2x' );

            if ( preg_match( '/\ssrcset=(["\']).*?\1/i', $tag ) ) {
                $tag = preg_replace( '/\ssrcset=(["\']).*?\1/i', ' srcset="' . $srcset . '"', $tag, 1 );
            } else {
                $tag = preg_replace( '/>$/', ' srcset="' . $srcset . '">', $tag, 1 );
            }

            $tag = privilege_fp_set_attr( $tag, 'width', (string) $variants['1x']['w'] );
            $tag = privilege_fp_set_attr( $tag, 'height', (string) $variants['1x']['h'] );
            $tag = privilege_fp_set_attr( $tag, 'decoding', 'async' );

            // V5.1: o PSI de 22/08 (desktop) mostrou este <img> chegando com
            // loading="lazy" + fetchpriority="low" + sizes="auto" — atributos
            // que o próprio WordPress core aplica automaticamente a imagens
            // que ele não reconhece como prioritárias, e que aqui pousaram no
            // logo do cabeçalho, sempre visível de cara. Era o maior item do
            // relatório de layout shift do desktop (0,216 do CLS). O logo
            // nunca deveria ser lazy; forçamos eager/high explicitamente.
            $tag = privilege_fp_set_attr( $tag, 'loading', 'eager' );
            $tag = privilege_fp_set_attr( $tag, 'fetchpriority', 'high' );

            // "sizes" só faz sentido com srcset de descritores de largura
            // (100w, 300w...); o nosso é de densidade (1x, 2x) — sizes="auto"
            // do core não se aplica aqui e é removido para não conflitar.
            $tag = preg_replace( '/\ssizes=(["\']).*?\1/i', '', $tag, 1 );

            return $tag;
        },
        $html
    );
}


/* =============================================================================
 * 6) Orquestrador — roda os passos 6a–6f, nessa ordem, sobre o HTML
 *    completo da Home. Substitui privilege_fp_v40_optimize_home_html.
 * ============================================================================= */
function privilege_fp_optimize_home_html( $html ) {
    if (
        ! is_string( $html )
        || '' === $html
        || stripos( $html, '<html' ) === false
        || stripos( $html, '</head>' ) === false
    ) {
        return $html;
    }

    $html = privilege_fp_insert_dynamic_lcp_preload( $html );
    $html = privilege_fp_async_heavy_css( $html );
    $html = privilege_fp_fix_accessible_names( $html );
    $html = privilege_fp_auto_webp_content_images( $html );
    $html = privilege_fp_inline_small_local_css( $html );
    $html = privilege_fp_optimize_logo_html( $html );

    return $html;
}

function privilege_fp_start_home_html_buffer() {
    if (
        is_admin()
        || wp_doing_ajax()
        || wp_doing_cron()
        || ! is_front_page()
        || is_feed()
        || is_embed()
    ) {
        return;
    }

    ob_start( 'privilege_fp_optimize_home_html' );
}
add_action( 'template_redirect', 'privilege_fp_start_home_html_buffer', 0 );


/* =============================================================================
 * 7) CSS principal do Aiko: cópia derivada só com font-display:swap
 *    (endereça diretamente o achado "Exibição de fontes" do PSI para
 *    QUALQUER @font-face do tema, não só os ícones). O tema original nunca
 *    é tocado — a cópia fica em uploads/privilege-performance/, versionada
 *    pelo filemtime do style.css de origem.
 *
 *    REMOVIDO na V5.0: a V4.6 também tentava remover blocos @media
 *    desktop-only (min-width>=768px) para a variante mobile. Medido no HAR:
 *    a diferença de tamanho TRANSFERIDO (já comprimido) entre a variante
 *    desktop e a mobile era de ~0,2 KiB — a peça de CSS que pesa não está
 *    concentrada nesses blocos. Complexidade removida por não estar
 *    pagando o que custava manter.
 * ============================================================================= */
function privilege_fp_add_font_display_swap( $css ) {
    return preg_replace_callback(
        '/@font-face\s*\{[^}]*\}/is',
        static function ( $m ) {
            $block = $m[0];

            if ( false !== stripos( $block, 'font-display:' ) ) {
                return preg_replace( '/font-display\s*:\s*[^;}\s]+/i', 'font-display:swap', $block, 1 );
            }

            return preg_replace( '/}\s*$/', 'font-display:swap;}', $block, 1 );
        },
        $css
    );
}

function privilege_fp_get_aiko_optimized_css_url() {
    $source = trailingslashit( get_template_directory() ) . 'style.css';

    if ( ! is_file( $source ) || ! is_readable( $source ) ) {
        return '';
    }

    $uploads = wp_upload_dir();
    if ( ! empty( $uploads['error'] ) ) {
        return '';
    }

    $dir = trailingslashit( $uploads['basedir'] ) . 'privilege-performance';
    if ( ! wp_mkdir_p( $dir ) ) {
        return '';
    }

    $mtime  = (int) filemtime( $source );
    $name   = 'aiko-v5-' . $mtime . '.css';
    $target = trailingslashit( $dir ) . $name;
    $url    = trailingslashit( $uploads['baseurl'] ) . 'privilege-performance/' . $name;

    if ( ! is_file( $target ) ) {
        $css = file_get_contents( $source );

        if ( false === $css || '' === $css ) {
            return '';
        }

        $css     = privilege_fp_add_font_display_swap( $css );
        $written = file_put_contents( $target, $css, LOCK_EX );

        if ( false === $written ) {
            return '';
        }
    }

    return $url;
}

function privilege_fp_replace_aiko_stylesheet_url( $src, $handle ) {
    if ( is_admin() || ! is_front_page() ) {
        return $src;
    }

    if (
        false === stripos( $src, '/themes/aiko/style.css' )
        && 'aiko-style' !== $handle
        && 'aiko-parent-style' !== $handle
    ) {
        return $src;
    }

    $optimized = privilege_fp_get_aiko_optimized_css_url();

    return $optimized ? $optimized : $src;
}
add_filter( 'style_loader_src', 'privilege_fp_replace_aiko_stylesheet_url', 999, 2 );


/* =============================================================================
 * 8) Mobile: descarrega o JS de floating-image só na Home mobile. A 1ª
 *    dobra já tem transform/animação desativados pelo fast-paint (passo 2),
 *    então esse script só sobrava como leitura geométrica/reflow extra no
 *    Lighthouse mobile (uma das fontes do "Reflow forçado" no PSI).
 *    Inalterado desde a V4.6.
 * ============================================================================= */
function privilege_fp_dequeue_mobile_floating_image_js() {
    if ( ! is_front_page() || ! wp_is_mobile() ) {
        return;
    }

    $handles = array( 'bt_bb_floating_image', 'bt-bb-floating-image', 'boldthemes-floating-image' );

    foreach ( $handles as $handle ) {
        wp_dequeue_script( $handle );
    }
}
add_action( 'wp_enqueue_scripts', 'privilege_fp_dequeue_mobile_floating_image_js', 9999 );


/* =============================================================================
 * 9) CSS avulso de baixo risco: não amplia o logo "node" acima da resolução
 *    nativa (Práticas recomendadas), alvos de toque de 44px no rodapé
 *    (Acessibilidade), content-visibility em seções profundas (custo de
 *    layout/paint abaixo da dobra). Mantido da V4/V4.6 — só sem a parte de
 *    @font-face duplicada, que virou o passo 3.
 * ============================================================================= */
function privilege_fp_misc_head_css() {
    if ( is_admin() || ! is_front_page() ) {
        return;
    }
    ?>
    <style id="privilege-fp-misc-css">
        img[alt="node"] {
            width:189px !important;
            height:51px !important;
            max-width:189px !important;
            object-fit:contain;
        }

        .menu-politicas a,
        footer a[href*="politica"],
        footer a[href*="termos-de-uso"] {
            min-height:44px;
            min-width:44px;
            display:inline-flex;
            align-items:center;
            padding:8px 6px;
        }

        @supports (content-visibility:auto) {
            body.home .bt_bb_wrapper > .bt_bb_section:nth-of-type(n+4) {
                content-visibility:auto;
                contain-intrinsic-size:auto 900px;
            }
        }
    </style>
    <?php
}
add_action( 'wp_head', 'privilege_fp_misc_head_css', 2 );


/* =============================================================================
 * 10) /llms.txt virtual — inalterado.
 * ============================================================================= */
function privilege_fp_llms_txt() {
    $path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';

    if ( '/llms.txt' !== $path ) {
        return;
    }

    status_header( 200 );
    nocache_headers();
    header( 'Content-Type: text/plain; charset=utf-8' );

    echo "# Agência Privilége\n\n";
    echo "Agência digital especializada em criação de sites, e-commerce, SEO, Social Media e soluções digitais em Juiz de Fora.\n\n";
    echo "## Links principais\n";
    echo '- ' . home_url( '/' ) . "\n";
    echo '- ' . home_url( '/servicos/' ) . "\n";
    echo '- ' . home_url( '/portfolio/' ) . "\n";
    echo '- ' . home_url( '/noticias/' ) . "\n";
    echo '- ' . home_url( '/orcamentos/' ) . "\n";

    exit;
}
add_action( 'template_redirect', 'privilege_fp_llms_txt', -100 );


/* =============================================================================
 * 11) Resource hints — inalterado (sem preconnect de terceiros aqui).
 * ============================================================================= */
function privilege_fp_resource_hints( $hints, $relation_type ) {
    if ( ! is_front_page() ) {
        return $hints;
    }

    if ( 'preconnect' === $relation_type ) {
        return array_values( array_unique( $hints ) );
    }

    return $hints;
}
add_filter( 'wp_resource_hints', 'privilege_fp_resource_hints', 20, 2 );


/* =============================================================================
 * 12) Admin bar: controles dedicados de cache da Home — inalterado da V4.x.
 *     Ações separadas para não disparar preload global de milhares de URLs
 *     por engano ao mexer só na Home.
 * ============================================================================= */
function privilege_fp_add_home_cache_admin_bar( $wp_admin_bar ) {
    if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $wp_admin_bar->add_node(
        array(
            'id'    => 'privilege-fp-home-cache',
            'title' => '⚡ Cache da Home',
            'href'  => false,
            'meta'  => array(
                'title' => 'Controles direcionados do cache da Home',
            ),
        )
    );

    $purge_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=privilege_fp_purge_home_cache' ),
        'privilege_fp_purge_home_cache'
    );

    $generate_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=privilege_fp_generate_home_cache' ),
        'privilege_fp_generate_home_cache'
    );

    $wp_admin_bar->add_node(
        array(
            'parent' => 'privilege-fp-home-cache',
            'id'     => 'privilege-fp-purge-home-cache',
            'title'  => '🗑 Limpar cache da Home',
            'href'   => $purge_url,
            'meta'   => array(
                'title' => 'Remover somente a Home do cache do FlyingPress',
            ),
        )
    );

    $wp_admin_bar->add_node(
        array(
            'parent' => 'privilege-fp-home-cache',
            'id'     => 'privilege-fp-generate-home-cache',
            'title'  => '⚡ Gerar cache da Home',
            'href'   => $generate_url,
            'meta'   => array(
                'title' => 'Gerar/preaquecer somente a Home, desktop e mobile',
            ),
        )
    );
}
add_action( 'admin_bar_menu', 'privilege_fp_add_home_cache_admin_bar', 999 );

function privilege_fp_store_home_cache_error( $stage, $message ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return;
    }

    set_transient(
        'privilege_fp_home_cache_error_' . $user_id,
        array(
            'stage'   => sanitize_key( $stage ),
            'message' => sanitize_text_field( (string) $message ),
        ),
        5 * MINUTE_IN_SECONDS
    );
}

function privilege_fp_warm_home_cache_fallback( $home_url ) {
    $agents = array(
        'desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36',
        'mobile'  => 'Mozilla/5.0 (Linux; Android 14; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Mobile Safari/537.36',
    );

    $results = array();

    foreach ( $agents as $variant => $user_agent ) {
        $results[ $variant ] = wp_remote_get(
            $home_url,
            array(
                'timeout'     => 10,
                'redirection' => 3,
                'blocking'    => true,
                'cookies'     => array(),
                'headers'     => array(
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ),
                'user-agent'  => $user_agent,
            )
        );
    }

    return $results;
}

function privilege_fp_home_cache_fallback_ok( $results ) {
    if ( ! is_array( $results ) || empty( $results['desktop'] ) || empty( $results['mobile'] ) ) {
        return false;
    }

    foreach ( array( 'desktop', 'mobile' ) as $variant ) {
        if ( is_wp_error( $results[ $variant ] ) ) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $results[ $variant ] );
        if ( $code < 200 || $code >= 400 ) {
            return false;
        }
    }

    return true;
}

function privilege_fp_store_fallback_errors( $results ) {
    if ( ! is_array( $results ) ) {
        privilege_fp_store_home_cache_error( 'generate-fallback', 'Resposta inesperada ao acionar desktop/mobile.' );
        return;
    }

    $messages = array();
    foreach ( array( 'desktop', 'mobile' ) as $variant ) {
        if ( ! isset( $results[ $variant ] ) ) {
            $messages[] = $variant . ': sem resposta';
            continue;
        }

        if ( is_wp_error( $results[ $variant ] ) ) {
            $messages[] = $variant . ': ' . $results[ $variant ]->get_error_message();
            continue;
        }

        $code = (int) wp_remote_retrieve_response_code( $results[ $variant ] );
        if ( $code < 200 || $code >= 400 ) {
            $messages[] = $variant . ': HTTP ' . $code;
        }
    }

    if ( $messages ) {
        privilege_fp_store_home_cache_error( 'generate-fallback', implode( ' | ', $messages ) );
    }
}

function privilege_fp_home_cache_redirect( $status ) {
    $redirect = wp_get_referer();
    if ( ! $redirect ) {
        $redirect = admin_url();
    }

    wp_safe_redirect(
        add_query_arg(
            'privilege_fp_home_cache',
            rawurlencode( $status ),
            $redirect
        )
    );
    exit;
}

function privilege_fp_purge_home_cache() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Você não tem permissão para executar esta ação.', 'privilege' ) );
    }

    check_admin_referer( 'privilege_fp_purge_home_cache' );

    $home_url = trailingslashit( home_url( '/' ) );

    if ( ! class_exists( '\\FlyingPress\\Purge' ) || ! is_callable( array( '\\FlyingPress\\Purge', 'purge_urls' ) ) ) {
        privilege_fp_home_cache_redirect( 'purge-api-missing' );
    }

    try {
        \FlyingPress\Purge::purge_urls( array( $home_url ) );
        privilege_fp_home_cache_redirect( 'purge-ok' );
    } catch ( Throwable $e ) {
        privilege_fp_store_home_cache_error( 'purge', get_class( $e ) . ': ' . $e->getMessage() );
        error_log( '[Privilege] FlyingPress purge Home falhou: ' . get_class( $e ) . ': ' . $e->getMessage() );
        privilege_fp_home_cache_redirect( 'purge-error' );
    }
}
add_action( 'admin_post_privilege_fp_purge_home_cache', 'privilege_fp_purge_home_cache' );

function privilege_fp_prewarm_generated_assets() {
    // V5.1: gera os arquivos derivados (WebP do logo, cópia com
    // font-display:swap do CSS do Aiko) ANTES do preload da Home, para que a
    // primeira requisição real — inclusive a do próprio preload abaixo — já
    // encontre tudo pronto em disco, em vez de gerar na hora. Processamento
    // de imagem/leitura+regravação de um CSS de ~800 KB de forma síncrona no
    // meio de uma requisição de página é o tipo de custo que só aparece uma
    // vez (até o próximo purge), mas se um teste de PageSpeed calhar de
    // bater exatamente nessa requisição, o resultado sai artificialmente
    // pior — plausível candidato para a oscilação de LCP mobile vista no
    // PSI de 22/08. As duas funções abaixo já são seguras para rodar fora de
    // um request de front-end: só leem/gravam arquivo, sem depender de nada
    // específico do contexto da Home.
    privilege_fp_get_logo_webp_urls();
    privilege_fp_get_aiko_optimized_css_url();
}

function privilege_fp_generate_home_cache() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Você não tem permissão para executar esta ação.', 'privilege' ) );
    }

    check_admin_referer( 'privilege_fp_generate_home_cache' );

    privilege_fp_prewarm_generated_assets();

    $home_url = trailingslashit( home_url( '/' ) );

    if ( class_exists( '\\FlyingPress\\Preload' ) && is_callable( array( '\\FlyingPress\\Preload', 'preload_url' ) ) ) {
        try {
            \FlyingPress\Preload::preload_url( $home_url );
            privilege_fp_home_cache_redirect( 'generate-api-ok' );
        } catch ( Throwable $e ) {
            privilege_fp_store_home_cache_error( 'generate-preload', get_class( $e ) . ': ' . $e->getMessage() );
            error_log( '[Privilege] FlyingPress preload Home falhou; usando fallback: ' . get_class( $e ) . ': ' . $e->getMessage() );
        }
    }

    $fallback = privilege_fp_warm_home_cache_fallback( $home_url );

    if ( privilege_fp_home_cache_fallback_ok( $fallback ) ) {
        privilege_fp_home_cache_redirect( 'generate-fallback-ok' );
    }

    privilege_fp_store_fallback_errors( $fallback );
    privilege_fp_home_cache_redirect( 'generate-error' );
}
add_action( 'admin_post_privilege_fp_generate_home_cache', 'privilege_fp_generate_home_cache' );

function privilege_fp_home_cache_admin_notice() {
    if ( ! current_user_can( 'manage_options' ) || empty( $_GET['privilege_fp_home_cache'] ) ) {
        return;
    }

    $status = sanitize_key( wp_unslash( $_GET['privilege_fp_home_cache'] ) );
    $class  = 'notice notice-success is-dismissible';

    switch ( $status ) {
        case 'purge-ok':
            $message = 'FlyingPress: somente o cache da Home foi removido. As demais páginas não foram afetadas.';
            break;

        case 'generate-api-ok':
            $message = 'FlyingPress: somente a Home foi enviada ao preload.';
            break;

        case 'generate-fallback-ok':
            $class   = 'notice notice-warning is-dismissible';
            $message = 'FlyingPress: preload direto indisponível neste contexto. As variantes desktop e mobile da Home foram acionadas para entrar na fila. Nenhuma outra URL foi adicionada.';
            break;

        case 'purge-api-missing':
            $class   = 'notice notice-error is-dismissible';
            $message = 'FlyingPress: a API de purge por URL não foi encontrada nesta instalação.';
            break;

        case 'purge-error':
            $class   = 'notice notice-error is-dismissible';
            $message = 'FlyingPress: não foi possível remover o cache da Home.';
            break;

        case 'generate-error':
            $class   = 'notice notice-error is-dismissible';
            $message = 'FlyingPress: não foi possível acionar a geração direcionada da Home.';
            break;

        default:
            $class   = 'notice notice-warning is-dismissible';
            $message = 'FlyingPress: ação de cache da Home concluída com estado não reconhecido.';
            break;
    }

    $error_key = 'privilege_fp_home_cache_error_' . get_current_user_id();
    $detail    = get_transient( $error_key );

    if ( is_array( $detail ) && ! empty( $detail['message'] ) ) {
        delete_transient( $error_key );
        $message .= ' Detalhe [' . ( ! empty( $detail['stage'] ) ? $detail['stage'] : 'erro' ) . ']: ' . $detail['message'];
    }

    printf(
        '<div class="%1$s"><p>%2$s</p></div>',
        esc_attr( $class ),
        esc_html( $message )
    );
}
add_action( 'admin_notices', 'privilege_fp_home_cache_admin_notice' );


/* =============================================================================
 * 13) Marcador leve para inspeção de source/cache.
 * ============================================================================= */
function privilege_fp_debug_marker() {
    if ( ! is_admin() ) {
        echo "\n<!-- PRIVILEGE-MU-FLYINGPRESS-V5.0 -->\n";
    }
}
add_action( 'wp_head', 'privilege_fp_debug_marker', PHP_INT_MAX );
