<?php
/**
 * Plugin Name: Blog Privilége
 * Plugin URI: https://example.com/blog-privilege
 * Description: Cria automaticamente artigos de blog sobre sites, SEO, marketing digital, marcas, design, e-commerce e naming com imagem destacada realista gerada por IA.
 * Version: 1.2.0
 * Author: Blog Privilége
 * License: GPLv2 or later
 * Text Domain: blog-privilege
 */

if (!defined('ABSPATH')) {
    exit;
}

final class BPV_Blog_Privilege {
    const VERSION               = '1.2.0';
    const CRON_HOOK             = 'bpv_blog_privilege_generate_post';
    const OPTION_ENABLED        = 'bpv_blog_privilege_enabled';
    const OPTION_INDEX          = 'bpv_blog_privilege_topic_index';
    const OPTION_TOTAL          = 'bpv_blog_privilege_total_posts';
    const OPTION_LAST_RUN       = 'bpv_blog_privilege_last_run';
    const OPTION_LAST_POST      = 'bpv_blog_privilege_last_post_id';
    const OPTION_LAST_ERR       = 'bpv_blog_privilege_last_error';
    const OPTION_CONTENT_HASHES = 'bpv_blog_privilege_content_hashes';
    const OPTION_PHRASE_HASHES  = 'bpv_blog_privilege_phrase_hashes';
    const OPTION_TITLE_HASHES   = 'bpv_blog_privilege_title_hashes';
    const OPTION_IMAGE_LOG      = 'bpv_blog_privilege_image_log';
    const LOCK_KEY              = 'bpv_blog_privilege_generation_lock';

    public static function init() {
        add_filter('cron_schedules', array(__CLASS__, 'add_cron_interval'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'generate_scheduled_post'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'handle_admin_actions'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'plugin_action_links'));
    }

    public static function activate() {
        self::ensure_terms();

        if (get_option(self::OPTION_ENABLED, null) === null) {
            add_option(self::OPTION_ENABLED, 'yes');
        }
        if (get_option(self::OPTION_INDEX, null) === null) {
            add_option(self::OPTION_INDEX, 0);
        }
        if (get_option(self::OPTION_TOTAL, null) === null) {
            add_option(self::OPTION_TOTAL, 0);
        }
        if (get_option(self::OPTION_CONTENT_HASHES, null) === null) {
            add_option(self::OPTION_CONTENT_HASHES, array(), '', false);
        }
        if (get_option(self::OPTION_PHRASE_HASHES, null) === null) {
            add_option(self::OPTION_PHRASE_HASHES, array(), '', false);
        }
        if (get_option(self::OPTION_TITLE_HASHES, null) === null) {
            add_option(self::OPTION_TITLE_HASHES, array(), '', false);
        }
        if (get_option(self::OPTION_IMAGE_LOG, null) === null) {
            add_option(self::OPTION_IMAGE_LOG, array(), '', false);
        }

        self::schedule_event();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        delete_transient(self::LOCK_KEY);
    }

    public static function add_cron_interval($schedules) {
        $schedules['bpv_two_minutes'] = array(
            'interval' => 120,
            'display'  => 'A cada 2 minutos - Blog Privilége',
        );
        return $schedules;
    }

