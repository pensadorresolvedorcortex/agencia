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
        add_action('wp_ajax_sse_scan_batch', array($this, 'handle_scan_batch'));
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
        $scan_nonce = wp_create_nonce('sse_scan_batch_action');
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

            <div style="display:flex;gap:12px;align-items:center;margin:16px 0;flex-wrap:wrap;">
                <button type="button" class="button button-primary" id="sse-scan-btn" data-nonce="<?php echo esc_attr($scan_nonce); ?>"><?php echo esc_html__('Escanear URLs (lotes)', 'simple-sitemap-exporter'); ?></button>

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

            <p id="sse-scan-status" style="font-weight:600;"></p>

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
        <script>
        (function(){
            var btn = document.getElementById('sse-scan-btn');
            var status = document.getElementById('sse-scan-status');
            if (!btn || !status) return;

            function scanStep(isStart){
                var form = new FormData();
                form.append('action', 'sse_scan_batch');
                form.append('_ajax_nonce', btn.getAttribute('data-nonce'));
                form.append('start', isStart ? '1' : '0');

                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: form })
                    .then(function(resp){ return resp.json(); })
                    .then(function(payload){
                        if (!payload || !payload.success) {
                            throw new Error('Falha no escaneamento em lote.');
                        }

                        var data = payload.data || {};
                        status.textContent = 'Escaneando em lotes... URLs coletadas: ' + (data.total || 0);

                        if (data.done) {
                            status.textContent = 'Escaneamento concluído. ' + (data.total || 0) + ' URL(s) encontrada(s). Recarregando...';
                            window.location.reload();
                            return;
                        }

                        window.setTimeout(function(){ scanStep(false); }, 150);
                    })
                    .catch(function(err){
                        status.textContent = 'Erro: ' + (err && err.message ? err.message : 'erro desconhecido');
                        btn.disabled = false;
                    });
            }

            btn.addEventListener('click', function(){
                btn.disabled = true;
                status.textContent = 'Iniciando escaneamento em lotes...';
                scanStep(true);
            });
        })();
        </script>
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
                __('Atenção: o protocolo limita cada sitemap a %d URLs. O plugin vai gerar sitemap index com múltiplas partes.', 'simple-sitemap-exporter'),
                $this->sitemap->get_max_urls()
            );
        }

        $this->redirect_with_notice($notice);
    }

    public function handle_scan_batch()
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'simple-sitemap-exporter')), 403);
        }

        check_ajax_referer('sse_scan_batch_action');

        $start = isset($_POST['start']) && '1' === sanitize_text_field(wp_unslash($_POST['start']));

        if ($start) {
            $state = $this->scanner->create_initial_state();
            $items = array();
            $this->storage->save_scan_state($state);
            $this->storage->save_scan_items($items);
        } else {
            $state = $this->storage->get_scan_state();
            $items = $this->storage->get_scan_items();

            if (empty($state)) {
                $state = $this->scanner->create_initial_state();
                $items = array();
            }
        }

        $result = $this->scanner->run_batch($state, $items, 300);

        $new_state = isset($result['state']) ? $result['state'] : array();
        $new_items = isset($result['items']) ? $result['items'] : array();
        $done = ! empty($result['done']);

        if ($done) {
            $final_urls = $this->scanner->finalize_items($new_items);
            $this->storage->save_urls($final_urls);
            $this->storage->clear_scan_state();
            $this->storage->clear_scan_items();

            wp_send_json_success(
                array(
                    'done' => true,
                    'total' => count($final_urls),
                )
            );
        }

        $this->storage->save_scan_state($new_state);
        $this->storage->save_scan_items($new_items);

        wp_send_json_success(
            array(
                'done' => false,
                'total' => count($new_items),
                'phase' => isset($new_state['phase']) ? $new_state['phase'] : '',
            )
        );
    }

    public function handle_generate()
    {
        $this->assert_permissions('sse_generate_nonce', 'sse_generate_action');

        $urls = $this->storage->get_urls();
        if (empty($urls)) {
            $urls = $this->scanner->scan_urls();
            $this->storage->save_urls($urls);
        }

        $payload = $this->sitemap->build_payload($urls);
        $saved_to_file = $this->sitemap->persist_payload($payload);

        $is_index = ! empty($payload['is_index']);

        if ($saved_to_file) {
            $notice = $is_index
                ? __('Sitemap index gerado em /sitemap.xml com múltiplas partes (sitemap-1.xml, sitemap-2.xml, ...).', 'simple-sitemap-exporter')
                : __('sitemap.xml gerado e salvo em /sitemap.xml.', 'simple-sitemap-exporter');
        } else {
            $notice = $is_index
                ? __('Sitemap index gerado dinamicamente em /sitemap.xml com múltiplas partes, sem gravação física.', 'simple-sitemap-exporter')
                : __('sitemap.xml gerado. Sem permissão de escrita no arquivo físico, será servido dinamicamente em /sitemap.xml.', 'simple-sitemap-exporter');
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

            $payload = $this->sitemap->build_payload($urls);
            $xml = isset($payload['index_xml']) ? (string) $payload['index_xml'] : '';
            $this->sitemap->persist_payload($payload);
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
