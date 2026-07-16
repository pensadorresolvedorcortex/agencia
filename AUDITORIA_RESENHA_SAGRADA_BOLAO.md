# Auditoria solicitada para o plugin Resenha Sagrada Bolão

A URL correta indicada é `https://github.com/DheniellGuimaraes/agencia/blob/main/resenha-sagrada-bolao.zip`. A página pública do GitHub confirma que o arquivo `resenha-sagrada-bolao.zip` existe no branch `main` e possui 323 KB.

## Bloqueio do checkout atual

O checkout local disponível para edição ainda não contém `resenha-sagrada-bolao.zip`; ele contém apenas os artefatos anteriores do branch local. Também não foi possível sincronizar/baixar o arquivo diretamente por `git fetch`, `curl` ou GitHub Raw no terminal porque o proxy do ambiente retornou HTTP 403 para GitHub.

## Pontos que permanecem preservados neste branch

- Nenhuma alteração foi feita em lógica de banco de dados.
- Nenhuma alteração foi feita em pontuação de palpites.
- Nenhum shortcode foi criado.
- Nenhuma alteração visual foi aplicada.
- Nenhum arquivo binário incompatível foi gerado.

## Jogos informados para atualização pendente no ZIP correto

- Quinta-feira, 9 de julho de 2026, 17h — França x Marrocos
- Sexta-feira, 10 de julho de 2026, 16h — Espanha x Bélgica
- Sábado, 11 de julho de 2026, 18h — Noruega x Inglaterra
- Sábado, 11 de julho de 2026, 22h — Argentina x Suíça

## Observação de segurança

Não gerei um ZIP substituto nem alterei outro plugin, porque isso poderia quebrar o bolão em produção e violar a regra de preservar pontuações e a forma atual de inserção das seleções. Para aplicar a mudança com segurança, o arquivo `resenha-sagrada-bolao.zip` precisa estar presente no checkout local ou o ambiente precisa permitir a sincronização com o `main` remoto.
