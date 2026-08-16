# Performance antes/depois

| Métrica/recurso | Antes (HAR) | Depois | Delta |
|---|---:|---:|---:|
| Aiko CSS descompactado | 810.676 B | NOT VALIDATED | NOT VALIDATED |
| Aiko CSS transferido | 84.277 B | NOT VALIDATED | NOT VALIDATED |
| Builder CSS descompactado | 303.083 B | NOT VALIDATED | NOT VALIDATED |
| Builder CSS transferido | 29.186 B | NOT VALIDATED | NOT VALIDATED |
| Hero | 13.198 B / 338,8 ms | NOT VALIDATED | NOT VALIDATED |
| Lighthouse mobile | ~83–84 (informado) | NOT VALIDATED | NOT VALIDATED |

Esta versão torna o LCP mobile um elemento de imagem priorizável e elimina o trabalho visual do parallax no hero, mas deliberadamente não afirma redução de bytes: o desmembramento seguro requer WordPress staging e comparação visual.

## Pacote

O ZIP não é versionado porque o fluxo de revisão não aceita binários. Gere-o com `python3 tools/build_package.py`; para a versão 2.0.1 atual o SHA-256 determinístico é `47b16fe83548fce0e14f6146f6990d213b9dc839beac3708ff94ce55f003be95`.
