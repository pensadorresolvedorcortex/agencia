Blog Privilège AI Premium Final v4.5.0

Instalação:
- O repositório mantém somente os arquivos-fonte para permitir revisão de código e pull requests no GitHub.
- Para instalar, compacte localmente a pasta blog-privilege-ai e envie o arquivo gerado em Plugins > Adicionar plugin > Enviar plugin no WordPress.
- O pacote ZIP compilado não é versionado, evitando arquivos binários incompatíveis com a visualização de alterações do GitHub.

Alterações v4.5.0:
- Novo botão no dashboard envia para a Lixeira todas as postagens do plugin cujo slug termina em número.
- A exclusão é limitada aos posts identificados pelo Blog Privilège; conteúdos externos não são tocados.
- Processamento em lotes de 100 via WP-Cron evita timeout ao tratar milhares de postagens.
- Confirmação explícita, contador de progresso, falhas e itens restantes tornam a operação auditável e recuperável.

Alterações v4.4.0:
- Nova auditoria local de indexabilidade mostra posts analisados, slugs numerados, grupos de títulos/conteúdos repetidos e a visibilidade do WordPress.
- Novo reparador manual corrige os slugs antigos terminados em sufixos como “-2” e “-3”, limitado aos posts gerados pelo plugin.
- Cada alteração de URL passa novamente pelo gerador semântico e pela validação global de colisão.
- O reparo usa wp_update_post para preservar o URL antigo no mecanismo _wp_old_slug e no redirecionamento 301 nativo do WordPress.
- Conteúdos e títulos antigos repetidos são sinalizados para revisão, mas nunca excluídos automaticamente.

Alterações v4.3.0:
- Barreira transacional anti-duplicidade: o post não é publicado se título, slug ou conteúdo já existirem.
- Slugs validados na memória permanente do plugin e em toda a tabela de posts antes da inserção.
- O gerador usa modificadores editoriais com significado; nunca aceita os sufixos automáticos “-2”, “-3” do WordPress.
- Se o WordPress alterar o slug durante a gravação, a operação é revertida imediatamente.
- Assinatura permanente do conteúdo em postmeta, mantendo a verificação eficiente mesmo com grande volume de artigos.
- Após oito tentativas repetidas, a geração falha de forma segura em vez de publicar o último conteúdo duplicado.
- Correção da semente do metadata SEO para eliminar uma variável ainda não inicializada.

Observação sobre indexação:
O plugin elimina colisões sob seu controle e mantém URLs limpas, mas nenhum software pode garantir a indexação: a decisão final é do Google. Depois da publicação, envie o sitemap e acompanhe cobertura, canonical e qualidade no Google Search Console.

Auditoria aplicada antes das alterações:
- Plugin de arquivo único em blog-privilege.php, com uninstall.php e README.txt.
- Hooks: cron_schedules, action bpv_blog_privilege_generate_post, admin_menu, admin_init e plugin_action_links.
- Cron job: bpv_blog_privilege_generate_post a cada 2 minutos via bpv_two_minutes.
- Criação/publicação do artigo: BPV_Blog_Privilege::generate_scheduled_post(), que define tópico, categoria, tags, título, slug, conteúdo, excerpt e chama wp_insert_post() com post_status publish.
- Título editorial: BPV_Blog_Privilege::generate_unique_title().
- Slug: BPV_Blog_Privilege::generate_slug().
- Imagem destacada: BPV_Blog_Privilege::create_featured_image().
- Motor de imagem: prioridade para fotografia real gratuita via Openverse com filtros semânticos; fallback Wikimedia Commons; fallback gratuito via LoremFlickr; fallback curado Unsplash categorizado por web design/UI-UX, programação/desenvolvimento, marketing e branding; mídia local existente antes da IA; IA fotográfica com prompt premium apenas como última alternativa e backoff automático quando houver rate limit.
- Prompt anterior: ai_image_prompt() já tentava fotografia realista, mas não usava briefing visual completo nem validação técnica antes de registrar a mídia.
- Opções preservadas: enabled, topic index, total, last run, last post, last error, content hashes, phrase hashes, title hashes, image log e opções de direção de arte.
- Transient preservado e fortalecido: bpv_blog_privilege_generation_lock agora usa token, expiração curta, liberação no shutdown, botão manual e limpeza automática a cada 15 minutos para destravar gerações órfãs.
- Logs existentes preservados e ampliados com diagnóstico por etapa e memória anti-repetição de imagens por hash real do arquivo, hash físico de attachment e chave durável de origem/foto.

