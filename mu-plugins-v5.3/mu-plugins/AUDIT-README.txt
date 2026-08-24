PRIVILEGE MU-PLUGINS — FLYINGPRESS V3 (AUDIT CANDIDATE)
========================================================

OBJETIVO
- remover a pilha legada de W3 Total Cache / WP Rocket;
- deixar FlyingPress controlar cache, RUCSS, minificação, Delay JS, lazy-load e imagens críticas;
- remover inline jQuery e defers manuais;
- reduzir CSS global que não pertence à Home;
- preservar customizações funcionais e o bot.

ARQUIVOS REMOVIDOS DESTA ÁRVORE
- 000-pse-mobile-performance-controller-v1.12.php
- 999-privilege-final-sprint-95.php
- performance-home.php
- privilege-site-controller/modules/performance-core.php
- privilege-site-controller/modules/performance-extended.php

ARQUIVOS NOVOS
- privilege-site-controller/modules/performance-flyingpress.php
- privilege-site-controller/modules/bot-whatsapp.php

IMPORTANTE
Substitua a árvore antiga, não apenas copie por cima, senão os MU-plugins raiz
legados continuarão sendo carregados automaticamente.

FlyingPress:
- manter Remove Unused CSS ativo;
- manter Delay JS em "Carregar ao ficar inativo", como já validado no site;
- manter cache/otimizações separadas para celular;
- após trocar os MU-plugins, usar Purge Everything / Limpar e pré-carregar.

Esta versão é um candidato de homologação. Validar visualmente:
- header/menu;
- hero;
- sliders;
- portfolio/load more;
- formulários;
- Trustindex;
- bot WhatsApp;
- páginas de blog/categoria/post.

V3.1 delta:
- marca as duas primeiras top_section_coverage_image da Home com privilege-lcp-critical, loading=eager e fetchpriority=high;
- preload mobile do 2674-1800w.webp com versionamento pelo filemtime;
- substitui o ícone de carrinho via RemixIconsFinance por SVG inline para retirar essa fonte do caminho crítico quando ela não for necessária em outro componente.
- IMPORTANTE: em FlyingPress > Otimização > Imagens/Vídeos/iFrames > Editar exclusões, adicionar: privilege-lcp-critical

V3.7: fallback de cache da Home agora aciona separadamente desktop e mobile, importante quando o cache mobile separado do FlyingPress esta ativo.

V3.9: removido o workaround de output buffering da V3.8. O preload quebrado de aiko-home.css deve ser corrigido diretamente no header.php do Aiko, eliminando a causa em vez de filtrar o HTML final.

V4.0: na Home, pós-processamento final do HTML para forçar o LCP top_white_03_01.png como eager/high e remover preloads de imagens abaixo da dobra, preservando somente o preload do LCP.

V4.1: mantém os ajustes de LCP da V4.0 e passa a carregar content_elements.crush.css de forma assíncrona apenas na Home, preservando aiko/style.css bloqueante por segurança visual.

V4 ULTIMATE
- Base: V4.1 estável.
- Aiko style.css permanece bloqueante por segurança visual.
- Bold content_elements.crush.css + slick/frontend-script assíncronos.
- LCP top_white_03_01.png: preload único + eager + fetchpriority high.
- Preloads concorrentes removidos.
- Imagens abaixo da dobra conhecidas: lazy + low.
- Acessibilidade: aria-label em logos/links de ícone, títulos H5/H6 do Bold normalizados para H4, alvos de toque de políticas ampliados.
- Best Practices: logo Node não é ampliado acima da resolução nativa.
- Fonte RemixIconsSystem: font-display swap.
- content-visibility em seções profundas.
- /llms.txt virtual com H1 e links principais.

BASELINE FLYINGPRESS RECOMENDADA:
- Minificar CSS/JS: ON
- Remove Unused CSS: OFF (esta versão faz o tratamento crítico necessário sem depender do cloud RUCSS)
- Delay JS: ON / "Carregar ao ficar inativo"
- Lazy Load: ON
- Properly size images: ON
- Self-host external CSS/JS: ON
- Separate mobile cache: ON
- Host Google Fonts locally: ON
- Preload Fonts: ON inicialmente; se houver regressão, testar OFF.
- Image Optimization: WebP ou AVIF com perdas; processar biblioteca.

