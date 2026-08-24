<?php
/**
 * Privilege Site Controller — Painel de Diagnóstico (V5.0)
 *
 * Página de administração somente leitura. Três coisas, nesta ordem de
 * importância:
 *   1) a ação manual de maior impacto (RUCSS no FlyingPress), que nenhum
 *      código deste pacote pode acionar sozinho com segurança — mexer em
 *      opção de outro plugin sem saber o schema exato da versão instalada
 *      arrisca corromper configuração; um clique no painel do próprio
 *      FlyingPress é o caminho seguro;
 *   2) tabela resumida do que a V5.0 corrigiu, com a evidência de cada item;
 *   3) duas checagens 100% locais (leitura de arquivo, sem HTTP) para
 *      confirmar que os caminhos dos quais o módulo de performance depende
 *      continuam existindo — se um deles sumir (ex.: alguém renomeia o
 *      tema ou substitui o logo de origem), aparece aqui antes de virar
 *      dúvida em produção.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * V5.2: extrai (sem parser de CSS completo — regex simples, suficiente
 * para blocos de regra não aninhados, inclusive os que estão dentro de
 * um @media) os blocos de regra cujo SELETOR contenha qualquer um dos
 * termos de busca. Objetivo: mostrar aqui dentro do wp-admin a regra CSS
 * real do tema para as classes do "coverage image" do hero, sem precisar
 * pedir pra ninguém abrir o style.css manualmente. Puramente de leitura —
 * não grava nada.
 */
function privilege_diag_extract_css_rules( $css, array $needles ) {
    $matches = array();

    if ( '' === $css ) {
        return $matches;
    }

    if ( ! preg_match_all( '/[^{}]+\{[^{}]*\}/s', $css, $blocks ) ) {
        return $matches;
    }

    foreach ( $blocks[0] as $block ) {
        $selector_part = strstr( $block, '{', true );

        if ( false === $selector_part ) {
            continue;
        }

        foreach ( $needles as $needle ) {
            if ( false !== stripos( $selector_part, $needle ) ) {
                $matches[] = trim( $block );
                break;
            }
        }
    }

    return $matches;
}

function privilege_diag_menu() {
    add_menu_page(
        'Privilege Performance',
        'Privilege Performance',
        'manage_options',
        'privilege-performance',
        'privilege_diag_render_page',
        'dashicons-performance',
        80
    );
}
add_action( 'admin_menu', 'privilege_diag_menu' );

