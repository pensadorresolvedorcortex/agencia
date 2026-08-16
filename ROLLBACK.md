# Rollback

1. Para rollback de uma família, abra **Jeito Performance → Intervenções ativas**, desmarque somente Hero/LCP, Content Visibility, Post Grid ou Eixos de fontes e salve.
2. Aguarde a purga automática no request administrativo seguinte, aqueça a Home e valide novamente; as demais otimizações permanecem ativas para permitir comparação A/B.
3. Para rollback total, desative **Jeito Performance Premium**.
4. Exclua `wp-content/plugins/jeito-performance-premium`.
5. Purgue Page Cache e Minify do W3 Total Cache e faça warm-up.
6. Verifique que `jpp-aiko-mobile-hero`, `jpp-mobile-lcp` e `jpp-deferred-post-grid` não aparecem mais.

O plugin não modifica tema ou Builder. Ele grava somente versão instalada, feature flags, contador transitório de tentativas e diagnóstico da última purga. A desativação interrompe todas as integrações; a exclusão pelo WordPress executa `uninstall.php` e remove essas options.

`Content visibility` é experimental e vem desligado por padrão; a 2.2.1 também migra instalações anteriores para o estado desligado. Ativá-lo novamente no dashboard é uma decisão explícita de staging, não um requisito para as demais otimizações.
