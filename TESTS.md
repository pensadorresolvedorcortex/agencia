# Testes e validação

## Automatizados nesta entrega

```bash
find jeito-performance-premium tests -name '*.php' -print0 | xargs -0 -n1 php -l
php -d zend.assertions=1 -d assert.exception=1 tests/run.php
python3 tools/build_package.py --output /tmp/jeito-performance-premium-3.2.0.zip
unzip -t /tmp/jeito-performance-premium-3.2.0.zip
python3 tools/analyze_inputs.py
python3 tests/test_analyzer.py
node tests/deferred-grid.test.js
php -d zend.assertions=1 -d assert.exception=1 tests/cli-command.test.php
python3 tests/test_package.py
python3 tests/test_aiko_css.py
python3 tests/test_builder_css.py
```

## Staging obrigatório (NOT VALIDATED)

1. Instalar e ativar o ZIP, purgar W3TC e aquecer a Home.
2. Confirmar no HTML final o `<picture>`/`<img>` `jpp-mobile-lcp` e o style `jpp-aiko-mobile-hero`; eventual preload preexistente não deve ser duplicado; `aiko-style` não deve ser reescrito fora da feature modular.
3. Testar header/hero em 360, 768, 1024 e 1440 px; menu, links, CTA e redução de movimento.
4. Executar Lighthouse mobile três vezes antes/depois e registrar a mediana.
5. Conferir páginas de produto, carrinho, checkout, conta, posts, pesquisa e 404.
6. Confirmar no dashboard apenas uma purga por versão e verificar que um segundo `admin_init` não repete a operação.
7. Confirmar que grids abaixo da dobra não chamam `bt_bb_get_css_grid` antes de chegar a 200 px do viewport; filtros e “Load more” devem permanecer imediatos.
8. Verificar navegação por âncoras e CLS ao rolar todas as seções; sliders, grids, mapas, parallax e seções animadas não podem receber `content-visibility`.
9. Comparar tipografia da Home, inclusive menus e estados interativos; confirmar que a URL Google Fonts contém somente `wght@300;400;500;700` e que outras páginas permanecem intactas.
10. Simular atualização de Aiko/Builder fora das faixas testadas e confirmar que todas as integrações específicas entram em fail-safe.
11. Usar “Verificar Home” após purge/warm-up e exigir aprovação de todos os checks ativos; comparar os bytes retornados com o baseline HAR.
12. Executar `wp jeito-performance verify > verification.json` no deploy e exigir exit code zero.
13. Repetir três runs mobile/desktop e confirmar ausência do reflow atribuído a `deferred-post-grid.js` e aprovação de `fetchpriority=high` no elemento LCP.
14. Medir CLS primeiro com Content Visibility desligado (default e migração 2.2.1); habilitá-lo apenas em um teste A/B isolado e mantê-lo desligado se CLS ou navegação por âncoras piorarem.
15. Na primeira gravação e em uma alteração posterior das features, confirmar uma única purga no request administrativo seguinte, aquecer a Home e só então executar o verificador; salvar o mesmo estado não deve gerar nova purga.
16. Simular falha do provedor de cache: a pendência deve sobreviver às duas primeiras tentativas e encerrar após sucesso ou a terceira falha, sem loop infinito.
17. No resultado do verificador, exigir que Hero corresponda a um `<picture>` com `<source srcset>` e `<img class="jpp-mobile-lcp" loading="eager" fetchpriority="high">`.
18. Exigir aprovação de todas as URLs Google Fonts: nenhuma pode conter eixo `ital`, e todo `wght` explícito deve ser exatamente `300;400;500;700`, inclusive quando percent-encoded.
19. Habilitar Aiko Home CSS isoladamente: comparar screenshots em 360/768/1024/1440 px, confirmar ausência de WooCommerce/mini-cart/shortcodes na Home e verificar que child theme e páginas internas mantêm o `style.css` original.
20. Inserir temporariamente `[products]` e um bloco WooCommerce na Home e confirmar, em ambos os casos, que o HTML volta ao `style.css` original; remover o conteúdo e confirmar que o bundle modular retorna após purge.
21. Ativar mini-cart/widget WooCommerce em header ou sidebar e confirmar o fallback; testar também a hipótese de Shop/Carrinho/Checkout/Conta ser configurada como Home.
22. Mover o widget WooCommerce para “Widgets inativos” e confirmar que o bundle modular volta a ser usado; widgets órfãos também não devem bloquear a feature.
23. Confirmar no HTML pós-W3TC que os bundles Home contêm `?ver=3.2.0`; uma atualização posterior deve mudar a query.
24. Confirmar que a waterfall não solicita `assets/deferred-post-grid.js`: o adapter deve existir apenas inline após `bt_bb_css_post_grid`.
25. Confirmar que o HTML público contém `jpp-mobile-lcp`, `loading="eager"`, `fetchpriority="high"` e o preload do mesmo URL antes de repetir PageSpeed.
26. Exigir `aiko-home.css` e `builder-home.css` no verificador; inserir `[bt_bb_tabs]` e confirmar fallback apenas do Builder para `content_elements.crush.css`.

Não há alegação de score ou equivalência visual sem esses passos.
