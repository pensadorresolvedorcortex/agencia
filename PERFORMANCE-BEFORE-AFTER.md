# Performance antes/depois

| Métrica/recurso | Antes (HAR) | Depois | Delta |
|---|---:|---:|---:|
| Aiko CSS descompactado | 810.676 B | NOT VALIDATED | NOT VALIDATED |
| Aiko CSS transferido | 84.277 B | NOT VALIDATED | NOT VALIDATED |
| Builder CSS descompactado | 303.083 B | NOT VALIDATED | NOT VALIDATED |
| Builder CSS transferido | 29.186 B | NOT VALIDATED | NOT VALIDATED |
| Hero | 13.198 B / 338,8 ms | NOT VALIDATED | NOT VALIDATED |
| Lighthouse mobile | ~83–84 (informado) | NOT VALIDATED | NOT VALIDATED |

Captura intermediária após 2.0.1: mobile 90 (FCP 2,0 s; LCP 2,7 s; CLS 0,094; TBT 0 ms; SI 4,1 s) e desktop 96 (FCP/LCP 0,8 s; CLS 0; TBT 0 ms; SI 1,9 s). Run informado após 3.0.0: Mobile 89 / Desktop 97, ainda com ambos os bundles globais e falha `hero,lcp_priority`. São runs individuais, não mediana.

Esta versão torna o LCP mobile um elemento de imagem priorizável e elimina o trabalho visual do parallax no hero, mas deliberadamente não afirma redução de bytes: o desmembramento seguro requer WordPress staging e comparação visual.

## Pacote

O ZIP não é versionado porque o fluxo de revisão não aceita binários. Gere-o com `python3 tools/build_package.py`; o SHA-256 determinístico da versão 3.1.0 é `b61379c0d823cb56d9c60c574d2a37eb4cb88f53207ed1f84f586938ae5c33e0`.
