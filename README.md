# Portal Grupos WhatsApp

Plugin WordPress independente para cadastro, moderação e descoberta de grupos, canais e comunidades. Requer PHP 8.1+, WordPress 6.4+ e HTTPS.

## Instalação

1. Envie `portal-grupos-whatsapp-1.0.0.zip` em **Plugins > Adicionar plugin > Enviar plugin**.
2. Ative o plugin. A ativação cria as tabelas, papéis, capacidades, taxonomias e agenda as rotinas.
3. Salve os links permanentes se o servidor não aceitar `/grupo/` e `/ir/` imediatamente.
4. Em **Portal Grupos > Configurações**, informe a URL do logo e os limites do catálogo.
5. Crie páginas apenas após aprovação editorial e insira os shortcodes abaixo.

## Shortcodes

| Shortcode | Finalidade |
|---|---|
| `[pgw_entrar]` | Login por e-mail, senha e confirmação por código |
| `[pgw_criar_conta]` | Cadastro e confirmação do e-mail |
| `[pgw_confirmar_codigo]` | Confirma códigos de uso único |
| `[pgw_recuperar_senha]` | Recuperação pela infraestrutura nativa |
| `[pgw_minha_conta]` | Perfil, avatar e segurança |
| `[pgw_meus_grupos]` | Grupos do proprietário autenticado |
| `[pgw_enviar_grupo]` | Envio moderado de grupo |
| `[pgw_mostrar_grupos limit="30" load_more="1"]` | Showcase, catálogo e carregamento assíncrono |
| `[pgw_categorias limit="10" count="0"]` | Navegação de categorias |
| `[pgw_busca]` | Busca pública |
| `[pgw_header]`, `[pgw_footer]` | Shell independente |
| `[pgw_perfil_resumido]` | Resumo do usuário atual |

## Banco, cron e segurança

As tabelas `{prefix}pgw_auth_challenges`, `pgw_rate_limits`, `pgw_events`, `pgw_reports` e `pgw_audit_log` são criadas por `dbDelta`; a versão fica em `pgw_db_version`. O cron horário remove desafios vencidos, limites antigos e cadastros não confirmados. O verificador de links roda duas vezes ao dia e é conservador.

Convites aceitam somente HTTPS e rotas reconhecidas em `chat.whatsapp.com`, `whatsapp.com` e `www.whatsapp.com`. Códigos são armazenados exclusivamente como hash, possuem cinco tentativas, dez minutos de validade, cooldown e uso único. IP e user-agent são armazenados apenas como HMAC.

A validação de convites está isolada em `PGW\Security\InviteUrlValidator`, sem chamadas de rede, e possui testes para esquemas, hosts semelhantes, credenciais, portas, caracteres de controle e rotas inválidas. Os 40 registros demonstrativos ficam em `data/demo-groups.php` e não armazenam links externos.

O catálogo consulta todos os grupos aprovados, inclusive os que não possuem metadado de destaque. O showcase ordena as prioridades como 2 (esquerda), 1 (centro) e 3 (direita); somente a prioridade 1 recebe elevação e fundo reforçado.

O botão **Carregar mais** usa offset absoluto baseado na quantidade já renderizada. Assim, um catálogo inicial de 30 itens continua no item 31 mesmo quando cada nova carga contém 15 resultados, sem repetir os itens 16–30. A ordenação usa data e ID para permanecer estável entre requisições.

## Google e e-mail

O login Google permanece desativado sem configuração e a ausência de credenciais não afeta o login por e-mail. Não há credenciais incluídas neste repositório. Defina `PGW_GOOGLE_CLIENT_ID` e `PGW_GOOGLE_CLIENT_SECRET` em `wp-config.php` quando o módulo OIDC for habilitado. A URI canônica a cadastrar é `https://SEU-DOMINIO/confirmar-codigo/`.

O envio usa `wp_mail()`. Configure um provedor SMTP transacional no WordPress e valide SPF, DKIM e DMARC no domínio; nenhum segredo SMTP deve ser salvo no plugin.

## Dados e backup

Desativar preserva dados e remove somente agendas. Antes de atualizar, faça backup do banco e de `wp-content/uploads`. A desinstalação não apaga usuários ou grupos silenciosamente.

## Hooks úteis

* `pgw_event_retention_seconds`: altera a retenção dos eventos agregados.
* `pgw_cleanup_cron`: rotina de limpeza.
* `pgw_link_check_cron`: verificação conservadora de links.

## Desenvolvimento e build

