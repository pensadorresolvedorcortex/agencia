# MISSÃO: OTIMIZAÇÃO PROFUNDA MOBILE DO STUDIO PRIVILEGE

Você é o engenheiro principal responsável por investigar e corrigir os gargalos de performance mobile do site WordPress:

**https://www.studioprivilege.com.br/**

Seu trabalho não é apenas sugerir otimizações. Você deve:

1. analisar profundamente o código-fonte;
2. identificar a origem exata de cada gargalo;
3. propor a solução tecnicamente mais segura;
4. implementar as correções;
5. validar sintaxe e funcionamento;
6. medir impacto;
7. impedir regressões;
8. documentar cada mudança;
9. produzir os artefatos finais prontos para instalação.

Não aceite soluções cosméticas, heurísticas frágeis ou alterações que apenas façam o dashboard parecer melhor.

A prioridade absoluta é:

**performance MOBILE real medida por Lighthouse/PageSpeed**, preservando integralmente layout, funcionalidades, SEO, acessibilidade e experiência visual.

---

# 1. ARQUIVOS DE ENTRADA

Analise integralmente os seguintes arquivos antes de modificar qualquer coisa:

```text
/mnt/data/aiko.zip
/mnt/data/bold-page-builder.zip
/mnt/data/GTmetrix-www.studioprivilege.com.br-20260815T145916-O8d1Lhax.har
/mnt/data/jeito-performance-premium-2.6.1.zip
```

Caso uma versão posterior do Jeito Performance esteja presente no workspace, use a versão mais recente como base.

Extraia cada ZIP em diretório separado e mantenha cópias intactas dos originais.

Estrutura sugerida:

```text
/work/original/aiko/
/work/original/bold-page-builder/
/work/original/jeito-performance/

/work/analysis/
/work/patched/
/work/tests/
/work/reports/
```

Nunca edite a única cópia do código-fonte original.

---

# 2. CONTEXTO TÉCNICO JÁ IDENTIFICADO

Considere estes fatos como hipóteses iniciais a serem confirmadas pelo código e pelo HAR.

## Aiko

O tema é:

```text
Aiko 1.0.5
```

O arquivo global:

```text
style.css
```

possui aproximadamente:

```text
920 KB não minificados
~810 KB após minificação
```

No HAR ele corresponde aproximadamente a:

```text
/wp-content/cache/minify/ea034.css
```

O tema registra globalmente o stylesheet principal por mecanismo equivalente a:

```php
wp_enqueue_style( 'aiko-style', get_stylesheet_uri(), ... );
```

O SCSS principal agrega vários módulos de framework, tema, WooCommerce, componentes, desktop, responsive, widgets, Gutenberg e elementos Bold Builder.

Investigue integralmente essa arquitetura.

---

## Bold Page Builder

O plugin é aproximadamente:

```text
Bold Page Builder 5.9.6
```

O arquivo:

```text
content_elements.crush.css
```

possui aproximadamente:

```text
349 KB original
~303 KB servido
```

No HAR ele corresponde aproximadamente a:

```text
/wp-content/cache/minify/9d2aa.css
```

Ele contém, entre outros recursos:

```text
Icon7Stroke
FontAwesome
grid
sections
buttons
headlines
images
tabs
slider
post grid
componentes individuais
```

Investigue como esses módulos são registrados, combinados e carregados.

---

# 3. RESULTADO ATUAL

A performance mobile está aproximadamente na faixa:

```text
83–84 Lighthouse
```

A meta final é chegar o mais próximo possível de:

```text
99–100 Lighthouse mobile
```

Mas NÃO falsifique, manipule ou otimize especificamente o detector do Lighthouse.

O objetivo é melhorar a página real.

Use métricas intermediárias como verdade técnica:

```text
FCP < 1.8 s
LCP < 2.5 s
CLS < 0.05
TBT ~ 0 ms
Speed Index significativamente reduzido
```

O TBT historicamente já chegou a:

```text
0 ms
```

Portanto não trate JavaScript como gargalo principal sem evidência nova.

---

# 4. REGRA FUNDAMENTAL: UMA FAMÍLIA POR VEZ