V4.5
- Mantém a base Ultimate.
- Corrige especificamente o link .responsive-menu-logo com aria-label.
- Corrige o heading "POR QUE A Agência Privilége representa a escolha mais estratégica?" de h3 para h2.
- Reforça font-display:swap para RemidiconsSystem/RemixIconsSystem.
- Não mexe no aiko/style.css para evitar regressão visual/CLS.

V4.6 — foco simultâneo em Performance + Acessibilidade
- Link do logo: aria-label robusto para responsive-logo/menu-logo/sticky-logo.
- Heading auditado: h3 específico convertido para h2 mesmo com markup interno.
- Aiko CSS: cópia server-side segura; font-display:swap em todos @font-face.
- Mobile: remove apenas @media desktop-only com min-width >= 768px.
- LCP: preload antecipado no início do wp_head, com srcset/sizes quando disponível.
- Mobile: tenta descarregar bt_bb_floating_image JS para reduzir forced reflow.
- content-visibility começa na 4ª seção abaixo da dobra.
- Mantém Aiko CSS síncrono/estrutural para evitar a regressão de CLS da V4.2.

V4.9 — baseada EXCLUSIVAMENTE na V4.6 estável
- Não altera o carregamento síncrono do CSS principal do Aiko.
- Não usa critical CSS experimental.
- Não altera cores, grid, header, hero ou estrutura visual.
- Corrige aria-label das variações do logo.
- Corrige somente o heading "Atendimento" de H4 para H3.
- Cria Python.png 2x em WebP para resolver imagem em baixa resolução sem mudar layout.
- Reforça font-display:swap para _RemidiconsSystem/RemixIconsSystem.
- Mantém todas as otimizações de LCP, cache Home, mobile CSS e acessibilidade da V4.6.

V4.10 — base V4.9/V4.6 estável
- Preserva Aiko CSS principal síncrono e toda estrutura visual.
- Inline apenas de CSS local <= 8 KB, excluindo Aiko principal e content_elements.
- Reforça font-display:swap + preload para _RemidiconsSystem.
- Converte apenas logo.png 600x200 para WebP 600x200, sem alterar layout.
- Mantém Acessibilidade 100 / Práticas 100 obtidas na V4.9.


================================================================================
V5.0 — AUDITORIA COMPLETA (código + PageSpeed Insights mobile + HAR real do GTmetrix)
================================================================================
Data: 21/08/2026. Contexto: pontuação de Desempenho mobile em 78 depois de
48h+ de tentativas manuais nas versões V3 a V4.10. Desta vez, o PSI mobile e
um HAR real do GTmetrix (mesma tarde, ~27 min de diferença) foram cruzados
com o código-fonte desta árvore, requisição por requisição, em vez de mais
uma rodada de ajuste por tentativa.

ACHADOS (comprovados por requisição de rede real, não suposição)

1) Preload da fonte de ícones apontava para um caminho que não existe.
   O preload de altíssima prioridade (wp_head, priority 99999, desde a V4.9)
   apontava para ".../_RemidiconsSystem/RemidiconsSystem.woff" — nome errado,
   com "_" a mais, reforçado de novo na V4.10 sem nunca ter sido conferido
   contra uma requisição real. No HAR: HTTP 302, redireciona de volta para a
   própria Home — o preload inteiro é desperdiçado nisso. A fonte realmente
   usada pelo tema (".../RemixIconsSystem/RemixIconsSystem.woff", HTTP 200
   confirmado no mesmo HAR, cache de 1 ano) nunca recebia preload e era
   descoberta tarde — exatamente a maior latência que a "Árvore de
   dependência da rede" do PSI aponta (2.643 ms).

2) Preload de LCP fixo numa imagem decorativa, não no conteúdo real.
   A promoção de LCP (V4.0/V4.6) era hardcoded para
   "/2023/11/top_white_03_01.png" — no HAR, uma imagem de ~2,8 KiB. A V4.0
   também REMOVIA qualquer outro preload de imagem que aparecesse. A imagem
   de conteúdo real da Home hoje (.../2026/08/t2.webp, 16 KiB, HTTP 200 no
   HAR) não tinha preload nenhum: o slot de prioridade máxima ia para o
   elemento errado.

3) Rotina de WebP do Python.png virou código morto sem avisar.
   privilege_v49_get_python_2x_url (V4.9) checava a existência de
   "/2025/08/Python.png" — o HAR confirma que a Home hoje referencia
   ".../2026/08/Python.webp". O conteúdo mudou, a checagem nunca mais bateu;
   a função para de fazer efeito sem gerar nenhum erro. É o mesmo padrão dos
   achados #1 e #2: um patch preso a um nome de arquivo específico perde
   efeito silenciosamente assim que o conteúdo da Home muda.

