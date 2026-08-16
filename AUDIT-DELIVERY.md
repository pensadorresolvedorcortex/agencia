# Auditoria integral da entrega

## Comparação com os inputs

| Dado | Input verificado | Entrega | Estado |
|---|---:|---|---|
| Aiko | 1.0.5 | guarda `>=1.0.5 <1.1.0` | consistente |
| Bold Builder | 5.9.6 | guarda `>=5.9.6 <6.0.0` | consistente |
| Aiko `style.css` | 920.153 B fonte / 810.676 B HAR | reportado sem alegar redução | consistente |
| Builder CSS | 349.439 B fonte / 303.083 B HAR | reportado sem alegar redução | consistente |
| Hero WebP | 13.198 B / 338,8 ms | transformado somente quando parallax existe | consistente |
| Post Grid | 2 AJAX imediatos | adapter condicional + marcador pós-W3TC | implementado, falta staging |
| HTML | 692.604 B no HAR / 2.036 nós no MHTML | baseline e verificador de bytes | consistente |
| FontAwesome | 264.648 B descompactados | somente auditado; subset não inventado | pendente explícito |
| Google Fonts | 100/300/400/500/700/900 + itálicos | Home limitada a 300/400/500/700 | implementado, falta visual |

## Divergências encontradas e saneadas

1. A documentação dizia que nenhuma mudança havia sido aplicada ao AJAX mesmo após existir adapter; texto corrigido.
2. O verificador tratava Hero e Google Fonts ausentes como falha; agora recursos inexistentes são “não aplicáveis”.
3. A detecção do Post Grid dependia do nome do arquivo, incompatível com hashes W3TC; substituída por marcador inline estável.
4. O GET do verificador enviava `no-cache`, contrariando a intenção de verificar Page Cache; header removido.
5. O ZIP estava versionado como binário. Ele foi removido e substituído por build determinístico testado byte-for-byte.

## Binários

Os ZIPs, HAR e MHTML de entrada permanecem como fixtures originais já existentes. Nenhum novo binário é adicionado pela entrega. O release é gerado fora do Git por:

```bash
python3 tools/build_package.py --output /tmp/jeito-performance-premium-2.0.1.zip
```

SHA-256 determinístico atual:

```text
47b16fe83548fce0e14f6146f6990d213b9dc839beac3708ff94ce55f003be95
```

## Limites honestos

Não há WordPress, W3TC, navegador ou Lighthouse executável no repositório. Portanto, equivalência visual, HTML de staging, Lighthouse e deltas pós-instalação continuam `NOT VALIDATED`. O código local, testes, verificador, rollback e artefato reproduzível estão concluídos; a aceitação de produção depende do checklist de `TESTS.md`.
