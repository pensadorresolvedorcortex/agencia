# Auditoria PageSpeed — 15/08/2026

Fonte: capturas `01.png`–`08.png` fornecidas após a instalação da versão 2.0.1. As imagens foram baixadas somente para inspeção e não são adicionadas ao Git.

## Resultado observado

| Métrica | Mobile | Desktop | Meta |
|---|---:|---:|---:|
| Performance | 90 | 96 | 100 |
| FCP | 2,0 s | 0,8 s | <1,8 s |
| LCP | 2,7 s | 0,8 s | <2,5 s |
| Speed Index | 4,1 s | 1,9 s | reduzir |
| TBT | 0 ms | 0 ms | ~0 ms |
| CLS | 0,094 | 0 | <0,05 |

## Gargalos confirmados

### 1. CSS bloqueante

Mobile estima 1.420 ms de economia; desktop, 1.040 ms. Os dois bundles continuam no caminho crítico:

* Aiko `ea034.css`: ~82,7 KiB transferidos, 1.440 ms mobile / 1.060 ms desktop;
* Builder `9d2aa.css`: ~28,9 KiB, 580 ms mobile / 210 ms desktop.

O diagnóstico de CSS não usado estima ~106 KiB: aproximadamente 79 KiB no Aiko e 27 KiB no Builder. Isso confirma a análise de origem, mas não torna seguro aplicar `media=print` global: sem Critical CSS comprovado haveria FOUC e CLS. A solução correta continua sendo compilar bundles source-level, preservar cascata e validar screenshots.

### 2. LCP mobile

O LCP observado era o `div.bt_bb_background_image_holder.bt_bb_parallax`. A solução final 3.0.0 não promove heuristicamente preloads: converte o recurso em `<img>` descobrível com `loading="eager"` e `fetchpriority="high"`. O preload já presente no MHTML pode permanecer, mas é opcional e não é duplicado pelo plugin.

### 3. Reflow forçado

Mobile registra 49 ms associados ao adapter `deferred-post-grid.js`; desktop registra 22 ms. A causa local era a leitura síncrona de `getBoundingClientRect()` durante a inicialização. A versão 2.1.0 remove essa leitura e delega inclusive grids visíveis ao callback assíncrono do `IntersectionObserver`. Load More e offsets posteriores continuam imediatos.

### 4. JavaScript não minificado

`content_elements.js` tem economia estimada de apenas ~2,9 KiB nos dois perfis. Como TBT é 0 ms, isso não é o limitador do score e não justifica substituir código vendor por uma cópia minificada mantida pelo plugin.

### 5. Cadeia crítica

As cadeias mostram CSS, fontes e scripts do Builder. `fonts.googleapis.com` e `fonts.gstatic.com` já possuem preconnect. Adicionar mais preconnects não é recomendado pelo próprio relatório. A prioridade é reduzir CSS bloqueante; não adicionar hints cosméticos.

## Decisões

| Família | Decisão |
|---|---|
| Prioridade do elemento LCP | CORRIGIR no `<img>`; preload opcional |
| Reflow introduzido pelo adapter | CORRIGIR |
| CSS assíncrono global | REJEITAR sem Critical CSS validado |
| Minificação vendor de 2,9 KiB | NÃO PRIORITÁRIO |
| Mais preconnects | REJEITAR |
| Modularização Aiko/Builder | PRÓXIMA intervenção source-level |

O primeiro artefato source-level está disponível na 2.7.0 como opt-in: a compilação Aiko da Home remove apenas `framework/assets/scss/woocommerce`, `assets/scss/woocommerce` e `assets/scss/single-product`, reduzindo 192.198 B não comprimidos. Ele não é ativado automaticamente antes da validação visual e da confirmação de ausência de comércio dinâmico na Home.

### Controle de CLS na 2.2.1

Como o CLS mobile observado é 0,094, acima da meta de 0,05, `content-visibility` deixa de ser habilitado por padrão. Uma migração única também o desliga em upgrades que salvaram o default anterior. A funcionalidade permanece disponível como experimento opt-in no dashboard, mas só deve ser mantida se a comparação A/B em staging demonstrar redução de trabalho sem deslocamento adicional. Essa decisão remove uma variável não validada do caminho para diagnosticar o CLS real.

## Limite da medição

As capturas são execuções individuais, não a mediana de três runs. Após instalar 3.0.0, repetir três execuções mobile e desktop e comparar FCP, LCP, CLS, Speed Index e o tempo de reflow. Nenhum delta pós-3.0.0 é declarado antes disso.
