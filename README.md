# Elfsight Age Verification — compatibilidade com PHP 8.5

O código-fonte corrigido está no diretório
`elfsight-age-verification-cc/`. As propriedades usadas pelo painel foram
declaradas explicitamente para que o PHP 8.2 ou superior não tente criá-las
dinamicamente.

## Importante sobre o ZIP antigo

O arquivo `elfsight-age-verification-cc.zip` existente na raiz é o pacote
original e **não contém as correções**. Ele foi mantido sem alterações porque
arquivos binários não são aceitos no fluxo de revisão deste repositório.

Não instale o plugin usando o link direto desse ZIP. Gere um pacote atualizado
a partir dos arquivos-fonte:

```bash
./build-plugin.sh
```

O pacote corrigido será criado em
`dist/elfsight-age-verification-cc.zip`.

## Atualização no WordPress

1. Gere o pacote com o comando acima.
2. Envie `dist/elfsight-age-verification-cc.zip` em **Plugins > Adicionar
   plugin > Enviar plugin** e escolha substituir a versão atualmente instalada.
   Não exclua o plugin pelo painel, pois `uninstall.php` remove seus dados.
3. Ative o plugin e confirme que a versão exibida é **1.1.1**. Se o painel ainda mostrar 1.1.0,
   limpe o cache de opcode do PHP ou solicite isso à hospedagem.

Também é possível enviar diretamente o diretório
`elfsight-age-verification-cc/` para `wp-content/plugins/` via SFTP, substituindo
todos os arquivos da instalação anterior.
