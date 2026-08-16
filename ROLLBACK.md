# Rollback

1. Para rollback de uma família, abra **Jeito Performance → Intervenções ativas**, desmarque somente Hero/LCP, Content Visibility, Post Grid ou Eixos de fontes e salve.
2. Purgue o cache e valide novamente; as demais otimizações permanecem ativas para permitir comparação A/B.
3. Para rollback total, desative **Jeito Performance Premium**.
4. Exclua `wp-content/plugins/jeito-performance-premium`.
5. Purgue Page Cache e Minify do W3 Total Cache e faça warm-up.
6. Verifique que `jpp-aiko-mobile-hero`, `jpp-mobile-lcp` e `jpp-deferred-post-grid` não aparecem mais.

O plugin não modifica tema ou Builder. Ele grava somente versão instalada, feature flags e diagnóstico da última purga. A desativação interrompe todas as integrações; a exclusão pelo WordPress executa `uninstall.php` e remove essas options.
