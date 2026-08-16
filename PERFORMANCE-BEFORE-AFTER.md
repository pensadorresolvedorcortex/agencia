# Performance antes/depois

| Métrica/recurso | Antes (HAR) | Depois | Delta |
|---|---:|---:|---:|
| Aiko CSS descompactado | 810.676 B | NOT VALIDATED | NOT VALIDATED |
| Aiko CSS transferido | 84.277 B | NOT VALIDATED | NOT VALIDATED |
| Builder CSS descompactado | 303.083 B | NOT VALIDATED | NOT VALIDATED |
| Builder CSS transferido | 29.186 B | NOT VALIDATED | NOT VALIDATED |
| Hero | 13.198 B / 338,8 ms | NOT VALIDATED | NOT VALIDATED |
| Lighthouse mobile | ~83–84 (informado) | NOT VALIDATED | NOT VALIDATED |

Captura intermediária após 2.0.1: mobile 90 (FCP 2,0 s; LCP 2,7 s; CLS 0,094; TBT 0 ms; SI 4,1 s) e desktop 96 (FCP/LCP 0,8 s; CLS 0; TBT 0 ms; SI 1,9 s). São runs individuais, não mediana.

Esta versão torna o LCP mobile um elemento de imagem priorizável e elimina o trabalho visual do parallax no hero, mas deliberadamente não afirma redução de bytes: o desmembramento seguro requer WordPress staging e comparação visual.

## Pacote

O ZIP não é versionado porque o fluxo de revisão não aceita binários. Gere-o com `python3 tools/build_package.py`; o SHA-256 determinístico da versão 3.0.0 é `e907ad22e88eed72d7932f0322559510d0767b45e01118bd2e38c56e23676cdb`.
