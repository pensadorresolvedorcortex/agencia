# Alterações

## 2.0.1

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
