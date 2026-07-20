Blog Privilège AI Premium Final v4.1.0

Auditoria aplicada antes das alterações:
- Plugin de arquivo único em blog-privilege.php, com uninstall.php e README.txt.
- Hooks: cron_schedules, action bpv_blog_privilege_generate_post, admin_menu, admin_init e plugin_action_links.
- Cron job: bpv_blog_privilege_generate_post a cada 2 minutos via bpv_two_minutes.
- Criação/publicação do artigo: BPV_Blog_Privilege::generate_scheduled_post(), que define tópico, categoria, tags, título, slug, conteúdo, excerpt e chama wp_insert_post() com post_status publish.
- Título editorial: BPV_Blog_Privilege::generate_unique_title().
- Slug: BPV_Blog_Privilege::generate_slug().
- Imagem destacada: BPV_Blog_Privilege::create_featured_image().
- Motor de imagem: try_ai_contextual_photo() usando Pollinations; fallback Openverse em try_openverse_human_photo(); fallback local GD em render_humanized_local_image().
- Prompt anterior: ai_image_prompt() já tentava fotografia realista, mas não usava briefing visual completo nem validação técnica antes de registrar a mídia.
- Opções preservadas: enabled, topic index, total, last run, last post, last error, content hashes, phrase hashes, title hashes, image log e opções de direção de arte.
- Transient preservado: bpv_blog_privilege_generation_lock.
- Logs existentes preservados e ampliados com diagnóstico por etapa.

Alterações v4.1.0:
- Novo SEO Engine 4.1 para slugs de 3 a 6 palavras, até 60 caracteres, sem repetição, datas, IDs, hashes e stopwords.
- Metadados SEO separados em _bpv_seo_title e _bpv_meta_description.
- Novo motor visual em camadas: título + resumo + público + categoria + identidade da marca => briefing visual => prompt final.
- Prompt final obrigatório com REALISTIC EDITORIAL PHOTOGRAPHY, REAL PEOPLE, NATURAL HUMAN EXPRESSIONS, PROFESSIONAL BUSINESS ENVIRONMENT, CORPORATE LIFESTYLE PHOTOGRAPHY, CINEMATIC LIGHTING, 35MM CAMERA, SHALLOW DEPTH OF FIELD, HIGH DETAIL e PREMIUM MAGAZINE STYLE.
- Bloqueios explícitos contra cartoon, illustration, vector art, flat design, 3D characters, avatars, fake people, AI looking faces, text inside image, logos e watermarks.
- Validação técnica da imagem antes do registro na mídia: formato, dimensões editoriais e tamanho mínimo.
- Até 3 tentativas de geração por IA antes dos fallbacks preservados.
- Validação editorial básica do artigo antes do fechamento da geração.
- Painel administrativo ampliado com diagnóstico da última geração: Artigo, SEO, Slug, Imagem e Publicação, com erro técnico quando existir.

Compatibilidade:
- Não recria o plugin do zero.
- Não remove painel, cron, geração, histórico, anti-repetição, categorias, publicação WordPress, integração de imagem existente nem configurações atuais.
- Mantém fallbacks para servidores sem resposta externa ou sem GD.
