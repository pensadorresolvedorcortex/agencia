<?php
/**
 * Plugin Name: RMA Governance
 * Description: Workflow de 3 aceites para entidades RMA com logs de auditoria.
 * Version: 0.4.3
 * Author: RMA
 */

if (! defined('ABSPATH')) {
    exit;
}

final class RMA_Governance {
    private const CPT = 'rma_entidade';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('init', [$this, 'handle_entity_document_upload']);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_governance_menu'], 99);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_governance_content'], 100);
        add_action('wp_footer', [$this, 'inject_entity_notifications_dropdown'], 101);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_home_index_cards'], 102);
        add_action('wp_head', [$this, 'disable_global_preload_overlays'], 1);

        add_action('wp_ajax_rma_mark_entity_notifications_read', [$this, 'ajax_mark_entity_notifications_read']);

        add_action('rma/entity_approved', [$this, 'on_entity_approved'], 10, 2);
        add_action('rma/entity_rejected', [$this, 'on_entity_rejected'], 10, 2);
        add_action('rma/entity_resubmitted', [$this, 'on_entity_resubmitted'], 10, 1);
        add_action('rma/entity_finance_updated', [$this, 'on_entity_finance_updated'], 10, 3);

        add_shortcode('rma_governanca_entidade_documentos', [$this, 'render_entity_governance_documents']);
        add_shortcode('rma_governanca_entidade_pendencias', [$this, 'render_entity_governance_pendencias']);
        add_shortcode('rma_governanca_entidade_status', [$this, 'render_entity_governance_status']);
        add_shortcode('rma_governanca_entidade_upload', [$this, 'render_entity_governance_upload']);
    }

    public function register_admin_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . self::CPT,
            'Governança RMA',
            'Governança',
            'edit_others_posts',
            'rma-governance-audit',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page(): void {
        if (! current_user_can('edit_others_posts')) {
            wp_die('Você não tem permissão para acessar esta página.');
        }

        $this->handle_admin_actions();

        $status_filter = isset($_GET['status_filter']) ? sanitize_key((string) wp_unslash($_GET['status_filter'])) : '';
        $allowed_status_filters = ['', 'pendente', 'em_analise', 'recusado', 'aprovado'];
        if (! in_array($status_filter, $allowed_status_filters, true)) {
            $status_filter = '';
        }

        $search_filter = isset($_GET['search']) ? sanitize_text_field((string) wp_unslash($_GET['search'])) : '';

        $all_rows = $this->load_governance_rows();
        $rows = $this->load_governance_rows([
            'status_filter' => $status_filter,
            'search_filter' => $search_filter,
        ]);
        $summary = $this->build_summary($all_rows);

        $selected_entity_id = isset($_GET['entity_id']) ? (int) $_GET['entity_id'] : 0;
        $selected = $selected_entity_id > 0 ? $this->load_entity_details($selected_entity_id) : null;

        $notice = isset($_GET['rma_notice']) ? sanitize_text_field(rawurldecode((string) wp_unslash($_GET['rma_notice']))) : '';
        $notice_type = isset($_GET['rma_notice_type']) ? sanitize_key((string) wp_unslash($_GET['rma_notice_type'])) : '';
        if ($selected_entity_id > 0 && $selected === null && $notice === '') {
            $notice = 'A entidade selecionada não foi encontrada para gerenciamento.';
            $notice_type = 'error';
        }
        ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Maven+Pro:wght@600&display=swap" rel="stylesheet">
        <style>
            .rma-gov-wrap,
            .rma-gov-wrap * {
                font-family: 'Maven Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
                font-weight: 600 !important;
                box-sizing: border-box;
            }

            .rma-gov-wrap {
                --rma-bg: linear-gradient(150deg, #fcfdff 0%, #f2f7ff 100%);
                --rma-card: rgba(255, 255, 255, 0.86);
                --rma-border: rgba(255, 255, 255, 0.78);
                --rma-shadow: 0 24px 60px rgba(15, 23, 42, 0.11);
                --rma-text: #162538;
                --rma-muted: #5b6a7c;
                --rma-approve: #0f9f6f;
                --rma-reject: #ce3f4a;
                --rma-pending: #d78d11;
                margin-top: 14px;
                border-radius: 24px;
                padding: 28px;
                border: 1px solid rgba(255,255,255,0.9);
                background: var(--rma-bg);
                box-shadow: var(--rma-shadow);
                color: var(--rma-text);
            }

            .rma-gov-head { margin-bottom: 16px; }
            .rma-filter-bar { display:flex; flex-wrap:wrap; gap:8px; margin: 12px 0 18px; }
            .rma-filter-bar input, .rma-filter-bar select { border:1px solid rgba(15,23,42,.2); border-radius:10px; padding:8px 10px; background:#fff; min-width:170px; }
            .rma-filter-bar button, .rma-filter-bar a { border-radius:10px; padding:8px 11px; text-decoration:none; border:none; cursor:pointer; }
            .rma-filter-bar button { background:#162538; color:#fff; }
            .rma-filter-bar a { background:rgba(15,23,42,.08); color:#162538; }
            #rma-governance-detail { scroll-margin-top: 80px; }
            .rma-gov-head h1 { margin: 0 0 8px 0 !important; font-size: 30px !important; }
            .rma-gov-head p { margin: 0 !important; color: var(--rma-muted); }

            .rma-gov-notice {
                border-radius: 12px;
                padding: 12px 14px;
                margin: 12px 0 18px;
                border: 1px solid transparent;
            }
            .rma-gov-notice.success { background: rgba(15,159,111,.11); border-color: rgba(15,159,111,.3); color: #0d7d58; }
            .rma-gov-notice.error { background: rgba(206,63,74,.1); border-color: rgba(206,63,74,.24); color: #b2303c; }

            .rma-gov-cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
                gap: 12px;
                margin-bottom: 22px;
            }
            .rma-gov-card {
                border-radius: 16px;
                padding: 14px;
                background: var(--rma-card);
                border: 1px solid var(--rma-border);
                box-shadow: 0 10px 30px rgba(15,23,42,0.07);
                backdrop-filter: blur(14px);
                -webkit-backdrop-filter: blur(14px);
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .rma-gov-card .icon {
                width: 30px;
                height: 30px;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: #fff;
                border: 1px solid rgba(15,23,42,.08);
            }
            .rma-gov-card strong { display: block; font-size: 22px; line-height: 1.1; }
            .rma-muted { color: var(--rma-muted); font-size: 12px; }

            .rma-gov-table-wrap,
            .rma-gov-detail {
                background: var(--rma-card);
                border: 1px solid var(--rma-border);
                border-radius: 18px;
                box-shadow: 0 10px 30px rgba(15,23,42,.06);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
            }
            .rma-gov-table-wrap { overflow: auto; margin-bottom: 18px; }

            table.rma-gov-table,
            table.rma-gov-nested {
                width: 100%;
                border-collapse: collapse;
                background: transparent !important;
            }
            .rma-gov-table th,
            .rma-gov-table td,
            .rma-gov-nested th,
            .rma-gov-nested td {
                border-bottom: 1px solid rgba(15,23,42,.08) !important;
                padding: 12px;
                text-align: left;
                vertical-align: top;
            }
            .rma-gov-table th,
            .rma-gov-nested th {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: .05em;
                color: var(--rma-muted);
            }

            .rma-badge {
                border-radius: 999px;
                padding: 6px 11px;
                font-size: 12px;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                border: 1px solid transparent;
            }
            .rma-badge.aprovado { color: var(--rma-approve); background: rgba(15,159,111,.12); border-color: rgba(15,159,111,.25); }
            .rma-badge.recusado { color: var(--rma-reject); background: rgba(206,63,74,.12); border-color: rgba(206,63,74,.25); }
            .rma-badge.em_analise,
            .rma-badge.pendente { color: var(--rma-pending); background: rgba(215,141,17,.12); border-color: rgba(215,141,17,.25); }

            .rma-link {
                color: #1559d6 !important;
                text-decoration: none !important;
            }
            .rma-link:hover { text-decoration: underline !important; }

            .rma-gov-detail {
                padding: 18px;
                display: grid;
                gap: 14px;
            }
            .rma-gov-detail-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 12px;
            }
            .rma-detail-card {
                background: rgba(255,255,255,.9);
                border: 1px solid rgba(15,23,42,.08);
                border-radius: 14px;
                padding: 12px;
            }
            .rma-detail-card h3 { margin: 0 0 8px; font-size: 15px; }
            .rma-actions form { display: grid; gap: 8px; margin-bottom: 10px; }
            .rma-actions textarea,
            .rma-actions input[type="text"] {
                width: 100%;
                border: 1px solid rgba(15,23,42,.2);
                border-radius: 10px;
                padding: 8px 10px;
                background: #fff;
                font-size: 13px;
            }
            .rma-actions button {
                border: none;
                border-radius: 10px;
                padding: 9px 12px;
                color: #fff;
                cursor: pointer;
                width: fit-content;
            }
            .rma-btn-approve { background: #00bfa5; }
            .rma-btn-reject { background: #e8063c; }
            .rma-btn-resubmit { background: linear-gradient(135deg, #d78d11 0%, #bd7a0f 100%); }

            .rma-timeline { margin: 0; padding-left: 18px; }
            .rma-timeline li { margin: 0 0 8px 0; color: var(--rma-text); }
        </style>

        <div class="wrap rma-gov-wrap">
            <div class="rma-gov-head">
                <h1>Governança RMA</h1>
                <p>Status operacionais e gestão prática de documentos e auditoria.</p>
            </div>

            <?php if ($notice !== '') : ?>
                <div class="rma-gov-notice <?php echo esc_attr($notice_type === 'success' ? 'success' : 'error'); ?>">
                    <?php echo esc_html($notice); ?>
                </div>
            <?php endif; ?>

            <form method="get" class="rma-filter-bar">
                <input type="hidden" name="post_type" value="<?php echo esc_attr(self::CPT); ?>">
                <input type="hidden" name="page" value="rma-governance-audit">
                <select name="status_filter">
                    <option value="" <?php selected($status_filter, ''); ?>>Todos os status</option>
                    <option value="pendente" <?php selected($status_filter, 'pendente'); ?>>Aguardando</option>
                    <option value="em_analise" <?php selected($status_filter, 'em_analise'); ?>>Em análise</option>
                    <option value="recusado" <?php selected($status_filter, 'recusado'); ?>>Recusado</option>
                    <option value="aprovado" <?php selected($status_filter, 'aprovado'); ?>>Aprovado</option>
                </select>
                <input type="text" name="search" placeholder="Buscar por entidade ou ID" value="<?php echo esc_attr($search_filter); ?>">
                <button type="submit">Filtrar</button>
                <a href="<?php echo esc_url($this->build_admin_url()); ?>">Limpar</a>
            </form>

            <div class="rma-gov-cards">
                <?php echo $this->summary_card('approve', 'Aprovadas', $summary['approved']); ?>
                <?php echo $this->summary_card('reject', 'Recusadas', $summary['rejected']); ?>
                <?php echo $this->summary_card('pending', 'Aguardando análise', $summary['pending']); ?>
                <?php echo $this->summary_card('document', 'Arquivos privados', $summary['documents']); ?>
            </div>

            <div id="rma-governance-detail">
                <?php if ($selected !== null) : ?>
                    <div class="rma-gov-detail">
                        <div>
                            <h2 style="margin:0 0 6px;">Entidade selecionada: <?php echo esc_html($selected['title']); ?></h2>
                            <span class="rma-badge <?php echo esc_attr($selected['status']); ?>">
                                <?php echo $this->status_icon($selected['status']); ?>
                                <?php echo esc_html($this->status_label($selected['status'])); ?>
                            </span>
                            <?php if ($selected['rejection_reason'] !== '') : ?>
                                <p class="rma-muted" style="margin-top:8px;">Motivo da recusa: <?php echo esc_html($selected['rejection_reason']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="rma-gov-detail-grid">
                            <div class="rma-detail-card rma-actions">
                                <h3>Ações de governança</h3>
                                <?php if (in_array($selected['status'], ['pendente', 'em_analise'], true)) : ?>
                                    <form method="post">
                                        <input type="hidden" name="rma_governance_action" value="approve">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_approve'); ?>
                                        <textarea name="comment" rows="2" placeholder="Comentário opcional do aceite"></textarea>
                                        <button class="rma-btn-approve" type="submit">Registrar Aprovado</button>
                                    </form>

                                    <form method="post">
                                        <input type="hidden" name="rma_governance_action" value="reject">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_reject'); ?>
                                        <input type="text" name="reason" required placeholder="Motivo obrigatório da recusa">
                                        <button class="rma-btn-reject" type="submit">Recusar Entidade</button>
                                    </form>

                                    <form method="post">
                                        <input type="hidden" name="rma_governance_action" value="force_approve">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_force_approve'); ?>
                                        <button class="rma-btn-approve" type="submit">Liberar Acesso Agora</button>
                                    </form>
                                <?php elseif ($selected['status'] === 'recusado') : ?>
                                    <form method="post">
                                        <input type="hidden" name="rma_governance_action" value="resubmit">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_resubmit'); ?>
                                        <button class="rma-btn-resubmit" type="submit">Reenviar para análise</button>
                                    </form>
                                <?php else : ?>
                                    <p class="rma-muted">Entidade liberada. Você pode manter, suspender ou remover permanentemente.</p>
                                <?php endif; ?>

                                <?php if (in_array($selected['status'], ['aprovado', 'suspenso'], true)) : ?>
                                    <form method="post">
                                        <input type="hidden" name="rma_governance_action" value="suspend">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_suspend'); ?>
                                        <button class="rma-btn-reject" type="submit">Suspender Entidade</button>
                                    </form>

                                    <form method="post" onsubmit="return confirm('Confirma a exclusão permanente da entidade e dos documentos privados?');">
                                        <input type="hidden" name="rma_governance_action" value="delete">
                                        <input type="hidden" name="entity_id" value="<?php echo esc_attr((string) $selected['id']); ?>">
                                        <?php wp_nonce_field('rma_gov_action_' . $selected['id'] . '_delete'); ?>
                                        <button class="rma-btn-reject" style="background:#a10024;" type="submit">Deletar Entidade</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <?php if (! empty($selected['contact_sections'])) : ?>
                                <div class="rma-detail-card">
                                    <h3>Dados institucionais de contato</h3>
                                    <?php foreach ($selected['contact_sections'] as $section_label => $items) : ?>
                                        <p style="margin:8px 0 4px;"><strong><?php echo esc_html($section_label); ?></strong></p>
                                        <ul class="rma-timeline" style="margin-bottom:8px;">
                                            <?php foreach ($items as $item_label => $item_value) : ?>
                                                <li><span class="rma-muted"><?php echo esc_html($item_label); ?>:</span> <?php echo esc_html($item_value); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="rma-detail-card">
                                <h3>Aceites registrados</h3>
                                <?php if (empty($selected['approvals'])) : ?>
                                    <p class="rma-muted">Ainda não há aceites para esta entidade.</p>
                                <?php else : ?>
                                    <ul class="rma-timeline">
                                        <?php foreach ($selected['approvals'] as $approval) : ?>
                                            <li>
                                                <?php echo esc_html($approval['user_name']); ?> · <?php echo esc_html($approval['datetime']); ?> · <span class="rma-muted">Etapa <?php echo esc_html((string) ($approval['stage'] ?? 0)); ?></span>
                                                <?php if ($approval['comment'] !== '') : ?>
                                                    <br><span class="rma-muted"><?php echo esc_html($approval['comment']); ?></span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rma-detail-card">
                            <h3>Documentos privados (<?php echo esc_html((string) count($selected['documents'])); ?>)</h3>
                            <?php if (empty($selected['documents'])) : ?>
                                <p class="rma-muted">Não há documentos enviados nesta entidade.</p>
                            <?php else : ?>
                                <table class="rma-gov-nested">
                                    <thead>
                                    <tr>
                                        <th>Arquivo</th>
                                        <th>Tipo</th>
                                        <th>Enviado em</th>
                                        <th>Ação</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($selected['documents'] as $doc) : ?>
                                        <tr>
                                            <td><?php echo esc_html($doc['name']); ?></td>
                                            <td><?php echo esc_html($doc['document_type'] !== '' ? $doc['document_type'] : 'geral'); ?></td>
                                            <td><?php echo esc_html($doc['uploaded_at'] !== '' ? $doc['uploaded_at'] : '—'); ?></td>
                                            <td><a class="rma-link" href="<?php echo esc_url($doc['download_url']); ?>" target="_blank" rel="noopener">Baixar</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <div class="rma-detail-card">
                            <h3>Trilha de auditoria</h3>
                            <?php if (empty($selected['audit_logs'])) : ?>
                                <p class="rma-muted">Sem eventos de auditoria.</p>
                            <?php else : ?>
                                <ul class="rma-timeline">
                                    <?php foreach ($selected['audit_logs'] as $log) : ?>
                                        <li><?php echo esc_html($log['action']); ?> · <?php echo esc_html($log['datetime']); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="rma-gov-detail">
                        <div class="rma-detail-card">
                            <h3>Painel de gerenciamento</h3>
                            <p class="rma-muted">Clique em <strong>Gerenciar</strong> em qualquer entidade para abrir a análise completa (ações, documentos e auditoria) aqui no topo.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($selected === null) : ?>
                <div class="rma-gov-table-wrap">
                    <table class="rma-gov-table">
                        <thead>
                        <tr>
                            <th>Entidade</th>
                            <th>Status</th>
                            <th>Aceites</th>
                            <th>Documentos</th>
                            <th>Contatos extras</th>
                            <th>Último evento</th>
                            <th>Ações</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)) : ?>
                            <tr><td colspan="7">Nenhuma entidade encontrada para governança.</td></tr>
                        <?php else : ?>
                            <?php foreach ($rows as $row) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($row['title']); ?></strong><br>
                                        <span class="rma-muted">ID #<?php echo esc_html((string) $row['id']); ?></span>
                                    </td>
                                    <td>
                                        <span class="rma-badge <?php echo esc_attr($row['status']); ?>">
                                            <?php echo $this->status_icon($row['status']); ?>
                                            <?php echo esc_html($this->status_label($row['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html((string) $row['approvals_count']); ?>/3</td>
                                    <td><?php echo esc_html((string) $row['documents_count']); ?></td>
                                    <td><?php echo esc_html((string) $row['extra_contacts_count']); ?></td>
                                    <td><span class="rma-muted"><?php echo esc_html($row['last_audit'] !== '' ? $row['last_audit'] : '—'); ?></span></td>
                                    <td>
                                        <a class="rma-link" href="<?php echo esc_url($this->build_admin_url(['entity_id' => $row['id'], 'status_filter' => $status_filter, 'search' => $search_filter])); ?>#rma-governance-detail">Gerenciar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p style="margin-top:14px;"><a class="rma-link" href="<?php echo esc_url($this->build_admin_url(['status_filter' => $status_filter, 'search' => $search_filter])); ?>">← Voltar para lista de entidades</a></p>
            <?php endif; ?>

        </div>
        <?php
    }

    private function handle_admin_actions(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';
        if ($page !== 'rma-governance-audit') {
            return;
        }

        $action = isset($_POST['rma_governance_action']) ? sanitize_key((string) wp_unslash($_POST['rma_governance_action'])) : '';
        $entity_id = isset($_POST['entity_id']) ? (int) $_POST['entity_id'] : 0;

        if ($entity_id <= 0 || ! in_array($action, ['approve', 'reject', 'resubmit', 'force_approve', 'suspend', 'delete'], true)) {
            $this->redirect_with_notice($entity_id, 'Ação inválida.', 'error');
        }

        $nonce = isset($_POST['_wpnonce']) ? (string) wp_unslash($_POST['_wpnonce']) : '';
        if (! wp_verify_nonce($nonce, 'rma_gov_action_' . $entity_id . '_' . $action)) {
            $this->redirect_with_notice($entity_id, 'Falha de segurança ao validar ação.', 'error');
        }

        $request = new WP_REST_Request('POST');
        $request->set_param('id', $entity_id);

        if ($action === 'approve') {
            $request->set_param('comment', sanitize_textarea_field((string) ($_POST['comment'] ?? '')));
            $response = $this->approve_entity($request);
        } elseif ($action === 'reject') {
            $request->set_param('reason', sanitize_text_field((string) ($_POST['reason'] ?? '')));
            $response = $this->reject_entity($request);
        } elseif ($action === 'resubmit') {
            $response = $this->resubmit_entity($request);
        } elseif ($action === 'force_approve') {
            $response = $this->force_approve_entity($request);
        } elseif ($action === 'suspend') {
            $response = $this->suspend_entity($request);
        } else {
            $response = $this->delete_entity($request);
        }

        $payload = $response instanceof WP_REST_Response ? $response->get_data() : [];
        $status = $response instanceof WP_REST_Response ? (int) $response->get_status() : 500;

        if ($status >= 200 && $status < 300) {
            $ok_message = 'Ação executada com sucesso.';
            if ($action === 'approve') {
                $ok_message = 'Aceite registrado com sucesso.';
            } elseif ($action === 'reject') {
                $ok_message = 'Entidade recusada com sucesso.';
            } elseif ($action === 'resubmit') {
                $ok_message = 'Entidade reenviada para análise.';
            } elseif ($action === 'force_approve') {
                $ok_message = 'Acesso liberado com sucesso para a entidade.';
            } elseif ($action === 'suspend') {
                $ok_message = 'Entidade suspensa com sucesso.';
            } elseif ($action === 'delete') {
                $ok_message = 'Entidade removida permanentemente.';
            }

            if ($action === 'delete') {
                $this->redirect_with_notice(0, $ok_message, 'success');
            }

            $this->redirect_with_notice($entity_id, $ok_message, 'success');
        }

        $error_message = is_array($payload) ? (string) ($payload['message'] ?? 'Não foi possível executar a ação.') : 'Não foi possível executar a ação.';
        $this->redirect_with_notice($entity_id, $error_message, 'error');
    }

    private function redirect_with_notice(int $entity_id, string $message, string $type): void {
        $args = [
            'entity_id' => $entity_id,
            'rma_notice' => rawurlencode($message),
            'rma_notice_type' => $type === 'success' ? 'success' : 'error',
        ];

        wp_safe_redirect($this->build_admin_url($args));
        exit;
    }

    private function build_admin_url(array $args = []): string {
        $base = [
            'post_type' => self::CPT,
            'page' => 'rma-governance-audit',
        ];

        return add_query_arg(array_merge($base, $args), admin_url('edit.php'));
    }

    private function load_entity_details(int $entity_id): ?array {
        if ($entity_id <= 0 || get_post_type($entity_id) !== self::CPT) {
            return null;
        }

        $approvals = get_post_meta($entity_id, 'governance_approvals', true);
        $approvals = is_array($approvals) ? $approvals : [];

        $documents = get_post_meta($entity_id, 'entity_documents', true);
        $documents = is_array($documents) ? $documents : [];

        $audit_logs = get_post_meta($entity_id, 'governance_audit_logs', true);
        $audit_logs = is_array($audit_logs) ? $audit_logs : [];
        $audit_logs = array_reverse($audit_logs);

        $normalized_approvals = [];
        foreach ($approvals as $approval) {
            $user_id = (int) ($approval['user_id'] ?? 0);
            $user = $user_id > 0 ? get_userdata($user_id) : null;
            $normalized_approvals[] = [
                'user_name' => $user ? (string) $user->display_name : 'Usuário #' . $user_id,
                'datetime' => (string) ($approval['datetime'] ?? ''),
                'comment' => (string) ($approval['comment'] ?? ''),
                'stage' => (int) ($approval['stage'] ?? 0),
            ];
        }

        $normalized_documents = [];
        foreach ($documents as $doc) {
            $doc_id = sanitize_text_field((string) ($doc['id'] ?? ''));
            if ($doc_id === '') {
                continue;
            }

            $normalized_documents[] = [
                'name' => sanitize_file_name((string) ($doc['name'] ?? 'documento')),
                'document_type' => sanitize_key((string) ($doc['document_type'] ?? '')),
                'uploaded_at' => sanitize_text_field((string) ($doc['uploaded_at'] ?? '')),
                'download_url' => rest_url(sprintf('rma/v1/entities/%d/documents/%s', $entity_id, rawurlencode($doc_id))),
            ];
        }

        $normalized_logs = [];
        foreach ($audit_logs as $log) {
            $normalized_logs[] = [
                'action' => sanitize_key((string) ($log['action'] ?? 'evento')),
                'datetime' => sanitize_text_field((string) ($log['datetime'] ?? '')),
            ];
        }

        return [
            'id' => $entity_id,
            'title' => (string) get_the_title($entity_id),
            'status' => $this->normalized_governance_status($entity_id),
            'rejection_reason' => (string) get_post_meta($entity_id, 'governance_rejection_reason', true),
            'approvals' => $normalized_approvals,
            'documents' => $normalized_documents,
            'audit_logs' => array_slice($normalized_logs, 0, 20),
            'contact_sections' => $this->entity_contact_sections($entity_id),
        ];
    }

    private function load_governance_rows(array $filters = []): array {
        $posts = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['draft', 'publish', 'pending', 'private'],
            'posts_per_page' => 200,
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        if (empty($posts)) {
            return [];
        }

        $rows = [];
        $status_filter = sanitize_key((string) ($filters['status_filter'] ?? ''));
        $search_filter = sanitize_text_field((string) ($filters['search_filter'] ?? ''));

        foreach ($posts as $post) {
            $entity_id = (int) $post->ID;
            $approvals = get_post_meta($entity_id, 'governance_approvals', true);
            $approvals = is_array($approvals) ? $approvals : [];

            $documents = get_post_meta($entity_id, 'entity_documents', true);
            $documents = is_array($documents) ? $documents : [];

            $audit_logs = get_post_meta($entity_id, 'governance_audit_logs', true);
            $audit_logs = is_array($audit_logs) ? $audit_logs : [];
            $last_audit = '';

            if (! empty($audit_logs)) {
                $last_log = end($audit_logs);
                $action = (string) ($last_log['action'] ?? 'evento');
                $datetime = (string) ($last_log['datetime'] ?? '');
                $last_audit = trim($action . ' · ' . $datetime, " ·");
            }

            $title = (string) get_the_title($entity_id);
            $status = $this->normalized_governance_status($entity_id);

            if ($status_filter !== '' && $status !== $status_filter) {
                continue;
            }

            if ($search_filter !== '' && stripos($title, $search_filter) === false && stripos((string) $entity_id, $search_filter) === false) {
                continue;
            }

            $rows[] = [
                'id' => $entity_id,
                'title' => $title,
                'status' => $status,
                'approvals_count' => count($approvals),
                'documents_count' => count($documents),
                'extra_contacts_count' => $this->entity_extra_contacts_count($entity_id),
                'last_audit' => $last_audit,
            ];
        }

        return $rows;
    }

    private function entity_contact_sections(int $entity_id): array {
        $meta_values = [
            'endereco_correspondencia' => sanitize_text_field((string) get_post_meta($entity_id, 'endereco_correspondencia', true)),
            'whatsapp_representante_legal' => preg_replace('/[^0-9\+\-\(\)\s]/', '', (string) get_post_meta($entity_id, 'whatsapp_representante_legal', true)),
            'nome_responsavel_contato_rma' => sanitize_text_field((string) get_post_meta($entity_id, 'nome_responsavel_contato_rma', true)),
            'whatsapp_responsavel_contato_rma' => preg_replace('/[^0-9\+\-\(\)\s]/', '', (string) get_post_meta($entity_id, 'whatsapp_responsavel_contato_rma', true)),
            'nome_assessor_imprensa' => sanitize_text_field((string) get_post_meta($entity_id, 'nome_assessor_imprensa', true)),
            'email_assessoria_imprensa' => sanitize_email((string) get_post_meta($entity_id, 'email_assessoria_imprensa', true)),
            'whatsapp_assessor_imprensa' => preg_replace('/[^0-9\+\-\(\)\s]/', '', (string) get_post_meta($entity_id, 'whatsapp_assessor_imprensa', true)),
        ];

        $sections = [
            'Correspondência' => [
                'Endereço para correspondência' => $meta_values['endereco_correspondencia'],
            ],
            'Representação legal' => [
                'WhatsApp do Representante Legal' => $meta_values['whatsapp_representante_legal'],
            ],
            'Contato com a RMA' => [
                'Responsável pelo contato com a RMA' => $meta_values['nome_responsavel_contato_rma'],
                'WhatsApp do responsável' => $meta_values['whatsapp_responsavel_contato_rma'],
            ],
            'Assessoria de imprensa' => [
                'Nome do assessor de imprensa' => $meta_values['nome_assessor_imprensa'],
                'E-mail assessoria de imprensa' => $meta_values['email_assessoria_imprensa'],
                'WhatsApp do assessor de imprensa' => $meta_values['whatsapp_assessor_imprensa'],
            ],
        ];

        $normalized = [];
        foreach ($sections as $label => $items) {
            $filled_items = array_filter($items, static function ($value): bool {
                return trim((string) $value) !== '';
            });
            if (! empty($filled_items)) {
                $normalized[$label] = $filled_items;
            }
        }

        return $normalized;
    }

    private function entity_extra_contacts_count(int $entity_id): int {
        $sections = $this->entity_contact_sections($entity_id);
        $count = 0;
        foreach ($sections as $items) {
            $count += count($items);
        }

        return $count;
    }

    private function build_summary(array $rows): array {
        $summary = [
            'approved' => 0,
            'rejected' => 0,
            'pending' => 0,
            'documents' => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'pendente');
            if ($status === 'aprovado') {
                $summary['approved']++;
            } elseif ($status === 'recusado') {
                $summary['rejected']++;
            } else {
                $summary['pending']++;
            }

            $summary['documents'] += (int) ($row['documents_count'] ?? 0);
        }

        return $summary;
    }

    private function summary_card(string $icon, string $label, int $value): string {
        return sprintf(
            '<div class="rma-gov-card"><span class="icon">%s</span><div><strong>%s</strong><span class="rma-muted">%s</span></div></div>',
            $this->svg_icon($icon),
            esc_html((string) $value),
            esc_html($label)
        );
    }

    private function status_label(string $status): string {
        $labels = [
            'aprovado' => 'Aprovado',
            'recusado' => 'Recusado',
            'suspenso' => 'Suspenso',
            'em_analise' => 'Em análise',
            'pendente' => 'Aguardando',
        ];

        return $labels[$status] ?? 'Aguardando';
    }

    private function status_icon(string $status): string {
        if ($status === 'aprovado') {
            return $this->svg_icon('approve');
        }

        if ($status === 'recusado') {
            return $this->svg_icon('reject');
        }

        if ($status === 'em_analise') {
            return $this->svg_icon('analysis');
        }

        if ($status === 'suspenso') {
            return $this->svg_icon('suspended');
        }

        return $this->svg_icon('pending');
    }

    private function svg_icon(string $name): string {
        $icons = [
            'approve' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 7L10 17l-5-5" stroke="#0f9f6f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'reject' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" stroke="#ce3f4a" stroke-width="2" stroke-linecap="round"/></svg>',
            'pending' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8" stroke="#d78d11" stroke-width="2"/><path d="M12 8v5l3 2" stroke="#d78d11" stroke-width="2" stroke-linecap="round"/></svg>',
            'analysis' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 5h9M10 12h9M10 19h9" stroke="#d78d11" stroke-width="2" stroke-linecap="round"/><circle cx="6" cy="5" r="1.5" fill="#d78d11"/><circle cx="6" cy="12" r="1.5" fill="#d78d11"/><circle cx="6" cy="19" r="1.5" fill="#d78d11"/></svg>',
            'document' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" stroke="#2351a3" stroke-width="2"/><path d="M14 3v5h5" stroke="#2351a3" stroke-width="2"/></svg>',
            'suspended' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="8" stroke="#e8063c" stroke-width="2"/><path d="M8 12h8" stroke="#e8063c" stroke-width="2" stroke-linecap="round"/></svg>',
        ];

        return $icons[$name] ?? $icons['pending'];
    }

    public function register_routes(): void {
        register_rest_route('rma/v1', '/entities/(?P<id>\d+)/approve', [
            'methods' => 'POST',
            'callback' => [$this, 'approve_entity'],
            'permission_callback' => [$this, 'can_approve'],
        ]);

        register_rest_route('rma/v1', '/entities/(?P<id>\d+)/reject', [
            'methods' => 'POST',
            'callback' => [$this, 'reject_entity'],
            'permission_callback' => [$this, 'can_approve'],
        ]);

        register_rest_route('rma/v1', '/entities/(?P<id>\d+)/resubmit', [
            'methods' => 'POST',
            'callback' => [$this, 'resubmit_entity'],
            'permission_callback' => [$this, 'can_resubmit'],
        ]);
    }

    public function can_approve(): bool {
        return current_user_can('edit_others_posts');
    }


    public function can_resubmit(WP_REST_Request $request): bool {
        if (! is_user_logged_in()) {
            return false;
        }

        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return false;
        }

        if (current_user_can('edit_others_posts')) {
            return true;
        }

        return (int) get_post_field('post_author', $entity_id) === get_current_user_id();
    }


    private function governance_stage_label(int $stage): string {
        $labels = [
            1 => 'Documentação',
            2 => 'Compliance',
            3 => 'Diretoria',
        ];

        return $labels[$stage] ?? 'N/I';
    }

    private function resolve_reviewer_stage(int $user_id): int {
        if ($user_id <= 0) {
            return 0;
        }

        $user = get_userdata($user_id);
        if (! $user instanceof WP_User) {
            return 0;
        }

        if (in_array('administrator', (array) $user->roles, true)) {
            return 3;
        }

        if (in_array('editor', (array) $user->roles, true)) {
            return 2;
        }

        if (user_can($user, 'edit_others_posts')) {
            return 1;
        }

        return 0;
    }

    private function current_required_stage(array $approvals): int {
        $stages = [];
        foreach ($approvals as $entry) {
            $stage = (int) ($entry['stage'] ?? 0);
            if ($stage > 0) {
                $stages[$stage] = true;
            }
        }

        if (! empty($stages[1]) && ! empty($stages[2]) && ! empty($stages[3])) {
            return 0;
        }

        if (empty($stages[1])) {
            return 1;
        }

        if (empty($stages[2])) {
            return 2;
        }

        return 3;
    }

    public function approve_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $status = $this->normalized_governance_status($entity_id);
        if ($status === 'aprovado') {
            return new WP_REST_Response(['message' => 'Entidade já aprovada.'], 409);
        }

        if ($status === 'recusado') {
            return new WP_REST_Response([
                'message' => 'Entidade recusada deve ser reenviada antes de novos aceites.',
                'current_status' => $status,
            ], 409);
        }

        if (! in_array($status, ['pendente', 'em_analise'], true)) {
            return new WP_REST_Response([
                'message' => 'Status de governança inválido para aceite.',
                'current_status' => $status,
            ], 409);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_REST_Response(['message' => 'Usuário não autenticado.'], 401);
        }

        $reviewer_stage = $this->resolve_reviewer_stage($user_id);
        if ($reviewer_stage <= 0) {
            return new WP_REST_Response(['message' => 'Usuário sem papel habilitado na matriz de aprovação.'], 403);
        }

        $author_id = (int) get_post_field('post_author', $entity_id);
        if ($author_id > 0 && $author_id === $user_id) {
            return new WP_REST_Response([
                'message' => 'Auto-aceite não é permitido para a própria entidade.',
                'current_status' => $status,
            ], 409);
        }

        $approvals = get_post_meta($entity_id, 'governance_approvals', true);
        $approvals = is_array($approvals) ? $approvals : [];

        foreach ($approvals as $entry) {
            if ((int) ($entry['user_id'] ?? 0) === $user_id) {
                return new WP_REST_Response(['message' => 'Usuário já registrou aceite nesta entidade.'], 409);
            }
        }

        if (count($approvals) >= 3) {
            return new WP_REST_Response(['message' => 'Limite de 3 aceites já atingido.'], 409);
        }

        $required_stage = $this->current_required_stage($approvals);
        if ($required_stage <= 0) {
            return new WP_REST_Response(['message' => 'Aprovação já concluída.'], 409);
        }

        if ($reviewer_stage !== $required_stage) {
            return new WP_REST_Response([
                'message' => 'Esta aprovação exige a etapa ' . $required_stage . ' (' . $this->governance_stage_label($required_stage) . ').',
                'required_stage' => $required_stage,
                'reviewer_stage' => $reviewer_stage,
            ], 409);
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $comment = $this->limit_text(sanitize_textarea_field((string) $request->get_param('comment')), 1000);

        $approvals[] = [
            'user_id' => $user_id,
            'datetime' => current_time('mysql', true),
            'ip' => $ip,
            'comment' => $comment,
            'stage' => $reviewer_stage,
        ];

        $new_status = count($approvals) >= 3 ? 'aprovado' : 'em_analise';
        update_post_meta($entity_id, 'governance_approvals', $approvals);
        update_post_meta($entity_id, 'governance_status', $new_status);
        delete_post_meta($entity_id, 'governance_rejection_reason');

        $this->append_audit_log($entity_id, 'approve', [
            'user_id' => $user_id,
            'ip' => $ip,
            'comment' => $comment,
            'approvals_count' => count($approvals),
            'stage' => $reviewer_stage,
            'required_stage' => $required_stage,
        ]);

        if ($new_status === 'aprovado') {
            wp_update_post([
                'ID' => $entity_id,
                'post_status' => 'publish',
            ]);
            do_action('rma/entity_approved', $entity_id, $approvals);
        } else {
            wp_update_post([
                'ID' => $entity_id,
                'post_status' => 'draft',
            ]);
        }

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'approvals_count' => count($approvals),
            'governance_status' => $new_status,
        ]);
    }

    public function reject_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $status = $this->normalized_governance_status($entity_id);
        if ($status === 'aprovado') {
            return new WP_REST_Response(['message' => 'Entidade aprovada não pode ser recusada diretamente.'], 409);
        }

        if ($status === 'recusado') {
            return new WP_REST_Response([
                'message' => 'Entidade já está recusada. Use o reenvio para reiniciar o ciclo.',
                'current_status' => $status,
            ], 409);
        }

        if (! in_array($status, ['pendente', 'em_analise'], true)) {
            return new WP_REST_Response([
                'message' => 'Status de governança inválido para recusa.',
                'current_status' => $status,
            ], 409);
        }

        $reason = $this->limit_text(sanitize_textarea_field((string) $request->get_param('reason')), 1000);
        if ($reason === '') {
            return new WP_REST_Response(['message' => 'Motivo da recusa é obrigatório.'], 422);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_REST_Response(['message' => 'Usuário não autenticado.'], 401);
        }
        update_post_meta($entity_id, 'governance_status', 'recusado');
        update_post_meta($entity_id, 'governance_rejection_reason', $reason);
        update_post_meta($entity_id, 'governance_approvals', []);

        wp_update_post([
            'ID' => $entity_id,
            'post_status' => 'draft',
        ]);

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        $this->append_audit_log($entity_id, 'reject', [
            'user_id' => $user_id,
            'ip' => $ip,
            'reason' => $reason,
        ]);

        do_action('rma/entity_rejected', $entity_id, $reason);

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'governance_status' => 'recusado',
            'reason' => $reason,
        ]);
    }


    public function resubmit_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $current_status = $this->normalized_governance_status($entity_id);
        if ($current_status !== 'recusado') {
            return new WP_REST_Response([
                'message' => 'Somente entidades recusadas podem ser reenviadas.',
                'current_status' => $current_status,
            ], 409);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_REST_Response(['message' => 'Usuário não autenticado.'], 401);
        }
        update_post_meta($entity_id, 'governance_status', 'pendente');
        update_post_meta($entity_id, 'governance_approvals', []);
        delete_post_meta($entity_id, 'governance_rejection_reason');

        wp_update_post([
            'ID' => $entity_id,
            'post_status' => 'draft',
        ]);

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        $this->append_audit_log($entity_id, 'resubmit', [
            'user_id' => $user_id,
            'ip' => $ip,
        ]);

        do_action('rma/entity_resubmitted', $entity_id);

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'governance_status' => 'pendente',
            'message' => 'Entidade reenviada para análise.',
        ]);
    }



    private function suspend_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || ! current_user_can('edit_others_posts')) {
            return new WP_REST_Response(['message' => 'Usuário sem permissão para suspender entidade.'], 403);
        }

        update_post_meta($entity_id, 'governance_status', 'suspenso');

        wp_update_post([
            'ID' => $entity_id,
            'post_status' => 'draft',
        ]);

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $this->append_audit_log($entity_id, 'suspend', [
            'user_id' => $user_id,
            'ip' => $ip,
        ]);

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'governance_status' => 'suspenso',
        ]);
    }

    private function delete_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || ! current_user_can('delete_others_posts')) {
            return new WP_REST_Response(['message' => 'Usuário sem permissão para deletar entidade.'], 403);
        }

        $this->purge_private_documents($entity_id);
        $deleted = wp_delete_post($entity_id, true);

        if (! $deleted) {
            return new WP_REST_Response(['message' => 'Falha ao deletar entidade.'], 500);
        }

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'deleted' => true,
        ]);
    }

    private function purge_private_documents(int $entity_id): void {
        $docs = get_post_meta($entity_id, 'entity_documents', true);
        $docs = is_array($docs) ? $docs : [];

        foreach ($docs as $doc) {
            $path = (string) ($doc['path'] ?? '');
            if ($path === '') {
                continue;
            }

            $real = realpath($path);
            if ($real === false || ! is_file($real)) {
                continue;
            }

            @unlink($real);
        }
    }



    private function force_approve_entity(WP_REST_Request $request): WP_REST_Response {
        $entity_id = (int) $request->get_param('id');
        if (get_post_type($entity_id) !== self::CPT) {
            return new WP_REST_Response(['message' => 'Entidade inválida.'], 404);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0 || ! current_user_can('edit_others_posts')) {
            return new WP_REST_Response(['message' => 'Usuário sem permissão para liberar acesso.'], 403);
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $approvals = [
            [
                'user_id' => $user_id,
                'datetime' => current_time('mysql', true),
                'ip' => $ip,
                'comment' => 'Aprovação administrativa direta (etapa 1).',
                'stage' => 1,
            ],
            [
                'user_id' => $user_id,
                'datetime' => current_time('mysql', true),
                'ip' => $ip,
                'comment' => 'Aprovação administrativa direta (etapa 2).',
                'stage' => 2,
            ],
            [
                'user_id' => $user_id,
                'datetime' => current_time('mysql', true),
                'ip' => $ip,
                'comment' => 'Aprovação administrativa direta (etapa 3).',
                'stage' => 3,
            ],
        ];

        update_post_meta($entity_id, 'governance_approvals', $approvals);
        update_post_meta($entity_id, 'governance_status', 'aprovado');
        delete_post_meta($entity_id, 'governance_rejection_reason');

        wp_update_post([
            'ID' => $entity_id,
            'post_status' => 'publish',
        ]);

        $this->append_audit_log($entity_id, 'force_approve', [
            'user_id' => $user_id,
            'ip' => $ip,
        ]);

        do_action('rma/entity_approved', $entity_id, $approvals);

        return new WP_REST_Response([
            'entity_id' => $entity_id,
            'governance_status' => 'aprovado',
            'approvals_count' => count($approvals),
        ]);
    }



    public function render_entity_governance_documents(): string {
        if (! is_user_logged_in()) {
            return '<p>Faça login para visualizar os documentos da governança.</p>';
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada ao usuário atual.</p>';
        }

        $status = $this->normalized_governance_status($entity_id);
        $rejection_reason = (string) get_post_meta($entity_id, 'governance_rejection_reason', true);
        $documents = get_post_meta($entity_id, 'entity_documents', true);
        $documents = is_array($documents) ? $documents : [];

        ob_start();
        echo $this->render_entity_governance_styles();
        ?>
        <div class="rma-gov-entity-wrap">
            <?php echo $this->render_entity_governance_nav('rma-governanca-documentos'); ?>
            <div class="rma-gov-entity-head">
                <h3>Governança da Entidade</h3>
                <p>Documentos enviados no cadastro para análise da Equipe RMA.</p>
            </div>

            <div class="rma-gov-entity-meta">
                <div class="rma-gov-entity-card"><small>Entidade</small><strong><?php echo esc_html(get_the_title($entity_id)); ?></strong></div>
                <div class="rma-gov-entity-card"><small>Status da governança</small><span class="rma-badge <?php echo esc_attr($status); ?>"><?php echo esc_html(strtoupper(str_replace('_', ' ', $status))); ?></span></div>
                <div class="rma-gov-entity-card"><small>Documentos enviados</small><strong><?php echo esc_html((string) count($documents)); ?></strong></div>
            </div>

            <?php if ($rejection_reason !== '') : ?>
                <div class="rma-gov-entity-alert"><strong>Motivo da reprovação:</strong> <?php echo esc_html($rejection_reason); ?></div>
            <?php endif; ?>

            <div class="rma-gov-entity-table-wrap">
                <table class="rma-gov-entity-table">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th>Enviado em</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($documents)) : ?>
                        <tr><td colspan="4">Nenhum documento enviado no cadastro.</td></tr>
                    <?php else : ?>
                        <?php foreach ($documents as $doc) :
                            $doc_id = sanitize_text_field((string) ($doc['id'] ?? ''));
                            $name = sanitize_text_field((string) ($doc['name'] ?? 'Documento sem nome'));
                            $type = sanitize_key((string) ($doc['document_type'] ?? 'geral'));
                            $uploaded_at = sanitize_text_field((string) ($doc['uploaded_at'] ?? ''));
                            $download = $doc_id !== '' ? rest_url(sprintf('rma/v1/entities/%d/documents/%s', $entity_id, rawurlencode($doc_id))) : '';
                        ?>
                        <tr>
                            <td><?php echo esc_html($name); ?></td>
                            <td><?php echo esc_html($type !== '' ? $type : 'geral'); ?></td>
                            <td><?php echo esc_html($uploaded_at !== '' ? $uploaded_at : '—'); ?></td>
                            <td>
                                <?php if ($download !== '') : ?>
                                    <a class="rma-gov-entity-link" href="<?php echo esc_url($download); ?>" target="_blank" rel="noopener">Baixar</a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function get_entity_id_by_author(int $user_id): int {
        if ($user_id <= 0) {
            return 0;
        }

        $ids = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft', 'pending'],
            'fields' => 'ids',
            'author' => $user_id,
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return (int) ($ids[0] ?? 0);
    }

    public function render_entity_governance_pendencias(): string {
        if (! is_user_logged_in()) {
            return '<p>Faça login para visualizar pendências de governança.</p>';
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada ao usuário atual.</p>';
        }

        $status = $this->normalized_governance_status($entity_id);
        $reason = (string) get_post_meta($entity_id, 'governance_rejection_reason', true);
        $docs = get_post_meta($entity_id, 'entity_documents', true);
        $docs = is_array($docs) ? $docs : [];

        $pending = [];
        if ($status !== 'aprovado') {
            $pending[] = 'Governança em análise pela Equipe RMA.';
        }
        if (count($docs) === 0) {
            $pending[] = 'Nenhum documento enviado. Envie seus documentos para continuar a avaliação.';
        }
        if ($reason !== '') {
            $pending[] = 'Existem ajustes solicitados pela RMA: ' . $reason;
        }

        ob_start();
        echo $this->render_entity_governance_styles();
        echo '<div class="rma-gov-entity-wrap">';
        echo $this->render_entity_governance_nav('rma-governanca-pendencias');
        echo '<div class="rma-gov-entity-head"><h3>Pendências da Governança</h3><p>Acompanhe os pontos pendentes antes da liberação final.</p></div>';
        echo '<div class="rma-gov-entity-table-wrap"><table class="rma-gov-entity-table"><thead><tr><th>Item</th><th>Status</th></tr></thead><tbody>';
        if (empty($pending)) {
            echo '<tr><td>Nenhuma pendência encontrada.</td><td><span class="rma-badge aprovado">OK</span></td></tr>';
        } else {
            foreach ($pending as $item) {
                echo '<tr><td>' . esc_html($item) . '</td><td><span class="rma-badge pendente">PENDENTE</span></td></tr>';
            }
        }
        echo '</tbody></table></div></div>';
        return (string) ob_get_clean();
    }


    public function render_entity_governance_status(): string {
        if (! is_user_logged_in()) {
            return '<p>Faça login para visualizar o status da filiação.</p>';
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada ao usuário atual.</p>';
        }

        $governance_status = $this->normalized_governance_status($entity_id);
        $finance_status = (string) get_post_meta($entity_id, 'finance_status', true);
        $docs_status = (string) get_post_meta($entity_id, 'documentos_status', true);
        $docs = get_post_meta($entity_id, 'entity_documents', true);
        $docs = is_array($docs) ? $docs : [];
        $due_at = (string) get_post_meta($entity_id, 'anuidade_vencimento', true);
        if ($due_at === '') {
            $due_at = (string) get_post_meta($entity_id, 'finance_due_at', true);
        }

        ob_start();
        echo $this->render_entity_governance_styles();
        echo '<div class="rma-gov-entity-wrap">';
        echo $this->render_entity_governance_nav('rma-governanca-status');
        echo '<div class="rma-gov-entity-head"><h3>Status da Filiação</h3><p>Acompanhe o status atual da sua entidade junto à RMA.</p></div>';
        echo '<div class="rma-gov-entity-meta">';
        echo '<div class="rma-gov-entity-card"><small>Governança</small>' . $this->format_entity_badge($governance_status) . '</div>';
        echo '<div class="rma-gov-entity-card"><small>Financeiro</small>' . $this->format_entity_badge($finance_status !== '' ? $finance_status : 'pendente') . '</div>';
        echo '<div class="rma-gov-entity-card"><small>Documentação</small><strong>' . esc_html($docs_status !== '' ? strtoupper($docs_status) : 'PENDENTE') . '</strong></div>';
        echo '<div class="rma-gov-entity-card"><small>Documentos enviados</small><strong>' . esc_html((string) count($docs)) . '</strong></div>';
        echo '</div>';
        echo '<div class="rma-gov-entity-table-wrap"><table class="rma-gov-entity-table"><tbody>';
        echo '<tr><th>Entidade</th><td>' . esc_html(get_the_title($entity_id)) . '</td></tr>';
        echo '<tr><th>Vencimento da anuidade</th><td>' . esc_html($due_at !== '' ? $due_at : 'Não definido') . '</td></tr>';
        echo '<tr><th>Situação de filiação</th><td>' . ($governance_status === 'aprovado' && $finance_status === 'adimplente' ? '<span class="rma-badge aprovado">ATIVA</span>' : '<span class="rma-badge pendente">EM REGULARIZAÇÃO</span>') . '</td></tr>';
        $contact_sections = $this->entity_contact_sections($entity_id);
        foreach ($contact_sections as $section_label => $items) {
            $lines = [];
            foreach ($items as $item_label => $item_value) {
                $lines[] = '<span class="rma-muted">' . esc_html($item_label) . ':</span> ' . esc_html($item_value);
            }
            echo '<tr><th>' . esc_html($section_label) . '</th><td>' . implode('<br>', $lines) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '</div>';

        return (string) ob_get_clean();
    }

    public function render_entity_governance_upload(): string {
        if (! is_user_logged_in()) {
            return '<p>Faça login para enviar documentos para a governança.</p>';
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada ao usuário atual.</p>';
        }

        $notice = isset($_GET['rma_doc_notice']) ? sanitize_text_field((string) wp_unslash($_GET['rma_doc_notice'])) : '';
        $type = isset($_GET['rma_doc_notice_type']) ? sanitize_key((string) wp_unslash($_GET['rma_doc_notice_type'])) : '';

        ob_start();
        echo $this->render_entity_governance_styles();
        echo '<div class="rma-gov-entity-wrap">';
        echo $this->render_entity_governance_nav('rma-governanca-upload');
        echo '<div class="rma-gov-entity-head"><h3>Enviar Documentos</h3><p>Envie novos documentos para análise da Equipe RMA.</p></div>';
        if ($notice !== '') {
            echo '<div class="rma-gov-entity-alert" style="background:' . ($type === 'success' ? 'rgba(15,159,111,.09);border-color:rgba(15,159,111,.25);color:#0d7d58' : 'rgba(206,63,74,.09);border-color:rgba(206,63,74,.25);color:#b2303c') . '">' . esc_html($notice) . '</div>';
        }
        echo '<form method="post" enctype="multipart/form-data" class="rma-gov-entity-table-wrap" style="padding:14px">';
        wp_nonce_field('rma_entity_upload_document', 'rma_entity_upload_document_nonce');
        echo '<input type="hidden" name="rma_entity_upload_action" value="1" />';
        echo '<p><label>Tipo do documento</label><br/><input type="text" name="document_type" placeholder="ex.: estatuto, ata, comprovante" style="width:100%;max-width:480px;border:1px solid rgba(15,23,42,.2);border-radius:10px;padding:8px 10px"></p>';
        echo '<p><label>Arquivo</label><br/><input type="file" name="entity_document_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required></p>';
        echo '<p><button type="submit" style="border:none;border-radius:10px;padding:10px 14px;background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;cursor:pointer">Enviar documento</button></p>';
        echo '</form></div>';
        return (string) ob_get_clean();
    }

    public function handle_entity_document_upload(): void {
        if (! is_user_logged_in() || ! isset($_POST['rma_entity_upload_action'])) {
            return;
        }

        $nonce = isset($_POST['rma_entity_upload_document_nonce']) ? sanitize_text_field((string) wp_unslash($_POST['rma_entity_upload_document_nonce'])) : '';
        if (! wp_verify_nonce($nonce, 'rma_entity_upload_document')) {
            $this->redirect_entity_dashboard_notice('Falha de segurança ao enviar documento.', 'error');
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            $this->redirect_entity_dashboard_notice('Entidade não encontrada para este usuário.', 'error');
        }

        if (empty($_FILES['entity_document_file']['name'])) {
            $this->redirect_entity_dashboard_notice('Selecione um arquivo para envio.', 'error');
        }

        $file = $_FILES['entity_document_file'];
        $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $name = sanitize_file_name((string) ($file['name'] ?? 'documento'));
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (! in_array($ext, $allowed_ext, true)) {
            $this->redirect_entity_dashboard_notice('Formato inválido. Use PDF, JPG, PNG, DOC ou DOCX.', 'error');
        }

        $upload_dir = wp_upload_dir();
        $base = trailingslashit($upload_dir['basedir']) . 'rma-private/' . $entity_id;
        if (! wp_mkdir_p($base)) {
            $this->redirect_entity_dashboard_notice('Não foi possível preparar a pasta de upload.', 'error');
        }

        $unique_name = wp_unique_filename($base, $name);
        $target = trailingslashit($base) . $unique_name;
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || ! is_uploaded_file($tmp) || ! move_uploaded_file($tmp, $target)) {
            $this->redirect_entity_dashboard_notice('Falha ao salvar o arquivo enviado.', 'error');
        }

        $docs = get_post_meta($entity_id, 'entity_documents', true);
        $docs = is_array($docs) ? $docs : [];

        $docs[] = [
            'id' => wp_generate_uuid4(),
            'name' => $unique_name,
            'path' => $target,
            'document_type' => sanitize_key((string) ($_POST['document_type'] ?? 'geral')),
            'uploaded_at' => current_time('mysql', true),
            'uploaded_by' => get_current_user_id(),
            'mime_type' => sanitize_text_field((string) ($file['type'] ?? '')),
            'size' => (int) ($file['size'] ?? 0),
            'is_public' => isset($_POST['document_public']) ? '1' : '0',
        ];

        update_post_meta($entity_id, 'entity_documents', $docs);
        update_post_meta($entity_id, 'documentos_status', 'enviado');
        update_post_meta($entity_id, 'governance_status', 'em_analise');

        $this->append_audit_log($entity_id, 'entity_document_upload', [
            'user_id' => get_current_user_id(),
            'count' => count($docs),
        ]);

        $this->push_entity_notification($entity_id, 'governanca', 'Documento enviado', 'Recebemos seu novo documento. A Equipe RMA irá analisar em breve.', add_query_arg('ext', 'rma-governanca-documentos', home_url('/dashboard/')));

        $this->redirect_entity_dashboard_notice('Documento enviado com sucesso para análise da RMA.', 'success');
    }

    public function inject_entity_dashboard_governance_menu(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }

        ?>
        <script>
        (function(){
            var titles = Array.prototype.slice.call(document.querySelectorAll('.menu-title'));
            if (!titles.length) { return; }

            var findToggle = function(expected){
                expected = String(expected || '').toLowerCase();
                return titles.find(function(node){
                    var txt = (node.textContent || '').trim().toLowerCase();
                    return txt === expected;
                }) || null;
            };

            var currentUrl = new URL(window.location.href);
            var activeExt = (currentUrl.searchParams.get('ext') || '').toLowerCase();
            var extUrl = function(ext){
                var u = new URL(currentUrl.toString());
                u.searchParams.set('ext', ext);
                return u.toString();
            };
            var linkClass = function(ext){ return 'nav-link' + (activeExt === ext ? ' active' : ''); };

            var mountSubmenu = function(toggleNode, exts, html){
                if (!toggleNode) { return; }
                var navLink = toggleNode.closest('a.nav-link');
                if (!navLink) { return; }
                var collapseId = navLink.getAttribute('href');
                if (!collapseId || collapseId.charAt(0) !== '#') { return; }
                var collapse = document.querySelector(collapseId);
                if (!collapse) { return; }
                collapse.innerHTML = html;
                if (exts.indexOf(activeExt) !== -1) {
                    collapse.classList.add('show');
                    navLink.classList.add('active');
                    navLink.setAttribute('aria-expanded', 'true');
                }
            };

            mountSubmenu(
                findToggle('governança') || findToggle('governanca'),
                ['rma-governanca-documentos','rma-governanca-pendencias','rma-governanca-status','rma-governanca-upload'],
                [
                    '<ul class="nav flex-column sub-menu">',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-documentos')+'" href="'+extUrl('rma-governanca-documentos')+'">Documentos Enviados</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-pendencias')+'" href="'+extUrl('rma-governanca-pendencias')+'">Pendências</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-status')+'" href="'+extUrl('rma-governanca-status')+'">Status</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-upload')+'" href="'+extUrl('rma-governanca-upload')+'">Enviar Documentos</a></li>',
                    '</ul>'
                ].join('')
            );

            mountSubmenu(
                findToggle('financeiro'),
                ['rma-financeiro-visao-geral','rma-financeiro-cobranca','rma-financeiro-faturas','rma-financeiro-historico','rma-financeiro-pix','rma-financeiro-relatorios'],
                [
                    '<ul class="nav flex-column sub-menu">',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-visao-geral')+'" href="'+extUrl('rma-financeiro-visao-geral')+'">Visão Geral</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-cobranca')+'" href="'+extUrl('rma-financeiro-cobranca')+'">Minha Cobrança</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-faturas')+'" href="'+extUrl('rma-financeiro-faturas')+'">Minhas Faturas</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-historico')+'" href="'+extUrl('rma-financeiro-historico')+'">Histórico</a></li>',
                    '</ul>'
                ].join('')
            );

            var documentsToggle = findToggle('documentos');
            if (documentsToggle) {
                var documentsItem = documentsToggle.closest('li.nav-item');
                if (documentsItem) {
                    documentsItem.style.display = 'none';
                }
            }

            var supportToggle = findToggle('suporte');
            if (supportToggle) {
                var supportLink = supportToggle.closest('a.nav-link');
                if (supportLink) {
                    supportLink.setAttribute('href', extUrl('saved-services'));
                    var supportIcon = supportLink.querySelector('.menu-icon, i');
                    if (supportIcon) {
                        supportIcon.className = 'menu-icon rma-support-icon';
                        supportIcon.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block"><path d="M4 6.5C4 5.67 4.67 5 5.5 5h13c.83 0 1.5.67 1.5 1.5v8c0 .83-.67 1.5-1.5 1.5H9l-4.2 3.1c-.33.24-.8.01-.8-.4V16.5C4 15.67 4.67 15 5.5 15" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 9h8M8 12h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
                    }
                    if (['saved-services','rma-suporte','rma-suporte-novo','rma-suporte-tickets'].indexOf(activeExt) !== -1) {
                        supportLink.classList.add('active');
                    }
                }
            }
        })();
        </script>
        <?php
    }


    public function disable_global_preload_overlays(): void {
        if (is_admin()) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        $is_dashboard_route = stripos($request_uri, '/dashboard') !== false;

        echo '<style id="rma-disable-global-preload">.rma-db-preload .main-panel .content-wrapper,.rma-db-preload .main-content .content-wrapper,.rma-db-preload .dashboard-content-area{visibility:visible!important}.rma-db-preload body:before,.rma-db-preload body:after,.preloader,.loader,.loader-bg,.page-loader,.loading-screen,.loading-overlay,.loader-wrapper,.site-preloader,.se-pre-con,.animsition-loading,.pace,.pace-inactive,.spinner,.spinner-border{display:none!important;opacity:0!important;visibility:hidden!important;pointer-events:none!important}</style>';
        echo '<script>(function(){try{document.documentElement.classList.remove("rma-db-preload");var sels=[".preloader",".loader",".loader-bg",".page-loader",".loading-screen",".loading-overlay",".loader-wrapper",".site-preloader",".se-pre-con",".animsition-loading",".pace",".spinner",".spinner-border"];sels.forEach(function(sel){document.querySelectorAll(sel).forEach(function(el){el.style.display="none";if(el.remove){el.remove();}});});}catch(e){}})();</script>';

        if (! is_user_logged_in() || ! $is_dashboard_route) {
            return;
        }

        echo '<style id="rma-entity-dashboard-lock-style">.rma-entity-dashboard-lock .main-panel .content-wrapper,.rma-entity-dashboard-lock .main-content .content-wrapper,.rma-entity-dashboard-lock .dashboard-content-area{opacity:0!important;transition:opacity .16s ease}</style>';
        echo '<script>(function(){try{var root=document.documentElement;var removeLock=function(){root.classList.remove("rma-entity-dashboard-lock");};root.classList.add("rma-entity-dashboard-lock");window.setTimeout(removeLock,2800);window.addEventListener("load",removeLock,{once:true});window.addEventListener("pageshow",removeLock);}catch(e){}})();</script>';

        $current_user_id = get_current_user_id();
        $entity_id = (int) get_user_meta($current_user_id, 'employer_id', true);
        if ($entity_id <= 0) {
            return;
        }

        $ext = isset($_GET['ext']) ? sanitize_key((string) wp_unslash($_GET['ext'])) : '';
        $is_home_index = $ext === '' || in_array($ext, ['dashboard', 'index'], true);

        echo '<style id="rma-hide-legacy-dashboard-widgets">.info-boxes,.all-proposals,.most-viewed-widget,.current-package-widget,.card.grid-margin.all-proposals,.card.grid-margin.most-viewed-widget,.card.grid-margin.tips.package-info.current-package-widget,.content-wrapper>.row:first-child{display:none!important}</style>';

        if (! $is_home_index) {
            return;
        }

        echo '<script>(function(){var tries=0,max=60;function hasRmaHome(){return !!document.getElementById("rma-neon-analytics")||!!document.getElementById("rma-smart-home-fallback");}function prune(){if(hasRmaHome()){return true;}[".info-boxes",".all-proposals",".most-viewed-widget",".current-package-widget"].forEach(function(sel){document.querySelectorAll(sel).forEach(function(node){var card=node.closest(".card,.col-xl-4,.col-xl-8,.col-lg-12,.col-md-12,.row")||node;if(card&&card.parentNode){card.parentNode.removeChild(card);}});});document.querySelectorAll(".content-wrapper .row").forEach(function(row){var txt=(row.textContent||"").toLowerCase();if(txt.indexOf("welcome back employer")!==-1||txt.indexOf("profile views")!==-1||txt.indexOf("recent proposals")!==-1||txt.indexOf("most viewed projects")!==-1||txt.indexOf("current plan detail")!==-1||txt.indexOf("bem-vindo de volta")!==-1||(txt.indexOf("/ painel de controle")!==-1&&txt.indexOf("bem-vindo a rma")==-1)){row.remove();}});return false;}var timer=window.setInterval(function(){tries++;if(prune()||tries>=max){window.clearInterval(timer);}},80);if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",prune);}else{prune();}})();</script>';
    }


    public function inject_entity_dashboard_preload_guard(): void {
        return;
    }

    public function inject_entity_dashboard_home_index_cards(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }

        $ext = isset($_GET['ext']) ? sanitize_key((string) wp_unslash($_GET['ext'])) : '';
        if ($ext !== '' && ! in_array($ext, ['dashboard', 'index'], true)) {
            return;
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return;
        }

        $docs = get_post_meta($entity_id, 'entity_documents', true);
        $docs = is_array($docs) ? $docs : [];
        $documents_count = count($docs);

        $gov_status = $this->normalized_governance_status($entity_id);
        $gov_pending = $gov_status === 'aprovado' ? 0 : 1;

        $finance_status = sanitize_key((string) get_post_meta($entity_id, 'finance_status', true));
        if ($finance_status === '') {
            $finance_status = 'inadimplente';
        }
        $finance_open = $finance_status === 'adimplente' ? 0 : 1;

        $tickets = get_post_meta($entity_id, 'rma_support_tickets', true);
        $tickets = is_array($tickets) ? $tickets : [];
        $open_tickets = 0;
        foreach ($tickets as $ticket) {
            if (sanitize_key((string) ($ticket['status'] ?? 'aberto')) === 'aberto') {
                $open_tickets++;
            }
        }

        $cards = [
            ['title' => 'Documentos enviados', 'value' => (string) $documents_count, 'url' => add_query_arg('ext', 'rma-governanca-documentos', home_url('/dashboard/'))],
            ['title' => 'Pendências Documentais', 'value' => (string) $gov_pending, 'url' => add_query_arg('ext', 'rma-governanca-pendencias', home_url('/dashboard/'))],
            ['title' => 'Status financeiro', 'value' => $finance_open > 0 ? 'Pendente' : 'Em dia', 'url' => add_query_arg('ext', 'rma-financeiro-cobranca', home_url('/dashboard/'))],
            ['title' => 'Tickets de suporte abertos', 'value' => (string) $open_tickets, 'url' => add_query_arg('ext', 'rma-suporte-tickets', home_url('/dashboard/'))],
        ];

        $analytics_cards = [
            ['title' => 'Usuários online', 'value' => '2', 'bg' => '#45aaf2', 'tone' => 'dark'],
            ['title' => 'Exibições hoje', 'value' => '185', 'bg' => '#e6007e', 'tone' => 'dark'],
            ['title' => 'Visitas nos últimos 15 dias', 'value' => '4.378', 'bg' => '#00e6d2', 'tone' => 'dark'],
            ['title' => 'Navegadores mais usados', 'value' => [
                ['label' => 'Chrome', 'value' => 31.0],
                ['label' => 'Safari', 'value' => 10.0],
                ['label' => 'Outros', 'value' => 54.0],
            ], 'bg' => '#ff6b00', 'tone' => 'dark'],
            ['title' => 'Sistemas operacionais mais usados', 'value' => [
                ['label' => 'Windows', 'value' => 20.0],
                ['label' => 'iOS', 'value' => 9.0],
                ['label' => 'Outros', 'value' => 54.0],
            ], 'bg' => '#7b61ff', 'tone' => 'dark'],
        ];

        $analytics_charts = [
            'visitas_15' => [220, 190, 230, 245, 210, 275, 260, 248, 320, 290, 340, 315, 360, 330, 305],
            'usuarios_15' => [2, 1, 1, 2, 1, 2, 2, 3, 2, 2, 3, 4, 3, 4, 3],
            'por_pais' => [
                ['label' => 'Brasil', 'value' => 34.0],
                ['label' => 'Argentina', 'value' => 8.0],
                ['label' => 'Chile', 'value' => 6.0],
                ['label' => 'Outros', 'value' => 52.0],
            ],
            'plataformas' => [
                ['label' => 'Windows', 'value' => 20.0],
                ['label' => 'iOS', 'value' => 9.0],
                ['label' => 'Android', 'value' => 11.0],
                ['label' => 'macOS', 'value' => 6.0],
                ['label' => 'Outros', 'value' => 54.0],
            ],
            'navegadores' => [
                ['label' => 'Chrome', 'value' => 31.0],
                ['label' => 'Safari', 'value' => 10.0],
                ['label' => 'Firefox', 'value' => 5.0],
                ['label' => 'Outros', 'value' => 54.0],
            ],
        ];

        ?>
        <script>
        (function(){
            var cards = <?php echo wp_json_encode($cards); ?>;
            var analyticsCards = <?php echo wp_json_encode($analytics_cards); ?>;
            var chartData = <?php echo wp_json_encode($analytics_charts); ?>;
            var aliases = ['projetos publicados','projetos em destaque','projetos em andamento','projetos concluídos'];
            var palette = ['#E6007E','#FF6B00','#FFD400','#00E6D2','#7B61FF','#00A3FF'];

            function fmtPct(v){ return Number(v).toFixed(1).replace('.', ',') + '%'; }

            function renderTopValue(value){
                if (Array.isArray(value)) {
                    return value.map(function(item){ return '<span class=\"rma-neon-line\">'+item.label+' '+fmtPct(item.value)+'</span>'; }).join('');
                }
                return '<span class=\"rma-neon-line\">'+String(value || '')+'</span>';
            }

            function renderLine(values, stroke) {
                var w = 320, h = 180, p = 16;
                var min = Math.min.apply(null, values), max = Math.max.apply(null, values);
                var range = Math.max(1, max - min);
                var step = (w - p * 2) / Math.max(1, values.length - 1);
                var pts = values.map(function(v, i){
                    var x = p + i * step;
                    var y = h - p - ((v - min) / range) * (h - p * 2);
                    return x.toFixed(1) + ',' + y.toFixed(1);
                }).join(' ');
                var sum = values.reduce(function(a,b){ return a + Number(b || 0); }, 0);
                var avg = values.length ? (sum / values.length) : 0;
                var last = values.length ? values[values.length - 1] : 0;
                return '<svg viewBox=\"0 0 '+w+' '+h+'\" width=\"100%\" height=\"180\" aria-hidden=\"true\"><polyline fill=\"none\" stroke=\"'+stroke+'\" stroke-width=\"3\" points=\"'+pts+'\"/><polyline fill=\"none\" stroke=\"rgba(148,163,184,.35)\" stroke-width=\"1\" points=\"'+pts+'\"/></svg><div class=\"rma-line-meta\"><span>Mín: '+min+'</span><span>Méd: '+avg.toFixed(1).replace('.', ',')+'</span><span>Máx: '+max+'</span><span>Último: '+last+'</span></div>'; 
            }

            function renderDonut(items){
                var cx=92, cy=92, r=56, c=2*Math.PI*r, acc=0;
                var segs='';
                items.forEach(function(item,idx){
                    var frac = Math.max(0, Number(item.value)||0) / 100;
                    var len = c * frac;
                    segs += '<circle cx="'+cx+'" cy="'+cy+'" r="'+r+'" fill="none" stroke="'+palette[idx%palette.length]+'" stroke-width="24" stroke-dasharray="'+len+' '+(c-len)+'" stroke-dashoffset="'+(-acc)+'" transform="rotate(-90 '+cx+' '+cy+')"></circle>';
                    acc += len;
                });
                var legend = items.map(function(item,idx){ return '<span><i style="background:'+palette[idx%palette.length]+'"></i>'+item.label+' '+fmtPct(item.value)+'</span>'; }).join('');
                return '<div class="rma-donut-wrap"><svg viewBox="0 0 184 184" width="184" height="184">'+segs+'</svg><div class="rma-donut-legend">'+legend+'</div></div>';
            }

            function cleanupLegacyShell(){
                var legacySelectors = ['.all-proposals','.most-viewed-widget','.current-package-widget','.card.grid-margin.all-proposals','.card.grid-margin.most-viewed-widget','.card.grid-margin.tips.package-info.current-package-widget'];
                legacySelectors.forEach(function(sel){
                    document.querySelectorAll(sel).forEach(function(node){
                        var card = node.closest('.card,.col-xl-4,.col-xl-8,.col-lg-12,.col-md-12,.row') || node;
                        if (card && card.parentNode) {
                            card.parentNode.removeChild(card);
                        }
                    });
                });

                document.querySelectorAll('.content-wrapper .row').forEach(function(row){
                    var txt = (row.textContent || '').toLowerCase();
                    if (txt.indexOf('welcome back employer') !== -1 || txt.indexOf('profile views') !== -1 || txt.indexOf('recent proposals') !== -1 || txt.indexOf('most viewed projects') !== -1 || txt.indexOf('current plan detail') !== -1 || txt.indexOf('bem-vindo de volta') !== -1 || (txt.indexOf('/ painel de controle') !== -1 && txt.indexOf('bem-vindo a rma') === -1)) {
                        row.remove();
                    }
                });
            }

            function mount() {
                cleanupLegacyShell();
                var wrappers = Array.prototype.slice.call(document.querySelectorAll('.info-boxes .metric'));
                if (!wrappers.length) {
                    wrappers = Array.prototype.slice.call(document.querySelectorAll('.metric,.counter-box,.card')).filter(function(node){
                        var txt = (node.textContent || '').toLowerCase();
                        return aliases.some(function(a){ return txt.indexOf(a) !== -1; });
                    });
                }

                wrappers.slice(0, cards.length).forEach(function(wrap, i){
                    var item = cards[i] || {};
                    var titleEl = wrap.querySelector('.title,.counter-title,h6,h5,h4');
                    var numEl = wrap.querySelector('.number,.counter-value,strong,h3');
                    var linkEl = wrap.querySelector('a');
                    if (titleEl && item.title) { titleEl.textContent = item.title; titleEl.style.fontSize='18px'; titleEl.style.fontFamily='Maven Pro, Segoe UI, Arial, sans-serif'; }
                    if (numEl && typeof item.value !== 'undefined') { numEl.textContent = String(item.value); numEl.style.fontSize='18px'; numEl.style.fontFamily='Maven Pro, Segoe UI, Arial, sans-serif'; }
                    if (linkEl && item.url) { linkEl.setAttribute('href', item.url); }
                    wrap.style.fontFamily='Maven Pro, Segoe UI, Arial, sans-serif';
                    wrap.style.fontSize='18px';
                });

                var removeTitles = ['visualizações de perfil','visualizacoes de perfil','propostas recentes','projetos mais vistos','detalhe do plano atual'];
                Array.prototype.slice.call(document.querySelectorAll('.card,.dashboard-card,.widget')).forEach(function(card){
                    var heading = card.querySelector('h2,h3,h4,.card-title');
                    var txt = (heading ? heading.textContent : card.textContent || '').toLowerCase();
                    if (removeTitles.some(function(t){ return txt.indexOf(t) !== -1; })) {
                        var col = card.closest('.col-xl-4,.col-xl-8,.col-xl-9,.col-lg-4,.col-lg-8,.col-lg-9,.col-md-4,.col-md-8,.col-md-9,.col-12,.col-sm-12,.col-sm-6,.col-md-6');
                        if (col) { col.style.display = 'none'; } else { card.style.display = 'none'; }
                    }
                });

                if (!document.getElementById('rma-neon-analytics-style')) {
                    var style = document.createElement('style');
                    style.id = 'rma-neon-analytics-style';
                    style.textContent = '.rma-neon-wrap{margin:16px 0 12px;padding:0 28px;font-family:"Maven Pro",Segoe UI,Arial,sans-serif}.rma-neon-top,.rma-neon-bottom{display:grid;gap:14px}.rma-neon-top{grid-template-columns:repeat(5,minmax(220px,1fr));margin-bottom:14px}.rma-neon-bottom{grid-template-columns:repeat(5,minmax(220px,1fr))}.rma-neon-card{border-radius:18px;padding:16px;box-shadow:0 12px 30px rgba(15,23,42,.18);border:1px solid rgba(255,255,255,.35);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}.rma-neon-card.tone-dark,.rma-neon-card.tone-dark *{color:#fff !important}.rma-neon-card.tone-light,.rma-neon-card.tone-light *{color:#10253a !important}.rma-neon-title{margin:0;font-size:14px;line-height:1.25;font-weight:700;letter-spacing:0;text-transform:none}.rma-neon-value{margin:8px 0 0;font-size:16px;line-height:1.25;font-weight:700;letter-spacing:0;text-transform:none}.rma-neon-line{display:block;font-size:16px;line-height:1.25;white-space:nowrap}.rma-chart-card{background:rgba(255,255,255,.64);border:1px solid rgba(255,255,255,.7);border-radius:18px;padding:14px;box-shadow:0 12px 30px rgba(15,23,42,.10);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}.rma-chart-title{margin:0 0 10px;font-size:18px;font-weight:500;color:#777777;text-align:center;letter-spacing:0;text-transform:none}.rma-chart-plot{min-height:220px;border-radius:14px;background:linear-gradient(180deg,rgba(255,255,255,.78),rgba(229,245,255,.68));padding:8px}.rma-line-meta{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:6px}.rma-line-meta span{font-size:12px;color:#526579;background:rgba(255,255,255,.75);border:1px solid rgba(203,213,225,.8);border-radius:999px;padding:2px 8px}.rma-donut-wrap{display:grid;place-items:center;gap:10px}.rma-donut-legend{display:flex;flex-wrap:wrap;gap:8px;justify-content:center}.rma-donut-legend span{font-size:18px;color:#1f3348;display:flex;align-items:center;gap:6px}.rma-donut-legend i{width:10px;height:10px;border-radius:999px;display:inline-block}@media (max-width:1400px){.rma-neon-top,.rma-neon-bottom{grid-template-columns:repeat(2,minmax(220px,1fr));margin-top: 35px!important;}}';
                    document.head.appendChild(style);
                }

                if (!document.getElementById('rma-dashboard-home-top-style')) {
                    var topStyle = document.createElement('style');
                    topStyle.id = 'rma-dashboard-home-top-style';
                    topStyle.textContent = '.rma-home-top{margin:0 0 14px}.rma-home-head{display:flex;flex-direction:column;gap:6px;margin:0 0 12px}.rma-home-head h2{margin:0;font-size:25px;font-weight:500;color:#1f2937;line-height:1.1}.rma-home-breadcrumb{display:flex;align-items:center;gap:8px;color:#6b7280;font-size:15px}.rma-home-breadcrumb .rma-home-ico{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;color:#6b7280}.rma-home-kpis{display:grid;grid-template-columns:repeat(4,minmax(220px,1fr));gap:14px}.rma-home-kpi{background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 4px 14px rgba(15,23,42,.06);overflow:hidden}.rma-home-kpi-main{display:flex;align-items:center;justify-content:space-between;padding:18px}.rma-home-kpi-title{display:flex;align-items:center;gap:12px;font-size:15px;color:#6b7280}.rma-home-kpi-dot{width:38px;height:38px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;color:#fff}.rma-home-kpi-dot svg{width:18px;height:18px;display:block}.rma-home-kpi-value{font-size:25px;color:#111827;font-weight:500;line-height:1}.rma-home-kpi-footer{border-top:1px solid #eef2f7;padding:12px 18px;display:flex;align-items:center;justify-content:space-between;color:#8b98a7;font-size:14px;text-decoration:none}.rma-home-kpi-footer:hover{color:#6b7280}.rma-home-kpi[data-i="0"] .rma-home-kpi-dot{background:#45aaf2}.rma-home-kpi[data-i="1"] .rma-home-kpi-dot{background:#e6007e}.rma-home-kpi[data-i="2"] .rma-home-kpi-dot{background:#f5b700}.rma-home-kpi[data-i="3"] .rma-home-kpi-dot{background:#22c55e}@media (max-width:1400px){.rma-home-kpis{grid-template-columns:repeat(2,minmax(220px,1fr));}.rma-home-head h2{font-size:40px}.rma-home-kpi-title{font-size:14px}.rma-home-kpi-value{font-size:30px}.rma-home-kpi-footer{font-size:13px}}';
                    document.head.appendChild(topStyle);
                }

                var mainContent = document.querySelector('.main-panel .content-wrapper,.main-content .content-wrapper,.main-content,.content-wrapper,.dashboard-content-area');
                if (mainContent && !document.getElementById('rma-home-top')) {
                    var topWrap = document.createElement('section');
                    topWrap.id = 'rma-home-top';
                    topWrap.className = 'rma-home-top';
                    var iconSvgs = [
                        '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 8.5A1.5 1.5 0 0 1 5.5 7h13A1.5 1.5 0 0 1 20 8.5v8A1.5 1.5 0 0 1 18.5 18h-13A1.5 1.5 0 0 1 4 16.5v-8Z" stroke="currentColor" stroke-width="1.7"/><path d="m7 10 5 3 5-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
                        '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="m12 4 2.1 4.3L19 9l-3.5 3.4.8 4.8L12 15l-4.3 2.2.8-4.8L5 9l4.9-.7L12 4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>',
                        '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.7"/><path d="M12 8v5l3 2" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
                        '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.7"/><path d="m8.6 12.4 2.2 2.1 4.6-4.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                    ];
                    var cardsHtml = cards.map(function(item, idx){
                        var safeTitle = (item && item.title ? String(item.title) : '');
                        var safeValue = (item && typeof item.value !== 'undefined' ? String(item.value) : '0');
                        var safeUrl = (item && item.url ? String(item.url) : '#');
                        var iconSvg = iconSvgs[idx] || iconSvgs[0];
                        return '<article class="rma-home-kpi" data-i="'+idx+'"><div class="rma-home-kpi-main"><p class="rma-home-kpi-title"><span class="rma-home-kpi-dot">'+iconSvg+'</span>'+safeTitle+'</p><strong class="rma-home-kpi-value">'+safeValue+'</strong></div><a class="rma-home-kpi-footer" href="'+safeUrl+'"><span>Ver detalhes</span><span>➜</span></a></article>';
                    }).join('');

                    var homeSvg = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.8 10.2 12 3.9l8.2 6.3v9a1 1 0 0 1-1 1h-4.7v-5.2h-5v5.2H4.8a1 1 0 0 1-1-1v-9Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>';
                    topWrap.innerHTML = '<div class="rma-home-head"><h2>Bem-vindo a RMA</h2><div class="rma-home-breadcrumb"><span class="rma-home-ico">'+homeSvg+'</span><span>/ Painel de controle</span></div></div><div class="rma-home-kpis">'+cardsHtml+'</div>';
                    mainContent.insertAdjacentElement('afterbegin', topWrap);
                }

                var host = (wrappers[0] && wrappers[0].closest('.row')) || document.querySelector('.info-boxes') || document.querySelector('.main-panel .content-wrapper,.main-content .content-wrapper,.main-content,.content-wrapper,.dashboard-content-area');
                if (host && !document.getElementById('rma-neon-analytics')) {
                    var wrap = document.createElement('section');
                    wrap.id = 'rma-neon-analytics';
                    wrap.className = 'rma-neon-wrap';

                    var top = document.createElement('div');
                    top.className = 'rma-neon-top';
                    analyticsCards.forEach(function(item){
                        var c = document.createElement('div');
                        c.className = 'rma-neon-card tone-' + (item.tone || 'dark');
                        c.style.background = item.bg;
                        c.innerHTML = '<p class=\"rma-neon-title\">'+item.title+'</p><p class=\"rma-neon-value\">'+renderTopValue(item.value)+'</p>'; 
                        top.appendChild(c);
                    });

                    var bottom = document.createElement('div');
                    bottom.className = 'rma-neon-bottom';
                    var cardsLower = [
                        {title:'Visitas nos últimos 15 dias', html: renderLine(chartData.visitas_15 || [], '#c61f4b')},
                        {title:'Usuários nos últimos 15 dias', html: renderLine(chartData.usuarios_15 || [], '#f2b58f')},
                        {title:'Visitas por país', html: renderDonut(chartData.por_pais || [])},
                        {title:'Plataformas usadas', html: renderDonut(chartData.plataformas || [])},
                        {title:'Navegadores usados', html: renderDonut(chartData.navegadores || [])}
                    ];
                    cardsLower.forEach(function(item){
                        var c = document.createElement('div');
                        c.className = 'rma-chart-card';
                        c.innerHTML = '<p class="rma-chart-title">'+item.title+'</p><div class="rma-chart-plot">'+item.html+'</div>';
                        bottom.appendChild(c);
                    });

                    wrap.appendChild(top);
                    wrap.appendChild(bottom);
                    host.insertAdjacentElement('afterend', wrap);
                }

                cleanupLegacyShell();
                if (document.documentElement.classList.contains('rma-db-preload')) { document.documentElement.classList.remove('rma-db-preload'); }
                if (document.documentElement.classList.contains('rma-entity-dashboard-lock')) { document.documentElement.classList.remove('rma-entity-dashboard-lock'); }
                return wrappers.length > 0 || !!document.getElementById('rma-neon-analytics');
            }

            var tries = 0;
            var timer = setInterval(function(){
                tries++;
                if (mount() || tries > 20) { clearInterval(timer); }
            }, 350);
        })();
        </script>
        <?php
    }

    public function inject_entity_dashboard_governance_content(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }

        $ext = isset($_GET['ext']) ? sanitize_key((string) wp_unslash($_GET['ext'])) : '';
        $map = [
            'rma-governanca-documentos' => '[rma_governanca_entidade_documentos]',
            'rma-governanca-pendencias' => '[rma_governanca_entidade_pendencias]',
            'rma-governanca-status' => '[rma_governanca_entidade_status]',
            'rma-governanca-upload' => '[rma_governanca_entidade_upload]',
            'rma-map-directory' => '[rma_map_directory mode="dashboard_map_ext"]',
        ];

        $finance_exts = ['rma-financeiro-visao-geral','rma-financeiro-cobranca','rma-financeiro-faturas','rma-financeiro-pix','rma-financeiro-historico','rma-financeiro-relatorios','saved-services','rma-suporte','rma-suporte-novo','rma-suporte-tickets'];
        if (in_array($ext, $finance_exts, true)) {
            $content = $this->render_entity_finance_support_bridge($ext);
        } else {
            if (! isset($map[$ext])) {
                return;
            }
            $content = do_shortcode($map[$ext]);
        }
        ?>
        <script>
        (function(){
            var html = <?php echo wp_json_encode($content); ?>;
            var ext = <?php echo wp_json_encode($ext); ?>;
            var selectors = ['.main-panel .content-wrapper','.main-content .content-wrapper','.main-content','.content-wrapper','.dashboard-content-area'];
            var attempts = 0;

            function cleanupLegacyDashboardRows(){
                document.querySelectorAll('.content-wrapper .row').forEach(function(row){
                    var txt = (row.textContent || '').toLowerCase();
                    if (txt.indexOf('welcome back employer') !== -1 || txt.indexOf('profile views') !== -1 || txt.indexOf('recent proposals') !== -1 || txt.indexOf('most viewed projects') !== -1 || txt.indexOf('current plan detail') !== -1 || txt.indexOf('bem-vindo de volta') !== -1 || (txt.indexOf('/ painel de controle') !== -1 && txt.indexOf('bem-vindo a rma') === -1)) {
                        row.remove();
                    }
                });
            }

            var mount = function(){
                var target = null;
                for (var i=0;i<selectors.length;i++) {
                    target = document.querySelector(selectors[i]);
                    if (target) { break; }
                }
                if (!target) {
                    attempts++;
                    if (attempts < 30) {
                        window.setTimeout(mount, 120);
                    }
                    return;
                }

                if (target.getAttribute('data-rma-mounted-ext') === ext) {
                    return;
                }

                cleanupLegacyDashboardRows();
                target.innerHTML = html;
                target.setAttribute('data-rma-mounted-ext', ext);

                var scripts = target.querySelectorAll('script');
                scripts.forEach(function(oldScript){
                    var newScript = document.createElement('script');
                    Array.prototype.slice.call(oldScript.attributes).forEach(function(attr){
                        newScript.setAttribute(attr.name, attr.value);
                    });
                    newScript.text = oldScript.textContent || '';
                    oldScript.parentNode.replaceChild(newScript, oldScript);
                });

                cleanupLegacyDashboardRows();
                if (document.documentElement.classList.contains('rma-entity-dashboard-lock')) { document.documentElement.classList.remove('rma-entity-dashboard-lock'); }
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', mount, { once: true });
            } else {
                mount();
            }
        })();
        </script>
        <?php
    }

    private function render_entity_finance_support_bridge(string $ext): string {
        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return '<div style="background:#fff;border:1px solid #dbe7f3;border-radius:12px;padding:14px">Nenhuma entidade vinculada.</div>';
        }

        $ext = $ext === 'rma-financeiro-pix' ? 'rma-financeiro-cobranca' : $ext;
        $entity_name = (string) get_the_title($entity_id);
        $finance_status = sanitize_key((string) get_post_meta($entity_id, 'finance_status', true));

        $due_raw = (string) get_post_meta($entity_id, 'anuidade_vencimento', true);
        if ($due_raw === '') {
            $due_raw = (string) get_post_meta($entity_id, 'finance_due_at', true);
        }
        $due_ts = $due_raw !== '' ? strtotime($due_raw . ' UTC') : 0;
        $due_date = $due_ts ? wp_date('d/m/Y', $due_ts) : 'Não definido';
        $days_left = $due_ts ? (int) floor(($due_ts - time()) / DAY_IN_SECONDS) : 0;

        $status_label = $finance_status === 'adimplente' ? '🟢 Adimplente até ' . $due_date : '🔴 Anuidade vencida';
        $status_hint = $finance_status === 'adimplente' ? 'Filiação ativa e regular.' : 'Regularize para manter sua filiação ativa.';

        $annual_value = (float) get_option('rma_annual_due_value', '0');
        $annual_value_label = 'R$ ' . number_format($annual_value, 2, ',', '.');
        $annual_year = $due_ts ? (int) gmdate('Y', $due_ts) : ((int) gmdate('Y') + 1);

        $history = get_post_meta($entity_id, 'finance_history', true);
        $history = is_array($history) ? array_reverse($history) : [];
        $history_display = [];
        $seen_history = [];
        foreach ($history as $item) {
            $year_key = sanitize_text_field((string) ($item['year'] ?? ''));
            $signature = $year_key !== ''
                ? 'year:' . $year_key
                : md5(wp_json_encode([
                    (string) ($item['paid_at'] ?? ''),
                    (string) ($item['finance_status'] ?? ''),
                    (string) ($item['total'] ?? ''),
                    (string) ($item['order_id'] ?? ''),
                ]));
            if (isset($seen_history[$signature])) {
                continue;
            }
            $seen_history[$signature] = true;
            $history_display[] = $item;
        }
        $last_payment = $history_display[0] ?? null;

        $latest_order = null;
        if (function_exists('wc_get_orders')) {
            $orders = wc_get_orders([
                'limit' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'status' => ['pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed', 'refunded'],
                'meta_key' => 'rma_entity_id',
                'meta_value' => $entity_id,
            ]);
            if (! empty($orders) && $orders[0] instanceof WC_Order) {
                $latest_order = $orders[0];
            }
        }

        $order_status = $latest_order instanceof WC_Order ? (string) $latest_order->get_status() : 'nao_gerada';
        $order_total = $latest_order instanceof WC_Order ? 'R$ ' . number_format((float) $latest_order->get_total(), 2, ',', '.') : $annual_value_label;
        $pix_payload = $latest_order instanceof WC_Order ? (string) $latest_order->get_meta('rma_pix_payload') : '';
        $pix_qr = $latest_order instanceof WC_Order ? (string) $latest_order->get_meta('rma_pix_qrcode') : '';

        $mandate_keys = ['mandato_fim', 'diretoria_mandato_fim', 'governance_board_mandate_until'];
        $mandate_ts = 0;
        foreach ($mandate_keys as $key) {
            $v = (string) get_post_meta($entity_id, $key, true);
            if ($v !== '') {
                $mandate_ts = strtotime($v . ' UTC');
                if ($mandate_ts) {
                    break;
                }
            }
        }

        $alerts = [];
        if ($mandate_ts) {
            $days_to_mandate = (int) floor(($mandate_ts - time()) / DAY_IN_SECONDS);
            if ($days_to_mandate <= 45) {
                $alerts[] = '⚠ Seu mandato vence em ' . max(0, $days_to_mandate) . ' dias. Atualize os dados da diretoria.';
            }
        }
        if ($finance_status !== 'adimplente') {
            $alerts[] = '⚠ Anuidade pendente pode gerar restrições institucionais.';
        }

        $support_notice = '';
        if (in_array($ext, ['saved-services','rma-suporte','rma-suporte-novo','rma-suporte-tickets'], true)
            && isset($_POST['rma_support_submit'])
            && wp_verify_nonce(sanitize_text_field((string) wp_unslash($_POST['rma_support_nonce'] ?? '')), 'rma_support_submit_' . $entity_id)
        ) {
            $subject = sanitize_text_field((string) wp_unslash($_POST['rma_support_subject'] ?? ''));
            $message = sanitize_textarea_field((string) wp_unslash($_POST['rma_support_message'] ?? ''));
            $priority = sanitize_key((string) wp_unslash($_POST['rma_support_priority'] ?? 'outros'));
            $allowed_priorities = ['problema_tecnico', 'duvida_institucional', 'financeiro', 'outros'];
            if ($subject !== '' && $message !== '' && in_array($priority, $allowed_priorities, true)) {
                $tickets = get_post_meta($entity_id, 'rma_support_tickets', true);
                $tickets = is_array($tickets) ? $tickets : [];
                $tickets[] = [
                    'id' => 'TKT-' . strtoupper(substr(md5((string) microtime(true) . '-' . (string) $entity_id), 0, 8)),
                    'subject' => $subject,
                    'message' => $message,
                    'priority' => $priority,
                    'status' => 'aberto',
                    'created_at' => current_time('mysql', true),
                ];
                update_post_meta($entity_id, 'rma_support_tickets', $tickets);
                $support_notice = 'Chamado enviado com sucesso para a Equipe RMA.';
            } else {
                $support_notice = 'Preencha todos os campos do chamado antes de enviar.';
            }
        }

        $tabs = [
            'rma-financeiro-visao-geral' => 'Visão Geral',
            'rma-financeiro-cobranca' => 'Minha Cobrança',
            'rma-financeiro-faturas' => 'Minhas Faturas',
            'rma-financeiro-historico' => 'Histórico',
        ];

        $base = home_url('/dashboard/');
        $html = $this->render_entity_governance_styles();
        $html .= '<div class="rma-gov-entity-wrap">';
        $html .= '<div class="rma-gov-entity-tabs">';
        foreach ($tabs as $key => $label) {
            $class = $ext === $key ? ' is-active' : '';
            $html .= '<a class="rma-gov-entity-tab' . esc_attr($class) . '" href="' . esc_url(add_query_arg('ext', $key, $base)) . '">' . esc_html($label) . '</a>';
        }
        $html .= '</div>';

        if ($ext === 'rma-financeiro-visao-geral') {
            $checkout_url = home_url('/checkout/');
            $product_id = (int) get_option('rma_annual_dues_product_id', 0);
            $generate_links = [];
            for ($qty = 1; $qty <= 3; $qty++) {
                $label = $qty . ' anuidade' . ($qty > 1 ? 's' : '');
                $link = $product_id > 0 ? add_query_arg(['add-to-cart' => $product_id, 'quantity' => $qty], $checkout_url) : $checkout_url;
                $generate_links[] = '<a class="rma-gov-entity-link rma-fin-link-pill" href="' . esc_url($link) . '">' . esc_html($label) . '</a>';
            }
            $html .= '<div class="rma-gov-entity-head"><h3>Dashboard Financeiro</h3><p>Estou em dia? Quanto devo? O que preciso fazer agora?</p></div>';
            $html .= '<div class="rma-gov-entity-meta">';
            $status_chip = $finance_status === 'adimplente' ? '<span class="rma-fin-chip is-success">Adimplente</span>' : '<span class="rma-fin-chip is-danger">Inadimplente</span>';
            $html .= '<div class="rma-gov-entity-card rma-fin-highlight"><small>Status da filiação</small>' . $status_chip . '<strong>' . esc_html($status_label) . '</strong><span class="rma-fin-subline">' . esc_html($status_hint) . '</span></div>';
            $html .= '<div class="rma-gov-entity-card"><small>Próximo vencimento</small><strong>' . esc_html($due_date) . '</strong><span class="rma-fin-subline">Dias restantes: ' . esc_html((string) max(0, $days_left)) . '</span></div>';
            $last_label = $last_payment ? ('Ano: ' . (string) ($last_payment['year'] ?? '-') . ' · Data: ' . (string) ($last_payment['paid_at'] ?? '-') . ' · Forma: PIX') : 'Sem pagamento confirmado';
            $html .= '<div class="rma-gov-entity-card"><small>Último pagamento</small><strong>' . esc_html($last_label) . '</strong></div>';
            $html .= '<div class="rma-gov-entity-card"><small>Valor da anuidade atual</small><strong>' . esc_html('Anuidade RMA ' . (string) $annual_year . ' · ' . $annual_value_label) . '</strong></div>';
            $html .= '</div>';
            $html .= '<div class="rma-fin-cta"><strong>Gerar cobrança</strong><span>Escolha quantas anuidades deseja pagar agora e siga para o checkout.</span><div class="rma-fin-links">' . implode('', $generate_links) . '</div></div>';
            $html .= '<div class="rma-gov-entity-table-wrap"><table class="rma-gov-entity-table"><thead><tr><th>Situação da entidade</th><th>Status</th></tr></thead><tbody>';
            $html .= '<tr><td>Participação na rede</td><td>✔ Ativa</td></tr><tr><td>Visibilidade no mapa</td><td>✔ Habilitada</td></tr>';
            if ($finance_status !== 'adimplente') {
                $html .= '<tr><td>Situação institucional</td><td>⚠ Entidades inadimplentes podem ter restrições institucionais.</td></tr>';
            }
            if (! empty($alerts)) {
                foreach ($alerts as $alert) {
                    $html .= '<tr><td>Alerta automático</td><td>' . esc_html($alert) . '</td></tr>';
                }
            }
            $html .= '</tbody></table></div>';
                } elseif ($ext === 'rma-financeiro-faturas') {
            $checkout_url = home_url('/checkout/');
            $product_id = (int) get_option('rma_annual_dues_product_id', 0);
            $generate_links = [];
            for ($qty = 1; $qty <= 3; $qty++) {
                $label = 'Gerar ' . $qty . ' anuidade' . ($qty > 1 ? 's' : '');
                $link = $product_id > 0 ? add_query_arg(['add-to-cart' => $product_id, 'quantity' => $qty], $checkout_url) : $checkout_url;
                $generate_links[] = '<a class="rma-gov-entity-link rma-fin-link-pill" href="' . esc_url($link) . '">' . esc_html($label) . '</a>';
            }
            $html .= '<div class="rma-gov-entity-head"><h3>Minhas Faturas</h3><p>Acompanhe próximos vencimentos e pague múltiplos anos da filiação.</p></div>';
            $html .= '<div class="rma-gov-entity-meta">';
            $html .= '<div class="rma-gov-entity-card"><small>Próximo vencimento</small><strong>' . esc_html($due_date) . '</strong></div>';
            $html .= '<div class="rma-gov-entity-card"><small>Status atual</small><strong>' . esc_html($status_human[$order_status] ?? strtoupper($order_status)) . '</strong></div>';
            $html .= '<div class="rma-gov-entity-card"><small>Valor por anuidade</small><strong>' . esc_html($annual_value_label) . '</strong></div>';
            $html .= '</div>';
            $html .= '<div class="rma-fin-cta"><strong>Próximos vencimentos</strong><span>Renove sua filiação para manter sua entidade regular na RMA.</span><div class="rma-fin-links">' . implode('', $generate_links) . '</div></div>';
            $html .= '<div class="rma-gov-entity-table-wrap"><table class="rma-gov-entity-table"><thead><tr><th>Descrição</th><th>Detalhe</th></tr></thead><tbody>';
            $html .= '<tr><td>Anuidade vigente</td><td>' . esc_html('RMA ' . (string) $annual_year . ' · ' . $annual_value_label) . '</td></tr>';
            $html .= '<tr><td>Vencimento principal</td><td>' . esc_html($due_date) . '</td></tr>';
            $html .= '<tr><td>Pagamento de múltiplos anos</td><td>Use os botões acima para gerar 1, 2 ou 3 anuidades em um único checkout.</td></tr>';
            $html .= '</tbody></table></div>';
} elseif ($ext === 'rma-financeiro-cobranca') {
            $checkout_url = home_url('/checkout/');
            $product_id = (int) get_option('rma_annual_dues_product_id', 0);
            $generate_url = $product_id > 0 ? add_query_arg(['add-to-cart' => $product_id, 'quantity' => 1], $checkout_url) : $checkout_url;
            $generate_links = [];
            for ($qty = 1; $qty <= 3; $qty++) {
                $label = 'Gerar ' . $qty . ' anuidade' . ($qty > 1 ? 's' : '');
                $link = $product_id > 0 ? add_query_arg(['add-to-cart' => $product_id, 'quantity' => $qty], $checkout_url) : $checkout_url;
                $generate_links[] = '<a class="rma-gov-entity-link rma-fin-link-pill" href="' . esc_url($link) . '">' . esc_html($label) . '</a>';
            }
            $status_human = [
                'nao_gerada' => 'Não gerada',
                'pending' => 'Aguardando pagamento',
                'on-hold' => 'Aguardando pagamento',
                'processing' => 'Paga',
                'completed' => 'Paga',
                'failed' => 'Vencida',
                'cancelled' => 'Vencida',
            ];
            $html .= '<div class="rma-gov-entity-head"><h3>Minha Cobrança</h3><p>Gere e pague sua anuidade com PIX.</p></div>';
            $html .= '<div class="rma-gov-entity-meta">';
            $html .= '<div class="rma-gov-entity-card"><small>Cobrança atual</small><strong>Anuidade ' . esc_html((string) $annual_year) . ' · ' . esc_html($order_total) . '</strong></div>';
            $html .= '<div class="rma-gov-entity-card"><small>Vencimento</small><strong>' . esc_html($due_date) . '</strong></div>';
            $html .= '<div class="rma-gov-entity-card"><small>Status</small><strong>' . esc_html($status_human[$order_status] ?? strtoupper($order_status)) . '</strong></div>';
            $html .= '</div>';
            $html .= '<div class="rma-fin-cta"><strong>Cobrança PIX</strong><span>Gere uma cobrança instantânea ou selecione múltiplas anuidades.</span><div class="rma-fin-links"><a class="rma-gov-entity-tab is-active" href="' . esc_url($generate_url) . '">Gerar cobrança PIX</a>' . implode('', $generate_links) . '</div></div>';
            $html .= '<div class="rma-gov-entity-table-wrap"><table class="rma-gov-entity-table"><thead><tr><th>Área de pagamento</th><th>Conteúdo</th></tr></thead><tbody>';
            $html .= '<tr><td>QR Code PIX</td><td>' . ($pix_qr !== '' ? '<img src="' . esc_url($pix_qr) . '" alt="QR Code PIX" style="max-width:180px;height:auto" />' : 'Será exibido após geração da cobrança.') . '</td></tr>';
            $html .= '<tr><td>Código copia e cola</td><td>' . ($pix_payload !== '' ? '<code>' . esc_html($pix_payload) . '</code>' : 'Será exibido após geração da cobrança.') . '</td></tr>';
            $html .= '<tr><td>Instruções</td><td>Após o pagamento a confirmação é automática e pode levar alguns minutos.</td></tr>';
            $html .= '</tbody></table></div>';
        } elseif ($ext === 'rma-financeiro-historico' || $ext === 'rma-financeiro-relatorios') {
            $html .= '<div class="rma-gov-entity-head"><h3>Histórico Financeiro</h3><p>Todos os pagamentos realizados pela entidade.</p></div>';
            $html .= '<div class="rma-gov-entity-table-wrap"><table class="rma-gov-entity-table"><thead><tr><th>Ano</th><th>Valor</th><th>Status</th><th>Data pagamento</th><th>Forma</th><th>Comprovante</th></tr></thead><tbody>';
            if (empty($history_display)) {
                $html .= '<tr><td colspan="6">Sem histórico financeiro disponível.</td></tr>';
            } else {
                foreach (array_slice($history_display, 0, 20) as $item) {
                    $total = 'R$ ' . number_format((float) ($item['total'] ?? 0), 2, ',', '.');
                    $order_id = (int) ($item['order_id'] ?? 0);
                    $receipt_url = '#';
                    if ($order_id > 0 && function_exists('wc_get_order')) {
                        $ord = wc_get_order($order_id);
                        if ($ord instanceof WC_Order) {
                            $receipt_url = $ord->get_view_order_url();
                        }
                    }
                    $html .= '<tr><td>' . esc_html((string) ($item['year'] ?? '-')) . '</td><td>' . esc_html($total) . '</td><td>' . esc_html(strtoupper((string) ($item['finance_status'] ?? '-'))) . '</td><td>' . esc_html((string) ($item['paid_at'] ?? '-')) . '</td><td>PIX</td><td>' . ($receipt_url !== '#' ? '<a class="rma-gov-entity-link" href="' . esc_url($receipt_url) . '">Baixar recibo</a>' : '—') . '</td></tr>';
                }
            }
            $html .= '</tbody></table></div>';
            $html .= '<div class="rma-fin-timeline"><strong>Linha do tempo:</strong> ';
            $timeline = [];
            foreach (array_slice($history_display, 0, 5) as $item) {
                $timeline[] = esc_html((string) ($item['year'] ?? '-')) . ' ✔ ' . esc_html(strtoupper((string) ($item['finance_status'] ?? 'PAGO')));
            }
            $html .= implode(' · ', $timeline ?: ['Sem registros']);
            $html .= '</div>';
        } else {
            $html .= '<div class="rma-gov-entity-head"><h3>Suporte da Entidade</h3><p>Abra e acompanhe tickets com a Equipe RMA.</p></div>';
            if ($support_notice !== '') {
                $html .= '<div class="rma-gov-entity-alert" style="background:rgba(15,159,111,.09);border-color:rgba(15,159,111,.25);color:#0d7d58">' . esc_html($support_notice) . '</div>';
            }
            $html .= '<div class="rma-fin-cta"><strong>🎫 Abrir chamado</strong><span>Informe o tipo e os detalhes do atendimento desejado.</span><div class="rma-fin-links"><button type="button" class="rma-fin-open-modal" onclick="var d=this.closest(&quot;.rma-gov-entity-wrap&quot;).querySelector(&quot;#rma-support-modal&quot;);if(d){d.showModal();}">Abrir chamado</button></div></div>';
            $html .= '<dialog id="rma-support-modal" class="rma-support-modal"><form method="post" class="rma-support-form">';
            $html .= wp_nonce_field('rma_support_submit_' . $entity_id, 'rma_support_nonce', true, false);
            $html .= '<input type="hidden" name="rma_support_submit" value="1" />';
            $html .= '<h4 style="margin:0 0 10px">Novo chamado de suporte</h4>';
            $html .= '<p style="margin:0 0 8px"><label>Assunto<br/><input type="text" name="rma_support_subject" required style="width:100%"/></label></p>';
            $html .= '<fieldset style="margin:0 0 10px;border:1px solid #d5e4f5;border-radius:10px;padding:8px 10px"><legend style="padding:0 6px">Categoria</legend><label style="display:block;margin:0 0 4px"><input type="radio" name="rma_support_priority" value="problema_tecnico" checked /> 1 - Problema técnico</label><label style="display:block;margin:0 0 4px"><input type="radio" name="rma_support_priority" value="duvida_institucional" /> 2 - Dúvida institucional</label><label style="display:block;margin:0 0 4px"><input type="radio" name="rma_support_priority" value="financeiro" /> 3 - Financeiro</label><label style="display:block"><input type="radio" name="rma_support_priority" value="outros" /> 4 - Outros</label></fieldset>';
            $html .= '<p style="margin:0 0 10px"><label>Descrição<br/><textarea name="rma_support_message" rows="4" required style="width:100%"></textarea></label></p>';
            $html .= '<div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="rma-fin-open-modal" onclick="this.closest(&quot;dialog&quot;).close();">Cancelar</button><button type="submit" class="rma-gov-entity-tab is-active">Enviar para Equipe RMA</button></div>';
            $html .= '</form></dialog>';
            $tickets = get_post_meta($entity_id, 'rma_support_tickets', true);
            $tickets = is_array($tickets) ? array_reverse($tickets) : [];
            $priority_labels = [
                'problema_tecnico' => '1 - Problema técnico',
                'duvida_institucional' => '2 - Dúvida institucional',
                'financeiro' => '3 - Financeiro',
                'outros' => '4 - Outros',
            ];
            $html .= '<div class="rma-gov-entity-table-wrap"><table class="rma-gov-entity-table"><thead><tr><th>Ticket</th><th>Categoria</th><th>Status</th></tr></thead><tbody>';
            if (empty($tickets)) {
                $html .= '<tr><td colspan="3">Nenhum ticket aberto.</td></tr>';
            } else {
                foreach (array_slice($tickets, 0, 15) as $t) {
                    $priority_key = sanitize_key((string) ($t['priority'] ?? 'outros'));
                    $priority_label = $priority_labels[$priority_key] ?? '4 - Outros';
                    $html .= '<tr><td>' . esc_html((string) ($t['id'] ?? '-')) . '</td><td>' . esc_html($priority_label) . '</td><td>' . esc_html(strtoupper((string) ($t['status'] ?? 'aberto'))) . '</td></tr>';
                }
            }
            $html .= '</tbody></table></div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function redirect_entity_dashboard_notice(string $message, string $type): void {
        $base = home_url('/dashboard/');
        $url = add_query_arg([
            'ext' => 'rma-governanca-upload',
            'rma_doc_notice' => rawurlencode($message),
            'rma_doc_notice_type' => $type === 'success' ? 'success' : 'error',
        ], $base);
        wp_safe_redirect($url);
        exit;
    }


    private function render_entity_governance_nav(string $active_ext): string {
        $base = home_url('/dashboard/');
        $items = [
            'rma-governanca-documentos' => 'Documentos Enviados',
            'rma-governanca-pendencias' => 'Pendências',
            'rma-governanca-status' => 'Status',
            'rma-governanca-upload' => 'Enviar Documentos',
        ];

        $html = '<div class="rma-gov-entity-tabs">';
        foreach ($items as $ext => $label) {
            $class = $ext === $active_ext ? ' is-active' : '';
            $url = add_query_arg('ext', $ext, $base);
            $html .= '<a class="rma-gov-entity-tab' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        $html .= '</div>';

        return $html;
    }

    private function render_entity_governance_styles(): string {
        return '<style>
            .rma-gov-entity-wrap,.rma-gov-entity-wrap *{font-family:"Maven Pro",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;box-sizing:border-box}
            .rma-gov-entity-wrap{background:radial-gradient(circle at top right,#f7fffb 0,#eef4ff 46%,#f8fbff 100%);border:1px solid #dbe8f7;box-shadow:0 24px 60px rgba(15,23,42,.10);border-radius:24px;padding:24px;color:#162538}
            .dropdown-menu.dropdown-menu-right a.selected{background-color:rgb(129 176 65)}
            .dropdown-item.active,.dropdown-item:active{color:#fff;text-decoration:none;background-color:#7dae3b}
            .text-primary{color:#7dae3b !important}
            .rma-gov-entity-tabs{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 16px}
            .rma-gov-entity-tab{text-decoration:none;color:#17416f;background:#fff;border:1px solid #d4e2f2;padding:8px 14px;border-radius:999px;font-size:12px;font-weight:700;transition:all .2s ease}
            .rma-gov-entity-tab:hover{transform:translateY(-1px);box-shadow:0 8px 16px rgba(21,89,214,.12)}
            .rma-gov-entity-tab.is-active{background:linear-gradient(135deg,#7bad39,#9dc26b 55%,#20cf98);color:#fff;border-color:transparent;box-shadow:0 10px 20px rgba(80,233,164,.30)}
            .rma-gov-entity-head{background:linear-gradient(135deg,#7bad39,#9dc26b 55%,#20cf98);color:#fff;border-radius:18px;padding:18px 20px;margin-bottom:16px;box-shadow:0 14px 34px rgba(80,233,164,.30)}
            .rma-gov-entity-head h3{margin:0 0 6px;font-size:22px;line-height:1.2;color:#fff}
            .rma-gov-entity-head p{margin:0;opacity:1;font-size:14px;color:#fff}
            .rma-gov-entity-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px}
            .rma-gov-entity-card{background:#fff;border:1px solid #dce8f5;border-radius:14px;padding:13px;box-shadow:0 10px 24px rgba(15,23,42,.06)}
            .rma-gov-entity-card small{display:block;color:#607086;font-size:12px;margin-bottom:7px;text-transform:uppercase;letter-spacing:.04em}
            .rma-gov-entity-card strong{font-size:16px;display:block;line-height:1.3}
            .rma-fin-highlight{background:linear-gradient(180deg,#ffffff 0,#f8fcff 100%)}
            .rma-fin-subline{display:block;margin-top:6px;color:#5f6f83;font-size:13px}
            .rma-fin-chip{display:inline-flex;align-items:center;gap:6px;width:max-content;margin-bottom:8px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;border:1px solid transparent}
            .rma-fin-chip.is-success{background:rgba(15,159,111,.12);border-color:rgba(15,159,111,.28);color:#0f7b57}
            .rma-fin-chip.is-danger{background:rgba(206,63,74,.12);border-color:rgba(206,63,74,.28);color:#b2303c}
            .rma-fin-cta{margin:0 0 12px;background:#fff;border:1px solid #d7e6f6;border-radius:14px;padding:12px;display:grid;gap:8px}
            .rma-fin-cta strong{font-size:15px;color:#12385e}
            .rma-fin-cta span{color:#5f6f83;font-size:13px}
            .rma-fin-links{display:flex;flex-wrap:wrap;gap:8px}
            .rma-fin-link-pill{display:inline-flex;padding:6px 10px;border-radius:999px;background:#f3f8ff;border:1px solid #d4e3f6;font-size:12px;font-weight:700}
            .rma-badge{border-radius:999px;padding:4px 10px;font-size:12px;display:inline-flex;border:1px solid transparent}
            .rma-badge.aprovado{color:#0f9f6f;background:rgba(15,159,111,.12);border-color:rgba(15,159,111,.25)}
            .rma-badge.recusado{color:#ce3f4a;background:rgba(206,63,74,.12);border-color:rgba(206,63,74,.25)}
            .rma-badge.em_analise,.rma-badge.pendente{color:#d78d11;background:rgba(215,141,17,.12);border-color:rgba(215,141,17,.25)}
            .rma-gov-entity-table-wrap{overflow:auto;background:#fff;border:1px solid #dce8f5;border-radius:14px;box-shadow:0 10px 24px rgba(15,23,42,.05)}
            .rma-gov-entity-table{width:100%;border-collapse:collapse}
            .rma-gov-entity-table th,.rma-gov-entity-table td{padding:12px;border-bottom:1px solid #e6eff8;text-align:left;font-size:13px}
            .rma-gov-entity-table th{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:#5b6a7c;background:#f8fbff}
            .rma-gov-entity-link{color:#1559d6;text-decoration:none;font-weight:700}
            .rma-gov-entity-link:hover{text-decoration:underline}
            .rma-fin-timeline{margin-top:12px;background:#fff;border:1px solid #dce8f5;border-radius:14px;padding:12px;box-shadow:0 10px 24px rgba(15,23,42,.05)}
            .rma-fin-open-modal{padding:8px 12px;border-radius:10px;border:1px solid #cde0f5;background:#fff;color:#17416f;font-weight:700;cursor:pointer}
            .rma-support-modal{border:0;border-radius:14px;max-width:560px;width:95%;padding:0;box-shadow:0 20px 60px rgba(15,23,42,.35)}
            .rma-support-modal::backdrop{background:rgba(15,23,42,.45)}
            .rma-support-form{padding:16px;background:#fff}
            .rma-support-form input,.rma-support-form select,.rma-support-form textarea{border:1px solid #d5e4f5;border-radius:8px;padding:8px 10px;font:inherit}
            .rma-gov-entity-alert{background:rgba(206,63,74,.09);border:1px solid rgba(206,63,74,.25);color:#b2303c;border-radius:10px;padding:10px;margin:0 0 12px}
        </style>';
    }


    private function format_entity_badge(string $status): string {
        $status = sanitize_key($status !== '' ? $status : 'pendente');
        $label = strtoupper(str_replace('_', ' ', $status));
        if (in_array($status, ['aprovado', 'adimplente', 'active', 'ativo'], true)) {
            return '<span class="rma-badge aprovado">' . esc_html($label) . '</span>';
        }
        if (in_array($status, ['recusado', 'inadimplente', 'failed', 'cancelled', 'refunded'], true)) {
            return '<span class="rma-badge recusado">' . esc_html($label) . '</span>';
        }
        return '<span class="rma-badge pendente">' . esc_html($label) . '</span>';
    }

    public function on_entity_approved(int $entity_id): void {
        $this->push_entity_notification($entity_id, 'governanca', 'Filiação aprovada', 'Sua entidade foi aprovada na governança da RMA.', add_query_arg('ext', 'rma-governanca-status', home_url('/dashboard/')));
    }

    public function on_entity_rejected(int $entity_id, string $reason = ''): void {
        $message = $reason !== '' ? 'Sua filiação foi recusada: ' . $reason : 'Sua filiação foi recusada. Verifique as pendências.';
        $this->push_entity_notification($entity_id, 'governanca', 'Filiação recusada', $message, add_query_arg('ext', 'rma-governanca-pendencias', home_url('/dashboard/')));
    }

    public function on_entity_resubmitted(int $entity_id): void {
        $this->push_entity_notification($entity_id, 'governanca', 'Filiação reenviada', 'Sua entidade foi reenviada para nova análise da governança.', add_query_arg('ext', 'rma-governanca-status', home_url('/dashboard/')));
    }

    public function on_entity_finance_updated(int $entity_id, int $order_id, array $history = []): void {
        $last = ! empty($history) ? end($history) : [];
        $finance_status = (string) ($last['finance_status'] ?? get_post_meta($entity_id, 'finance_status', true));
        $title = $finance_status === 'adimplente' ? 'Pagamento confirmado' : 'Atualização financeira';
        $message = $finance_status === 'adimplente'
            ? 'Seu pagamento foi confirmado e seu status financeiro está adimplente.'
            : 'Houve atualização no financeiro da sua entidade. Consulte os detalhes no painel.';
        $this->push_entity_notification($entity_id, 'financeiro', $title, $message, add_query_arg('ext', 'rma-governanca-status', home_url('/dashboard/')));
    }

    private function push_entity_notification(int $entity_id, string $category, string $title, string $message, string $url = ''): void {
        if ($entity_id <= 0 || get_post_type($entity_id) !== self::CPT) {
            return;
        }

        $items = get_post_meta($entity_id, 'entity_notifications', true);
        $items = is_array($items) ? $items : [];

        $items[] = [
            'id' => wp_generate_uuid4(),
            'category' => sanitize_key($category),
            'title' => sanitize_text_field($title),
            'message' => sanitize_text_field($message),
            'url' => esc_url_raw($url),
            'read' => false,
            'datetime' => current_time('mysql', true),
        ];

        if (count($items) > 80) {
            $items = array_slice($items, -80);
        }

        update_post_meta($entity_id, 'entity_notifications', $items);
    }

    private function get_entity_notifications(int $entity_id): array {
        $items = get_post_meta($entity_id, 'entity_notifications', true);
        return is_array($items) ? array_reverse($items) : [];
    }

    public function ajax_mark_entity_notifications_read(): void {
        if (! is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuário não autenticado.'], 401);
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            wp_send_json_error(['message' => 'Entidade não encontrada.'], 404);
        }

        $items = get_post_meta($entity_id, 'entity_notifications', true);
        $items = is_array($items) ? $items : [];
        foreach ($items as &$item) {
            $item['read'] = true;
        }
        unset($item);

        update_post_meta($entity_id, 'entity_notifications', $items);
        wp_send_json_success(['message' => 'Notificações marcadas como lidas.']);
    }

    public function inject_entity_notifications_dropdown(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }

        $entity_id = $this->get_entity_id_by_author(get_current_user_id());
        if ($entity_id <= 0) {
            return;
        }

        $items = array_slice($this->get_entity_notifications($entity_id), 0, 12);
        $unread = 0;
        $list_html = '';

        if (empty($items)) {
            $list_html = '<li class="dropdown-item-text" style="white-space:normal">Sem notificações no momento.</li>';
        } else {
            foreach ($items as $item) {
                $is_unread = empty($item['read']);
                if ($is_unread) {
                    $unread++;
                }

                $title = esc_html((string) ($item['title'] ?? 'Notificação'));
                $message = esc_html((string) ($item['message'] ?? ''));
                $datetime = esc_html((string) ($item['datetime'] ?? ''));
                $url = esc_url((string) ($item['url'] ?? ''));
                $style = $is_unread ? 'background:rgba(93,218,187,.08);' : '';

                $inner = '<div style="font-size:13px;line-height:1.25"><strong>' . $title . '</strong><br><span style="opacity:.85">' . $message . '</span><br><small style="opacity:.65">' . $datetime . '</small></div>';
                if ($url !== '') {
                    $list_html .= '<a class="dropdown-item" style="white-space:normal;' . $style . '" href="' . $url . '">' . $inner . '</a>';
                } else {
                    $list_html .= '<li class="dropdown-item-text" style="white-space:normal;' . $style . '">' . $inner . '</li>';
                }
            }
        }

        ?>
        <script>
        (function(){
            var root = document.querySelector('li.nav-item.dropdown.notification-click');
            if (!root) { return; }
            var dropdown = root.querySelector('.dropdown-menu.navbar-dropdown');
            if (!dropdown) { return; }
            dropdown.innerHTML = '<h6 class="p-3 mb-0">Notificações da Entidade</h6><div class="dropdown-divider"></div><?php echo wp_kses_post($list_html); ?>';

            var badgeContainer = root.querySelector('.badge-container');
            if (badgeContainer) {
                badgeContainer.innerHTML = <?php echo wp_json_encode($unread > 0 ? '<span class="badge badge-danger">' . $unread . '</span>' : ''); ?>;
            }

            var trigger = root.querySelector('a.notification-click, a.dropdown-toggle');
            if (trigger) {
                trigger.addEventListener('click', function(){
                    var data = new FormData();
                    data.append('action', 'rma_mark_entity_notifications_read');
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {method:'POST', credentials:'same-origin', body:data}).then(function(){
                        if (badgeContainer) { badgeContainer.innerHTML = ''; }
                    });
                }, {once:true});
            }
        })();
        </script>
        <?php
    }

    private function normalized_governance_status(int $entity_id): string {
        $status = (string) get_post_meta($entity_id, 'governance_status', true);
        $status = trim($status);

        if ($status === '') {
            return 'pendente';
        }

        return $status;
    }


    private function limit_text(string $value, int $max): string {
        if ($max <= 0) {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    private function append_audit_log(int $entity_id, string $action, array $data): void {
        $logs = get_post_meta($entity_id, 'governance_audit_logs', true);
        $logs = is_array($logs) ? $logs : [];

        $logs[] = [
            'action' => $action,
            'datetime' => current_time('mysql', true),
            'data' => $data,
        ];

        $max_logs = 200;
        if (count($logs) > $max_logs) {
            $logs = array_slice($logs, -1 * $max_logs);
        }

        update_post_meta($entity_id, 'governance_audit_logs', $logs);

        if (function_exists('rma_append_entity_audit_event')) {
            rma_append_entity_audit_event($entity_id, 'governance', $action, 'info', 'Evento de governança registrado.', $data);
        }
    }
}

new RMA_Governance();