Alterações v4.1.0:
- Novo SEO Engine 4.1 para slugs de 3 a 6 palavras, até 60 caracteres, sem repetição, datas, IDs, hashes e stopwords.
- Metadados SEO separados em _bpv_seo_title e _bpv_meta_description.
- Novo motor visual em camadas: título + resumo + público + categoria + identidade da marca => briefing visual => prompt final.
- Prompt final obrigatório com REALISTIC EDITORIAL PHOTOGRAPHY, REAL PEOPLE, NATURAL HUMAN EXPRESSIONS, PROFESSIONAL BUSINESS ENVIRONMENT, CORPORATE LIFESTYLE PHOTOGRAPHY, CINEMATIC LIGHTING, 35MM CAMERA, SHALLOW DEPTH OF FIELD, HIGH DETAIL e PREMIUM MAGAZINE STYLE.
- Bloqueios explícitos contra cartoon, illustration, vector art, flat design, 3D characters, avatars, fake people, AI looking faces, text inside image, logos e watermarks.
- Validação técnica da imagem antes do registro na mídia: formato JPG/PNG/WebP, proporção editorial, dimensões mínimas e tamanho mínimo.
- Anti-repetição visual reforçado: cada fonte retorna source_key própria (Openverse, Wikimedia, LoremFlickr, Unsplash e Pollinations), o plugin rejeita a origem antes/depois do download e grava também o hash real do arquivo salvo.
- Remoção do fallback Picsum genérico porque ele podia entregar paisagens, plantações e montanhas sem nexo com artigos de marca premium, autoridade digital e negócios modernos.
- Pool Unsplash categorizado por tema do artigo: web design/UI-UX, programming/development, marketing e branding, sempre com fotos de pessoas reais em escritórios/agências.
- Validação OCR opcional via Tesseract quando disponível no servidor: imagens com texto legível excessivo, logos, watermark, stock/sample/copyright são reprovadas antes de virarem destaque.
- Filtro semântico contra imagens desconectadas bloqueia metadados de montanhas, neve, paisagem, fazenda, plantação, agricultura, vinhedo, floresta, praia, rio, animais, flores e ruas/externas genéricas.
- Fallback ilustrativo/local GD removido para impedir imagens cartoon/vetor; o fluxo usa somente fotografia gratuita ou IA fotográfica estritamente bloqueada contra ilustração, com filtro de resultados Openverse contra tags/títulos de illustration, vector, cartoon, avatar, icon, render, logo e text.
- Validação editorial básica do artigo antes do fechamento da geração.
- Painel administrativo ampliado com visual SaaS premium branco glassmorphism, fonte Maven Pro 800, cards compactos para caber em telas de 800px de altura, cores neon por finalidade, preview da última imagem destacada, diagnóstico da última geração, status da trava de geração, botão Liberar trava, Artigo, SEO, Slug, Imagem e Publicação, com erro técnico quando existir.

Compatibilidade:
- Não recria o plugin do zero.
- Não remove painel, cron, geração, histórico, anti-repetição, categorias, publicação WordPress, integração de imagem existente nem configurações atuais.
- Mantém múltiplos fallbacks fotográficos gratuitos para servidores com HTTP disponível, rejeita imagens repetidas já usadas por source_key/hash/attachment físico, reaproveita mídia local existente somente se o arquivo ainda não foi usado quando serviços externos/IA falham, salva a imagem original quando o editor de imagem do WordPress falhar e força _thumbnail_id se set_post_thumbnail não confirmar a capa, evitando o antigo gerador local ilustrativo.