Não faça dezenas de alterações simultaneamente.

Trabalhe em ciclos.

Para cada problema:

```text
1. identificar
2. provar
3. localizar origem
4. criar hipótese
5. implementar UMA solução
6. validar
7. medir
8. manter ou rollback
9. documentar
10. somente então avançar
```

Se uma alteração piorar:

```text
Performance Score
FCP
LCP
CLS
Speed Index
TBT
```

investigue e reverta quando necessário.

Não continue empilhando alterações sobre uma regressão.

---

# 5. ITEM 1 — AIKO CSS

Este é o PRIMEIRO problema a ser resolvido.

Não comece pelo Bold Builder.

Mapeie precisamente:

```text
style.scss
style.css
framework-loader.scss
theme-loader.scss
```

e todos os SCSS importados.

Produza uma árvore completa:

```text
Aiko style.css
├── core
├── normalize
├── base
├── layout
├── header
├── header responsive
├── header desktop
├── footer
├── post
├── widgets
├── Gutenberg
├── WooCommerce
├── comentários
├── Bold Builder overrides
├── Contact Form 7
├── componentes
└── demais módulos
```

Calcule:

```text
bytes SCSS
bytes CSS compilado
percentual do CSS global
uso provável na Home
uso provável no mobile
```

---

# 6. AIKO: PROPOSTA DE MODULARIZAÇÃO

Investigue se é seguro substituir o stylesheet monolítico por múltiplos assets.

Modelo conceitual:

```text
aiko-core.css
aiko-mobile.css
aiko-desktop.css
aiko-content.css
aiko-commerce.css
aiko-components.css
```

Isso é apenas uma sugestão.

Você pode propor arquitetura superior.

Preserve rigorosamente:

```text
ordem da cascata
especificidade
variáveis CSS
media queries
dependências
customizer
CSS dinâmico
child themes
WooCommerce
Bold Builder
responsive
RTL se aplicável
```

---

# 7. WOOCommerce

Investigue quanto CSS global do Aiko pertence ao WooCommerce.

Se confirmado que grandes blocos WooCommerce são globais, mova-os para carregamento contextual.

Analise condições como:

```php
is_woocommerce()
is_product()
is_shop()
is_product_category()
is_cart()
is_checkout()
is_account_page()
```

Não carregue CSS de comércio na Home quando não necessário.

Tenha cuidado com:

```text
mini-cart
cart fragments
widgets
header cart
shortcodes WooCommerce inseridos fora das páginas WooCommerce
```

Não faça uma exclusão simplista que quebre esses casos.

---

# 8. DESKTOP × MOBILE

Investigue todo CSS específico de desktop.

Quando possível, carregue-o por mídia:

```html
media="(min-width: ...)"
```

em vez de colocá-lo no caminho crítico mobile.

Não use detecção PHP de user-agent como primeira opção.

Prefira:

```text
media queries
media attribute
CSS naturalmente responsivo
```

para preservar compatibilidade com cache.

---

# 9. CRITICAL CSS

Depois de modularizar corretamente a origem, avalie Critical CSS.

NÃO tente resolver 800 KB de CSS apenas com um parser heurístico pós-render.

A prioridade é:

```text
reduzir o que entra no bundle
↓
modularizar
↓
carregar contextualmente
↓
só depois Critical CSS
```

O Critical CSS mobile deve cobrir apenas:

```text
document
body
header
menu mobile
hero
headline
texto inicial
CTA inicial
tipografia da primeira dobra
elemento LCP
```

Meta inicial:

```text
< 50–70 KB não comprimidos
```

Se puder ficar menor sem quebrar layout, melhor.

---

# 10. ITEM 2 — BOLD PAGE BUILDER

Somente após concluir e validar o Item 1.

Mapeie integralmente:

```text
content_elements.crush.css
```

até seus arquivos-fonte.

Identifique todos os componentes individuais.

Exemplo:

```text
bt_bb_section
bt_bb_row
bt_bb_column
bt_bb_headline
bt_bb_button
bt_bb_icon
bt_bb_image
bt_bb_slider
bt_bb_css_post_grid
bt_bb_card_icon
bt_bb_card_image
bt_bb_tabs
bt_bb_video
bt_bb_progress_bar
bt_bb_cost_calculator
...
```

