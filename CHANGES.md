# Alterações

## 3.1.0

* Adicionado fallback idempotente no HTML final para sanar `hero/lcp_priority` quando o caminho Builder/cache não executa `do_shortcode_tag`.
* Preload do hero é criado ou normalizado para Mobile/Desktop com `fetchpriority=high`, sem duplicação.
* Aiko Home CSS e novo Builder Home CSS source-level passam a ativos por default/migração, mantendo fail-safe por conteúdo e rollback.
* Builder CSS reduz de 299.625 B para 231.288 B na compilação equivalente; gzip de 28.425 B para 20.256 B.

## 3.0.0

* Removido o output buffer global de `wp_head` e a promoção heurística da primeira imagem preloadada; o MHTML já contém preload e o hero semântico carrega sua própria prioridade.
* O verificador passou a exigir `loading="eager"` e `fetchpriority="high"` no `<img>` LCP real, sem transformar preload opcional em requisito nem incentivar duplicação.
* Adicionada auditoria final crítica com baseline HAR/GTmetrix/PageSpeed, revisão de todas as decisões, métricas não inventadas, soluções rejeitadas, riscos e veredicto.

## 2.7.5

* Adicionado `?ver=JPP_VERSION` ao `aiko-home.css`; a substituição via `style_loader_src` já não perde o cache-buster que existia no stylesheet original.
* Atualizações futuras do CSS passam a gerar uma URL distinta no navegador, CDN e W3TC, evitando que um bundle antigo sobreviva à purga.
* Adicionado teste explícito da versão na URL modular.

## 2.7.4

* Corrigido falso fallback do CSS modular: widgets WooCommerce guardados em `wp_inactive_widgets` não são renderizados e já não bloqueiam o bundle da Home.
* Grupos `orphaned_widgets_*` também são ignorados; somente widgets presentes em sidebars realmente ativas acionam a proteção.
* Adicionados testes distintos para mini-cart ativo, widget inativo e widget órfão.

## 2.7.3

* Ampliado o fail-safe do CSS modular para contextos WooCommerce (`shop`, produto, carrinho, checkout e conta) quando algum deles também for configurado como Home.
* Widgets ativos com IDs WooCommerce, inclusive mini-cart em header/sidebar, restauram automaticamente o stylesheet completo.
* Adicionados testes para widget `woocommerce_widget_cart`, contexto WooCommerce, shortcodes, blocos e child theme.

## 2.7.2

* Adicionado fail-safe de comércio ao Aiko Home CSS: blocos Gutenberg WooCommerce e shortcodes de produto/loja no conteúdo da Home mantêm automaticamente o stylesheet original.
* A guarda atua mesmo com a feature experimental marcada, evitando remover estilos necessários após uma edição de conteúdo esquecida no dashboard.
* Adicionados testes para `[products]`, bloco `woocommerce/product-collection`, Home auditada sem comércio e child theme.

## 2.7.1

* Fixado Dart Sass em `1.102.0` para impedir que atualizações futuras do compilador alterem silenciosamente o CSS versionado.
* O builder agora exige o SHA-256 exato do `aiko.zip` auditado antes de extrair ou compilar, falhando de forma segura para qualquer arquivo diferente.
* Adicionado teste negativo do archive e documentada a projeção gzip reproduzível: 82.122 B completos contra 63.751 B no bundle da Home (−18.371 B; resultado de build, não medição de rede).

## 2.7.0

* Adicionado `aiko-home.css`, compilado no nível Sass sem os dois módulos WooCommerce e sem `single-product`: 613.013 B contra 805.211 B da compilação completa equivalente (−192.198 B não comprimidos).
* A substituição mantém o handle `aiko-style`, preservando inline CSS e dependências, atua somente na Home Aiko sem child theme e é experimental/opt-in até regressão visual em staging.
* Child themes, outras páginas e a configuração padrão continuam usando o stylesheet original.

## 2.6.0

* Eliminado falso positivo de fontes: encontrar uma URL correta já não oculta uma segunda URL Google Fonts com itálicos ou pesos não aprovados.
* Todas as URLs `fonts.googleapis.com/css2` do HTML são normalizadas (entidades e percent-encoding) e auditadas família por família.
* Famílias sem eixo explícito continuam válidas; eixos `wght` explícitos devem corresponder a `300;400;500;700` e `ital` é rejeitado.

## 2.5.0

* Eliminado falso positivo do verificador: a mera presença do nome `.jpp-mobile-lcp` no CSS não prova mais que o `<picture>/<img>` foi inserido.
* O check de prioridade agora exige que o `href` do preload `fetchpriority="high"` seja exatamente a URL do `srcset` do hero mobile, em vez de aceitar qualquer imagem prioritária, como o logo.
* Adicionados testes para marcador somente em CSS, preload do recurso errado e hero completo válido.

## 2.4.0

* Falhas transitórias de purga agora são repetidas em até três requests administrativos, em vez de descartar imediatamente a pendência e deixar HTML antigo no cache.
* O diagnóstico registra o número da tentativa; sucesso encerra imediatamente e três falhas encerram o ciclo para impedir loop infinito.
* Uma nova alteração de feature reinicia o contador, e a desinstalação remove a option de tentativas.