Execute `npm test` para os testes de segurança puros, `npm run lint` para validar a sintaxe do arquivo principal e `npm run build` para recriar o ZIP. O empacotador inclui código de produção, dados, assets, idiomas, licença e documentação; testes e dependências de desenvolvimento permanecem fora do arquivo instalável.

## Proteção de formulários

Cadastro, login e envio de grupos combinam nonce WordPress, prova HMAC vinculada à finalidade, timestamp com validade máxima, honeypot fora da tela e tempos mínimos de 2, 1 e 3 segundos. O nome do honeypot é derivado por HMAC e nenhum CAPTCHA ou serviço externo é utilizado.
Cada prova inclui identificador aleatório de 128 bits e é consumida por inserção atômica com chave única; uma segunda submissão da mesma prova é rejeitada sem armazenar o conteúdo dos campos.

Os fluxos pré-autenticação usam um contexto opaco assinado, válido por 15 minutos, em vez de expor ID de usuário e finalidade na URL. O mesmo contexto é exigido no reenvio do código.

Na exclusão confirmada por senha e OTP, todas as sessões são revogadas, os grupos passam para `inactive`/rascunho com estado anterior preservado e o usuário nativo é removido. O conteúdo é reassociado a um administrador para impedir exclusão acidental; sem administrador disponível, a conta fica pendente para intervenção manual.

A troca de senha valida primeiro a senha atual, exige OTP e somente então libera uma autorização descartável de dez minutos para definir e confirmar a nova senha. A senha nova não é persistida temporariamente; ao concluir, todas as sessões anteriores são revogadas e o usuário recebe aviso por e-mail.

A área de segurança agrega as sessões nativas do WordPress sem exibir tokens, IP ou user-agent. O usuário pode preservar a sessão atual e revogar as demais, ou encerrar todas as sessões; ambas as ações usam nonce e auditoria.

A página individual oferece denúncia assíncrona pelos dez motivos documentados. O endpoint aceita somente grupos publicados e aprovados e combina nonce, prova assinada de uso único, honeypot, tempo mínimo de dois segundos e rate limit; motivos e detalhes passam por política explícita de normalização.

Administradores podem filtrar denúncias abertas, em análise, resolvidas ou improcedentes, abrir o grupo associado e registrar transições. Toda alteração exige a capacidade `pgw_manage_reports`, nonce específico e gera auditoria com estado anterior e posterior.

O link checker processa lotes vencidos e nunca declara expiração após uma única falha. Respostas 404/410 exigem três ocorrências; falhas de rede e 5xx usam estado temporário, enquanto bloqueios 401/403/429 permanecem possivelmente ativos. HEAD 405 recebe fallback GET com corpo limitado a 1 KB.

O painel **Portal Grupos > Pendentes e Correções** aplica uma política explícita de transições. Rejeição e solicitação de correção exigem motivo; cada decisão preserva o estado anterior, sincroniza o status nativo, registra moderador/data no log e notifica o proprietário por e-mail.

Destaques são definidos exclusivamente por administradores, com prioridade de 1 a 999 e janela opcional de início/fim. O frontend considera a janela em tempo real e o cron desativa destaques vencidos sem excluir o grupo, registrando a expiração uma única vez na auditoria.

Imagens usam os tamanhos nativos `pgw-square` (800×800), `pgw-hero` (1000×500) e `pgw-avatar` (512×512). Uploads validam arquivo real, MIME e limite configurável, rejeitam SVG do usuário e armazenam ponto focal; os ícones estruturais ficam no sprite local `assets/icons/sprite.svg`.

Os metadados privados de grupos usam o prefixo canônico `_pgw_`. A migration v4 converte instalações anteriores de `pgw_*` sem sobrescrever valores já migrados; opções, usermeta e term meta mantêm seus prefixos públicos conforme o contrato específico de cada domínio.

O catálogo aceita parâmetros compartilháveis `pgw_q`, `pgw_category`, `pgw_type`, `pgw_location`, `pgw_featured`, `pgw_link` e `pgw_order`. As ordenações suportadas são destaques, recentes, atualizados, mais acessados e A–Z; o carregamento assíncrono mantém o mesmo conjunto de filtros.

Em **Meus Grupos**, o proprietário pode editar e reenviar seus próprios registros. O handler valida ownership, nonce, rate limit, termos existentes, convite allowlisted e duplicidade por HMAC; qualquer alteração retorna o grupo para `pending`, preserva o estado anterior e remove mensagens antigas de rejeição/correção.