4) ~158 KiB de CSS não usado (achado do PSI) — decorre diretamente da
   decisão registrada na baseline da V4 ULTIMATE: "Remove Unused CSS: OFF
   (esta versão faz o tratamento crítico necessário sem depender do cloud
   RUCSS)". Carregamento assíncrono (a técnica loadCSS usada aqui desde a
   V4.0/V4.1) muda QUANDO o CSS chega — nunca reduz O QUE é baixado. Só o
   RUCSS do próprio FlyingPress remove seletor não usado por página, e é a
   única coisa capaz de atacar esse número diretamente. Na época a decisão
   de desligar fazia sentido como hipótese; os dados de rede desta auditoria
   mostram que as duas frentes (loading assíncrono x remoção de seletor não
   usado) não são a mesma coisa. Ver ação manual obrigatória abaixo.

5) Header montado no wp_footer e movido para o topo via JS só depois de
   DOMContentLoaded. Acontece no site inteiro, não só na Home — mesmo
   padrão no script que monta a sidebar de blog/categoria/notícias. Como o
   script já está no wp_footer (fim do body, depois de todo o cabeçalho no
   HTML), os elementos que ele procura já existem no DOM nesse ponto do
   parsing — esperar DOMContentLoaded era uma espera desnecessária.
   Contribuinte plausível para o CLS 0.11 do PSI: a div #content.site-content
   é justamente o maior item do relatório de layout shift do PSI (0,088 de
   0,110 do total) e é exatamente o seletor que um desses scripts manipula.
   Não é uma prova por reprodução visual ao vivo (sem acesso ao site para
   testar), mas é consistente com o seletor citado e com o padrão mais comum
   de CLS real (DOM reorganizado por JS depois do primeiro paint).

