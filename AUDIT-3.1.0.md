# Auditoria corretiva 3.1.0 — PageSpeed Mobile 89 / Desktop 97

## Evidência nova

O run informado após 3.0.0 registra Mobile 89 e Desktop 97. Mobile aponta 1.360 ms potenciais em CSS bloqueante (Aiko 82,7 KiB/1.370 ms; Builder 28,9 KiB/550 ms), 45 ms de reflow majoritariamente em script inline não atribuído e falha `hero,lcp_priority`. Desktop aponta 810 ms potenciais no Aiko, 160 ms no Builder, 200 ms no Google Fonts e 39 ms de reflow inline. Ambos estimam ~106 KiB de CSS não usado e apenas 2,9 KiB de economia por minificação de `content_elements.js`.

## Causa raiz confirmada

1. O filtro `do_shortcode_tag` não transformou o markup servido por esse caminho de renderização/cache; por isso o verificador e PageSpeed continuaram vendo o `div.bt_bb_background_image_holder`.
2. `aiko-home.css` estava opt-in e não ativo no HTML medido, comprovado pela permanência de `ea034.css` com 82,7 KiB.
3. O Builder continuava usando o bundle global `9d2aa.css`.
4. O reflow já não é atribuído ao adapter JPP; 45/39 ms estão concentrados em script inline da página e 0 ms em `bt_framework_misc.js`. Não existe trecho fonte correlacionável no repositório para substituir com segurança.
5. A economia JS de 2,9 KiB não é causa do score: TBT não foi apontado como regressão e a cadeia é dominada por CSS/TTFB.

## Correções oficializadas

* Fallback de transformação do HTML final da Home, idempotente, para cobrir renderização Builder que bypassa `do_shortcode_tag`.
* Preload único e incondicional do hero para Mobile e Desktop, com `fetchpriority=high`; preload existente é reutilizado, recebe prioridade e perde media mobile-only.
* Aiko Home CSS passa a default ativo e é migrado para ativo, mantendo todos os fail-safes de comércio/child theme.
* Builder Home CSS source-level passa a default ativo: 231.288 B compilados contra 299.625 B na compilação completa equivalente; gzip 20.256 B contra 28.425 B (−8.169 B). Componentes ausentes no snapshot são removidos; conteúdo que referencia qualquer um deles restaura o bundle completo.

## Limites honestos

O CSS continua render-blocking porque ele contém a cascata necessária. A redução é de bytes e parsing, não `media=print`. Eliminar integralmente o alerta exigiria Critical CSS visualmente validado e carregamento tardio do restante. Sem staging isso arriscaria FOUC/CLS. Google Fonts ainda bloqueia 200 ms no desktop; self-host exigiria adicionar/subsetar WOFF2 e validar licenças/glifos. O reflow inline exige o HTML/JS pós-cache exato da linha 284 para localizar a função; não é atribuível aos fontes entregues.