## 2.3.1

* Corrigida a primeira gravação das feature flags: WordPress usa `add_option()` nesse caso, portanto agora ela também agenda a purga pós-salvamento.
* Mantida validação estrita do nome da option e do formato allowlist antes de criar a pendência.
* Adicionado teste separado para primeira gravação, alteração posterior e salvamento idêntico.

## 2.3.0

* Agendada uma purga única de Page Cache/Minify sempre que o estado de uma feature realmente mudar, evitando que o HTML público mantenha markup, CSS ou scripts do estado anterior.
* Alterações idênticas não agendam purga, preservando a proteção contra flushes redundantes.
* Adicionado teste do ciclo salvar feature → purgar → não repetir.

## 2.2.2

* Corrigida a inserção de `fetchpriority="high"` em tags de preload autocontidas (`/>`), preservando HTML válido em temas/plugins que usam serialização XHTML.
* Adicionada regressão automatizada para impedir a forma inválida `/ fetchpriority="high">`.

## 2.2.1

* Adicionada migração única para desligar `content-visibility` também em instalações atualizadas que haviam persistido o antigo default ativo.
* Preservados os estados já salvos das outras três intervenções durante a migração.

## 2.2.0

* Tornado `content-visibility` opt-in: a captura mobile ainda apresenta CLS 0,094 e a intervenção não passou pela validação visual/âncoras exigida.
* Corrigido o dashboard para representar os defaults reais de cada feature flag em vez de marcar toda opção ausente como ativa.
* Mantidas ativas por padrão apenas as intervenções diretamente comprovadas: Hero/LCP, Post Grid tardio e eixos de fontes.

## 2.1.0

* Criado plugin WordPress instalável e sem dependência de hashes W3TC.
* Convertido o primeiro background parallax em um `<picture>/<img>` semântico apenas no mobile; desktop mantém o background original.
* Removidos efeitos de parallax do hero transformado apenas abaixo de 768 px.
* Removido `fetchpriority` do `<link rel="stylesheet">`, que não reduz o CSS monolítico e não é uma intervenção justificável.
* Evitado preload duplicado: o MHTML já contém o preload do hero.
* Adicionados testes unitários mínimos e documentação de validação/rollback.
* Adicionado dashboard administrativo glassmorphism totalmente translúcido, responsivo e acessível, com cards coloridos, diagnóstico do ambiente e modal técnico.
* Ampliada a auditoria para fontes, HTML/DOM, scripts e GTranslate usando exclusivamente o HAR e o MHTML fornecidos.
* Tornado o relatório de DOM reproduzível e adicionada retenção de foco por teclado no modal técnico.
* Adicionada coordenação de cache idempotente após ativação/atualização, priorizando a API pública do W3 Total Cache e registrando provedor, versão, horário e resultado.
* Adicionada limpeza integral das options do plugin no uninstall.
* Adiado o AJAX inicial de cada CSS Post Grid que estiver fora da proximidade do viewport, preservando carregamento imediato acima da dobra e browsers sem IntersectionObserver.
* Aplicado `content-visibility` somente a seções top-level a partir da terceira, sem animação e sem sliders, grids, mapas ou parallax.
* Adicionado parser estrutural das media queries do Aiko, comprovando que não há blocos `min-width` separáveis e impedindo uma divisão desktop/mobile insegura.
* Adicionado rollback granular e persistente para Hero/LCP, Content Visibility e Post Grid, com sanitização allowlist e controles acessíveis no dashboard.
* Limitados os eixos Google Fonts da Home aos pesos comprovados 300/400/500/700, removendo itálicos e pesos 100/900 não encontrados; alteração possui rollback próprio.
* Adicionadas guardas de compatibilidade para Aiko 1.0.5–1.0.x e Bold Builder 5.9.6–5.x; versões futuras desconhecidas recebem fail-safe sem transformação.
* Adicionada verificação autenticada do HTML da Home realmente servido após cache, com checks por feature, tamanho do documento e resultado acessível no dashboard.
* Corrigida a verificação para não solicitar bypass/revalidação do cache e adicionado marcador inline estável para validar o Post Grid mesmo quando W3TC troca URLs por hashes.
* Extraído verificador reutilizável e adicionado comando `wp jeito-performance verify`, com JSON e exit code não-zero para CI/deploy.
* Adicionado empacotador determinístico com ordem, timestamps e permissões fixas, além de teste byte-for-byte do ZIP instalável.
* Removido o ZIP binário do versionamento; o pacote instalável passa a ser gerado deterministicamente durante release/CI.
* Corrigida a prioridade dos preloads de imagem já existentes na Home sem duplicar recursos.
* Removida a leitura geométrica síncrona do adapter de Post Grid identificada nas capturas como reflow forçado.
* Adicionada auditoria comparativa dos novos resultados PageSpeed mobile 90/desktop 96.