REMOVIDO NESTA VERSÃO
- privilege_v410_icon_font_final_override, privilege_v49_icon_font_display_css,
  privilege_v45_icon_font_swap, e a parte de @font-face de
  privilege_v4_ultimate_head_css → 4 declarações concorrentes da mesma fonte,
  substituídas por 1 preload + 1 @font-face corretos (achado #1).
- privilege_v46_early_lcp_preload → substituído por preload dinâmico
  (achado #2).
- privilege_v49_get_python_2x_url → código morto (achado #3).
- privilege_v46_strip_desktop_media_blocks + privilege_v46_find_matching_brace
  → medido no HAR: diferença de ~0,2 KiB no tamanho JÁ COMPRIMIDO (gzip)
  entre a variante desktop e a mobile do mesmo CSS. Não estava pagando a
  complexidade de manter (parser de chaves aninhadas por regex).
- privilege_fp_set_img_attribute + privilege_v40_set_final_img_attribute →
  duas cópias quase idênticas do mesmo helper, consolidadas em
  privilege_fp_set_attr.
- Bloco "imagens abaixo da dobra conhecidas: lazy + low" com lista fixa de
  nomes de arquivo → removido; o lazy-load nativo do WordPress core (5.5+)
  já cobre o caso geral sem depender de lista, que ficava obsoleta a cada
  imagem nova.

NOVO NESTA VERSÃO
- Preload de LCP dinâmico: segue a imagem que o WordPress já marca
  fetchpriority=high no HTML final, sem hardcode de nome de arquivo —
  acompanha sozinho se a Home mudar de imagem no futuro, em vez de precisar
  de outro patch manual.
- WebP sob demanda para imagens locais sem srcset (generaliza o que V4.9/
  V4.10 faziam à mão, um arquivo hardcoded por vez — endereça Photoshop.png
  e ChatGPT-Plus.png sem precisar listar os nomes).
- Logo com srcset real 1x (368×123) / 2x (600×200) — a V4.10 convertia para
  WebP mas apagava o srcset, forçando toda tela (inclusive 1x) a baixar
  sempre a variante de 600×200.
- Fallback <noscript> no CSS assíncrono (ausente desde a V4.0/V4.1 — sem
  ele, com JS desabilitado o CSS assíncrono nunca chegava a aplicar).
- privilege-site-controller/modules/admin-dashboard.php (novo arquivo):
  painel em Administração > Privilege Performance, com a tabela de achados
  desta auditoria e a ação manual pendente em destaque.

MANTIDO SEM ALTERAÇÃO DE LÓGICA (comparado função a função com o código
anterior): remoção de emojis, fast-paint mobile da 1ª seção, promoção de LCP
por classe do Bold Builder via the_content, inline de CSS local pequeno,
descarregar bt_bb_floating_image no mobile, CSS avulso (logo node, alvos de
toque, content-visibility), /llms.txt virtual, resource hints, e toda a
suíte de cache dedicado da Home na admin bar (purge/gerar separado do resto
do site).

IMPORTANTE — continua valendo desde a V3.1: a exclusão "privilege-lcp-critical"
cadastrada em FlyingPress > Otimização > Imagens/Vídeos/iFrames > Editar
exclusões precisa continuar lá. Sem ela, o lazy-load do FlyingPress pode
voltar a sobrescrever o loading=eager que este módulo aplica nas imagens de
topo da Home.

AÇÃO MANUAL OBRIGATÓRIA — não é código, é configuração de outro plugin
Nenhuma linha deste pacote de mu-plugins liga isso por conta própria: mudar
opção de outro plugin por código, sem confirmar o schema exato da versão do
FlyingPress instalada no site, arrisca corromper outras configurações que
não têm relação com performance. Um clique no painel do próprio FlyingPress
é o caminho seguro — o painel Administração > Privilege Performance (novo
nesta versão) tem um atalho direto.
  1. FlyingPress > Otimização > CSS > "Remove Unused CSS" → Ativar.
     (Reverte a decisão registrada na baseline da V4 ULTIMATE — ver achado #4.)
  2. FlyingPress > Otimização > Imagens > conferir "Image Optimization" e
     rodar "processar biblioteca" de novo (o achado #3 sugere que nem toda
     imagem enviada recentemente passou por isso).
  3. Depois de ativar: Admin bar > ⚡ Cache da Home > Gerar cache da Home, e
     rodar um novo PageSpeed Insights mobile para confirmar a queda no CSS
     não usado.

BASELINE FLYINGPRESS RECOMENDADA (V5.0 — atualiza a baseline da V4 ULTIMATE)
- Minificar CSS/JS: ON
- Remove Unused CSS: ON — revertido nesta versão (ver ação manual acima).
- Delay JS: ON / "Carregar ao ficar inativo"
- Lazy Load: ON, com a exclusão privilege-lcp-critical mantida
- Properly size images: ON
- Self-host external CSS/JS: ON. Trustindex (loader.js) e Cloudflare
  Turnstile continuam direto do CDN de cada um — no HAR, os dois têm
  cache-control curto ou ausente (~24 KiB de economia possível no PSI), mas
  são scripts carregados de forma dinâmica, não um <script src> estático
  simples; auto-hospedar de forma confiável exigiria sincronização periódica
  e o risco de quebrar o widget de reviews/captcha não compensa ~24 KiB.
  Deixado de fora desta versão por esse motivo, registrado aqui para não
  ser esquecido, não porque não tenha custo.
- Separate mobile cache: ON
- Host Google Fonts locally: ON
- Preload Fonts: ON
- Image Optimization: WebP ou AVIF com perdas; processar biblioteca de novo
  (achado #3).

VALIDAR VISUALMENTE APÓS ATUALIZAR (mesma lista da V3, ainda vale — nada
nesta versão deveria alterar visual, mas qualquer mudança em CSS/JS merece
conferência antes de considerar fechado):
- header/menu (agora aparece mais cedo — não espera mais DOMContentLoaded);
- hero da Home;
- sliders;
- portfolio/load more;
- formulários;
- Trustindex;
- bot WhatsApp;
- páginas de blog/categoria/post (sidebar recebeu o mesmo ajuste do header);
- seção "Tecnologias e marcas" (Photoshop.png e ChatGPT-Plus.png agora
  convertidos para WebP sob demanda — confirmar que aparecem corretamente
  na primeira visita depois do deploy, momento em que o .webp ainda não
  existe em disco e precisa ser gerado pela primeira requisição).

NÃO INCLUÍDO NESTA VERSÃO, DE PROPÓSITO
- Reescrita do JS de reposicionamento do header para injeção 100% server-side
  (eliminaria de vez o risco de CLS do achado #5, em vez de só reduzir a
  janela). Exigiria conhecer a estrutura exata do template do tema Aiko além
  do que este pacote de mu-plugins alcança, e não dá para testar visualmente
  sem acesso ao site ao vivo. Ficou registrado como próximo passo de maior
  retorno depois da ação manual do RUCSS.
- Auto-hospedagem do loader.js do Trustindex e do script do Cloudflare
  Turnstile — ver nota na baseline acima.

================================================================================
V5.1 — segunda rodada: PSI mobile + desktop (a 1ª auditoria só tinha mobile)
================================================================================
Data: 22/08/2026. Depois do deploy da V5.0, novo PSI mobile e — pela primeira
vez — desktop. Resultado misto e informativo: dois dos três achados da V5.0
se confirmaram com dados novos (CLS mobile 0,11→0,054, já verde; latência da
fonte de ícones saiu da árvore de dependência da rede, 2.643ms→2.144ms), mas
o desktop revelou um problema real e "novo" — novo no sentido de nunca ter
sido visível antes, já que nenhuma auditoria anterior (V3 a V5.0) tinha dado
desktop pra olhar.

ACHADOS

1) Logo do header chegava com loading="lazy" + fetchpriority="low", aplicados
   pelo próprio WordPress core (não pela V5.0, nem por versão nenhuma deste
   pacote — a função de logo só mexe em src/srcset/width/height/decoding e
   nunca tocou esses dois atributos). No PSI desktop, "Causas da troca de
   layout" aponta exatamente esse <img> — sizes="auto" incluso, uma marca
   registrada do próprio core — como o maior item isolado do relatório,
   0,216 de CLS sozinho. É o tipo de coisa que só aparece pra quem testa
   desktop: no mobile o mesmo <img> está lá, com os mesmos atributos, mas o
   CLS resultante fica pequeno o bastante pra não aparecer na lista.

2) O elemento que o PSI desktop aponta como candidato a LCP ("Descoberta de
   solicitações de LCP") não é uma tag <img> — é um <div
   class="bt_bb_background_image_holder"> do Bold Builder com
   background-image via style inline. A checagem "fetchpriority=high precisa
   ser aplicada" aparece com X porque esse tipo de elemento é INVISÍVEL para
   qualquer heurística (do WordPress core ou da V5.0) que só olha tag <img>.

3) O link do logo no desktop usa class="logo" puro — não
   responsive-logo/responsive-menu-logo/responsive-sticky-logo, os únicos
   padrões que qualquer versão deste pacote (V4.5 em diante) cobria. PSI
   desktop: "Os links não têm um nome compreensível". De novo, um padrão que
   só existe no desktop.

4) LCP mobile oscilou de 4,2s (21/08) pra 5,0s (22/08) apesar da latência do
   caminho crítico ter melhorado (item acima). Não há uma causa provada — é
   plausível que a amostra tenha batido numa requisição de cache frio logo
   depois do purge, quando WebP do logo e a cópia do CSS do Aiko ainda
   precisavam ser gerados na hora; processamento de imagem e regravação de
   ~800 KB de CSS de forma síncrona pesa exatamente numa única requisição
   dessas. Não afirmado como causa única — Lighthouse tem variância normal
   de execução pra execução — mas endereçado com pré-aquecimento (achado #5
   abaixo) por ser de baixo risco e alto potencial de ajudar.

5) Console do navegador: cloudflare.com retornou 429 (Too Many Requests) no
   endpoint do Turnstile durante o teste. Padrão consistente com
   rate-limiting por repetição de testes automatizados em sequência curta
   (múltiplos PageSpeed rodados na mesma tarde), não com tráfego real. Nada
   no código deste pacote controla limite de taxa do lado do Cloudflare;
   registrado aqui para acompanhar, não veio com correção nesta versão.

CORRIGIDO NESTA VERSÃO
- privilege_fp_optimize_logo_html(): força loading="eager" e
  fetchpriority="high" no logo; remove sizes="auto" (não se aplica a srcset
  de descritor de densidade, que é o nosso caso) — achado #1.
- privilege_fp_insert_dynamic_lcp_preload(): passa a cobrir DUAS fontes de
  candidato a LCP — a <img fetchpriority=high> de antes E, nova, o hero com
  background-image inline do Bold Builder
  (privilege_fp_find_lcp_background_urls(), extrai a URL do style de forma
  dinâmica, sem hardcode de arquivo) — achado #2. As duas podem coexistir;
  preferi cobrir as duas fontes a apostar em qual é a real em todo
  viewport/dispositivo.
- privilege_fp_fix_accessible_names(): aria-label passa a cobrir também
  class="logo" isolada (token exato, não substring — não confunde com
  outras classes que contenham "logo") — achado #3.
- privilege_fp_prewarm_generated_assets() (nova): chamada no início de
  privilege_fp_generate_home_cache(), gera o WebP do logo e a cópia do CSS
  do Aiko ANTES do preload da Home, para a requisição de preload (e a
  primeira visita real) já encontrar tudo pronto em disco — achado #4.

NÃO CORRIGIDO NESTA VERSÃO, DE PROPÓSITO
- Rate limit do Cloudflare Turnstile (achado #5): sem ação do lado do site;
  se persistir fora de janela de teste, vale abrir chamado com o Cloudflare.
- "Reduza o CSS não usado" continua ~163 KiB (era ~158 KiB na V5.0, dentro
  da margem de ruído): esperado — a ação manual do RUCSS na V5.0 ainda não
  foi aplicada. Não é uma regressão desta versão, é a mesma pendência.

BASELINE FLYINGPRESS: sem mudança em relação à V5.0. A ação manual nº 1
("Remove Unused CSS" → Ativar) continua sendo o maior item pendente e
nenhum achado desta rodada substitui isso.

VALIDAR VISUALMENTE APÓS ATUALIZAR
- logo no header, desktop e mobile (agora eager/high — confirmar que não
  aparece atrasado nem com prioridade errada em conexão lenta);
- seção hero da Home (novo preload do background-image — confirmar que não
  duplica download em nenhum navegador testado);
- os mesmos itens já listados na V5.0.

================================================================================
V5.2 — CLS do desktop piorou (0,216 → 0,784): hero do Bold Builder, não o logo
================================================================================
Data: 23/08/2026. Terceira rodada de PSI (mobile + desktop). Painorama misto,
de novo: mobile mostrando melhora real e consistente, desktop revelando um
problema mais sério do que o da V5.1 — na mesma área (CLS), mas outra causa.

CONFIRMAÇÕES POSITIVAS (mobile)
- CLS mobile: 0,11 (21/08) → 0,054 (22/08) → 0,028 (23/08). Tendência clara e
  consistente de melhora a cada rodada, já bem dentro da faixa "boa" (<0,1).
- Latência do caminho crítico: 2.643ms → 2.144ms → 2.045ms. Também melhorando
  rodada a rodada. O bug do preload da fonte de ícones (V5.0) segue resolvido
  — não reapareceu nas métricas de nenhuma rodada seguinte.
- Render-blocking estável (~1.640-1.660ms nas últimas duas rodadas, levemente
  melhor que os 1.870ms originais).

ACHADO PRINCIPAL DESTA RODADA
Desktop: CLS subiu de 0,216 (22/08, causado pelo logo com loading=lazy,
corrigido na V5.1) para 0,784 (23/08) — e agora o item dominante (0,767 dos
0,784, ~98% do total) é OUTRO elemento: a imagem de topo do Bold Builder,
classe bt_bb_section_top_section_coverage_image, com width="1800"
height="410" no HTML — os mesmos atributos que essa imagem sempre teve
(nenhuma versão deste pacote, V3 a V5.1, jamais tocou o width/height dela,
só loading/fetchpriority/decoding). O Lighthouse ainda assim reporta
"Elemento de imagem sem tamanho definido".

Isso é a assinatura de um padrão comum em "imagens de cobertura": o CSS do
tema provavelmente estica/corta essa imagem (object-fit:cover, ou
width/height:100% de um contêiner) para preencher uma seção cuja altura é
definida por outra coisa — independente da proporção real de 1800x410 que
o HTML anuncia. O navegador reserva espaço pela proporção do width/height
(errada pro layout final), e corrige quando o CSS real é aplicado. Como essa
mesma imagem já é loading=eager/fetchpriority=high (LCP), é plausível que a
MESMA instabilidade de layout também esteja empurrando o LCP mobile pra
cima (4,2s → 5,0s → 5,6s nas três rodadas, apesar da latência de rede estar
melhorando): se o navegador precisa de reflows extras até estabilizar o
tamanho final dessa imagem, o timestamp de "maior pintura de conteúdo" só é
registrado depois desses reflows.

Não é uma certeza — não tenho o CSS do tema pra confirmar o mecanismo exato,
só o HTML processado. Por isso a correção desta versão é deliberadamente
parcial, e o painel de administração ganhou uma ferramenta pra fechar essa
lacuna de informação (ver abaixo).

CORRIGIDO NESTA VERSÃO (parcial, de baixo risco)
- privilege_fp_mark_front_page_lcp_images(): além de loading/fetchpriority/
  decoding (já existia), agora calcula aspect-ratio a partir do width/height
  que a PRÓPRIA tag já tem (não é número fixo) e injeta via style inline com
  !important. Por especificidade, inline sempre vence CSS externo — MAS
  aspect-ratio só tem efeito na dimensão que o CSS deixar como "auto"; se o
  tema fixar height a um valor absoluto (ex.: 100% de uma seção com altura
  própria), essa regra fica sem efeito nessa dimensão. Decisão deliberada:
  não forcei height:auto, porque isso poderia encolher visualmente uma seção
  desenhada para ser mais alta que a proporção nativa da imagem — e essa é
  uma mudança visual que eu não tenho como conferir sem ver o site ao vivo.

NOVO NESTA VERSÃO
- admin-dashboard.php ganhou uma seção que lê o style.css do tema (e do
  child theme, se houver) e extrai os blocos de regra que mencionam
  bt_bb_section_top_section_coverage_image, bt_bb_cell ou
  bt_bb_background_image_holder — exibidos direto no painel (Administração >
  Privilege Performance), sem precisar abrir arquivo nenhum manualmente. Não
  é um parser de CSS completo (regex simples, não rotula qual @media cada
  regra pertence), mas é o suficiente para eu conseguir ver a regra real na
  próxima rodada e aplicar a correção definitiva com confiança, em vez de
  seguir advinhando.

SOBRE O CHECKLIST "DESCOBERTA DE SOLICITAÇÕES DE LCP" (desktop)
"A propriedade fetchpriority=high precisa ser aplicada" continua com X mesmo
depois do preload adicionado na V5.1 para o hero com background-image
inline. Isso é esperado e é uma limitação estrutural, não um bug: essa
checagem específica do Lighthouse parece olhar o atributo fetchpriority na
própria tag do elemento — e um background-image via CSS não tem tag <img>,
não tem esse atributo possível. O preload já feito continua trazendo o
ganho prático de priorizar a busca do recurso; o quadradinho em si é
provável que não fique verde enquanto esse hero for implementado como
background-image em vez de <img>. Convertê-lo exigiria alterar a
estrutura HTML gerada pelo tema, um risco maior que não tomei sem
conseguir testar visualmente.

NÃO INVESTIGADO NESTA RODADA (dados insuficientes no que foi compartilhado)
- "Evitar animações não compostas — 4 elementos animados encontrados"
  (mobile e desktop): a seção aparece fechada nos prints, sem os elementos
  listados. Sem saber quais 4 elementos são, não dá pra agir com segurança.
- "Evitar tarefas longas da linha de execução principal": mesma situação,
  seção fechada.
- Se continuarem aparecendo depois da próxima rodada, valeria expandir essas
  duas seções no PageSpeed e enviar o conteúdo.

BASELINE FLYINGPRESS: sem mudança. RUCSS ainda é a ação manual pendente de
maior impacto (achado independente deste, sobre CSS não usado, não sobre
CLS/LCP) e continua sem ser aplicada (158 KiB mobile / 158 KiB desktop nesta
rodada — estável, nem piorou nem foi corrigido).

================================================================================
V5.3 — quarta rodada confirma a correção da V5.2; sem mudança de performance
================================================================================
Data: 23/08/2026 (mesma tarde da V5.2, ~1h depois). Novo PSI mobile + desktop
pedido logo em seguida ao deploy da V5.2, confirmando o efeito da correção
antes de qualquer outra mudança.

CONFIRMADO
- Desktop: Desempenho 72 → 97. CLS 0,784 → 0,003. LCP → 1,0s (verde). O
  aspect-ratio dinâmico da V5.2, sozinho, foi suficiente — o cenário mais
  arriscado que a V5.2 tinha cogitado (CSS do tema fixando a altura da seção
  de forma independente da imagem, exigindo forçar height:auto e arriscando
  encolher a seção visualmente) NÃO se confirmou. Não foi necessária nenhuma
  mudança adicional nem o CSS do tema que o painel passou a extrair.
- Mobile: Desempenho 74 → 75. CLS segue em 0,028 (estável). Latência do
  caminho crítico: 2.045ms → 1.893ms, continua melhorando. LCP mobile
  praticamente parado (5,6s → 5,5s) — ver análise abaixo.

ANÁLISE: por que o LCP mobile não acompanhou o salto do desktop
Com o CLS mobile já baixo (0,028) antes mesmo desta rodada, a instabilidade
de layout não era o gargalo do LCP mobile — ao contrário do desktop, onde
CLS e LCP claramente compartilhavam a mesma causa raiz. O teste mobile do
PSI roda propositalmente sob CPU e rede limitadas (Moto G Power emulado,
4G lento); nessas condições, os ~163 KiB de CSS não usado (aiko-v5.css)
que ainda chegam em toda carga pesam diretamente no tempo até a página
poder pintar o conteúdo principal — exatamente o que LCP mede. RUCSS
continua sendo a ação manual pendente desde a V5.0; esta rodada deu um
argumento mais específico para ela: não é só "158 KiB desperdiçados", é
"o motivo mais provável do LCP mobile não ter melhorado junto com tudo o
resto".

TRÊS ACHADOS AVALIADOS NESTA RODADA E DELIBERADAMENTE NÃO ALTERADOS

1) Console: cloudflare.com retornando 429 (Too Many Requests) no endpoint do
   Turnstile, derrubando "Práticas recomendadas" para 96 em mobile E desktop
   (era 100 antes). Reapareceu de forma consistente com o padrão já visto na
   V5.1/V5.2: rate-limit do lado do Cloudflare por testes de PageSpeed
   repetidos em sequência curta. Considerei (e descartei) a ideia de atrasar
   o carregamento do script do Turnstile para reduzir a frequência de
   chamadas — mas: (a) não tenho como confirmar com segurança qual
   plugin/mecanismo carrega esse script sem arriscar adivinhar o handle
   errado, e (b) mesmo acertando, atrasar carregamento não muda quantas
   vezes o endpoint é chamado por MINUTO durante uma sequência de testes —
   só quando, dentro de cada carregamento individual. O risco (quebrar
   proteção antispam de um formulário que gera negócio para a agência) não
   compensa um ganho que não existe de fato para tráfego real.

2) "Reflow forçado" (desktop) apontando um bloco de JavaScript grande,
   codificado em URL, sem nome de arquivo legível. Decodificado: é o script
   interno do próprio Bold Builder — um MutationObserver que recalcula a
   largura da tela (bt_bb_update_res) e aplica classes de override
   responsivas em elementos com o atributo data-bt-override, em TODO o
   site, não só na Home. 31ms de reflow, com TBT já em 0ms. É engrenagem
   central da responsividade do tema; eu não tenho visibilidade de tudo que
   depende desse mecanismo para avaliar o risco de mexer nele, e o ganho já
   é imperceptível dentro de um TBT que já é 0ms.

