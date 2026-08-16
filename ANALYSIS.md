# Auditoria técnica — Studio Privilege

## Escopo e limitações

Foram analisados integralmente os dois ZIPs, o HTML MHTML e o HAR fornecidos. O pacote de entrada `jeito-performance-premium-2.6.1.zip` citado no briefing **não está no repositório**; portanto, esta entrega cria uma integração instalável, isolada e conservadora em vez de alegar que atualizou código indisponível. Não houve acesso a uma instalação WordPress executável, logo Lighthouse, HTML pós-cache e regressão visual estão marcados como **NOT VALIDATED**.

## ITEM 1 — AIKO CSS MONOLÍTICO

**Problema:** `style.css` possui 920.153 bytes e bloqueia renderização.

**Evidência (VERIFIED):** no HAR, `ea034.css` expande para 810.676 bytes, transfere 84.277 bytes e leva 515,3 ms. O fonte registra globalmente o handle `aiko-style` em `functions.php:177`.

**Origem:** `style.scss` usa `theme.scss`, `framework/assets/scss/framework-loader.scss` e `assets/scss/theme-loader.scss`.

### Mapa de composição

| Família | Fontes principais | bytes SCSS | classificação |
|---|---|---:|---|
| Elementos do tema | `assets/scss/elements.scss` | 92.563 | componente/conteúdo |
| WooCommerce | framework + tema | 100.659 | comércio/contextual |
| Post/conteúdo | framework + tema | 53.198 | conteúdo |
| Header desktop | framework + tema | 36.110 | desktop |
| Header responsivo | framework + tema | 23.819+ | mobile/global |
| Widgets | framework + tema | 31.145 | componente |
| Base/layout/normalize | vários | 31.573+ | obrigatório global |
| Comentários | framework + tema | 13.567 | conteúdo/contextual |

Os loaders ainda incluem footer, preloader, Bold Builder overrides, animação, post-grid, Gutenberg, contato, cards, calculadora, progress bar, floating image, links, animated headline, single product e process. Os números são bytes dos fontes e **não podem ser somados como equivalentes exatos ao CSS compilado**, pois Sass expande mixins e variáveis.

**Impacto:** 88,1% dos bytes CSS descompactados observados para os dois bundles principais pertencem ao Aiko (810.676 / 1.113.759).

**Solução implementada:** preserva o stylesheet e a cascata. Uma separação automática por seletores foi rejeitada: quebraria cascata, CSS dinâmico, shortcodes de comércio e child themes. A primeira dobra mobile substitui o paint do background por um elemento de imagem descoberto durante o parse e neutraliza transform/transition/animation somente no parallax transformado.

**Riscos:** a geometria e o recorte `object-fit: cover` requerem confirmação visual no HTML servido. O desktop mantém o background original.

**Resultado:** ANTES: 810.676 bytes descompactados. DEPOIS: **NOT VALIDATED**. DELTA: **NOT VALIDATED**. Decisão: **INVESTIGAR** em staging antes de qualquer extração de CSS.

### Auditoria das media queries compiladas

O parser estrutural encontrou 73 blocos `@media` top-level, somando apenas 53.195 B no `style.css` original. Desses, 38 blocos `max-width` de até 768 px somam 24.007 B. Não existe nenhum bloco top-level `min-width`: os estilos desktop principais estão no fluxo global e os arquivos responsivos adicionam overrides por `max-width`.

**Decisão:** simplesmente atribuir `media="(min-width:...)"` a um suposto arquivo desktop não é possível sem primeiro separar regras globais compartilhadas das regras desktop. Essa evidência rejeita uma divisão automática por media query, que removeria a base necessária ou inverteria a cascata. O inventário completo é produzido por `tools/analyze_inputs.py`.

## ITEM 2 — Bold Page Builder

**Problema:** `content_elements.crush.css` é global.

**Evidência (VERIFIED):** `9d2aa.css` expande para 303.083 bytes, transfere 29.186 bytes e leva 339,2 ms. O plugin registra `bt_bb_content_elements` em `bold-builder.php:565` e também carrega Slick globalmente em `bold-builder.php:584`.

**Implementação:** o HTML renderizado do primeiro `bt_bb_section` com parallax é interceptado pelo filtro oficial `do_shortcode_tag`. A URL já resolvida pelo Builder alimenta um `<picture>` mobile com `<img loading="eager" fetchpriority="high">`; em desktop o `source` não é selecionado e permanece o background original. O preload não é duplicado, pois o MHTML prova que já existe. Não há hash W3TC nem URL de mídia hardcoded.

**Resultado:** recurso hero do HAR: 13.198 bytes e 338,8 ms. DEPOIS/DELTA: **NOT VALIDATED**. Decisão: **MANTER somente após teste em staging**.

### Mapa modular do Builder