Para cada componente determine:

```text
CSS
JS
fontes
ícones
dependências
enqueue
condições
uso na Home
uso acima da dobra
uso abaixo da dobra
uso mobile
```

---

# 11. NÃO CARREGAR COMPONENTE AUSENTE

Implemente, sempre que tecnicamente seguro:

```text
componente não existe
→ asset não deve ser carregado
```

Quando um componente existe apenas abaixo da dobra:

```text
→ avaliar CSS tardio
→ avaliar JS tardio
→ avaliar IntersectionObserver
→ avaliar content-visibility
```

Quando estiver oculto no mobile:

```text
→ verificar se assets podem ser removidos do mobile
```

Mas preserve funcionalidades responsivas e não confunda:

```text
display:none temporário
```

com:

```text
componente inexistente
```

---

# 12. HERO / LCP

Investigue diretamente no Aiko e Bold Builder como o hero atual é construído.

O recurso LCP atual é aproximadamente:

```text
/wp-content/uploads/2026/08/agencia-desenvolvimento-de-sites.webp
```

Historicamente o LCP é:

```text
background-image
dentro de bt_bb_parallax
```

O HAR já indicou preload:

```html
rel="preload"
as="image"
fetchpriority="high"
```

Não presuma que isso resolve o problema.

Meça:

```text
descoberta do recurso
priority
request start
download
paint
render delay
```

---

# 13. HERO MOBILE

Investigue se o melhor caminho é transformar o background mobile em:

```html
<img>
```

com:

```html
loading="eager"
fetchpriority="high"
decoding="async"
```

ou manter background adequadamente priorizado.

A escolha deve ser baseada em medição.

No mobile, teste a remoção de:

```text
parallax
transform inicial
transition
animation
fade
will-change desnecessário
```

somente no hero/LCP.

Preserve desktop se necessário.

---

# 14. ITEM 3 — FONTES E ÍCONES

Somente depois de Aiko + Builder CSS.

O HAR mostrou Google Fonts com famílias aproximadamente:

```text
Plus Jakarta Sans
Poppins
Play
```

e muitos pesos/itálicos.

Mapeie onde cada família e peso são realmente usados.

Não solicite automaticamente:

```text
100
300
400
500
700
900
+
todos os itálicos
```

se a página não usa tudo isso.

Determine o subconjunto real.

Considere:

```text
400
500
700
```

como hipótese inicial, mas confirme no CSS/DOM.

---

# 15. FONT AWESOME E ICON7STROKE

Analise:

```text
FontAwesome
Icon7Stroke
```

presentes no CSS do Bold Builder.

Identifique os ícones realmente usados na Home.

Investigue opções, na seguinte ordem:

```text
1. não carregar se não usado
2. carregar somente biblioteca necessária
3. subset
4. SVG inline para poucos ícones
5. stylesheet tardio se só abaixo da dobra
```

Não remova icon font se houver uso dinâmico que você não tenha comprovado como ausente.

---

# 16. ITEM 4 — DOM

A página já apresentou aproximadamente:

```text
1999 nós
~676–693 KB de HTML descompactado
```

Mapeie:

```text
número de sections
rows
columns
wrappers
elementos hidden
duplicação desktop/mobile
componentes invisíveis
grids
sliders
cards
formulários
```

Descubra quais partes vêm do Bold Builder.

Não remova wrappers apenas para satisfazer Lighthouse.

Priorize:

```text
componentes desnecessários
duplicação desktop/mobile
elementos invisíveis
markup redundante
conteúdo abaixo da dobra
```

---

# 17. CONTENT-VISIBILITY

Avalie:

```css
content-visibility: auto;
contain-intrinsic-size: ...;
```

para seções abaixo da dobra.

Use apenas quando:

```text
não afetar âncoras
não afetar sticky
não afetar scroll calculations
não quebrar sliders
não quebrar scripts do Builder
não causar CLS
```

Meça antes e depois.

---