function privilege_diag_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $uploads = wp_upload_dir();
    $has_uploads_dir = empty( $uploads['error'] ) && ! empty( $uploads['basedir'] );

    $font_path   = trailingslashit( get_template_directory() ) . 'assets/icon-sets/RemixIconsSystem/RemixIconsSystem.woff';
    $font_exists = is_file( $font_path );

    $logo_source_exists = false;
    if ( $has_uploads_dir ) {
        $logo_source_path   = trailingslashit( $uploads['basedir'] ) . '2025/08/logo.png';
        $logo_source_exists = is_file( $logo_source_path );
    }

    $flying_press_active = class_exists( '\\FlyingPress\\Purge' );
    $flying_press_url    = admin_url( 'admin.php?page=flying-press' );
    $version              = defined( 'PRIVILEGE_FP_VERSION' ) ? PRIVILEGE_FP_VERSION : '?';

    $theme_css_path  = trailingslashit( get_template_directory() ) . 'style.css';
    $coverage_css    = array();
    $child_css_path  = trailingslashit( get_stylesheet_directory() ) . 'style.css';
    if ( is_file( $theme_css_path ) && is_readable( $theme_css_path ) ) {
        $theme_css    = (string) file_get_contents( $theme_css_path );
        $coverage_css = privilege_diag_extract_css_rules(
            $theme_css,
            array( 'bt_bb_section_top_section_coverage_image', 'bt_bb_cell', 'bt_bb_background_image_holder' )
        );
    }
    if ( $child_css_path !== $theme_css_path && is_file( $child_css_path ) && is_readable( $child_css_path ) ) {
        $child_css    = (string) file_get_contents( $child_css_path );
        $coverage_css = array_merge(
            $coverage_css,
            privilege_diag_extract_css_rules(
                $child_css,
                array( 'bt_bb_section_top_section_coverage_image', 'bt_bb_cell', 'bt_bb_background_image_holder' )
            )
        );
    }
    ?>
    <div class="wrap">
        <h1>⚡ Privilege Performance — v<?php echo esc_html( $version ); ?></h1>

        <div class="notice notice-success" style="padding:16px;margin-top:16px;">
            <h2 style="margin-top:0;">✅ CLS do hero no desktop: resolvido (0,784 → 0,003)</h2>
            <p>
                Confirmado por dois PageSpeed Insights seguidos, com a V5.2 já no ar:
                o <code>aspect-ratio</code> calculado dinamicamente para a imagem de topo
                do Bold Builder (<code>bt_bb_section_top_section_coverage_image</code>)
                foi suficiente sozinho. Desempenho no desktop foi de 72 para <strong>97</strong>;
                LCP desktop caiu para 1,0s (verde). Não foi necessário forçar
                <code>height:auto</code> nem nenhuma alteração estrutural — o cenário mais
                arriscado que eu tinha cogitado (o CSS do tema fixando a altura da seção
                de forma independente da imagem) não se confirmou.
            </p>
            <p style="margin-bottom:0;">
                O extrator de CSS do tema (abaixo) continua disponível para o caso de um
                problema parecido aparecer de novo em outra imagem/seção — não precisa de
                nada agora, mas fica pronto.
            </p>
            <?php if ( ! empty( $coverage_css ) ) : ?>
                <details style="margin-top:10px;">
                    <summary style="cursor:pointer;color:#2271b1;">Ver regra CSS extraída (referência)</summary>
                    <pre style="background:#1e1e1e;color:#d4d4d4;padding:14px;overflow-x:auto;max-height:280px;font-size:12px;line-height:1.5;margin-top:8px;"><?php
                    foreach ( $coverage_css as $rule ) {
                        echo esc_html( $rule ) . "\n\n";
                    }
                    ?></pre>
                </details>
            <?php endif; ?>
        </div>

        <div class="notice" style="padding:16px;margin-top:16px;background:#f6f7f7;border-left:4px solid #8c8f94;">
            <h2 style="margin-top:0;">Itens avaliados na rodada de 23/08 e deixados como estão — por quê</h2>
            <p>
                Três achados apareceram nos relatórios seguintes e nenhum recebeu mudança
                de código. Registrado aqui para não parecer descuido:
            </p>
            <ul style="margin-bottom:0;">
                <li style="margin-bottom:10px;">
                    <strong>Console: <code>cloudflare.com ... 429 (Too Many Requests)</code></strong>
                    no widget Turnstile, derrubando "Práticas recomendadas" para 96 em
                    mobile e desktop. Rate-limit é decidido inteiramente pelos servidores do
                    Cloudflare, não pelo site — nenhum código aqui tem como alterar isso.
                    Reapareceu de forma consistente com testes de PageSpeed repetidos em
                    sequência curta; não há evidência de que aconteça com tráfego real.
                </li>
                <li style="margin-bottom:10px;">
                    <strong>"Reflow forçado" (desktop) apontando um script grande e
                    codificado em URL.</strong> Decodificado: é o JavaScript interno do
                    próprio Bold Builder (<code>MutationObserver</code> que detecta largura
                    de tela e aplica classes responsivas em todo o site, não só na Home).
                    31ms de reflow, com TBT já em 0ms. Mexer nisso arriscaria quebrar a
                    responsividade de qualquer elemento do Bold Builder no site inteiro por
                    um ganho que já é, na prática, imperceptível.
                </li>
                <li style="margin-bottom:0;">
                    <strong>"fetchpriority=high precisa ser aplicada" segue vermelho</strong>
                    para o hero com <code>background-image</code>. Essa checagem específica
                    do Lighthouse olha o atributo na própria tag do elemento — e
                    <code>background-image</code> via CSS não tem tag <code>&lt;img&gt;</code>,
                    logo não tem esse atributo possível. Resolver o quadradinho em si exigiria
                    inserir uma <code>&lt;img&gt;</code> visível sobrepondo o background —
                    mas essa seção usa <code>data-parallax-*</code> para animar
                    zoom/blur/opacidade via JS diretamente no
                    <code>background-image</code>; uma imagem sobreposta ficaria parada por
                    cima da animação, ou a esconderia por completo. Sem poder ver o
                    resultado ao vivo, o risco de quebrar visualmente o efeito de parallax
                    não compensa fechar um único quadradinho num relatório que já está em
                    97.
                </li>
            </ul>
        </div>

        <div class="notice notice-warning" style="padding:16px;margin-top:16px;">
            <h2 style="margin-top:0;">Ação manual de maior impacto — agora a única pendente: "Remove Unused CSS"</h2>
            <p>
                Com o CLS resolvido dos dois lados (mobile 0,028 / desktop 0,003) e o LCP
                desktop em 1,0s, esta é a última peça de alto impacto que falta — e agora
                com um alvo mais preciso: o <strong>LCP mobile segue em ~5,5s</strong>
                apesar da rede e do CLS mobile já estarem bons, porque o teste roda em CPU
                e conexão propositalmente limitadas (4G lento, Moto G Power) — condições
                em que o peso de <strong>~163 KiB de CSS não usado</strong> chegando em
                toda carga pesa mais na conta. Nenhuma técnica de carregamento assíncrono
                deste pacote de mu-plugins reduz o que É baixado — só adia QUANDO é
                baixado. É o único ajuste capaz de atacar esse número diretamente.
            </p>
            <p>
                Este painel não altera essa opção automaticamente: é uma configuração
                de outro plugin, e ativá-la às cegas por código — sem confirmar o
                schema exato da versão instalada — arrisca corromper outras opções do
                FlyingPress. Um clique no próprio painel do FlyingPress é o caminho
                seguro.
            </p>
            <p>
                <a href="<?php echo esc_url( $flying_press_url ); ?>" class="button button-primary">
                    Abrir FlyingPress
                </a>
                <span style="margin-left:8px;color:#666;">
                    Caminho: FlyingPress → aba Otimização → CSS → "Remove Unused CSS" → Ativar.
                </span>
            </p>
            <p style="margin-bottom:0;">
                Enquanto estiver ali, vale conferir também "Image Optimization →
                processar biblioteca" (endereça a raiz do achado de imagens PNG ainda
                não convertidas) e "Preload Critical Images" para a Home.
            </p>
            <p style="margin-bottom:0;">
                Depois de ativar, use <strong>Barra de admin → ⚡ Cache da Home → Gerar
                cache da Home</strong> e rode um novo PageSpeed Insights — mobile é o que
                deve mostrar a diferença — para confirmar a queda no CSS não usado e no
                LCP.
            </p>
        </div>

        <h2>O que a V5.0 corrigiu (comprovado por dados de rede reais)</h2>
        <table class="widefat striped" style="max-width:1000px;">
            <thead>
                <tr>
                    <th style="width:32%;">Achado</th>
                    <th style="width:34%;">Evidência</th>
                    <th style="width:34%;">Correção</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Preload da fonte de ícones apontava para um caminho com erro de digitação (<code>_RemidiconsSystem</code>)</td>
                    <td>HAR: HTTP 302 (redireciona de volta para a Home) em vez de 200</td>
                    <td>Preload corrigido para o caminho real (<code>RemixIconsSystem</code>), confirmado HTTP 200 no HAR</td>
                </tr>
                <tr>
                    <td>Preload de LCP fixo em <code>top_white_03_01.png</code>, uma imagem decorativa de ~2,8&nbsp;KiB, e removia qualquer outro preload de imagem</td>
                    <td>HAR: arquivo pequeno confirmado; a imagem de conteúdo real (<code>t2.webp</code>, 16&nbsp;KiB) não tinha preload</td>
                    <td>Preload passa a seguir dinamicamente a imagem que o WordPress já marca <code>fetchpriority="high"</code> — acompanha sozinho se a Home mudar de imagem</td>
                </tr>
                <tr>
                    <td>Logo: <code>srcset</code> era removido depois da conversão para WebP, forçando toda tela a baixar a variante grande</td>
                    <td>PSI: "imagem maior do que precisa ser (600×200) para as dimensões exibidas (368×123)"</td>
                    <td><code>srcset</code> real com 1x (368×123) e 2x (600×200)</td>
                </tr>
                <tr>
                    <td>2 imagens da seção "Tecnologias e marcas" ainda em PNG</td>
                    <td>PSI: <code>Photoshop.png</code>, <code>ChatGPT-Plus.png</code></td>
                    <td>Conversor de WebP sob demanda genérico (não depende de listar nome de arquivo)</td>
                </tr>
                <tr>
                    <td>Menu do cabeçalho é montado no rodapé da página e movido por JS só depois de <code>DOMContentLoaded</code></td>
                    <td>Código-fonte: script de reposicionamento do header (e o mesmo padrão na sidebar de blog/categoria)</td>
                    <td>Execução imediata — o script já está no <code>wp_footer</code>, então os elementos já existem no DOM nesse ponto — mais reserva de espaço via CSS no mobile</td>
                </tr>
                <tr>
                    <td>Rotina de WebP 2x do <code>Python.png</code> não executava mais</td>
                    <td>HAR: a Home hoje referencia <code>/2026/08/Python.webp</code>, não <code>/2025/08/Python.png</code></td>
                    <td>Removida (código morto); substituída pelo conversor genérico acima</td>
                </tr>
                <tr>
                    <td>4 declarações <code>@font-face</code> duplicadas para a mesma fonte, em hooks diferentes</td>
                    <td>Código-fonte: V4/V4.5/V4.9/V4.10 cada uma adicionando a própria versão</td>
                    <td>Consolidadas em uma única declaração</td>
                </tr>
                <tr>
                    <td>CSS assíncrono sem fallback para JavaScript desabilitado</td>
                    <td>Código-fonte: técnica <em>loadCSS</em> sem <code>&lt;noscript&gt;</code></td>
                    <td>Fallback <code>&lt;noscript&gt;</code> adicionado</td>
                </tr>
                <tr>
                    <td colspan="3" style="background:#f0f0f1;font-weight:600;">V5.1 (22/08 — 1ª vez com PSI desktop, não só mobile)</td>
                </tr>
                <tr>
                    <td>Logo do header chegava com <code>loading="lazy"</code> + <code>fetchpriority="low"</code>, aplicados pelo WordPress core</td>
                    <td>PSI desktop: maior item do relatório de layout shift (0,216 de CLS)</td>
                    <td><code>loading="eager"</code> + <code>fetchpriority="high"</code> forçados no logo; <code>sizes="auto"</code> (incompatível com srcset de densidade) removido</td>
                </tr>
                <tr>
                    <td>Hero com <code>background-image</code> inline (Bold Builder) sem preload — invisível para a checagem por <code>fetchpriority=high</code>, que só olha tag <code>&lt;img&gt;</code></td>
                    <td>PSI desktop, "Descoberta de solicitações de LCP": <code>fetchpriority=high</code> não aplicado ao elemento real de LCP</td>
                    <td>Extração dinâmica da URL do <code>background-image</code> (sem hardcode) + preload dedicado</td>
                </tr>
                <tr>
                    <td>Link do logo no desktop usa <code>class="logo"</code> puro — seletor diferente do mobile (<code>responsive-logo</code> etc.), nunca coberto</td>
                    <td>PSI desktop: "Os links não têm um nome compreensível"</td>
                    <td><code>aria-label</code> estendido para cobrir <code>class="logo"</code> isolada</td>
                </tr>
                <tr>
                    <td>Geração de WebP/CSS sob demanda podia rodar de forma síncrona bem na requisição testada pelo PageSpeed, inflando o tempo dessa amostra</td>
                    <td>Hipótese: LCP mobile oscilou 4,2s → 5,0s entre as duas auditorias sem causa direta encontrada no código</td>
                    <td>Botão "Gerar cache da Home" agora pré-aquece logo/CSS derivados antes do preload da própria Home</td>
                </tr>
            </tbody>
        </table>

        <h2>Checagem local (leitura de arquivo, sem depender do site ao vivo)</h2>
        <table class="widefat striped" style="max-width:1000px;">
            <tbody>
                <tr>
                    <td style="width:60%;">Fonte <code>RemixIconsSystem.woff</code> existe no tema no caminho esperado</td>
                    <td><?php echo $font_exists ? '✅ OK' : '⚠️ Não encontrada — confira <code>assets/icon-sets/RemixIconsSystem/</code> no tema Aiko manualmente'; ?></td>
                </tr>
                <tr>
                    <td>Origem do logo (<code>2025/08/logo.png</code>) existe em uploads</td>
                    <td><?php echo $logo_source_exists ? '✅ OK' : '⚠️ Não encontrada — a otimização de logo (srcset 1x/2x) depende deste arquivo e fica inativa sem ele'; ?></td>
                </tr>
                <tr>
                    <td>Classe <code>FlyingPress\Purge</code> disponível</td>
                    <td><?php echo $flying_press_active ? '✅ FlyingPress ativo' : '⚠️ Não detectado — os controles de cache da Home na barra de admin não vão funcionar'; ?></td>
                </tr>
            </tbody>
        </table>

        <p style="color:#666;margin-top:24px;">
            Relatório completo da auditoria — achados, HAR e trecho de código citados
            linha a linha — em <code>AUDIT-README.txt</code>, na raiz deste pacote de
            mu-plugins.
        </p>
    </div>
    <?php
}
