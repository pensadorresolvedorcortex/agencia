# Auditoria técnica final — Mobile e Desktop

## 1. Estado encontrado

A evidência é composta pelo HAR GTmetrix, pelo HTML preservado em `01.mhtml`, pelos fontes Aiko 1.0.5/Bold Builder 5.9.6 e pelas capturas PageSpeed de 15/08/2026. O HAR contém 43 requests, 701.709 B transferidos (body) e 2.508.445 B decodificados; `DOMContentLoaded` ocorre em 1.666,3 ms, `load` em 1.717 ms, LCP em 1.547 ms e fully loaded em 6.512 ms. A captura posterior registra Mobile 90 / Desktop 96; Mobile: FCP 2,0 s, LCP 2,7 s, CLS 0,094, TBT 0 ms, Speed Index 4,1 s; Desktop: FCP/LCP 0,8 s, CLS 0, TBT 0 ms e Speed Index 1,9 s.

O gargalo dominante permanece CSS bloqueante: Aiko `ea034.css` transfere 84.277 B/expande para 810.676 B; Builder `9d2aa.css` transfere 29.186 B/expande para 303.083 B. O diagnóstico PageSpeed estima 1.420 ms de economia mobile, 1.040 ms desktop e ~106 KiB de CSS não utilizado. O HTML mede 692.604 B no HAR, com 2.036 elementos no snapshot. TBT zero refuta JavaScript como prioridade principal.

## 2. Auditoria crítica das soluções anteriores

| Solução | Veredicto | Fundamentação final |
|---|---|---|
| `<picture>/<img>` mobile para o hero | manter | Torna o LCP descobrível no parse, mantém background desktop e usa `loading=eager`/`fetchpriority=high`. |
| Mutação de todo `wp_head` por output buffer | remover | O MHTML já contém preload correto e o `<img>` possui prioridade própria. Buffer global adicionava memória, risco de conflito e promovia “primeira imagem”, não necessariamente LCP. |
| Preload obrigatório no verificador | substituir | Preload não é requisito quando o elemento LCP é descobrível. A prova correta é o próprio `<img>` possuir eager/high; preload externo permanece opcional. |
| Parallax neutralizado só no mobile | manter | Remove trabalho visual no dispositivo limitado sem mudar desktop. |
| Deferred Post Grid via IntersectionObserver | manter | Elimina AJAX antecipado abaixo da dobra sem leitura geométrica síncrona; TBT continua zero. |
| `content-visibility` | manter opt-in/desligado | CLS mobile é 0,094; ativação automática não é justificável sem A/B. |
| Limitação de eixos Google Fonts | manter | Remove variantes não evidenciadas e possui rollback; verificador audita todas as URLs. |
| Aiko Home CSS source-level | manter opt-in | Remove 192.198 B compilados/18.371 B gzip projetados, preserva handle e possui fail-safes para child theme e comércio. |
| CSS assíncrono global/`media=print` | rejeitar | Pode causar FOUC, CLS e quebra da primeira dobra; não trata causa raiz. |
| Minificar cópia de JS vendor | rejeitar | Economia indicada ~2,9 KiB com TBT 0; cria manutenção duplicada sem benefício material. |
| Mais preconnects | rejeitar | Google Fonts já possui preconnect; hints adicionais competem por conexões. |

## 3. Alterações desta auditoria final

### `includes/class-integration-aiko.php`

**Problema:** bufferizava o `wp_head` inteiro e elevava a primeira imagem preloadada. **Causa raiz:** tentativa de corrigir um sinal de Lighthouse sem vincular a prioridade ao elemento LCP. **Solução anterior:** regex pós-render no head. **Solução final:** remover buffer e mutação; deixar a prioridade no `<img>` semântico do hero, já emitido com `loading="eager" fetchpriority="high"`. Isso reduz código, memória e risco de conflito.

### `includes/class-html-verifier.php`