# 18. ITEM 5 — CSS POST GRID / AJAX

O HAR mostra chamadas:

```text
/wp-admin/admin-ajax.php
```

com:

```text
action=bt_bb_get_css_grid
```

incluindo:

```text
portfolio
post
```

Investigue o código:

```text
bt_bb_css_post_grid.js
PHP correspondente ao AJAX
```

Determine:

```text
por que o AJAX dispara imediatamente
se grid está abaixo da dobra
se pode esperar IntersectionObserver
se primeira carga pode ser server-side
se pode existir HTML inicial
se cache pode ser aplicado
```

Não mude isso antes de concluir os itens anteriores.

---

# 19. ITEM 6 — GTRANSLATE

O HAR mostra:

```text
/wp-content/plugins/gtranslate/js/flags.js
```

e múltiplos SVGs de bandeiras.

Investigue se o GTranslate precisa carregar tudo durante renderização inicial.

Avalie:

```text
postergação
interação
IntersectionObserver
apenas idioma atual + seletor necessário
```

Mas não prejudique SEO internacional ou seleção de idioma.

---

# 20. ITEM 7 — JAVASCRIPT

Só tratar JS se houver evidência.

Atualmente o TBT historicamente chegou a:

```text
0 ms
```

Scripts relevantes encontrados incluem aproximadamente:

```text
slick.min.js
content_elements.js
aiko.js
bt_framework_misc.js
bt_bb_elements.js
bt_bb_card_icon.js
bt_bb_css_post_grid.js
WordPress hooks
WordPress i18n
GTranslate
W3TC lazyload
```

Crie tabela:

```text
script
origem
bytes
blocking time
uso
dependência
necessário acima da dobra?
candidato a defer?
candidato a delay?
```

Não deferir jQuery/Bold Builder/Aiko indiscriminadamente.

---

# 21. W3 TOTAL CACHE

Não trate URLs:

```text
ea034.css
9d2aa.css
```

como identificadores permanentes.

Eles são derivados.

Trabalhe preferencialmente com:

```text
WordPress handles
arquivo fonte
plugin/theme proprietário
função enqueue
dependências
```

O W3TC deve receber uma fila já otimizada.

Investigue:

```text
CSS minify
JS minify
page cache
browser cache
object cache
lazy loading
cache purge
hash regeneration
```

Evite conflito entre:

```text
Jeito Performance
W3TC
Aiko
Bold Builder
```

---

# 22. JEITO PERFORMANCE

O Jeito Performance deve deixar de ser apenas um pós-processador genérico.

Transforme-o gradualmente em um orquestrador de integrações.

Arquitetura sugerida:

```text
Jeito Performance
├── Integration_Aiko
├── Integration_BoldBuilder
├── Integration_W3TC
├── Integration_GTranslate
├── Integration_WooCommerce
├── Integration_Fonts
└── Integration_WordPress
```

Não é obrigatório usar exatamente esses nomes.

Use arquitetura limpa e extensível.

---

# 23. INTEGRAÇÃO COM AIKO

O Jeito Performance deve conseguir:

```text
detectar versão do Aiko
mapear handles
detectar módulos
detectar contexto WooCommerce
detectar mobile CSS
detectar CSS desktop
verificar HTML final
registrar intervenção
rollback
```

Evite hardcode baseado exclusivamente em hash.

---

# 24. INTEGRAÇÃO COM BOLD BUILDER

Deve conseguir:

```text
detectar versão
mapear elementos presentes
mapear componentes acima da dobra
detectar hero
detectar parallax
detectar grids
detectar sliders
detectar icons
detectar componentes abaixo da dobra
condicionar assets
```

Prefira hooks/filtros/WordPress APIs.

---

# 25. ALTERAÇÃO DIRETA DE VENDOR

Evite modificar:

```text
Aiko original
Bold Builder original
```

quando a mesma solução puder ser aplicada por:

```text
hooks
filters
wp_dequeue_style
wp_enqueue_style
wp_dequeue_script
wp_script_add_data
template override
child theme
Jeito Performance
```

Se uma alteração direta no vendor for tecnicamente indispensável:

1. documente exatamente por quê;
2. informe arquivo;
3. função;
4. trecho original;
5. patch;
6. risco;
7. efeito em atualizações;
8. alternativa rejeitada.

---

# 26. MU-PLUGIN

Avalie se alguma intervenção precisa executar antes dos plugins normais.

Somente nesse caso, considere um componente mínimo:

```text
/wp-content/mu-plugins/jeito-performance-early-loader.php
```

Não mova toda a aplicação para MU plugin.

O early loader deve ser mínimo e auditável.

---

# 27. NÃO ALTERAR O VISUAL

A otimização deve preservar:

```text
tipografia
cores
spacing
header
menu
hero
CTA
animações relevantes
cards
grids
formulários
footer
responsive
desktop
tablet
mobile
```

Compare screenshots antes/depois.

Qualquer diferença não intencional é regressão.

---

# 28. TESTES AUTOMATIZADOS OBRIGATÓRIOS

Para PHP:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

Para JS relevante:

```bash
node --check arquivo.js
```

Use também:

```bash
unzip -t pacote-final.zip
```

Adicione testes específicos para:

```text
enqueue/dequeue
WooCommerce contextual
desktop media
Bold Builder component detection
LCP
preload
fontes
CSS condicional
rollback
cache purge
W3TC hash changes
```

---

# 29. TESTES DE HTML FINAL

Nunca considere uma configuração aplicada apenas porque uma option do WordPress foi salva.

Após cada intervenção:

```text
purge
warm-up
GET da página
parse do HTML
verificação
```

Confirme que a alteração existe no documento efetivamente servido.

Exemplos:

```text
stylesheet removido
stylesheet contextual
media correto
preload presente
fetchpriority correto
parallax removido
async efetivo
Critical CSS presente
```

---

# 30. LIGHTHOUSE

Quando possível, execute Lighthouse mobile real.

Use pelo menos:

```text
3 execuções independentes
```

e calcule:

```text
mediana
```

Não use um único teste particularmente favorável como prova definitiva.

Registre:

```text
score
FCP
LCP
CLS
TBT
Speed Index
TTFB
```

---

# 31. COMPARAÇÃO A/B

Para cada grande intervenção produza:

```text
ANTES
DEPOIS
DELTA
```

Exemplo:

```text
Aiko monolítico:
810 KB

Aiko crítico:
XX KB

CSS restante:
YY KB

FCP:
2.9 → X

LCP:
3.4 → X

CLS:
0.08 → X
```

---

# 32. NÃO OTIMIZAR SOMENTE TRANSFER SIZE

Diferencie sempre:

```text
bytes transferidos
bytes descompactados
bytes parseados
bytes efetivamente usados
bytes render-blocking
```

Um arquivo gzip de 84 KB que expande para 810 KB ainda possui custo significativo de parse/style calculation.

---

# 33. PERFORMANCE BUDGET

Defina budgets iniciais mobile:

```text
Critical CSS:
< 70 KB uncompressed

CSS render blocking:
ideal < 100 KB uncompressed

Font CSS crítico:
próximo de zero

LCP image:
alta prioridade

TBT:
< 100 ms
preferencialmente ~0

CLS:
< 0.05

LCP:
< 2.5 s

FCP:
< 1.8 s
```

Ajuste apenas mediante justificativa técnica.

---

# 34. RELATÓRIO DE CADA ITEM

Para cada problema use obrigatoriamente esta estrutura:

```text
ITEM X — NOME

Problema:
...

Evidência:
...

Origem:
...

Arquivo(s):
...

Função/handle:
...

Impacto:
...

Solução proposta:
...

Riscos:
...

Implementação:
...

Validação:
...

Resultado:
ANTES:
DEPOIS:
DELTA:

Decisão:
MANTER / REVERTER / INVESTIGAR
```

---

# 35. NÃO ESCONDER FALHAS

Se uma solução não funcionar, registre:

```text
FAILED
```

Não transforme falha em “otimização aplicada”.

Se um recurso não puder ser medido:

```text
NOT VALIDATED
```

Se houver apenas inferência:

```text
INFERENCE
```

