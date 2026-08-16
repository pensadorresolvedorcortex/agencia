# Auditoria corretiva 3.2.0

## Evidência recebida

O run real posterior à 3.1.0 registrou **81 no Mobile**. Não há novo valor de Desktop no material recebido. A folha Aiko caiu de 82,7 KiB para 63,5 KiB, confirmando que o bundle modular Aiko foi efetivamente servido. A folha Builder permaneceu no hash `9d2aa.css` (28,9 KiB), portanto sua substituição não chegou ao grupo W3TC. O LCP continuou sendo o `div.bt_bb_background_image_holder`: TTFB 340 ms, atraso de descoberta 530 ms, transferência 690 ms e atraso de renderização 1.140 ms. O adapter externo acrescentou o último nó da cadeia crítica, em 2.213 ms.

## Causa raiz e correções

| Evidência | Causa raiz comprovada | Correção 3.2.0 |
|---|---|---|
| Hero semântico ausente no HTML servido | `do_shortcode_tag` não cobre todos os caminhos de template/cache do Builder; o fallback tardio também não apareceu no documento medido | transformação adicional no `the_content`, após `do_shortcode`, antes da captura do page cache |
| `lcp_priority` falhou | sem o `<picture>` não havia correspondência segura entre URL do hero e preload; o navegador manteve o background como LCP | a transformação anterior ao cache permite ao fallback final emitir/normalizar exatamente o preload desse URL, sem preload heurístico |
| `9d2aa.css` permaneceu | somente `style_loader_src` não foi suficiente no caminho observado pelo minificador | fallback em `style_loader_tag`, limitado ao handle Builder e sujeito às mesmas travas de compatibilidade/conteúdo |
| `deferred-post-grid.js` fechou a cadeia em 2.213 ms | um adapter de 0,75 KiB foi entregue como uma segunda requisição dependente | adapter anexado inline, depois do script Builder já enfileirado; elimina uma requisição sem mudar a sua ordem |

## Decisões mantidas

- O CSS modular Aiko foi mantido porque a redução observada de 19,2 KiB demonstra que a substituição funcionou; voltar ao bundle anterior seria regressão objetiva.
- Não foi aplicado `media=print/onload` aos bundles completos. Sem CSS crítico comprovado por cobertura e validação visual, isso cria FOUC/CLS e troca um problema mensurável por uma regressão funcional.
- Não foi removido `content_elements.js`: o relatório atribui 10 ms de reflow a ele, mas a página depende de seus componentes. Remoção ou atraso global seria destrutivo; a correção de origem exige alteração no código do fornecedor ou inventário de componentes do HTML real.
- Não são declarados novos scores, LCP ou economia pós-3.2.0 antes de nova medição. O alvo de 100 permanece uma meta, não um resultado fabricado.

## Aceite pós-instalação

Após ativar a 3.2.0 e concluir a purga W3TC, o HTML público deve conter `jpp-mobile-lcp`, `loading="eager"`, `fetchpriority="high"`, um preload de imagem sem restrição somente-mobile e `assets/builder-home.css`. A waterfall não deve conter uma solicitação separada a `deferred-post-grid.js`. Repetir três runs Mobile e Desktop; só a mediana pode oficializar novos números.