## Google OpenID Connect

Configure `PGW_GOOGLE_CLIENT_ID` e `PGW_GOOGLE_CLIENT_SECRET` no `wp-config.php` (prioridade máxima) ou no painel. Cadastre como redirect URI o valor exibido pelo WordPress: `/wp-admin/admin-post.php?action=pgw_google_callback`. O fluxo servidor usa authorization code, PKCE S256, state/nonce de uso único, valida issuer/audience/expiração/e-mail verificado pelo endpoint oficial e exige OTP antes da sessão. Colisão de e-mail nunca vincula somente por coincidência: o proprietário confirma por código.

Em **Minha Conta > Segurança**, usuários autenticados podem vincular outra identidade Google ou solicitar a desvinculação. As duas operações ficam associadas à sessão iniciadora e a alteração só é persistida depois do OTP enviado ao e-mail da conta. O plugin impede remover o Google quando ele é o único provedor registrado; nesse caso, o usuário pode criar uma senha após OTP para habilitar o provedor de e-mail antes da remoção.

Quando a claim `picture` está presente, a foto é importada somente de hosts HTTPS explícitos do Google, com redirecionamentos limitados, limite de resposta, MIME real e sideload pela biblioteca de mídia do WordPress. Uma foto enviada manualmente nunca é sobrescrita. Ao desvincular, apenas o avatar cuja origem seja Google é removido; o apagador de dados pessoais também limpa identificadores e referências Google.

## Recuperação de senha

O shortcode `[pgw_recuperar_senha]` recebe a solicitação e a conclusão sem encaminhar o visitante para a interface padrão do WordPress. A resposta de solicitação é sempre genérica, com nonce, prova temporal de uso único e limites por origem e e-mail. O e-mail usa `/recuperar-senha/` com chave nativa codificada; ao concluir, o WordPress invalida a chave e as sessões anteriores, o plugin registra auditoria e envia um aviso ao titular.

## Cadastro público

O shortcode `[pgw_criar_conta]` coleta nome, sobrenome opcional, e-mail, telefone opcional, senha e confirmação, além de consentimentos independentes para Termos e Privacidade. Telefones brasileiros são normalizados com código `+55` e números internacionais são armazenados em E.164. O fluxo combina honeypot, tempo mínimo, prova descartável e limites por origem/e-mail antes de criar um usuário nativo pendente e enviar o OTP.

## Troca de e-mail

A solicitação autenticada valida duplicidade, impede reutilizar o endereço atual e aplica limite por usuário. O OTP é enviado diretamente ao **novo endereço**, sem alterar imediatamente o usuário. A confirmação precisa ocorrer em até dez minutos; depois dela, todas as sessões anteriores são revogadas, a sessão atual é recriada e os endereços antigo e novo recebem avisos. Reenvios permanecem vinculados ao endereço pendente e ao contexto assinado da operação.

## Exportação da conta

Em **Minha Conta > Exportar meus dados**, o titular pode baixar um JSON versionado com seus dados cadastrais, consentimentos, provedores e grupos enviados. A ação exige sessão, nonce e limite por usuário, registra auditoria e nunca inclui hash de senha, OTP, cookies, tokens de sessão, client secret, IP ou tokens Google. A exportação inclui o convite dos grupos somente porque pertence ao próprio titular autenticado.

## Exclusão da conta

A exclusão exige a confirmação textual `EXCLUIR`, rate limit e OTP antes de qualquer alteração. Contas com acesso por e-mail também precisam validar a senha atual; contas cujo único provedor é Google não são bloqueadas por uma senha aleatória desconhecida, mas continuam obrigadas a confirmar o OTP no e-mail verificado. Depois da confirmação, sessões são revogadas e grupos são desativados e preservados para revisão administrativa antes da remoção do usuário nativo.

## Assistente de páginas

O plugin não cria páginas durante a ativação. Em **Portal Grupos > Assistente**, um administrador escolhe explicitamente quais das nove interfaces sugeridas deseja publicar. O processo usa nonce e capacidade própria, reaproveita páginas que já tenham o slug esperado, nunca altera o conteúdo existente, registra as novas páginas na auditoria e salva os IDs em `pgw_page_ids` para referência operacional.

## SEO e indexação