O manifesto `content_elements.css` importa 32 módulos ativos que somam 139.074 B em fonte antes do CSS Crush. A comparação conservadora entre os prefixos dos módulos e o snapshot encontrou 17 candidatos ausentes, totalizando 49.561 B de fonte: accordion, cost calculator, countdown, counter, CSS image grid, IE, Instagram feed, latest post, Magnific Popup, open map, price list, progress bar, service items, tabs, Twitter feed, video e widgets.

Essa lista é **INFERENCE**, não autorização de remoção: `base`, `grid`, `sections`, `screens` e `animations` são sempre classificados como core; estados dinâmicos, lightbox e conteúdo fora da Home ainda precisam ser validados. A ferramenta não remove nenhum módulo com base apenas no snapshot.

## Demais achados

* O HAR contém dois AJAX imediatos, de 10.325 e 2.702 bytes descompactados, ambos perto de 537 ms. A origem foi confirmada e o adapter por proximidade do viewport está implementado no Item 5; o delta real permanece pendente de staging.
* A extração WooCommerce é a maior oportunidade seguinte, mas exige compilar assets e verificar mini-cart, widgets e shortcodes numa instalação real.

## ITEM 3 — FONTES E ÍCONES

**Problema:** duas fontes locais FontAwesome são baixadas integralmente.

**Evidência (VERIFIED):** `FontAwesome6Solid.woff2` possui 154.840 B descompactados/155.284 B transferidos e `FontAwesome6Brands.woff2` possui 109.808 B/110.188 B. Juntas representam 264.648 B descompactados, mais que todos os JavaScripts observados no HAR. O CSS remoto do Google Fonts adiciona 32.033 B descompactados e solicita Plus Jakarta Sans e três arquivos Poppins observados.

**Solução proposta:** inventariar os codepoints usados em conteúdo, menu, estados hover e widgets; então gerar subset WOFF2 versionado. A remoção ou troca por SVG não foi aplicada sem a lista de ícones dinâmica do WordPress.

**Resultado:** ANTES: 264.648 B. DEPOIS/DELTA: **NOT VALIDATED**. Decisão: **INVESTIGAR**.

### Google Fonts — eixos da Home

**Evidência (VERIFIED):** a URL solicita Plus Jakarta Sans, Poppins e Play nos pesos 100/300/400/500/700/900, normais e itálicos. O HTML contém classes explícitas de peso 300 (1 ocorrência), 500 (7) e 700 (7); 400 é o peso normal implícito. Não existem elementos `em`, estilos inline itálicos ou classes de peso 100/900 no snapshot.

**Implementação:** na Home Aiko, somente os handles `boldthemes-fonts` e `bt_bb_google_fonts` têm o eixo reescrito para `wght@300;400;500;700`. Famílias e `display=swap` são preservados; admin, editor e demais páginas não são alterados. A flag “Eixos de fontes da Home” permite rollback imediato.

**Riscos:** conteúdo dinâmico futuro que use itálico ou pesos 100/900 na Home receberá síntese do navegador até a flag ser desligada. Validar tipografia e estados interativos em staging.

**Resultado:** CSS Google Fonts ANTES: 32.033 B descompactados. DEPOIS/DELTA: **NOT VALIDATED**. Decisão: **MANTER após inspeção tipográfica**.

## ITEM 4 — HTML / DOM

**Evidência (VERIFIED):** a resposta HTML tem 692.604 B descompactados e 92.442 B transferidos, sendo o segundo maior recurso descompactado do HAR. O parser do MHTML conta 2.036 elementos, incluindo 901 `div`, 543 `span`, 123 links, 58 imagens, 41 elementos `trustindex-image` e 22 seções. Existem 3.357 referências a classes `bt_bb_*`. O MHTML confirma wrappers aninhados `section → background wrapper → holder → port → cell → cell_inner → row → row_holder → column` já na primeira seção.

**Decisão:** não remover wrappers de vendor sem uma renderização WordPress comparável. Priorizar conteúdo duplicado desktop/mobile em staging.

### Content Visibility

**Implementação:** somente filhos diretos `.bt_bb_wrapper > .bt_bb_section` a partir da terceira seção podem receber `content-visibility:auto`. O seletor exige `bt_bb_animation_no_animation` e exclui, com `:has()`, sliders, content sliders, CSS/masonry post grids, Google/Leaflet maps, parallax, Trustindex e Contact Form 7. `contain-intrinsic-size:auto 800px` fornece estimativa inicial e permite ao navegador recordar a dimensão renderizada.

**Fallback:** browsers sem suporte a `:has()` ou `content-visibility` ignoram a regra. Hero, segunda seção, footer e componentes com cálculos sensíveis não são selecionados.

**Resultado:** custo de layout/paint pós-dobra: DEPOIS/DELTA **NOT VALIDATED**. Decisão: **MANTER após comparação de CLS, âncoras e screenshots**.

## ITEM 7 — JAVASCRIPT