**Problema:** exigia preload explícito apesar de o navegador descobrir o `<img>` durante parsing. **Causa raiz:** confundia otimização opcional com requisito funcional. **Solução anterior:** correlacionar `<link rel=preload>` e `srcset`. **Solução final:** validar no elemento LCP real `loading=eager` e `fetchpriority=high`. Um preload existente continua útil, mas não é duplicado nem obrigatório.

### Aiko Home CSS

Mantido opt-in: 613.013 B contra 805.211 B na compilação completa (−23,9%); gzip reproduzível 63.751 B contra 82.122 B. A diferença real pós-W3TC continua não medida. A ativação automática foi rejeitada porque conteúdo customizado fora das APIs detectáveis pode depender de WooCommerce.

## 4. Comparação objetiva das métricas

| Métrica | Antes disponível | Depois disponível | Resultado |
|---|---:|---:|---|
| Performance Mobile | 90 (captura 2.0.1) | NOT VALIDATED 3.0.0 | requer staging |
| Performance Desktop | 96 (captura 2.0.1) | NOT VALIDATED 3.0.0 | requer staging |
| LCP Mobile | 2,7 s | NOT VALIDATED | hero agora é elemento priorizado |
| LCP GTmetrix | 1,547 s | NOT VALIDATED | baseline HAR |
| CLS Mobile | 0,094 | NOT VALIDATED | `content-visibility` desligado |
| CLS Desktop | 0 | NOT VALIDATED | sem mudança global de layout |
| TBT Mobile/Desktop | 0 ms / 0 ms | NOT VALIDATED | JS não é alvo principal |
| FCP Mobile/Desktop | 2,0 s / 0,8 s | NOT VALIDATED | CSS continua determinante |
| Speed Index Mobile/Desktop | 4,1 s / 1,9 s | NOT VALIDATED | CSS/primeira dobra determinantes |
| Requests HAR | 43 | NOT VALIDATED | sem alegação inventada |
| Transferência HAR | 701.709 B | NOT VALIDATED | bodySize agregado |
| Tamanho decodificado HAR | 2.508.445 B | NOT VALIDATED | conteúdo agregado |
| Aiko CSS compilado | 805.211 B | 613.013 B candidato | −192.198 B build |
| Aiko CSS gzip reproduzível | 82.122 B | 63.751 B candidato | −18.371 B build |

## 5. Soluções rejeitadas

* **Critical CSS heurístico extraído do snapshot:** não cobre estados de menu, hover, conteúdo dinâmico, Customizer ou cache e pode aumentar CLS.
* **Remover FontAwesome sem subset real:** o HTML não prova todos os ícones de menus, widgets e estados interativos.
* **Defer/async global de Builder:** sliders e componentes dependem de ordem; TBT zero não justifica o risco.
* **Remover wrappers DOM do Builder:** altera layout/cascata e não é possível provar equivalência sem renderização WordPress.
* **Ativar automaticamente Aiko Home CSS:** a redução é real no build, mas a equivalência visual e markup customizado permanecem externos ao snapshot.

## 6. Riscos residuais e limite objetivo de 100

O impedimento mensurável no último run é Mobile LCP 2,7 s, FCP 2,0 s, CLS 0,094 e Speed Index 4,1 s. O recurso dominante é CSS bloqueante (Aiko/Builder; oportunidade PageSpeed 1.420 ms mobile). A única forma de declarar remoção do impacto é ativar o bundle source-level em staging, purgar/aquecer W3TC e medir três runs. Torná-lo obrigatório sem regressão pode quebrar comércio ou CSS customizado; por isso seria uma troca destrutiva não aceita.

Infraestrutura, TTFB do servidor, W3TC/CDN e terceiros só podem ser medidos no ambiente real. Nenhuma métrica pós-3.0.0 é inferida.

## 7. Veredicto técnico

**SIM**, dentro dos arquivos, evidências e restrições disponíveis: a implementação final remove a otimização cosmética de head buffering, valida prioridade no elemento LCP real, mantém intervenções comprovadas e deixa a redução source-level de CSS atrás de opt-in e fail-safes. **Não significa score 100 validado**: Mobile/Desktop pós-3.0.0 permanecem `NOT VALIDATED` até staging.
