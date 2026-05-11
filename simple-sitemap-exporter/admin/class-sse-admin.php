<?php

if (! defined('ABSPATH')) {
    exit;
}

class SSE_Admin
{
    /** @var SSE_Scanner */
    private $scanner;

    /** @var SSE_Sitemap */
    private $sitemap;

    /** @var SSE_Storage */
    private $storage;

    public function __construct($scanner, $sitemap, $storage)
    {
        $this->scanner = $scanner;
        $this->sitemap = $sitemap;
        $this->storage = $storage;

        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_post_sse_scan', array($this, 'handle_scan'));
        add_action('admin_post_sse_generate', array($this, 'handle_generate'));
        add_action('admin_post_sse_download', array($this, 'handle_download'));
    }

    public function register_menu()
    {
        add_management_page(
            __('Simple Sitemap Exporter', 'simple-sitemap-exporter'),
            __('Simple Sitemap Exporter', 'simple-sitemap-exporter'),
            'manage_options',
            'simple-sitemap-exporter',
            array($this, 'render_page')
        );
    }

    public function render_page()
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Você não tem permissão para acessar esta página.', 'simple-sitemap-exporter'));
        }

        $urls = $this->storage->get_urls();
        $last_updated_at = $this->storage->get_last_updated_at();
        $notice = isset($_GET['sse_notice']) ? sanitize_text_field(wp_unslash($_GET['sse_notice'])) : '';
        $sitemap_url = home_url('/sitemap.xml');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Simple Sitemap Exporter', 'simple-sitemap-exporter'); ?></h1>

            <?php if ($notice) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($notice); ?></p></div>
            <?php endif; ?>

            <p>
                <?php echo esc_html__('URL pública estável do sitemap:', 'simple-sitemap-exporter'); ?>
                <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($sitemap_url); ?></a>
            </p>

            <?php if ($last_updated_at) : ?>
                <p>
                    <?php echo esc_html__('Última atualização:', 'simple-sitemap-exporter'); ?>
                    <strong><?php echo esc_html($last_updated_at); ?></strong>
                </p>
            <?php endif; ?>

            <div style="display:flex;gap:12px;align-items:center;margin:16px 0;">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sse_scan" />
                    <?php wp_nonce_field('sse_scan_action', 'sse_scan_nonce'); ?>
                    <?php submit_button(__('Escanear URLs', 'simple-sitemap-exporter'), 'primary', 'submit', false); ?>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sse_generate" />
                    <?php wp_nonce_field('sse_generate_action', 'sse_generate_nonce'); ?>
                    <?php submit_button(__('Gerar sitemap.xml', 'simple-sitemap-exporter'), 'secondary', 'submit', false); ?>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="sse_download" />
                    <?php wp_nonce_field('sse_download_action', 'sse_download_nonce'); ?>
                    <?php submit_button(__('Exportar/Baixar sitemap.xml', 'simple-sitemap-exporter'), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <h2><?php echo esc_html__('URLs encontradas', 'simple-sitemap-exporter'); ?></h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('URL', 'simple-sitemap-exporter'); ?></th>
                        <th><?php echo esc_html__('Lastmod', 'simple-sitemap-exporter'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($urls)) : ?>
                        <tr>
                            <td colspan="2"><?php echo esc_html__('Nenhuma URL escaneada ainda.', 'simple-sitemap-exporter'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($urls as $entry) : ?>
                            <tr>
                                <td><code><?php echo esc_html(isset($entry['loc']) ? $entry['loc'] : ''); ?></code></td>
                                <td><?php echo esc_html(isset($entry['lastmod']) ? $entry['lastmod'] : ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function handle_scan()
    {
        $this->assert_permissions('sse_scan_nonce', 'sse_scan_action');

        $urls = $this->scanner->scan_urls();
        $this->storage->save_urls($urls);

        $count = count($urls);
        $notice = sprintf(
            /* translators: %d total scanned URLs */
            __('Escaneamento concluído. %d URL(s) encontrada(s).', 'simple-sitemap-exporter'),
            $count
        );

        if ($count > $this->sitemap->get_max_urls()) {
            $notice .= ' ' . sprintf(
                /* translators: %d max URLs supported in one sitemap file */
                __('Atenção: o protocolo limita um único sitemap a %d URLs. O XML final será truncado nesse limite.', 'simple-sitemap-exporter'),
                $this->sitemap->get_max_urls()
            );
        }

        $this->redirect_with_notice($notice);
    }

    public function handle_generate()
    {
        $this->assert_permissions('sse_generate_nonce', 'sse_generate_action');

        $urls = $this->storage->get_urls();
        if (empty($urls)) {
            $urls = $this->scanner->scan_urls();
            $this->storage->save_urls($urls);
        }

        $prepared_urls = $this->sitemap->prepare_urls($urls);
        $xml = $this->sitemap->build_xml($prepared_urls);
        $saved_to_file = $this->sitemap->persist_xml($xml);

        $notice = $saved_to_file
            ? __('sitemap.xml gerado e salvo em /sitemap.xml.', 'simple-sitemap-exporter')
            : __('sitemap.xml gerado. Sem permissão de escrita no arquivo físico, será servido dinamicamente em /sitemap.xml.', 'simple-sitemap-exporter');

        if (count($urls) > count($prepared_urls)) {
            $notice .= ' ' . sprintf(
                /* translators: %d max URLs supported in one sitemap file */
                __('Limite aplicado: somente as primeiras %d URLs foram incluídas no XML.', 'simple-sitemap-exporter'),
                $this->sitemap->get_max_urls()
            );
        }

        $this->redirect_with_notice($notice);
    }

    public function handle_download()
    {
        $this->assert_permissions('sse_download_nonce', 'sse_download_action');

        $xml = $this->storage->get_xml();
        if ('' === $xml) {
            $urls = $this->storage->get_urls();
            if (empty($urls)) {
                $urls = $this->scanner->scan_urls();
                $this->storage->save_urls($urls);
            }

            $xml = $this->sitemap->build_xml($this->sitemap->prepare_urls($urls));
            $this->sitemap->persist_xml($xml);
        }

        nocache_headers();
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Disposition: attachment; filename="sitemap.xml"');
        header('X-Content-Type-Options: nosniff');
        echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    private function assert_permissions($nonce_field, $nonce_action)
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permissão negada.', 'simple-sitemap-exporter'));
        }

        $nonce = isset($_POST[$nonce_field]) ? sanitize_text_field(wp_unslash($_POST[$nonce_field])) : '';
        if (! wp_verify_nonce($nonce, $nonce_action)) {
            wp_die(esc_html__('Falha de segurança (nonce inválido).', 'simple-sitemap-exporter'));
        }
    }

    private function redirect_with_notice($notice)
    {
        $url = add_query_arg(
            array(
                'page' => 'simple-sitemap-exporter',
                'sse_notice' => $notice,
            ),
            admin_url('tools.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