    private static function schedule_event() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 120, 'bpv_two_minutes', self::CRON_HOOK);
        }
    }

    private static function unschedule_event() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function plugin_action_links($links) {
        $url = admin_url('admin.php?page=blog-privilege');
        array_unshift($links, '<a href="' . esc_url($url) . '">Painel</a>');
        return $links;
    }

    public static function admin_menu() {
        add_menu_page(
            'Blog Privilége',
            'Blog Privilége',
            'manage_options',
            'blog-privilege',
            array(__CLASS__, 'render_admin_page'),
            'dashicons-welcome-write-blog',
            58
        );
    }

    public static function handle_admin_actions() {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        if (empty($_POST['bpv_blog_privilege_action'])) {
            return;
        }

        check_admin_referer('bpv_blog_privilege_action', 'bpv_blog_privilege_nonce');
        $action = sanitize_key(wp_unslash($_POST['bpv_blog_privilege_action']));

        if ($action === 'generate_now') {
            $post_id = self::generate_scheduled_post(true);
            if (is_wp_error($post_id)) {
                add_settings_error('bpv_blog_privilege', 'bpv_error', $post_id->get_error_message(), 'error');
            } else {
                add_settings_error('bpv_blog_privilege', 'bpv_success', 'Post criado com sucesso. ID: ' . absint($post_id), 'updated');
            }
        }

        if ($action === 'pause') {
            update_option(self::OPTION_ENABLED, 'no');
            self::unschedule_event();
            add_settings_error('bpv_blog_privilege', 'bpv_paused', 'Geração automática pausada.', 'updated');
        }

        if ($action === 'resume') {
            update_option(self::OPTION_ENABLED, 'yes');
            self::schedule_event();
            add_settings_error('bpv_blog_privilege', 'bpv_resumed', 'Geração automática retomada.', 'updated');
        }

        if ($action === 'reschedule') {
            self::unschedule_event();
            self::schedule_event();
            add_settings_error('bpv_blog_privilege', 'bpv_rescheduled', 'Agendamento recriado para execução a cada 2 minutos.', 'updated');
        }

        if ($action === 'clear_history') {
            update_option(self::OPTION_CONTENT_HASHES, array(), false);
            update_option(self::OPTION_PHRASE_HASHES, array(), false);
            update_option(self::OPTION_TITLE_HASHES, array(), false);
            update_option(self::OPTION_IMAGE_LOG, array(), false);
            add_settings_error('bpv_blog_privilege', 'bpv_cleared', 'Histórico de anti-repetição limpo.', 'updated');
        }
    }

    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        self::ensure_terms();

        $enabled    = get_option(self::OPTION_ENABLED, 'yes');
        $total      = absint(get_option(self::OPTION_TOTAL, 0));
        $index      = absint(get_option(self::OPTION_INDEX, 0));
        $last_run   = get_option(self::OPTION_LAST_RUN, 'Nunca');
        $last_post  = absint(get_option(self::OPTION_LAST_POST, 0));
        $last_error = get_option(self::OPTION_LAST_ERR, '');
        $next       = wp_next_scheduled(self::CRON_HOOK);
        $topics     = self::topics();
        $next_topic = isset($topics[$index % count($topics)]) ? $topics[$index % count($topics)] : $topics[0];
        $gd_status  = function_exists('imagecreatetruecolor') ? 'Disponível' : 'Indisponível';
        $curl       = function_exists('wp_remote_get') ? 'Disponível' : 'Indisponível';
        $history    = is_array(get_option(self::OPTION_CONTENT_HASHES, array())) ? count(get_option(self::OPTION_CONTENT_HASHES, array())) : 0;
        $image_log  = get_option(self::OPTION_IMAGE_LOG, array());
        $last_image = is_array($image_log) && !empty($image_log) ? end($image_log) : 'Nenhuma imagem gerada ainda';

        echo '<div class="wrap">';
        echo '<h1>Blog Privilége</h1>';
        settings_errors('bpv_blog_privilege');

        echo '<div style="max-width: 1040px; background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:22px; margin-top:18px;">';
        echo '<h2>Status do gerador automático</h2>';
        echo '<table class="widefat striped" style="max-width: 900px;">';
        echo '<tbody>';
        echo '<tr><th>Status</th><td>' . esc_html($enabled === 'yes' ? 'Ativo' : 'Pausado') . '</td></tr>';
        echo '<tr><th>Frequência</th><td>A cada 2 minutos, usando WP-Cron</td></tr>';
        echo '<tr><th>Próxima execução</th><td>' . esc_html($next ? wp_date('d/m/Y H:i:s', $next) : 'Não agendada') . '</td></tr>';
        echo '<tr><th>Próximo tema</th><td>' . esc_html($next_topic) . '</td></tr>';
        echo '<tr><th>Total de posts gerados</th><td>' . esc_html($total) . '</td></tr>';
        echo '<tr><th>Última execução</th><td>' . esc_html($last_run) . '</td></tr>';
        echo '<tr><th>Último post</th><td>' . ($last_post ? '<a href="' . esc_url(get_edit_post_link($last_post)) . '">Editar post #' . esc_html($last_post) . '</a>' : 'Nenhum') . '</td></tr>';
        echo '<tr><th>Anti-repetição</th><td>' . esc_html($history) . ' assinaturas de conteúdo monitoradas</td></tr>';
        echo '<tr><th>Imagem</th><td>Geração por IA contextual realista + fotos humanizadas de apoio + fallback local. GD: ' . esc_html($gd_status) . ' / HTTP: ' . esc_html($curl) . '</td></tr>';
        echo '<tr><th>Última imagem</th><td>' . esc_html($last_image) . '</td></tr>';
        echo '<tr><th>Último erro</th><td>' . esc_html($last_error ? $last_error : 'Nenhum') . '</td></tr>';
        echo '</tbody>';
        echo '</table>';

        echo '<form method="post" style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">';
        wp_nonce_field('bpv_blog_privilege_action', 'bpv_blog_privilege_nonce');
        echo '<button class="button button-primary" name="bpv_blog_privilege_action" value="generate_now">Gerar post agora</button>';
        if ($enabled === 'yes') {
            echo '<button class="button" name="bpv_blog_privilege_action" value="pause">Pausar automático</button>';
        } else {
            echo '<button class="button" name="bpv_blog_privilege_action" value="resume">Retomar automático</button>';
        }
        echo '<button class="button" name="bpv_blog_privilege_action" value="reschedule">Recriar agendamento</button>';
        echo '<button class="button" name="bpv_blog_privilege_action" value="clear_history">Limpar histórico anti-repetição</button>';
        echo '</form>';

        echo '<hr style="margin:24px 0;">';
        echo '<h2>Regras aplicadas nesta versão</h2>';
        echo '<p>O plugin gera posts publicados automaticamente com os novos temas de criação de sites, SEO, marketing digital, identidade visual, rebranding, design gráfico, e-commerce e naming. Mantém título de até 50 caracteres, slug longo, conteúdo entre 3.000 e 5.000 caracteres, categoria entre Notícias, Tendências ou Dicas, 30 tags e imagem destacada 1920x1080.</p>';
        echo '<p>O motor editorial usa sementes, ângulos, públicos, cenários, estruturas e validação por hash para reduzir repetição de títulos, frases longas e conteúdos completos.</p>';
        echo '<p>As imagens priorizam cenas realistas geradas por IA a partir do título e do tema da postagem. Caso a IA externa falhe, o plugin tenta fotografias humanizadas de apoio e, por último, gera uma imagem local em 1920x1080, sem ícones e sem textos.</p>';
        echo '</div>';
        echo '</div>';
    }

    public static function generate_scheduled_post($force = false) {
        if (!$force && get_option(self::OPTION_ENABLED, 'yes') !== 'yes') {
            return new WP_Error('bpv_disabled', 'A geração automática está pausada.');
        }

        if (get_transient(self::LOCK_KEY)) {
            return new WP_Error('bpv_locked', 'Já existe uma geração em andamento.');
        }

        set_transient(self::LOCK_KEY, 1, 115);

        self::ensure_terms();

        $topics = self::topics();
        $index  = absint(get_option(self::OPTION_INDEX, 0));
        $total  = absint(get_option(self::OPTION_TOTAL, 0));
        $topic  = $topics[$index % count($topics)];
        $seed   = self::seed($topic, $total, 0);

        $category_name = self::category_for_topic($topic);
        $category_id   = self::get_category_id($category_name);
        $tags          = self::tags_for_topic($topic, $total);
        $title         = self::generate_unique_title($topic, $seed, $total);
        $slug          = self::generate_slug($topic, $title, $category_name, $total, $seed);
        $content       = '';
        $attempt_seed  = $seed;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $attempt_seed = self::seed($topic, $total, $attempt);
            $content = self::generate_content($topic, $category_name, $tags, $title, $attempt_seed, $total + $attempt);
            if (!self::content_was_used($content)) {
                break;
            }
        }

        $excerpt = self::generate_excerpt($topic, $attempt_seed);

        $post_data = array(
            'post_title'    => $title,
            'post_name'     => $slug,
            'post_content'  => $content,
            'post_excerpt'  => $excerpt,
            'post_status'   => 'publish',
            'post_type'     => 'post',
            'post_author'   => self::get_author_id(),
            'post_category' => array($category_id),
            'meta_input'    => array(
                '_bpv_blog_privilege_topic'   => $topic,
                '_bpv_blog_privilege_seed'    => $attempt_seed,
                '_bpv_blog_privilege_version' => self::VERSION,
            ),
        );

        $post_id = wp_insert_post(wp_slash($post_data), true);

        if (is_wp_error($post_id)) {
            update_option(self::OPTION_LAST_ERR, $post_id->get_error_message());
            delete_transient(self::LOCK_KEY);
            return $post_id;
        }

        wp_set_post_tags($post_id, $tags, false);
        self::remember_title($title);
        self::remember_content($content);

        $image_result = self::create_featured_image($post_id, $topic, $category_name, $title, $attempt_seed);
        if (is_wp_error($image_result)) {
            update_option(self::OPTION_LAST_ERR, 'Post criado, mas a imagem não foi gerada: ' . $image_result->get_error_message());
        } else {
            update_option(self::OPTION_LAST_ERR, '');
        }

        update_option(self::OPTION_INDEX, ($index + 1) % count($topics));
        update_option(self::OPTION_TOTAL, $total + 1);
        update_option(self::OPTION_LAST_RUN, wp_date('d/m/Y H:i:s'));
        update_option(self::OPTION_LAST_POST, $post_id);

        delete_transient(self::LOCK_KEY);
        return $post_id;
    }

    private static function ensure_terms() {
        foreach (array('Notícias', 'Tendências', 'Dicas') as $category) {
            if (!term_exists($category, 'category')) {
                wp_insert_term($category, 'category');
            }
        }
    }

    private static function get_category_id($name) {
        $term = get_term_by('name', $name, 'category');
        if (!$term) {
            $created = wp_insert_term($name, 'category');
            if (!is_wp_error($created) && !empty($created['term_id'])) {
                return absint($created['term_id']);
            }
            return 1;
        }
        return absint($term->term_id);
    }

    private static function get_author_id() {
        $users = get_users(array(
            'role__in' => array('administrator', 'editor', 'author'),
            'number'   => 1,
            'orderby'  => 'ID',
            'order'    => 'ASC',
            'fields'   => array('ID'),
        ));
        if (!empty($users[0]->ID)) {
            return absint($users[0]->ID);
        }
        return 1;
    }

    private static function topics() {
        return array(
            'Planejamento estratégico de sites',
            'Arquitetura e estrutura de páginas',
            'Experiência do usuário (UX)',
            'Interface e design de sites (UI)',
            'Sites institucionais',
            'Sites corporativos',
            'Sites profissionais para pequenas empresas',
            'Sites de alta conversão',
            'Landing pages',
            'Otimização de sites existentes',
            'Reformulação de sites antigos',
            'Velocidade e performance de sites',
            'Segurança de sites',
            'Responsividade para dispositivos móveis',
            'Jornada do cliente dentro do site',
            'Fundamentos de SEO',
            'SEO para empresas locais',
            'SEO técnico',
            'SEO de conteúdo',
            'Pesquisa de palavras-chave',
            'Google e posicionamento orgânico',
            'Autoridade digital',
            'Conteúdo estratégico para busca',
            'Erros comuns de SEO',
            'Otimização de páginas existentes',
            'Google Search Console',
            'Google Meu Negócio / Perfil da Empresa',
            'Estratégias para aparecer antes dos concorrentes',
            'Tráfego orgânico',
            'Marketing de busca',
            'Estratégias digitais para empresas',
            'Presença digital profissional',
            'Funil de vendas digital',
            'Geração de leads',
            'Conversão de clientes pela internet',
            'Marketing de conteúdo',
            'Estratégia de canais digitais',
            'Planejamento digital',
            'Métricas e análise de resultados',
            'Erros de marketing digital',
            'Posicionamento online',
            'Autoridade de marca na internet',
            'Comunicação digital empresarial',
            'Aquisição de clientes',
            'Escalabilidade através do digital',
            'Construção de marcas',
            'Estratégia de marca (branding)',
            'Identidade visual corporativa',
            'Elementos visuais da marca',
            'Psicologia das cores',
            'Tipografia e percepção de marca',
            'Posicionamento de marca',
            'Personalidade da marca',
            'Consistência visual',
            'Marcas premium',
            'Percepção de valor',
            'Diferenciação no mercado',
            'Confiança através da marca',
            'Experiência de marca',
            'Gestão da identidade visual',
            'Rebranding',
            'Atualização de marcas antigas',
            'Evolução da identidade visual',
            'Mudança de posicionamento',
            'Expansão de empresas e marcas',
            'Recuperação da percepção da marca',
            'Modernização visual',
            'Erros de marcas desatualizadas',
            'Estratégia antes do redesign',
            'Transformação de marcas',
            'Design estratégico',
            'Comunicação visual',
            'Materiais institucionais',
            'Apresentações empresariais',
            'Criativos para campanhas',
            'Design para redes sociais',
            'Design comercial',
            'Hierarquia visual',
            'Composição gráfica',
            'Design profissional versus amador',
            'Estratégia para lojas virtuais',
            'Estrutura de e-commerce',
            'Experiência de compra online',
            'Conversão em lojas virtuais',
            'Jornada do consumidor no e-commerce',
            'Confiança e credibilidade online',
            'Otimização de vendas',
            'Fotografias e apresentação de produtos',
            'Checkout e abandono de carrinho',
            'Integração de sistemas de venda',
            'Criação de nomes de empresas',
            'Estratégia de naming',
            'Identidade verbal',
            'Diferenciação através do nome',
            'Memorização de marcas',
            'Arquitetura de nomes',
            'Posicionamento através da linguagem',
            'Registro e proteção de nomes',
            'Tom de voz da marca',
            'Construção de nomes fortes',
        );
    }

    private static function category_for_topic($topic) {
        $t = self::lower($topic);
        $tendencias = array('tendências', 'inteligência artificial', 'web design', 'design', 'ux/ui', 'experiência do usuário', 'mobile first', 'layouts responsivos', 'marketplaces digitais', 'produtos digitais', 'social media', 'instagram', 'facebook', 'branding', 'identidade visual');
        foreach ($tendencias as $needle) {
            if (strpos($t, $needle) !== false) {
                return 'Tendências';
            }
        }
        $noticias = array('google ads', 'meta ads', 'analytics', 'search console', 'plugins', 'woocommerce', 'wordpress', 'dropshipping', 'hospedagem', 'domínios');
        foreach ($noticias as $needle) {
            if (strpos($t, $needle) !== false) {
                return 'Notícias';
            }
        }
        return 'Dicas';
    }

    private static function compact_topic($topic) {
        $topic = self::normalize_spaces($topic);
        if (self::strlen($topic) <= 34) {
            return $topic;
        }

        $replacements = array(
            'Planejamento estratégico' => 'Planejamento',
            'Experiência do usuário' => 'UX',
            'Interface e design' => 'UI e design',
            'dispositivos móveis' => 'mobile',
            'Google Meu Negócio / Perfil da Empresa' => 'Perfil da Empresa',
            'Estratégias para aparecer antes dos concorrentes' => 'Aparecer antes dos concorrentes',
            'Estratégias digitais para empresas' => 'Estratégias digitais',
            'Identidade visual corporativa' => 'Identidade visual',
            'Tipografia e percepção de marca' => 'Tipografia de marca',
            'Design profissional versus amador' => 'Design profissional',
            'Fotografias e apresentação de produtos' => 'Fotos de produtos',
            'Posicionamento através da linguagem' => 'Linguagem de marca',
        );

        foreach ($replacements as $from => $to) {
            if ($topic === $from) {
                return $to;
            }
        }

        return self::truncate_text($topic, 34);
    }

    private static function generate_unique_title($topic, $seed, $total) {
        $base = self::compact_topic($topic);
        $prefixes = array('Guia de', 'Como usar', 'Estratégia de', 'O valor de', 'Como melhorar', 'Plano de', 'Tendência em', 'Caminho para');
        $suffixes = array('na prática', 'com mais clareza', 'para vender mais', 'com foco em resultado', 'sem complicação', 'para crescer', 'com estratégia', 'que gera confiança', 'para negócios', 'com alto impacto', 'sem desperdício', 'que atrai clientes');

        $candidates = array();
        $candidates[] = $base;
        for ($i = 0; $i < 16; $i++) {
            $candidates[] = self::pick($prefixes, $seed, $i) . ' ' . $base;
            $candidates[] = $base . ' ' . self::pick($suffixes, $seed, $i + 20);
        }

        $used = get_option(self::OPTION_TITLE_HASHES, array());
        if (!is_array($used)) {
            $used = array();
        }

        foreach ($candidates as $candidate) {
            $candidate = self::normalize_spaces($candidate);
            $candidate = self::limit_text($candidate, 50);
            if (self::strlen($candidate) > 50 || self::strlen($candidate) < 12) {
                continue;
            }
            $hash = md5(self::lower(remove_accents($candidate)));
            if (!isset($used[$hash])) {
                return $candidate;
            }
        }

        $numbered = self::limit_text($base . ' ' . (($total % 97) + 1), 50);
        return self::normalize_spaces($numbered);
    }

    private static function generate_slug($topic, $title, $category, $total, $seed) {
        $slug = $topic . ' ' . $title . ' guia estratégico completo para negócios digitais conversão SEO marketing performance experiência do usuário ' . $category . ' ' . gmdate('YmdHi') . ' ' . absint($total) . ' ' . substr(md5($seed), 0, 8);
        return sanitize_title($slug);
    }

    private static function generate_excerpt($topic, $seed) {
        $openers = array(
            'Veja uma análise prática para transformar presença digital em oportunidade comercial.',
            'Entenda como ajustar estratégia, conteúdo e experiência para atrair visitantes melhores.',
            'Descubra pontos de decisão que ajudam empresas a vender com mais clareza no digital.',
            'Confira caminhos objetivos para melhorar percepção de valor, tráfego e conversão online.',
        );
        return self::normalize_spaces(self::pick($openers, $seed, 9) . ' Tema: ' . $topic . '.');
    }

    private static function generate_content($topic, $category, $tags, $title, $seed, $serial) {
        $profile     = self::topic_profile($topic);
        $audience    = self::pick(self::audiences(), $seed, 1);
        $moment      = self::pick(self::business_moments(), $seed, 2);
        $scenario    = self::pick(self::scenarios(), $seed, 3);
        $metric      = self::pick($profile['metrics'], $seed, 4);
        $objection   = self::pick($profile['objections'], $seed, 5);
        $asset       = self::pick($profile['assets'], $seed, 6);
        $verb        = self::pick(array('organizar', 'validar', 'reposicionar', 'simplificar', 'priorizar', 'refinar', 'conectar', 'acelerar'), $seed, 7);
        $tone        = self::pick(array('direto', 'consultivo', 'comercial', 'analítico', 'editorial', 'estratégico'), $seed, 8);
        $opening     = self::pick(self::opening_patterns(), $seed, 10);
        $insight     = self::pick($profile['insights'], $seed, 11);
        $mistake     = self::pick($profile['mistakes'], $seed, 12);
        $opportunity = self::pick($profile['opportunities'], $seed, 13);
        $proof       = self::pick($profile['proofs'], $seed, 14);
        $action      = self::pick($profile['actions'], $seed, 15);
        $unique_ref  = 'Leitura editorial ' . (($serial % 31) + 1) . ', foco ' . self::pick(array('aquisição', 'retenção', 'posicionamento', 'vendas', 'usabilidade', 'autoridade'), $seed, 16) . '.';
        $bridge     = self::pick(array(
            'Com esse raciocínio, o artigo deixa de soar genérico e passa a orientar uma escolha concreta, com começo, contexto e próximo passo.',
            'Quando essa lógica entra na pauta, a publicação ganha função comercial e não fica presa a uma explicação comum sobre o tema.',
            'Esse enquadramento ajuda o leitor a enxergar utilidade imediata, em vez de apenas consumir uma lista de conceitos parecidos.',
            'A força do conteúdo aparece quando cada bloco reduz uma dúvida específica e aproxima o visitante de uma decisão possível.',
        ), $seed, 101);
        $metric_reading = self::pick(array(
            'A leitura desse dado separa curiosidade de oportunidade e mostra onde a comunicação precisa ser mais convincente.',
            'Essa métrica evita decisões por impressão visual e revela se o caminho digital está realmente aproximando pessoas qualificadas.',
            'Com esse acompanhamento, a empresa entende se o post gera apenas visitas ou se contribui para conversas comerciais melhores.',
            'O número funciona como termômetro para ajustar promessa, navegação e profundidade do conteúdo sem depender de achismo.',
        ), $seed, 102);
        $recorte_phrase = self::pick(array(
            'Em vez de repetir conceitos amplos, escolha uma dor específica, uma promessa verificável e um exemplo que converse com a rotina do cliente.',
            'Para escapar do lugar-comum, transforme o assunto em uma situação prática, com problema reconhecível e consequência clara.',
            'O texto ganha força quando troca definições abertas por uma análise aplicada ao momento de compra, pesquisa ou comparação.',
            'A melhor saída é tratar o tema como decisão: o que avaliar, qual risco evitar e qual benefício deve ficar evidente.',
        ), $seed, 103);
        $tone_phrase = self::pick(array(
            'Mostre o que muda antes e depois da melhoria, quais sinais indicam problema e qual ajuste deve vir primeiro.',
            'Traga critérios simples, exemplos plausíveis e uma explicação que pareça escrita para alguém prestes a decidir.',
            'Conduza a leitura por perguntas objetivas, provas de confiança e pequenas escolhas que reduzem incerteza.',
            'Organize a narrativa para que o visitante entenda valor, risco, benefício e ação sem precisar decifrar termos técnicos.',
        ), $seed, 104);
        $mistake_context = self::pick(array(
            'O problema cresce porque a falha parece discreta no começo, mas depois enfraquece a clareza e empurra a marca para uma disputa por preço.',
            'A consequência não aparece apenas no visual: ela atinge confiança, posicionamento e qualidade das oportunidades geradas.',
            'Esse tipo de deslize diminui relevância porque o leitor encontra pouca diferença entre uma empresa e outra.',
            'Quando isso acontece, a marca perde chance de explicar valor e deixa o usuário formar conclusões sozinho.',
        ), $seed, 105);
        $click_phrase = self::pick(array(
            'O título curto abre a porta, mas a entrega precisa sustentar a promessa com uma imagem humanizada, uma tese clara e uma estrutura útil.',
            'Um clique nasce da curiosidade, porém a permanência depende de conteúdo específico, visual crível e leitura sem aparência automática.',
            'Para gerar interesse sem exagero, conecte promessa, contexto e benefício já nas primeiras linhas do artigo.',
            'A publicação fica mais forte quando parece uma orientação de consultoria, não uma peça montada apenas para preencher calendário.',
        ), $seed, 106);
        $follow_phrase = self::pick(array(
            'Depois disso, acompanhe comportamento, origem de tráfego e conversões para evoluir com base em sinais reais.',
            'Na sequência, observe a resposta do público e ajuste a próxima publicação a partir dos dados encontrados.',
            'O ciclo fica mais eficiente quando cada novo post aproveita o que métricas, comentários e dúvidas já revelaram.',
            'A melhoria contínua ganha força quando a empresa testa pequenos ajustes antes de fazer mudanças maiores.',
        ), $seed, 107);

        $html  = '<p>' . esc_html(sprintf($opening, $topic, $audience, $moment)) . '</p>';
        $html .= '<p>' . esc_html('Nesta abordagem, o ponto de partida não é preencher o blog com palavras-chave soltas. O objetivo é ' . $verb . ' a jornada de quem pesquisa, compara e decide. Para ' . $audience . ', ' . $topic . ' precisa aparecer como solução aplicada a uma situação real: ' . $scenario . '.') . '</p>';

        $html .= '<h2>' . esc_html(self::pick(array('Por que este assunto muda a percepção da marca', 'Onde a estratégia começa a gerar valor', 'O que separa presença digital de presença útil', 'Como transformar atenção em decisão'), $seed, 17)) . '</h2>';
        $html .= '<p>' . esc_html($insight . ' ' . $bridge) . '</p>';
        $html .= '<p>' . esc_html('O indicador que merece atenção aqui é ' . $metric . '. ' . $metric_reading) . '</p>';

        $html .= '<h2>' . esc_html(self::pick(array('Como aplicar sem cair no conteúdo repetitivo', 'Um método simples para deixar o artigo mais forte', 'Como criar uma peça que merece ser lida', 'O caminho para fugir do óbvio'), $seed, 18)) . '</h2>';
        $html .= '<p>' . esc_html('Uma publicação sobre ' . $topic . ' precisa ter recorte. ' . $recorte_phrase . ' O tema ganha relevância quando explica uma decisão que o leitor está prestes a tomar.') . '</p>';
        $html .= '<p>' . esc_html('Para manter o texto com tom ' . $tone . ', use frases que respondam dúvidas concretas. ' . $tone_phrase . ' Esse formato aumenta permanência, reduz rejeição e deixa o post mais útil para busca orgânica.') . '</p>';

        $html .= '<h2>' . esc_html(self::pick(array('Pontos que merecem revisão no projeto', 'Critérios que ajudam a decidir melhor', 'Detalhes que interferem na conversão', 'Ajustes práticos para aumentar confiança'), $seed, 19)) . '</h2>';
        $items = self::checklist_items($topic, $seed, $profile, $asset, $objection, $proof, $action);
        $html .= '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . esc_html($item) . '</li>';
        }
        $html .= '</ul>';

        $html .= '<h2>' . esc_html(self::pick(array('Erros que deixam a página fraca', 'O que costuma travar o resultado', 'Riscos silenciosos na estratégia', 'Sinais de que o projeto precisa evoluir'), $seed, 20)) . '</h2>';
        $html .= '<p>' . esc_html($mistake . ' ' . $mistake_context) . '</p>';
        $html .= '<p>' . esc_html('Outro ponto crítico é ignorar a objeção principal: ' . $objection . '. Se o conteúdo não responde esse bloqueio, o visitante tende a adiar a decisão. Uma boa estrutura antecipa dúvidas, mostra critérios e oferece um caminho seguro para avançar.') . '</p>';

        $html .= '<h2>' . esc_html(self::pick(array('Como tornar o conteúdo mais clicável', 'Como criar interesse sem exagero', 'Como aumentar leitura e ação', 'Como aproximar conteúdo e venda'), $seed, 21)) . '</h2>';
        $html .= '<p>' . esc_html($click_phrase) . '</p>';
        $html .= '<p>' . esc_html($opportunity . ' Esse é o tipo de oportunidade que transforma ' . $topic . ' em ativo comercial. O post pode atrair tráfego, educar o público, reforçar autoridade e preparar o contato para uma conversa mais objetiva com a empresa.') . '</p>';

        $html .= '<h2>' . esc_html(self::pick(array('Próximo passo recomendado', 'Como colocar em prática agora', 'A decisão que vem antes da ferramenta', 'O ajuste mais inteligente para começar'), $seed, 22)) . '</h2>';
        $html .= '<p>' . esc_html($action . ' ' . $follow_phrase) . '</p>';
        $html .= '<p>' . esc_html($unique_ref . ' Em resumo, ' . $topic . ' funciona melhor quando nasce de uma pergunta comercial clara: como facilitar a decisão do usuário e mostrar valor antes que ele procure outra opção? Quando essa pergunta guia o conteúdo, cada publicação ganha mais utilidade, mais diferenciação e mais chance de atrair cliques qualificados.') . '</p>';

        $html = self::fit_content_length($html, $topic, $seed);
        return $html;
    }

    private static function topic_profile($topic) {
        $t = self::lower($topic);
        $base = array(
            'metrics' => array('taxa de conversão', 'tempo de permanência', 'cliques em chamadas principais', 'qualidade dos leads', 'origem do tráfego', 'custo por oportunidade'),
            'objections' => array('falta de confiança no primeiro contato', 'dúvida sobre preço e entrega', 'medo de escolher uma solução errada', 'comparação com concorrentes mais conhecidos', 'sensação de que o site não explica o suficiente'),
            'assets' => array('página principal', 'conteúdo de apoio', 'formulário de contato', 'menu de navegação', 'proposta de valor', 'chamada para ação'),
            'insights' => array('A atenção do visitante é conquistada quando a página responde rapidamente o que ele ganha, por que deve confiar e qual passo deve seguir.', 'Conteúdo útil não depende de volume; depende de precisão, intenção clara e coerência entre promessa, prova e chamada para ação.', 'A experiência digital precisa reduzir dúvida, não aumentar etapas. Cada bloco deve aproximar a pessoa de uma decisão consciente.'),
            'mistakes' => array('O erro mais comum é publicar uma explicação bonita, mas sem recorte, sem prioridade e sem relação direta com uma decisão de compra.', 'Muitas empresas tratam o tema como tendência visual e esquecem de revisar mensagem, navegação, prova e velocidade.', 'O projeto perde força quando todos os conteúdos usam a mesma estrutura e não apresentam um ponto de vista novo.'),
            'opportunities' => array('A oportunidade está em criar uma jornada mais clara, capaz de transformar curiosidade em confiança e confiança em contato.', 'O ganho aparece quando o conteúdo aproxima estratégia, experiência e oferta em uma narrativa simples de entender.', 'Existe espaço para diferenciar a marca ao traduzir decisões técnicas em benefícios comerciais percebidos pelo cliente.'),
            'proofs' => array('demonstrações visuais', 'exemplos práticos', 'dados de navegação', 'depoimentos', 'comparativos', 'sinais de segurança'),
            'actions' => array('Comece revisando a página que mais recebe visitas e identifique onde o usuário pode ficar em dúvida.', 'Escolha uma oferta principal e reorganize o conteúdo para conduzir o visitante até ela com menos ruído.', 'Revise título, promessa, prova, imagem e chamada final antes de investir em mais tráfego.'),
        );

        if (strpos($t, 'seo') !== false || strpos($t, 'search console') !== false) {
            $base['metrics'] = array('impressões orgânicas', 'cliques qualificados', 'posição média', 'indexação de páginas importantes', 'taxa de clique nos resultados', 'crescimento de termos relevantes');
            $base['objections'] = array('conteúdo parecido com concorrentes', 'páginas sem intenção de busca clara', 'títulos pouco atrativos', 'estrutura técnica frágil', 'ausência de autoridade temática');
            $base['assets'] = array('título SEO', 'descrição da página', 'estrutura de headings', 'links internos', 'conteúdo principal', 'dados do Search Console');
            $base['insights'][] = 'SEO forte nasce quando o conteúdo resolve uma intenção de busca real e prova que a página merece estar entre as melhores respostas.';
        } elseif (strpos($t, 'ads') !== false || strpos($t, 'tráfego') !== false) {
            $base['metrics'] = array('custo por lead', 'retorno sobre investimento', 'taxa de clique dos anúncios', 'taxa de conversão da landing page', 'frequência de exibição', 'qualidade do criativo');
            $base['objections'] = array('anúncio que promete mais do que a página entrega', 'segmentação ampla demais', 'criativos sem contraste', 'página de destino lenta', 'oferta pouco específica');
            $base['assets'] = array('criativo do anúncio', 'página de destino', 'segmentação', 'orçamento diário', 'evento de conversão', 'copy principal');
            $base['opportunities'][] = 'A mídia paga fica mais eficiente quando anúncio, oferta e página de destino contam a mesma história.';
        } elseif (strpos($t, 'woocommerce') !== false || strpos($t, 'e-commerce') !== false || strpos($t, 'lojas virtuais') !== false || strpos($t, 'pagamento') !== false) {
            $base['metrics'] = array('adição ao carrinho', 'abandono de checkout', 'ticket médio', 'taxa de recompra', 'conversão por dispositivo', 'tempo até a compra');
            $base['objections'] = array('medo de comprar em uma loja desconhecida', 'frete pouco claro', 'checkout longo', 'descrição fraca do produto', 'falta de prova social');
            $base['assets'] = array('página de produto', 'checkout', 'carrinho', 'banner principal', 'menu de categorias', 'meios de pagamento');
            $base['insights'][] = 'Em lojas virtuais, cada dúvida não respondida vira atrito, e cada atrito aumenta a chance de abandono.';
        } elseif (strpos($t, 'design') !== false || strpos($t, 'ux') !== false || strpos($t, 'layout') !== false || strpos($t, 'mobile') !== false) {
            $base['metrics'] = array('profundidade de rolagem', 'cliques em botões', 'interação mobile', 'taxa de rejeição', 'tempo de leitura', 'conclusão de formulários');
            $base['objections'] = array('visual bonito sem orientação', 'botões pouco visíveis', 'hierarquia confusa', 'experiência ruim no celular', 'excesso de elementos competindo por atenção');
            $base['assets'] = array('hero section', 'menu mobile', 'botões de ação', 'grid de conteúdo', 'contraste visual', 'layout responsivo');
            $base['proofs'] = array('mapas de calor', 'testes de usabilidade', 'comparação mobile', 'análise de cliques', 'feedback de usuários', 'protótipos');
        } elseif (strpos($t, 'whatsapp') !== false || strpos($t, 'crm') !== false || strpos($t, 'automação') !== false || strpos($t, 'e-mail') !== false) {
            $base['metrics'] = array('tempo de resposta', 'taxa de abertura', 'taxa de resposta', 'leads recuperados', 'contatos qualificados', 'oportunidades acompanhadas');
            $base['objections'] = array('atendimento demorado', 'perda de histórico', 'falta de follow-up', 'mensagens genéricas', 'ausência de prioridade comercial');
            $base['assets'] = array('fluxo de atendimento', 'lista de leads', 'sequência de mensagens', 'integração com CRM', 'botão de contato', 'automação de relacionamento');
        } elseif (strpos($t, 'copywriting') !== false || strpos($t, 'conteúdo') !== false || strpos($t, 'branding') !== false || strpos($t, 'identidade') !== false) {
            $base['metrics'] = array('cliques no CTA', 'tempo de leitura', 'respostas comerciais', 'compartilhamentos', 'engajamento qualificado', 'lembrança de marca');
            $base['objections'] = array('promessa genérica', 'tom desalinhado com o público', 'falta de prova', 'argumentos pouco específicos', 'chamada final fraca');
            $base['assets'] = array('headline', 'subtítulo', 'proposta de valor', 'storytelling', 'argumento principal', 'CTA');
            $base['opportunities'][] = 'Uma mensagem bem posicionada faz a marca parecer mais segura antes mesmo do primeiro contato.';
        }

        return $base;
    }

    private static function audiences() {
        return array('empreendedores locais', 'gestores de marketing', 'donos de lojas virtuais', 'prestadores de serviço', 'pequenas empresas em expansão', 'marcas que vendem online', 'equipes comerciais', 'negócios que querem profissionalizar o digital');
    }

    private static function business_moments() {
        return array('fase de reposicionamento', 'momento de aumentar vendas', 'início de um novo site', 'revisão da presença online', 'planejamento de campanha', 'expansão de canais digitais', 'melhoria da experiência mobile', 'ajuste de oferta e conversão');
    }

    private static function scenarios() {
        return array('um cliente acessa pelo celular e decide em poucos segundos se continua', 'uma campanha atrai visitantes, mas a página precisa convencer sem depender do vendedor', 'a marca recebe tráfego, porém ainda não transforma atenção em contatos qualificados', 'o concorrente parece mais confiável porque explica melhor a própria oferta', 'o usuário compara preços e precisa enxergar diferença de valor', 'a equipe quer escalar sem perder consistência na comunicação');
    }

    private static function opening_patterns() {
        return array(
            '%1$s ganhou importância porque %2$s precisam decidir com mais velocidade durante uma %3$s.',
            'Falar sobre %1$s é falar sobre como %2$s podem criar vantagem em uma %3$s.',
            'Quando %2$s olham para %1$s com método, a %3$s deixa de ser improviso.',
            '%1$s se tornou um tema decisivo para %2$s que atravessam uma %3$s.',
            'O valor de %1$s aparece quando %2$s precisam transformar uma %3$s em resultado concreto.'
        );
    }

    private static function checklist_items($topic, $seed, $profile, $asset, $objection, $proof, $action) {
        $items = array(
            'Revise se o principal ativo do projeto, como ' . $asset . ', comunica valor antes de pedir uma ação.',
            'Mapeie a objeção mais provável, especialmente ' . $objection . ', e responda isso no próprio conteúdo.',
            'Inclua ' . $proof . ' quando fizer sentido, porque prova reduz incerteza e aumenta confiança.',
            'Defina uma única próxima ação para que o visitante não se perca entre muitas alternativas.',
            'Compare a experiência no celular e no desktop para evitar quedas de leitura e conversão.',
            'Atualize o post sempre que métricas ou comportamento indicarem perda de relevância.',
        );
        $extra = array(
            'Use exemplos do mercado do cliente para evitar um texto com aparência genérica.',
            'Troque frases amplas por orientações que ajudem o leitor a tomar uma decisão concreta.',
            'Conecte a promessa do título com a primeira dobra da página ou do artigo.',
            'Evite repetir blocos idênticos em temas diferentes, pois isso enfraquece percepção de utilidade.',
            $action,
        );
        $items[] = self::pick($extra, $seed, 30);
        shuffle($items);
        return array_slice($items, 0, 6);
    }

    private static function fit_content_length($html, $topic, $seed) {
        $plain_length = self::strlen(wp_strip_all_tags($html));
        if ($plain_length < 3000) {
            $html .= '<h2>' . esc_html(self::pick(array('Complemento estratégico', 'Leitura final', 'Ponto de atenção'), $seed, 40)) . '</h2>';
            $html .= '<p>' . esc_html('Para ampliar o resultado, observe como ' . $topic . ' se conecta com oferta, velocidade, conteúdo e atendimento. Nenhum desses pontos trabalha sozinho. Quando a empresa mede cada etapa, ela identifica gargalos com mais precisão e evita mudanças baseadas apenas em opinião.') . '</p>';
        }
        $plain_length = self::strlen(wp_strip_all_tags($html));
        if ($plain_length < 3000) {
            $html .= '<p>' . esc_html('A recomendação é criar um pequeno plano de revisão, escolher uma métrica principal e testar melhorias em ciclos curtos. Esse processo mantém o conteúdo vivo, reduz repetição e aumenta a chance de conquistar visitantes com intenção real.') . '</p>';
        }
        return $html;
    }

    private static function tags_for_topic($topic, $total = 0) {
        $generic = array(
            $topic, 'WordPress', 'WooCommerce', 'E-commerce', 'Marketing Digital', 'Sites Profissionais',
            'Criação de Sites', 'Lojas Virtuais', 'SEO', 'Performance Web', 'Web Design', 'UX Design',
            'UI Design', 'Conversão Online', 'Vendas Online', 'Tráfego Pago', 'Google Ads', 'Meta Ads',
            'Social Media', 'Instagram para Empresas', 'Facebook para Empresas', 'Conteúdo Digital',
            'Branding', 'Identidade Visual', 'Landing Pages', 'Funil de Vendas', 'Automação de Marketing',
            'E-mail Marketing', 'Copywriting', 'Analytics', 'Google Analytics', 'Google Search Console',
            'CRM', 'WhatsApp Business', 'Meios de Pagamento', 'Hospedagem WordPress', 'Segurança WordPress',
            'Mobile First', 'Layout Responsivo', 'Marketplace', 'Dropshipping', 'Produtos Digitais',
            'Inteligência Artificial', 'Tendências Digitais', 'Experiência do Usuário', 'Pequenas Empresas',
            'Estratégia Digital', 'Presença Online', 'Criação de Conteúdo'
        );

        $t = self::lower($topic);
        $specific = array();
        if (strpos($t, 'seo') !== false) {
            $specific = array('SEO Técnico', 'SEO On Page', 'Palavras-chave', 'Indexação', 'Busca Orgânica', 'Otimização de Conteúdo', 'Intenção de Busca');
        } elseif (strpos($t, 'ads') !== false || strpos($t, 'tráfego') !== false) {
            $specific = array('Campanhas Online', 'Anúncios Digitais', 'ROI', 'Remarketing', 'Criativos de Anúncios', 'Mídia Paga', 'Landing Page de Alta Conversão');
        } elseif (strpos($t, 'woocommerce') !== false || strpos($t, 'e-commerce') !== false || strpos($t, 'lojas virtuais') !== false) {
            $specific = array('Checkout', 'Carrinho de Compras', 'Página de Produto', 'Catálogo Online', 'Venda pela Internet', 'Gestão de Pedidos', 'Conversão de Loja Virtual');
        } elseif (strpos($t, 'design') !== false || strpos($t, 'ux') !== false || strpos($t, 'layout') !== false || strpos($t, 'mobile') !== false) {
            $specific = array('Design Responsivo', 'Interface Digital', 'Experiência Mobile', 'Hierarquia Visual', 'Usabilidade', 'Design Moderno', 'Arquitetura de Informação');
        } elseif (strpos($t, 'whatsapp') !== false || strpos($t, 'crm') !== false || strpos($t, 'automação') !== false) {
            $specific = array('Atendimento Digital', 'Relacionamento com Cliente', 'Leads', 'Processo Comercial', 'Integrações Digitais', 'Automação de Vendas', 'Follow-up');
        } elseif (strpos($t, 'copywriting') !== false || strpos($t, 'conteúdo') !== false) {
            $specific = array('Redação Persuasiva', 'Oferta Digital', 'Conteúdo Estratégico', 'CTA', 'Storytelling', 'Comunicação de Valor', 'Headline');
        }

        $tokens = preg_split('/\s+/', remove_accents($topic));
        foreach ($tokens as $token) {
            $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
            if (strlen($token) > 3) {
                $specific[] = ucfirst(strtolower($token)) . ' Digital';
            }
        }

        $pool = array_merge($specific, $generic);
        $offset = $total % max(1, count($pool));
        $pool = array_merge(array_slice($pool, $offset), array_slice($pool, 0, $offset));

        $tags = array();
        foreach ($pool as $tag) {
            $tag = self::normalize_spaces($tag);
            if (!$tag) {
                continue;
            }
            $key = self::lower(remove_accents($tag));
            if (!isset($tags[$key])) {
                $tags[$key] = $tag;
            }
        }
        return array_slice(array_values($tags), 0, 30);
    }

    private static function create_featured_image($post_id, $topic, $category, $title, $seed) {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['error'])) {
            return new WP_Error('bpv_upload_dir', $upload_dir['error']);
        }

        $filename = 'blog-privilege-' . absint($post_id) . '-' . sanitize_title($topic) . '-' . substr(md5($seed), 0, 8) . '.jpg';
        $filepath = trailingslashit($upload_dir['path']) . $filename;

        $image_meta = self::try_ai_contextual_photo($filepath, $topic, $category, $title, $seed);
        if (is_wp_error($image_meta)) {
            $image_meta = self::try_openverse_human_photo($filepath, $topic, $seed);
        }
        if (is_wp_error($image_meta)) {
            $fallback = self::render_humanized_local_image($filepath, $topic, $category, $seed);
            if (is_wp_error($fallback)) {
                return $fallback;
            }
            $image_meta = array(
                'source' => 'fallback-local-humanized',
                'note'   => 'Imagem local humanizada gerada quando IA externa e fotos contextuais não estiverem disponíveis.',
            );
        }

        $filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/jpeg',
            'post_title'     => sanitize_text_field($title . ' - imagem'),
            'post_content'   => !empty($image_meta['attribution']) ? sanitize_textarea_field($image_meta['attribution']) : '',
            'post_excerpt'   => !empty($image_meta['attribution']) ? sanitize_text_field($image_meta['attribution']) : '',
            'post_status'    => 'inherit',
        );

        $attach_id = wp_insert_attachment($attachment, $filepath, $post_id);
        if (is_wp_error($attach_id) || !$attach_id) {
            return new WP_Error('bpv_attachment', 'Não foi possível registrar a imagem na biblioteca de mídia.');
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attach_id, $filepath);
        wp_update_attachment_metadata($attach_id, $attach_data);
        update_post_meta($attach_id, '_wp_attachment_image_alt', sanitize_text_field('Imagem humanizada relacionada a ' . $topic));
        update_post_meta($attach_id, '_bpv_image_meta', $image_meta);
        set_post_thumbnail($post_id, $attach_id);

        self::remember_image_log(!empty($image_meta['source']) ? $image_meta['source'] : 'imagem-humanizada');
        return $attach_id;
    }


    private static function try_ai_contextual_photo($filepath, $topic, $category, $title, $seed) {
        if (!function_exists('wp_remote_get')) {
            return new WP_Error('bpv_no_http', 'HTTP do WordPress indisponível para gerar imagem de IA.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $prompt = self::ai_image_prompt($topic, $category, $title, $seed);
        $params = array(
            'width'       => 1920,
            'height'      => 1080,
            'seed'        => abs(crc32($seed . '|ai-image')),
            'model'       => 'flux',
            'nologo'      => 'true',
            'enhance'     => 'true',
            'private'     => 'true',
            'safe'        => 'true',
        );
        $url = 'https://image.pollinations.ai/prompt/' . rawurlencode($prompt) . '?' . http_build_query($params, '', '&');
        $saved = self::download_crop_save_image($url, $filepath);
        if (is_wp_error($saved)) {
            return $saved;
        }

        return array(
            'source'      => 'ai-contextual-realistic',
            'prompt'      => $prompt,
            'attribution' => 'Imagem realista gerada por IA para o contexto editorial: ' . $topic,
        );
    }

    private static function ai_image_prompt($topic, $category, $title, $seed) {
        $context = self::image_context_for_topic($topic);
        $style = self::pick(array(
            'realistic editorial photography, natural light, shallow depth of field',
            'premium realistic business photography, candid moment, soft daylight',
            'cinematic realistic office photography, modern Brazilian business environment',
            'documentary style realistic photo, professional workspace, authentic people',
        ), $seed, 72);

        return self::normalize_spaces($style . ', ' . $context . ', theme: ' . $topic . ', article title context: ' . $title . ', no text, no logos, no icons, no watermark, 16:9, high detail');
    }

    private static function image_context_for_topic($topic) {
        $t = self::lower($topic);
        if (strpos($t, 'e-commerce') !== false || strpos($t, 'lojas virtuais') !== false || strpos($t, 'checkout') !== false || strpos($t, 'produto') !== false) {
            return 'entrepreneur managing an online store on a laptop, product samples on a clean desk, packaging materials in the background, credible ecommerce atmosphere';
        }
        if (strpos($t, 'seo') !== false || strpos($t, 'google') !== false || strpos($t, 'busca') !== false || strpos($t, 'tráfego orgânico') !== false || strpos($t, 'palavras-chave') !== false) {
            return 'marketing analyst reviewing organic search dashboards on a laptop with a colleague, modern office, charts visible but unreadable';
        }
        if (strpos($t, 'marca') !== false || strpos($t, 'branding') !== false || strpos($t, 'identidade') !== false || strpos($t, 'rebranding') !== false || strpos($t, 'naming') !== false || strpos($t, 'nome') !== false) {
            return 'brand strategy workshop with designers discussing color palettes, typography samples and mood boards on a table, realistic agency setting';
        }
        if (strpos($t, 'design') !== false || strpos($t, 'visual') !== false || strpos($t, 'criativos') !== false || strpos($t, 'apresentações') !== false) {
            return 'professional designer working on campaign visuals in a bright creative studio, laptop and design boards, realistic human scene';
        }
        if (strpos($t, 'site') !== false || strpos($t, 'landing') !== false || strpos($t, 'ux') !== false || strpos($t, 'ui') !== false || strpos($t, 'responsividade') !== false) {
            return 'web design team planning a professional website on laptop and mobile screens, user journey sketches on desk, realistic office environment';
        }
        if (strpos($t, 'leads') !== false || strpos($t, 'funil') !== false || strpos($t, 'marketing') !== false || strpos($t, 'digital') !== false || strpos($t, 'clientes') !== false) {
            return 'digital marketing team planning a customer acquisition funnel around a laptop, business meeting, natural realistic photo';
        }
        return 'professional business team in a modern agency office working on digital strategy around a laptop, realistic people, clean premium composition';
    }

    private static function try_openverse_human_photo($filepath, $topic, $seed) {
        if (!function_exists('wp_remote_get')) {
            return new WP_Error('bpv_no_http', 'HTTP do WordPress indisponível.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $queries = self::openverse_queries($topic, $seed);
        $license_attempts = array(
            array('license' => 'cc0,pdm', 'label' => 'openverse-cc0-pdm'),
            array('license_type' => 'commercial', 'label' => 'openverse-commercial'),
        );

        foreach ($license_attempts as $license_params) {
            foreach ($queries as $q_index => $query) {
                $params = array(
                    'q'         => $query,
                    'page_size' => 12,
                    'page'      => (abs(crc32($seed . '|page|' . $q_index)) % 8) + 1,
                    'mature'    => 'false',
                    'category'  => 'photograph',
                );
                foreach ($license_params as $k => $v) {
                    if ($k !== 'label') {
                        $params[$k] = $v;
                    }
                }

                $url = 'https://api.openverse.org/v1/images/?' . http_build_query($params, '', '&');
                $response = wp_remote_get($url, array(
                    'timeout' => 12,
                    'headers' => array(
                        'User-Agent' => 'Blog Privilege WordPress Plugin/' . self::VERSION . '; ' . home_url('/'),
                        'Accept'     => 'application/json',
                    ),
                ));

                if (is_wp_error($response)) {
                    continue;
                }

                $code = absint(wp_remote_retrieve_response_code($response));
                if ($code < 200 || $code >= 300) {
                    continue;
                }

                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (empty($data['results']) || !is_array($data['results'])) {
                    continue;
                }

                $results = self::prioritize_photo_results($data['results'], $seed);
                foreach ($results as $result) {
                    if (empty($result['url'])) {
                        continue;
                    }
                    if (!empty($result['width']) && !empty($result['height'])) {
                        if (absint($result['width']) < 1200 || absint($result['height']) < 675) {
                            continue;
                        }
                    }

                    $saved = self::download_crop_save_image($result['url'], $filepath);
                    if (is_wp_error($saved)) {
                        continue;
                    }

                    $attribution = self::format_openverse_attribution($result);
                    return array(
                        'source'              => $license_params['label'],
                        'query'               => $query,
                        'title'               => isset($result['title']) ? sanitize_text_field($result['title']) : '',
                        'creator'             => isset($result['creator']) ? sanitize_text_field($result['creator']) : '',
                        'license'             => isset($result['license']) ? sanitize_text_field($result['license']) : '',
                        'license_url'         => isset($result['license_url']) ? esc_url_raw($result['license_url']) : '',
                        'foreign_landing_url' => isset($result['foreign_landing_url']) ? esc_url_raw($result['foreign_landing_url']) : '',
                        'attribution'         => $attribution,
                    );
                }
            }
        }

        return new WP_Error('bpv_photo_not_found', 'Não foi possível obter uma foto humanizada externa.');
    }

    private static function openverse_queries($topic, $seed) {
        $t = self::lower($topic);
        $base = array(
            'business person laptop office website',
            'entrepreneur working laptop digital marketing',
            'small business owner laptop online store',
            'marketing team office laptop meeting',
            'designer working website laptop office',
            'business people planning website strategy',
        );

        if (strpos($t, 'e-commerce') !== false || strpos($t, 'woocommerce') !== false || strpos($t, 'lojas virtuais') !== false) {
            $base = array_merge(array('online store owner laptop packing orders', 'ecommerce entrepreneur laptop office', 'person managing online shop laptop'), $base);
        } elseif (strpos($t, 'seo') !== false || strpos($t, 'analytics') !== false || strpos($t, 'search console') !== false) {
            $base = array_merge(array('marketing analyst laptop office', 'person analyzing website data laptop', 'business analytics laptop meeting'), $base);
        } elseif (strpos($t, 'ads') !== false || strpos($t, 'tráfego') !== false || strpos($t, 'social') !== false || strpos($t, 'instagram') !== false || strpos($t, 'facebook') !== false) {
            $base = array_merge(array('digital marketing team planning campaign', 'social media manager laptop office', 'advertising strategy meeting laptop'), $base);
        } elseif (strpos($t, 'design') !== false || strpos($t, 'ux') !== false || strpos($t, 'layout') !== false || strpos($t, 'mobile') !== false) {
            $base = array_merge(array('web designer laptop office', 'ux designer working laptop', 'creative professional designing website'), $base);
        } elseif (strpos($t, 'whatsapp') !== false || strpos($t, 'crm') !== false || strpos($t, 'automação') !== false || strpos($t, 'e-mail') !== false) {
            $base = array_merge(array('customer service team laptop office', 'business communication laptop office', 'sales team crm laptop'), $base);
        }

        $offset = abs(crc32($seed . '|query')) % count($base);
        return array_slice(array_merge(array_slice($base, $offset), array_slice($base, 0, $offset)), 0, 4);
    }

    private static function prioritize_photo_results($results, $seed) {
        usort($results, function($a, $b) use ($seed) {
            $aw = !empty($a['width']) ? absint($a['width']) : 0;
            $ah = !empty($a['height']) ? absint($a['height']) : 0;
            $bw = !empty($b['width']) ? absint($b['width']) : 0;
            $bh = !empty($b['height']) ? absint($b['height']) : 0;
            $ascore = ($aw >= 1600 ? 2 : 0) + ($ah >= 900 ? 2 : 0) + abs(crc32($seed . ($a['id'] ?? $a['url'] ?? 'a'))) % 3;
            $bscore = ($bw >= 1600 ? 2 : 0) + ($bh >= 900 ? 2 : 0) + abs(crc32($seed . ($b['id'] ?? $b['url'] ?? 'b'))) % 3;
            return $bscore <=> $ascore;
        });
        return $results;
    }

    private static function download_crop_save_image($url, $filepath) {
        $tmp = download_url($url, 20);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $size = @getimagesize($tmp);
        if (!$size || empty($size[0]) || empty($size[1])) {
            @unlink($tmp);
            return new WP_Error('bpv_not_image', 'Arquivo baixado não é imagem válida.');
        }

        $editor = wp_get_image_editor($tmp);
        if (is_wp_error($editor)) {
            @unlink($tmp);
            return $editor;
        }

        $resized = $editor->resize(1920, 1080, true);
        if (is_wp_error($resized)) {
            @unlink($tmp);
            return $resized;
        }

        $saved = $editor->save($filepath, 'image/jpeg');
        @unlink($tmp);

        if (is_wp_error($saved)) {
            return $saved;
        }
        if (!file_exists($filepath)) {
            return new WP_Error('bpv_image_save', 'Imagem externa não foi salva.');
        }
        return true;
    }

    private static function format_openverse_attribution($result) {
        $parts = array();
        if (!empty($result['title'])) {
            $parts[] = 'Imagem: ' . wp_strip_all_tags($result['title']);
        }
        if (!empty($result['creator'])) {
            $parts[] = 'Criador: ' . wp_strip_all_tags($result['creator']);
        }
        if (!empty($result['license'])) {
            $parts[] = 'Licença: ' . wp_strip_all_tags($result['license']);
        }
        if (!empty($result['foreign_landing_url'])) {
            $parts[] = 'Fonte: ' . esc_url_raw($result['foreign_landing_url']);
        }
        return self::normalize_spaces(implode(' | ', $parts));
    }

    private static function render_humanized_local_image($filepath, $topic, $category, $seed) {
        if (!function_exists('imagecreatetruecolor')) {
            return new WP_Error('bpv_no_gd', 'A extensão PHP GD não está ativa no servidor.');
        }

        $w = 1920;
        $h = 1080;
        $img = imagecreatetruecolor($w, $h);
        if (!$img) {
            return new WP_Error('bpv_image_create', 'Não foi possível criar a imagem.');
        }
        imagealphablending($img, true);
        if (function_exists('imageantialias')) {
            imageantialias($img, true);
        }

        $palette = self::human_palette($topic, $seed);
        self::draw_photo_gradient($img, $w, $h, $palette);
        self::draw_soft_office_background($img, $w, $h, $palette, $seed);
        self::draw_workspace_scene($img, $w, $h, $palette, $seed, $topic);
        self::draw_camera_vignette($img, $w, $h);

        $saved = imagejpeg($img, $filepath, 92);
        imagedestroy($img);

        if (!$saved) {
            return new WP_Error('bpv_image_save', 'Não foi possível salvar a imagem local.');
        }
        return true;
    }

    private static function human_palette($topic, $seed) {
        $sets = array(
            array('bg1' => '#1b2336', 'bg2' => '#f0b36a', 'skin' => '#c98764', 'skin2' => '#f0c2a0', 'cloth' => '#14213d', 'accent' => '#f77f00', 'screen' => '#d7f3ff'),
            array('bg1' => '#192a36', 'bg2' => '#c9e4de', 'skin' => '#8f5d45', 'skin2' => '#e5b895', 'cloth' => '#0f4c5c', 'accent' => '#f4a261', 'screen' => '#eef8ff'),
            array('bg1' => '#2b1d2f', 'bg2' => '#ffd6a5', 'skin' => '#a96f50', 'skin2' => '#efc3a4', 'cloth' => '#3d405b', 'accent' => '#e07a5f', 'screen' => '#f7fbff'),
            array('bg1' => '#111827', 'bg2' => '#bde0fe', 'skin' => '#b7795b', 'skin2' => '#f1c5a7', 'cloth' => '#1d3557', 'accent' => '#ffb703', 'screen' => '#edf6f9'),
        );
        return self::pick($sets, $seed, 50);
    }

    private static function draw_photo_gradient($img, $w, $h, $palette) {
        $rgb1 = self::hex_to_rgb($palette['bg1']);
        $rgb2 = self::hex_to_rgb($palette['bg2']);
        for ($y = 0; $y < $h; $y++) {
            $ratio = $y / max(1, $h - 1);
            $r = (int) ($rgb1[0] * (1 - $ratio) + $rgb2[0] * $ratio);
            $g = (int) ($rgb1[1] * (1 - $ratio) + $rgb2[1] * $ratio);
            $b = (int) ($rgb1[2] * (1 - $ratio) + $rgb2[2] * $ratio);
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, $r, $g, $b));
        }
    }

    private static function draw_soft_office_background($img, $w, $h, $palette, $seed) {
        $whiteSoft = self::color($img, '#ffffff', 96);
        $glass     = self::color($img, '#ffffff', 78);
        $darkGlass = self::color($img, '#0f172a', 105);

        for ($i = 0; $i < 8; $i++) {
            $x = 80 + $i * 245 + (abs(crc32($seed . '|win|' . $i)) % 40);
            self::round_rect($img, $x, 90, $x + 170, 590, 28, $glass, true);
            imageline($img, $x + 85, 110, $x + 85, 570, $whiteSoft);
            imageline($img, $x + 15, 340, $x + 155, 340, $whiteSoft);
        }

        for ($i = 0; $i < 24; $i++) {
            $x = abs(crc32($seed . '|blurx|' . $i)) % $w;
            $y = 80 + abs(crc32($seed . '|blury|' . $i)) % 700;
            $rw = 80 + abs(crc32($seed . '|blurw|' . $i)) % 220;
            $rh = 22 + abs(crc32($seed . '|blurh|' . $i)) % 70;
            self::round_rect($img, $x, $y, $x + $rw, $y + $rh, 18, $darkGlass, true);
        }

        imagefilledrectangle($img, 0, 710, $w, $h, self::color($img, '#f5f1eb', 8));
        imagefilledrectangle($img, 0, 745, $w, $h, self::color($img, '#d7c4ae', 32));
    }

    private static function draw_workspace_scene($img, $w, $h, $palette, $seed, $topic) {
        $desk   = self::color($img, '#6b4f3d', 5);
        $shadow = self::color($img, '#000000', 92);
        $screen = self::color($img, $palette['screen'], 0);
        $screenDark = self::color($img, '#0b1320', 0);
        $accent = self::color($img, $palette['accent'], 0);
        $cloth = self::color($img, $palette['cloth'], 0);
        $skin = self::color($img, $palette['skin'], 0);
        $skin2 = self::color($img, $palette['skin2'], 0);
        $hair = self::color($img, '#2a1711', 0);

        imagefilledellipse($img, 980, 900, 1300, 210, $shadow);
        self::round_rect($img, 210, 705, 1710, 985, 48, $desk, true);
        imagefilledrectangle($img, 240, 705, 1680, 760, self::color($img, '#8a674b', 5));

        $scene = abs(crc32($seed . '|scene')) % 4;

        if ($scene === 0) {
            self::draw_person($img, 520, 450, 1.15, $skin, $skin2, $hair, $cloth, false);
            self::draw_person($img, 1350, 440, 1.05, $skin2, $skin, $hair, self::color($img, '#25324a', 0), true);
            self::draw_laptop($img, 820, 575, 1.22, $screen, $screenDark, $accent, $seed, $topic);
            self::draw_hands_on_table($img, 760, 790, 1.0, $skin2, $skin, $seed);
        } elseif ($scene === 1) {
            self::draw_person($img, 700, 420, 1.25, $skin2, $skin, $hair, $cloth, false);
            self::draw_laptop($img, 965, 570, 1.35, $screen, $screenDark, $accent, $seed, $topic);
            self::draw_coffee_and_notebook($img, 1280, 800, $accent, $seed);
            self::draw_hands_on_table($img, 910, 815, 1.08, $skin2, $skin, $seed);
        } elseif ($scene === 2) {
            self::draw_person($img, 455, 460, 0.95, $skin, $skin2, $hair, self::color($img, '#264653', 0), false);
            self::draw_person($img, 960, 390, 1.2, $skin2, $skin, $hair, $cloth, true);
            self::draw_person($img, 1460, 460, 0.95, $skin, $skin2, $hair, self::color($img, '#2d3142', 0), false);
            self::draw_laptop($img, 870, 580, 1.1, $screen, $screenDark, $accent, $seed, $topic);
        } else {
            self::draw_close_hand_laptop($img, 520, 610, $skin2, $skin, $screen, $screenDark, $accent, $seed, $topic);
            self::draw_person_profile($img, 1340, 430, 1.2, $skin, $skin2, $hair, $cloth);
        }

        self::draw_depth_lights($img, $w, $h, $palette, $seed);
    }

    private static function draw_person($img, $cx, $cy, $s, $skin, $skin2, $hair, $cloth, $flip = false) {
        $headW = (int)(126 * $s);
        $headH = (int)(150 * $s);
        imagefilledellipse($img, $cx, $cy, $headW, $headH, $skin);
        imagefilledarc($img, $cx - (int)(12 * $s), $cy - (int)(40 * $s), $headW + 26, $headH, 190, 350, $hair, IMG_ARC_PIE);
        imagefilledellipse($img, $cx - (int)(25 * $s), $cy - (int)(5 * $s), (int)(13 * $s), (int)(8 * $s), self::color($img, '#1a1a1a', 0));
        imagefilledellipse($img, $cx + (int)(25 * $s), $cy - (int)(5 * $s), (int)(13 * $s), (int)(8 * $s), self::color($img, '#1a1a1a', 0));
        imagearc($img, $cx, $cy + (int)(36 * $s), (int)(52 * $s), (int)(28 * $s), 15, 165, self::color($img, '#7a4335', 0));
        self::round_rect($img, $cx - (int)(110 * $s), $cy + (int)(70 * $s), $cx + (int)(110 * $s), $cy + (int)(330 * $s), (int)(50 * $s), $cloth, true);
        imagefilledellipse($img, $cx - (int)(54 * $s), $cy + (int)(128 * $s), (int)(52 * $s), (int)(70 * $s), $skin2);
        imagefilledellipse($img, $cx + (int)(54 * $s), $cy + (int)(128 * $s), (int)(52 * $s), (int)(70 * $s), $skin2);
    }

    private static function draw_person_profile($img, $cx, $cy, $s, $skin, $skin2, $hair, $cloth) {
        imagefilledellipse($img, $cx, $cy, (int)(155 * $s), (int)(185 * $s), $skin);
        imagefilledarc($img, $cx - (int)(45 * $s), $cy - (int)(45 * $s), (int)(170 * $s), (int)(190 * $s), 90, 275, $hair, IMG_ARC_PIE);
        imagefilledellipse($img, $cx - (int)(18 * $s), $cy - (int)(6 * $s), (int)(15 * $s), (int)(10 * $s), self::color($img, '#111111', 0));
        imagearc($img, $cx - (int)(20 * $s), $cy + (int)(48 * $s), (int)(46 * $s), (int)(24 * $s), 10, 160, self::color($img, '#7a4335', 0));
        self::round_rect($img, $cx - (int)(160 * $s), $cy + (int)(90 * $s), $cx + (int)(90 * $s), $cy + (int)(425 * $s), (int)(65 * $s), $cloth, true);
    }

    private static function draw_laptop($img, $x, $y, $s, $screen, $dark, $accent, $seed, $topic) {
        $sw = (int)(470 * $s);
        $sh = (int)(285 * $s);
        self::round_rect($img, $x, $y, $x + $sw, $y + $sh, (int)(22 * $s), $dark, true);
        self::round_rect($img, $x + (int)(18 * $s), $y + (int)(18 * $s), $x + $sw - (int)(18 * $s), $y + $sh - (int)(28 * $s), (int)(14 * $s), $screen, true);
        self::round_rect($img, $x - (int)(40 * $s), $y + $sh, $x + $sw + (int)(40 * $s), $y + $sh + (int)(45 * $s), (int)(12 * $s), self::color($img, '#cad3dc', 0), true);
        imagefilledellipse($img, $x + (int)($sw/2), $y + $sh + (int)(20 * $s), (int)(42 * $s), (int)(8 * $s), self::color($img, '#a5b0bd', 0));
        $muted = self::color($img, '#88a9bf', 25);
        for ($i = 0; $i < 5; $i++) {
            $lx = $x + (int)(60 * $s);
            $ly = $y + (int)((68 + $i * 34) * $s);
            $lw = (int)((160 + (abs(crc32($seed . '|line|' . $i)) % 170)) * $s);
            self::round_rect($img, $lx, $ly, $lx + $lw, $ly + (int)(12 * $s), (int)(6 * $s), $muted, true);
        }
        $chartX = $x + (int)(270 * $s);
        $chartY = $y + (int)(210 * $s);
        imagesetthickness($img, (int)(5 * $s));
        for ($i = 0; $i < 4; $i++) {
            imageline($img, $chartX + (int)($i * 42 * $s), $chartY - (int)((abs(crc32($seed . '|chart|' . $i)) % 72) * $s), $chartX + (int)(($i + 1) * 42 * $s), $chartY - (int)((abs(crc32($seed . '|chart|' . ($i + 1))) % 72) * $s), $accent);
        }
        imagesetthickness($img, 1);
    }

    private static function draw_hands_on_table($img, $x, $y, $s, $skin1, $skin2, $seed) {
        imagefilledellipse($img, $x, $y, (int)(190 * $s), (int)(62 * $s), $skin1);
        imagefilledellipse($img, $x + (int)(340 * $s), $y + (int)(10 * $s), (int)(190 * $s), (int)(62 * $s), $skin2);
        imagesetthickness($img, 7);
        for ($i = 0; $i < 4; $i++) {
            imageline($img, $x - (int)(55 * $s) + (int)($i * 26 * $s), $y - (int)(4 * $s), $x - (int)(35 * $s) + (int)($i * 30 * $s), $y - (int)(38 * $s), self::color($img, '#f1c7aa', 0));
            imageline($img, $x + (int)(280 * $s) + (int)($i * 28 * $s), $y + (int)(6 * $s), $x + (int)(295 * $s) + (int)($i * 28 * $s), $y - (int)(36 * $s), self::color($img, '#d79a7b', 0));
        }
        imagesetthickness($img, 1);
    }

    private static function draw_coffee_and_notebook($img, $x, $y, $accent, $seed) {
        imagefilledellipse($img, $x, $y, 120, 120, self::color($img, '#ffffff', 6));
        imagefilledellipse($img, $x, $y, 76, 76, self::color($img, '#6f4e37', 0));
        imagearc($img, $x + 55, $y, 62, 48, -60, 70, self::color($img, '#ffffff', 2));
        self::round_rect($img, $x + 145, $y - 75, $x + 420, $y + 90, 18, self::color($img, '#f7f3eb', 0), true);
        for ($i = 0; $i < 5; $i++) {
            imageline($img, $x + 178, $y - 38 + $i * 28, $x + 390, $y - 38 + $i * 28, self::color($img, '#d0c0af', 0));
        }
        imagefilledellipse($img, $x + 385, $y + 62, 18, 18, $accent);
    }

    private static function draw_close_hand_laptop($img, $x, $y, $skin1, $skin2, $screen, $dark, $accent, $seed, $topic) {
        self::draw_laptop($img, $x + 210, $y - 70, 1.28, $screen, $dark, $accent, $seed, $topic);
        imagefilledellipse($img, $x + 110, $y + 270, 460, 125, $skin1);
        imagefilledellipse($img, $x + 330, $y + 260, 330, 110, $skin2);
        imagesetthickness($img, 12);
        for ($i = 0; $i < 5; $i++) {
            imageline($img, $x - 30 + $i * 70, $y + 220, $x + 20 + $i * 70, $y + 115 + (abs(crc32($seed . '|finger|' . $i)) % 28), $skin2);
        }
        imagesetthickness($img, 1);
    }

    private static function draw_depth_lights($img, $w, $h, $palette, $seed) {
        for ($i = 0; $i < 10; $i++) {
            $x = abs(crc32($seed . '|lightx|' . $i)) % $w;
            $y = 40 + abs(crc32($seed . '|lighty|' . $i)) % 400;
            $size = 80 + abs(crc32($seed . '|lights|' . $i)) % 180;
            imagefilledellipse($img, $x, $y, $size, $size, self::color($img, $palette['accent'], 112));
        }
    }

    private static function draw_camera_vignette($img, $w, $h) {
        for ($i = 0; $i < 80; $i++) {
            $alpha = 124 - (int)($i * 1.35);
            if ($alpha < 35) {
                $alpha = 35;
            }
            $color = imagecolorallocatealpha($img, 0, 0, 0, $alpha);
            imagerectangle($img, $i, $i, $w - $i, $h - $i, $color);
        }
    }

    private static function content_was_used($html) {
        $plain = self::content_signature_text($html);
        $hashes = get_option(self::OPTION_CONTENT_HASHES, array());
        if (!is_array($hashes)) {
            $hashes = array();
        }
        $hash = md5($plain);
        if (isset($hashes[$hash])) {
            return true;
        }

        $stored_phrases = get_option(self::OPTION_PHRASE_HASHES, array());
        if (!is_array($stored_phrases)) {
            $stored_phrases = array();
        }
        $current = self::phrase_hashes($html);
        foreach ($current as $phrase_hash) {
            if (isset($stored_phrases[$phrase_hash])) {
                return true;
            }
        }
        return false;
    }

    private static function remember_content($html) {
        $plain = self::content_signature_text($html);
        $hashes = get_option(self::OPTION_CONTENT_HASHES, array());
        if (!is_array($hashes)) {
            $hashes = array();
        }
        $hashes[md5($plain)] = time();
        $hashes = self::trim_assoc($hashes, 700);
        update_option(self::OPTION_CONTENT_HASHES, $hashes, false);

        $phrases = get_option(self::OPTION_PHRASE_HASHES, array());
        if (!is_array($phrases)) {
            $phrases = array();
        }
        foreach (self::phrase_hashes($html) as $phrase_hash) {
            $phrases[$phrase_hash] = time();
        }
        $phrases = self::trim_assoc($phrases, 1800);
        update_option(self::OPTION_PHRASE_HASHES, $phrases, false);
    }

    private static function remember_title($title) {
        $used = get_option(self::OPTION_TITLE_HASHES, array());
        if (!is_array($used)) {
            $used = array();
        }
        $used[md5(self::lower(remove_accents($title)))] = time();
        $used = self::trim_assoc($used, 1000);
        update_option(self::OPTION_TITLE_HASHES, $used, false);
    }

    private static function remember_image_log($source) {
        $log = get_option(self::OPTION_IMAGE_LOG, array());
        if (!is_array($log)) {
            $log = array();
        }
        $log[] = wp_date('d/m/Y H:i') . ' - ' . $source;
        if (count($log) > 25) {
            $log = array_slice($log, -25);
        }
        update_option(self::OPTION_IMAGE_LOG, $log, false);
    }

    private static function content_signature_text($html) {
        $plain = wp_strip_all_tags($html);
        $plain = self::lower(remove_accents($plain));
        $plain = preg_replace('/\s+/', ' ', $plain);
        return trim($plain);
    }

    private static function phrase_hashes($html) {
        $plain = wp_strip_all_tags($html);
        $plain = preg_replace('/\s+/', ' ', $plain);
        $parts = preg_split('/(?<=[.!?])\s+/', $plain);
        $hashes = array();
        foreach ($parts as $part) {
            $part = self::normalize_spaces($part);
            if (self::strlen($part) < 110) {
                continue;
            }
            $hashes[] = md5(self::lower(remove_accents($part)));
        }
        return array_unique($hashes);
    }

    private static function trim_assoc($array, $max) {
        if (count($array) <= $max) {
            return $array;
        }
        asort($array);
        return array_slice($array, -$max, null, true);
    }

    private static function seed($topic, $total, $attempt = 0) {
        return $topic . '|' . absint($total) . '|' . absint($attempt) . '|' . wp_date('Y-m-d-H-i') . '|' . wp_salt('nonce');
    }

    private static function pick($items, $seed, $shift = 0) {
        if (empty($items)) {
            return '';
        }
        $key = abs(crc32((string) $seed . '|' . (string) $shift));
        return $items[$key % count($items)];
    }

    private static function limit_text($text, $max) {
        $text = self::normalize_spaces($text);
        if (self::strlen($text) <= $max) {
            return $text;
        }
        $cut = self::safe_substr($text, 0, $max);
        $pos = strrpos($cut, ' ');
        if ($pos !== false && $pos > 10) {
            $cut = substr($cut, 0, $pos);
        }
        $cut = rtrim($cut, ' ,.;:-');
        if (self::strlen($cut) > $max) {
            $cut = self::safe_substr($cut, 0, $max);
        }
        return self::normalize_spaces($cut);
    }

    private static function round_rect($img, $x1, $y1, $x2, $y2, $r, $color, $filled = true, $thickness = 1) {
        $x1 = (int) $x1; $y1 = (int) $y1; $x2 = (int) $x2; $y2 = (int) $y2; $r = (int) $r;
        if ($filled) {
            imagefilledrectangle($img, $x1 + $r, $y1, $x2 - $r, $y2, $color);
            imagefilledrectangle($img, $x1, $y1 + $r, $x2, $y2 - $r, $color);
            imagefilledellipse($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $color);
            imagefilledellipse($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $color);
            imagefilledellipse($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $color);
            imagefilledellipse($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $color);
        } else {
            imagesetthickness($img, $thickness);
            imageline($img, $x1 + $r, $y1, $x2 - $r, $y1, $color);
            imageline($img, $x1 + $r, $y2, $x2 - $r, $y2, $color);
            imageline($img, $x1, $y1 + $r, $x1, $y2 - $r, $color);
            imageline($img, $x2, $y1 + $r, $x2, $y2 - $r, $color);
            imagearc($img, $x1 + $r, $y1 + $r, $r * 2, $r * 2, 180, 270, $color);
            imagearc($img, $x2 - $r, $y1 + $r, $r * 2, $r * 2, 270, 360, $color);
            imagearc($img, $x1 + $r, $y2 - $r, $r * 2, $r * 2, 90, 180, $color);
            imagearc($img, $x2 - $r, $y2 - $r, $r * 2, $r * 2, 0, 90, $color);
            imagesetthickness($img, 1);
        }
    }

    private static function color($img, $hex, $alpha = 0) {
        $rgb = self::hex_to_rgb($hex);
        return imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], max(0, min(127, (int) $alpha)));
    }

    private static function hex_to_rgb($hex) {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    private static function normalize_spaces($text) {
        $text = preg_replace('/\s+/u', ' ', (string) $text);
        return trim($text);
    }

    private static function lower($text) {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower((string) $text, 'UTF-8');
        }
        return strtolower((string) $text);
    }

    private static function strlen($text) {
        if (function_exists('mb_strlen')) {
            return mb_strlen((string) $text, 'UTF-8');
        }
        return strlen((string) $text);
    }

    private static function safe_substr($text, $start, $length = null) {
        if (function_exists('mb_substr')) {
            return $length === null ? mb_substr((string) $text, $start, null, 'UTF-8') : mb_substr((string) $text, $start, $length, 'UTF-8');
        }
        return $length === null ? substr((string) $text, $start) : substr((string) $text, $start, $length);
    }
}

register_activation_hook(__FILE__, array('BPV_Blog_Privilege', 'activate'));
register_deactivation_hook(__FILE__, array('BPV_Blog_Privilege', 'deactivate'));
BPV_Blog_Privilege::init();