Login, cadastro, confirmação, recuperação, Minha Conta, Meus Grupos, envio, redirecionamentos, demos e grupos não aprovados recebem `noindex`, `nofollow` e `noarchive` pela API `wp_robots`. Grupos públicos aprovados recebem canonical, descrição, Open Graph e Twitter Card com a imagem `pgw-hero`. Quando Yoast SEO, Rank Math ou SEOPress está ativo, o plugin deixa de imprimir metadados sociais próprios para evitar duplicidade; a proteção de indexação privada permanece ativa.

## Perfil

Minha Conta permite editar nome, sobrenome, nome público, telefone e biografia curta de até 300 caracteres. Os valores textuais são limpos e limitados, o telefone reutiliza a normalização E.164 do cadastro e nenhuma alteração de perfil pode modificar e-mail, papéis ou provedores. Atualizações válidas usam as APIs nativas de usuário e geram evento de auditoria sem registrar o conteúdo anterior ou dados sensíveis.

## Imagens do conteúdo demo

Ao instalar ou usar **Recriar imagens**, o plugin produz localmente artes PNG abstratas e determinísticas nas cores da marca, sem chamadas externas, marcas de terceiros ou pessoas identificáveis. Cada demo recebe uma imagem quadrada 800×800 e um hero 1000×500 como attachments nativos, com metadados e tamanhos derivados do WordPress. A recriação remove apenas attachments dos demos; a remoção do conteúdo também limpa essas mídias. O recurso depende do suporte GD do PHP e falha de forma segura quando indisponível.

## Diagnóstico e desbloqueio

**Portal Grupos > Diagnóstico** verifica requisitos mínimos de PHP e WordPress, HTTPS, os dois eventos cron essenciais, todas as tabelas próprias e suporte a imagens PNG, além de informar se o Google está configurado sem revelar o secret. Administradores podem remover um rate limit específico informando ação e identificador; o handler recalcula os mesmos HMACs, nunca lista hashes ou IPs armazenados, exige capacidade e nonce e audita apenas o nome da ação desbloqueada.

### Cards e showcase

Os cards usam o CSS de produção em `assets/dist/frontend.css`. O showcase mantém a ordem visual 2–1–3 no desktop, eleva a prioridade 1 e remove a sobreposição em telas menores. A indicação “Link verificado” é exibida somente quando o checker persistiu o estado `active`.

### Importação do conteúdo demo

Em **Portal Grupos > Conteúdo Demo**, a ação **Importar 40 cards e páginas** cria os 40 grupos demonstrativos, suas artes locais e 12 páginas de interface com os shortcodes públicos. A importação é idempotente, não sobrescreve páginas existentes e registra separadamente as páginas criadas para que somente elas sejam removidas pela ação de limpeza.

### Artefato ZIP

O ZIP instalável é um artefato reproduzível e não é versionado no Git. Execute `npm run build` para gerar `build/portal-grupos-whatsapp-1.0.0.zip`; a regra em `.gitignore` evita que o binário seja incluído em commits ou pull requests.

### Modal de autenticação e OTP

`[pgw_entrar]` e `[pgw_criar_conta]` exibem o mesmo modal responsivo com abas **Entrar** e **Cadastre-se**, alterando apenas a aba inicialmente ativa. Depois do cadastro ou login, o fluxo encaminha para `[pgw_confirmar_codigo]`; quando o tema já enviou os headers, há fallback visual e client-side em vez de uma página vazia.

### Dashboard visual

A página principal de **Portal Grupos** apresenta métricas e atalhos dos módulos em uma superfície SaaS glassmorphism isolada por `.pgw-admin`, com Maven Pro em peso 800, layout responsivo e sem alterar o restante do WordPress.

### Catálogo no frontend e caches

Páginas que contêm `[pgw_mostrar_grupos]` no conteúdo ou nos dados do Elementor recebem `DONOTCACHEPAGE` e headers `no-cache`. Ao importar, remover ou recriar demos, o plugin invalida o object cache, limpa o cache de arquivos do Elementor e aciona integrações disponíveis de cache, evitando que o editor mostre cards enquanto o frontend permanece com HTML antigo.

O painel **Conteúdo Demo** também verifica a homepage estática, seu status de publicação e se o shortcode foi efetivamente salvo nos dados do Elementor. Alterações visíveis apenas no preview precisam de **Aplicar** no widget e **Publicar/Atualizar** na página antes de existirem no frontend.

### Auditoria de visibilidade pública

A consulta do catálogo exige `post_status=publish` e aceita `_pgw_status=approved` ou a ausência da meta em grupos publicados legados. O shortcode envia os parâmetros originais uma única vez ao normalizador. Toda publicação, atualização ou decisão de moderação invalida o cache anônimo, incluindo Elementor, LiteSpeed, WP Rocket, W3 Total Cache, WP Super Cache, WP Fastest Cache e SiteGround quando disponíveis.