Se houver prova:

```text
VERIFIED
```

---

# 36. MODAL DO JEITO PERFORMANCE

Preserve e aprimore o modal solicitado ao final de:

```text
Analisar, corrigir e revalidar
```

Ele deve mostrar:

```text
análise concluída
foco mobile
estratégias tentadas
estratégias efetivamente aplicadas
estratégias revertidas
implementações não confirmadas
métricas antes/depois
Lighthouse disponível ou não
próximo gargalo
```

Nunca feche o ciclo silenciosamente.

---

# 37. PRIORIZAÇÃO INICIAL

Comece nesta ordem:

```text
1. Aiko CSS monolítico
2. Bold Builder CSS monolítico
3. Fontes e ícones
4. DOM / seções abaixo da dobra
5. CSS Post Grid / AJAX
6. GTranslate
7. JavaScript somente se houver evidência
8. demais gargalos identificados
```

Não pule para o Item 2 até o Item 1 estar implementado e medido.

---

# 38. ENTREGÁVEIS

Ao final produza:

## Código

```text
Jeito Performance Premium atualizado
```

com número de versão incrementado semanticamente.

## Se necessário

```text
child theme
MU early loader
arquivos CSS compilados
```

somente quando tecnicamente justificado.

## Documentação

```text
ANALYSIS.md
CHANGES.md
TESTS.md
PERFORMANCE-BEFORE-AFTER.md
ROLLBACK.md
```

---

# 39. PACOTE FINAL

Crie ZIP instalável.

Exemplo:

```text
jeito-performance-premium-X.Y.Z.zip
```

Confirme que não existe diretório duplicado:

```text
plugin/plugin/...
```

Estrutura correta:

```text
jeito-performance-premium/
├── jeito-performance-premium.php
├── includes/
├── assets/
└── ...
```

---

# 40. CHECKSUM

Calcule:

```bash
sha256sum jeito-performance-premium-X.Y.Z.zip
```

Inclua o SHA-256 no relatório final.

---

# 41. REGRA DE AUTONOMIA

Você tem autorização para:

```text
ler todos os arquivos
extrair ZIPs
buscar referências
mapear dependências
alterar código
criar código novo
compilar CSS quando possível
executar testes
criar harnesses
empacotar ZIP
comparar resultados
corrigir seus próprios erros
```

Não pare após encontrar o primeiro problema.

Porém respeite a metodologia:

```text
ANALISAR
→ ITEM 1
→ IMPLEMENTAR
→ TESTAR
→ VALIDAR
→ DOCUMENTAR
→ ITEM 2
```

---

# 42. PROIBIÇÕES

Não:

```text
inventar resultados Lighthouse
declarar correção sem verificar HTML
alterar score interno para parecer melhor
ativar CSS defer global às cegas
deferir JS indiscriminadamente
editar hashes W3TC como solução permanente
remover recursos sem localizar dependência
quebrar desktop para melhorar mobile
remover acessibilidade
remover SEO
remover conteúdo
desativar funcionalidades silenciosamente
```

---

# 43. CRITÉRIO FINAL

A missão só termina quando houver:

```text
causa identificada
código alterado
implementação verificada
regressão visual descartada
testes técnicos aprovados
impacto medido
pacote instalável
rollback documentado
```

A prioridade não é produzir muitas modificações.

A prioridade é produzir **poucas modificações de alto impacto, tecnicamente justificadas e comprovadamente efetivas**.

Comece agora pelo:

# ITEM 1 — AIKO CSS MONOLÍTICO

Antes de modificar qualquer arquivo:

1. extraia o Aiko;
2. mapeie `style.scss → loaders → módulos → style.css`;
3. identifique os handles WordPress;
4. reconstrua a composição do `ea034.css`;
5. calcule o tamanho de cada família de estilos;
6. classifique cada bloco como:
   - obrigatório global;
   - mobile crítico;
   - desktop;
   - WooCommerce;
   - conteúdo;
   - componente;
   - potencialmente removível;
7. apresente o mapa técnico;
8. só então implemente a primeira correção;
9. valide;
10. prossiga autonomamente até concluir toda a missão.