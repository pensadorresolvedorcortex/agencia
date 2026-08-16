# Testes e validação

## Automatizados nesta entrega

```bash
find jeito-performance-premium tests -name '*.php' -print0 | xargs -0 -n1 php -l
php -d zend.assertions=1 -d assert.exception=1 tests/run.php
python3 tools/build_package.py --output /tmp/jeito-performance-premium-2.0.1.zip
unzip -t /tmp/jeito-performance-premium-2.0.1.zip
python3 tools/analyze_inputs.py
python3 tests/test_analyzer.py
node tests/deferred-grid.test.js
php -d zend.assertions=1 -d assert.exception=1 tests/cli-command.test.php
python3 tests/test_package.py
```

## Staging obrigatório (NOT VALIDATED)

1. Instalar e ativar o ZIP, purgar W3TC e aquecer a Home.
2. Confirmar no HTML final um único preload preexistente, o `<picture>` `jpp-mobile-lcp` e o style `jpp-aiko-mobile-hero`; `aiko-style` não deve ser reescrito.
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

Não há alegação de score ou equivalência visual sem esses passos.