### Isolamento da consulta do catálogo

As consultas de `[pgw_mostrar_grupos]` carregam a flag interna `pgw_catalog_query`, desativam filtros externos somente nessa consulta e reafirmam `post_type=pgw_group`, `post_status=publish` e sticky posts desligados no final de `pre_get_posts`. Uma defesa adicional descarta qualquer resultado que não seja `pgw_group`, sem interferir nas consultas de blog do tema ou Elementor.

### Cards compactos e header visual

A imagem destacada agora é full-bleed e ocupa toda a largura do topo do card, com alturas de 168px no catálogo, 186px no showcase e 200px no destaque central. Categoria e selo Destaque ficam sobre a imagem em pills translúcidas. Regras com maior especificidade e dimensões explícitas neutralizam o `height:auto` aplicado pelo Elementor, mantendo `object-fit: cover` e o ponto focal configurado.

### Modal de acesso no Elementor

O plugin injeta um modal de acesso oculto no rodapé para visitantes. No Elementor, informe `pgw-open-login` no campo **Classes CSS** do botão para abrir a aba Entrar, ou `pgw-open-register` para abrir diretamente o cadastro. A classe genérica `pgw-open-auth` abre Entrar. O modal preserva a página de origem; em **Meus Grupos**, o visitante retorna automaticamente à mesma página depois do OTP.

O cadastro mantém a conta pendente mesmo quando o servidor não confirma o envio do e-mail, exibindo uma orientação para reenviar o código ou revisar o SMTP. Isso evita excluir silenciosamente a conta recém-criada por uma falha temporária de entrega.

### Cores das categorias

O shortcode `[pgw_categorias limit="10" show_more="1"]` apresenta somente a quantidade definida em `limit`, sem o antigo controle “Ver mais categorias”; o atributo legado é aceito para não quebrar páginas existentes. Cada categoria recebe uma cor neon distinta. Para personalizar, acesse **Portal Grupos → Categorias**, adicione ou edite uma categoria e escolha sua cor no campo **Cor da categoria**. A mesma cor identifica a categoria sobre a imagem dos cards.

### Acesso aos links dos grupos

Todo botão **Entrar no Grupo** exige uma sessão autenticada. Para visitantes, o botão abre o modal unificado e configura o destino do grupo nos formulários de login e cadastro; depois do OTP, o usuário segue ao convite. A rota `/ir/` também aplica essa proteção quando acessada diretamente. Na página individual, o plugin suprime a miniatura duplicada emitida pelo tema, mantém apenas a imagem interna abaixo do título e centraliza o título em Maven Pro ExtraBold.

### Conta no header

Use `[minha-conta-header]` no cabeçalho. Visitantes veem **Entrar / Cadastrar** com atalhos para login, criação e recuperação de senha. Usuários autenticados veem sua foto, nome e um dropdown responsivo com Perfil e nome, Foto, Meus grupos, Enviar grupo, Senha e segurança, Alterar e-mail, Sessões, Exportar dados e Sair. Os formulários de autenticação enviam diretamente ao endpoint `admin-post.php`, evitando que Elementor, cache ou template impeçam o processamento e a transição para o OTP.

### Auditoria do cadastro e OTP

Na versão de banco 5, o upgrade recria de forma idempotente as tabelas de desafios e limites e restaura os papéis `pgw_pending_member` e `pgw_member` quando ausentes. O endpoint de autenticação confirma esse runtime antes de validar o formulário. A criação do desafio OTP somente prossegue quando a inserção no banco é confirmada; se a página `confirmar-codigo` não existir, o fluxo usa a página Entrar e o próprio shortcode renderiza a confirmação ao receber `pgw_flow`. Como páginas do Elementor podem permanecer em cache, o modal também renova por AJAX as provas assinadas de login e cadastro antes do envio, evitando timestamps expirados ou identificadores já consumidos.

### Fluxo OTP e retorno ao grupo

O link de confirmação agora preserva o destino solicitado (`pgw_redirect_to`) e o formulário de 2 fatores mantém esse destino em campo oculto. Após validar o código, o plugin consome o destino pendente do usuário ou usa o fallback assinado da requisição para levar a pessoa ao grupo selecionado. O e-mail do OTP é HTML, usa o logo configurado no tema quando disponível e inclui header, código em destaque e footer.
