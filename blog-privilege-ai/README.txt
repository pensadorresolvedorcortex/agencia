Blog Privilège AI Premium Final v4.1.0

Auditoria aplicada antes das alterações:
- Plugin de arquivo único em blog-privilege.php, com uninstall.php e README.txt.
- Hooks: cron_schedules, action bpv_blog_privilege_generate_post, admin_menu, admin_init e plugin_action_links.
- Cron job: bpv_blog_privilege_generate_post a cada 2 minutos via bpv_two_minutes.
- Criação/publicação do artigo: BPV_Blog_Privilege::generate_scheduled_post(), que define tópico, categoria, tags, título, slug, conteúdo, excerpt e chama wp_insert_post() com post_status publish.
- Título editorial: BPV_Blog_Privilege::generate_unique_title().
- Slug: BPV_Blog_Privilege::generate_slug().
- Imagem destacada: BPV_Blog_Privilege::create_featured_image().
- Motor de imagem: prioridade para fotografia real gratuita via Openverse; fallback Wikimedia Commons; fallback gratuito via LoremFlickr; fallback editorial via Picsum; fallback curado Unsplash; mídia local existente antes da IA; IA fotográfica com prompt premium apenas como última alternativa e backoff automático quando houver rate limit.
- Prompt anterior: ai_image_prompt() já tentava fotografia realista, mas não usava briefing visual completo nem validação técnica antes de registrar a mídia.
- Opções preservadas: enabled, topic index, total, last run, last post, last error, content hashes, phrase hashes, title hashes, image log e opções de direção de arte.
- Transient preservado e fortalecido: bpv_blog_privilege_generation_lock agora usa token, expiração curta, liberação no shutdown, botão manual e limpeza automática a cada 15 minutos para destravar gerações órfãs.
- Logs existentes preservados e ampliados com diagnóstico por etapa e memória anti-repetição de imagens por hash/attachment.

Alterações v4.1.0:
- Novo SEO Engine 4.1 para slugs de 3 a 6 palavras, até 60 caracteres, sem repetição, datas, IDs, hashes e stopwords.
- Metadados SEO separados em _bpv_seo_title e _bpv_meta_description.
- Novo motor visual em camadas: título + resumo + público + categoria + identidade da marca => briefing visual => prompt final.
- Prompt final obrigatório com REALISTIC EDITORIAL PHOTOGRAPHY, REAL PEOPLE, NATURAL HUMAN EXPRESSIONS, PROFESSIONAL BUSINESS ENVIRONMENT, CORPORATE LIFESTYLE PHOTOGRAPHY, CINEMATIC LIGHTING, 35MM CAMERA, SHALLOW DEPTH OF FIELD, HIGH DETAIL e PREMIUM MAGAZINE STYLE.
- Bloqueios explícitos contra cartoon, illustration, vector art, flat design, 3D characters, avatars, fake people, AI looking faces, text inside image, logos e watermarks.
- Validação técnica da imagem antes do registro na mídia: formato JPG/PNG/WebP, proporção editorial, dimensões mínimas e tamanho mínimo.
- Fallback ilustrativo/local GD removido para impedir imagens cartoon/vetor; o fluxo usa somente fotografia gratuita ou IA fotográfica estritamente bloqueada contra ilustração, com filtro de resultados Openverse contra tags/títulos de illustration, vector, cartoon, avatar, icon, render, logo e text.
- Validação editorial básica do artigo antes do fechamento da geração.
- Painel administrativo ampliado com visual SaaS premium branco glassmorphism, fonte Maven Pro 800, cards compactos para caber em telas de 800px de altura, cores neon por finalidade, preview da última imagem destacada, diagnóstico da última geração, status da trava de geração, botão Liberar trava, Artigo, SEO, Slug, Imagem e Publicação, com erro técnico quando existir.

Compatibilidade:
- Não recria o plugin do zero.
- Não remove painel, cron, geração, histórico, anti-repetição, categorias, publicação WordPress, integração de imagem existente nem configurações atuais.
- Mantém múltiplos fallbacks fotográficos gratuitos para servidores com HTTP disponível, rejeita imagens repetidas já usadas por hash/attachment, reaproveita mídia local existente quando serviços externos/IA falham, salva a imagem original quando o editor de imagem do WordPress falhar e força _thumbnail_id se set_post_thumbnail não confirmar a capa, evitando o antigo gerador local ilustrativo.