| Script | bytes descompactados | tempo HAR | decisão |
|---|---:|---:|---|
| `slick.min.js` | 42.863 | 620,5 ms | manter: sliders existem no HTML |
| `content_elements.js` | 42.610 | 622,4 ms | manter: inicializa elementos Builder |
| `bt_framework_misc.js` | 16.730 | 538,1 ms | investigar por função |
| `flags.js` | 14.499 | 445,7 ms | candidato a interação |
| `bt_bb_css_post_grid.js` | 7.113 | 610,0 ms | candidato a IntersectionObserver |

O tempo HAR é duração de rede, não blocking time de CPU. Como o TBT histórico informado é próximo de zero, nenhum `defer` global foi aplicado.

## ITEM 5 — CSS POST GRID / AJAX

**Problema:** `reinit()` chama `bt_bb_css_post_grid_load_posts(..., 0)` imediatamente para cada grid. O HAR confirma duas requisições `admin-ajax.php` iniciadas juntas, com 10.325 B e 2.702 B de resposta e duração próxima de 537 ms.

**Origem:** `content_elements/bt_bb_css_post_grid/bt_bb_css_post_grid.js`, método `reinit()`; action PHP `bt_bb_get_css_grid`.

**Implementação:** um adapter dependente do handle `bt_bb_css_post_grid` envolve apenas a primeira carga (`offset === 0`). Grids até 200 px do viewport continuam imediatos; grids distantes aguardam `IntersectionObserver`. Load more, filtros e browsers sem suporte mantêm o comportamento original. O adapter só é enfileirado se o Builder tiver enfileirado o próprio script.

**Riscos:** scripts de terceiros que chamem o método antes do adapter não serão interceptados. O carregamento e a geometria precisam ser verificados em staging.

**Resultado:** ANTES: 2 AJAX imediatos. DEPOIS/DELTA: **NOT VALIDATED**. Decisão: **MANTER após staging**.

## W3 TOTAL CACHE

**Problema:** mudanças de markup/CSS do plugin podem permanecer ocultas por Page Cache e Minify antigos.

**Origem:** cache externo ao ciclo de ativação/atualização do Jeito Performance.

**Implementação:** a versão instalada é comparada com `JPP_VERSION`. Uma única purga fica pendente e é executada no próximo `admin_init` autorizado, usando primeiro `w3tc_flush_all()` e, quando W3TC não está ativo, `wp_cache_flush()`. A pendência é removida mesmo em falha para evitar loops de purga; provedor, versão, timestamp e resultado ficam disponíveis no dashboard.

**Riscos:** uma purga esfria temporariamente o cache. Ela ocorre apenas uma vez por versão e nunca em cada request público.

**Resultado:** comportamento coberto por harness; integração W3TC real **NOT VALIDATED**. Decisão: **MANTER**.

## Budgets de aceitação em staging

Critical CSS <70 KB; CSS bloqueante ideal <100 KB; FCP <1,8 s; LCP <2,5 s; CLS <0,05; TBT <100 ms. Três execuções Lighthouse mobile e mediana são obrigatórias antes de declarar ganho.

## Rollback e isolamento de regressões

Hero/LCP mobile, Content Visibility, Post Grid tardio e eixos de fontes possuem flags independentes, habilitadas por padrão e salvas em uma option allowlist. Cada família pode ser desligada no dashboard sem editar código ou desativar as demais, permitindo cumprir o ciclo medir → manter/reverter. Valores desconhecidos são descartados na sanitização e a desinstalação remove a option.

## Compatibilidade de vendor

As integrações validam nome e versão antes de agir. O código auditado aceita Aiko `>=1.0.5 <1.1.0` e Bold Page Builder `>=5.9.6 <6.0.0`. Versões fora dessas faixas não recebem transformação de hero, CSS, fontes ou Post Grid; o dashboard sinaliza o ambiente como incompatível. Isso evita aplicar seletores e métodos auditados a uma futura arquitetura desconhecida.

## Verificação do HTML final

O dashboard oferece “Verificar Home”, protegido por nonce e `manage_options`. O servidor faz um GET público normal — sem header de bypass — com timeout, valida HTTP 200 e inspeciona o documento efetivamente entregue para Hero/LCP, Content Visibility, adapter do Post Grid e eixos de fontes conforme flags e compatibilidade. Hero, Grid e Google Fonts passam como “não aplicáveis” quando o recurso de origem não existe no HTML; quando existe, o marcador otimizado é obrigatório. O Post Grid usa um marcador inline estável, independente da URL hash criada pelo W3TC. O resultado inclui checks individuais e bytes reais do HTML, sem tratar options salvas como prova de aplicação.

O mesmo verificador está disponível por `wp jeito-performance verify`: imprime JSON adequado a artefatos de CI e retorna exit code 1 se qualquer check ativo falhar. Assim, purge → warm-up → verificação pode bloquear um deploy com cache ou markup incorreto.