3) "A propriedade fetchpriority=high precisa ser aplicada" segue vermelho
   pro hero com background-image (mesmo achado da V5.1/V5.2). Essa checagem
   olha o atributo na própria tag do elemento LCP; background-image via CSS
   não tem tag <img>, logo estruturalmente não tem esse atributo. Cheguei a
   avaliar inserir uma <img fetchpriority="high"> visível sobrepondo o
   background para satisfazer a checagem — mas essa seção usa
   data-parallax-zoom/blur/opacity para animar o PRÓPRIO background-image
   via JS durante o scroll; uma imagem sobreposta ficaria estática por cima
   da animação ou a esconderia por completo. Sem poder testar visualmente,
   não vale o risco de quebrar o efeito de parallax só para fechar um
   quadradinho — ainda mais com o desktop já em 97.

MUDANÇAS NESTA VERSÃO (documentação/painel — nenhuma mudança na lógica de
performance)
- admin-dashboard.php: a notice de CLS crítico (que pedia o CSS do tema)
  virou uma notice de confirmação, com os números antes/depois. O extrator
  de CSS do tema continua no painel, agora recolhido como referência para
  o caso de um problema parecido aparecer em outra imagem/seção no futuro.
- admin-dashboard.php: nova seção documentando os 3 achados acima e o
  motivo técnico específico de cada um não ter sido alterado — para não
  ficar só registrado numa conversa de chat.
- admin-dashboard.php: mensagem do RUCSS reforçada com o argumento do LCP
  mobile especificamente, não só o "CSS não usado" genérico.

BASELINE FLYINGPRESS: sem mudança. RUCSS segue como única ação manual
pendente de alto impacto.
