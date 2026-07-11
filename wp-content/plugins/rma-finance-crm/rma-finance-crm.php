<?php
/**
 * Plugin Name: RMA Finance CRM
 * Description: CRM financeiro completo para superadmin e entidades, com menus lógicos, KPIs e histórico financeiro.
 * Version: 0.2.0
 * Author: RMA
 */

if (! defined('ABSPATH')) {
    exit;
}

final class RMA_Finance_CRM {
    private const CPT = 'rma_entidade';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menus']);
        add_shortcode('rma_financeiro_entidade_crm', [$this, 'render_entity_shortcode']);
        add_shortcode('rma_financeiro_admin_crm', [$this, 'render_admin_shortcode']);
        add_action('init', [$this, 'handle_entity_support_ticket_submit']);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_documents_menu'], 102);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_finance_menu'], 103);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_support_menu'], 104);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_finance_content'], 105);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_support_content'], 106);
        add_action('wp_footer', [$this, 'inject_entity_dashboard_home_modules'], 107);
        add_action('plugins_loaded', [$this, 'maybe_disable_dashboard_injectors'], 100);
    }


    public function maybe_disable_dashboard_injectors(): void {
        if (! class_exists('RMA_Governance')) {
            return;
        }

        remove_action('wp_footer', [$this, 'inject_entity_dashboard_documents_menu'], 102);
        remove_action('wp_footer', [$this, 'inject_entity_dashboard_finance_menu'], 103);
        remove_action('wp_footer', [$this, 'inject_entity_dashboard_support_menu'], 104);
        remove_action('wp_footer', [$this, 'inject_entity_dashboard_finance_content'], 105);
        remove_action('wp_footer', [$this, 'inject_entity_dashboard_support_content'], 106);
        remove_action('wp_footer', [$this, 'inject_entity_dashboard_home_modules'], 107);
    }

    public function register_admin_menus(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        add_menu_page(
            __('CRM Financeiro RMA', 'rma-finance-crm'),
            __('CRM Financeiro', 'rma-finance-crm'),
            'manage_options',
            'rma-finance-crm',
            [$this, 'render_admin_dashboard_page'],
            'dashicons-chart-line',
            32
        );

        add_submenu_page('rma-finance-crm', __('Visão Geral', 'rma-finance-crm'), __('Visão Geral', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm', [$this, 'render_admin_dashboard_page']);
        add_submenu_page('rma-finance-crm', __('Contas a Receber', 'rma-finance-crm'), __('Contas a Receber', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm-receber', [$this, 'render_admin_receivables_page']);
        add_submenu_page('rma-finance-crm', __('Cobranças PIX', 'rma-finance-crm'), __('Cobranças PIX', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm-pix', [$this, 'render_admin_pix_page']);
        add_submenu_page('rma-finance-crm', __('Conciliação', 'rma-finance-crm'), __('Conciliação', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm-conciliacao', [$this, 'render_admin_reconciliation_page']);
        add_submenu_page('rma-finance-crm', __('Relatórios', 'rma-finance-crm'), __('Relatórios', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm-reports', [$this, 'render_admin_reports_page']);
        add_submenu_page('rma-finance-crm', __('Observabilidade', 'rma-finance-crm'), __('Observabilidade', 'rma-finance-crm'), 'manage_options', 'rma-finance-crm-observability', [$this, 'render_admin_observability_page']);
    }

    public function render_admin_dashboard_page(): void {
        $data = $this->get_admin_financial_data();
        $rows = [];
        foreach ($data['latest_entities'] as $e) {
            $rows[] = [$e['name'], $e['status_badge'], $e['estimated_value'], $e['due_year'], $e['last_order_badge']];
        }

        echo '<div class="wrap">';
        echo '<h1>CRM Financeiro RMA — Visão Geral</h1>';
        echo $this->build_shell_start('rma-finance-crm', 'Painel executivo consolidado para gestão financeira RMA.');
        $recent_orders = $this->get_recent_due_orders();
        echo $this->build_kpi_cards($data['kpis']);
        echo $this->build_progress_strip($data['adimplencia_rate']);
        echo $this->build_finance_command_center($data, $recent_orders);
        echo $this->build_table('Fila premium de fechamento', $this->build_closing_priority_rows($data['entities'], $recent_orders), ['Prioridade', 'Entidade', 'Diagnóstico', 'Ação de fechamento']);
        echo $this->build_table('Últimas movimentações', $rows, ['Entidade', 'Status', 'Valor', 'Ano', 'Pedido']);
        echo $this->build_shell_end();
        echo '</div>';
    }

    public function render_admin_receivables_page(): void {
        $data = $this->get_admin_financial_data();
        $rows = [];
        foreach ($data['entities'] as $entity) {
            if (($entity['finance_status'] ?? '') !== 'adimplente') {
                $rows[] = [
                    $entity['name'],
                    $entity['status_badge'],
                    $entity['due_date'],
                    $entity['estimated_value'],
                    $entity['last_order_badge'],
                ];
            }
        }

        echo '<div class="wrap"><h1>CRM Financeiro RMA — Contas a Receber</h1>';
        echo $this->build_shell_start('rma-finance-crm-receber', 'Entidades com pendências, vencimentos e prioridade de cobrança.');
        echo $this->build_receivables_command_center($data['entities']);
        echo $this->build_table('Fila tática de cobrança', $this->build_receivables_priority_rows($data['entities']), ['Prioridade', 'Entidade', 'Diagnóstico', 'Próxima ação']);
        echo $this->build_table('Entidades com pendência financeira', $rows, ['Entidade', 'Status financeiro', 'Vencimento', 'Valor estimado', 'Último pedido']);
        echo $this->build_shell_end();
        echo '</div>';
    }

    public function render_admin_pix_page(): void {
        $orders = $this->get_recent_due_orders();
        $rows = [];
        foreach ($orders as $order) {
            $rows[] = [
                '#' . (int) $order['id'],
                $order['entity_name'],
                $order['status_badge'],
                $order['total'],
                $order['due_year'],
            ];
        }

        echo '<div class="wrap"><h1>CRM Financeiro RMA — Cobranças PIX</h1>';
        echo $this->build_shell_start('rma-finance-crm-pix', 'Fila de pedidos PIX e acompanhamento da compensação.');
        echo $this->build_table('Pedidos PIX mais recentes', $rows, ['Pedido', 'Entidade', 'Status', 'Total', 'Ano']);
        echo $this->build_shell_end();
        echo '</div>';
    }

    public function render_admin_reconciliation_page(): void {
        $orders = $this->get_recent_due_orders();
        $pending = 0;
        $paid = 0;

        foreach ($orders as $order) {
            if (in_array($order['status'], ['processing', 'completed'], true)) {
                $paid++;
            } else {
                $pending++;
            }
        }

        $coverage = count($orders) > 0 ? (int) round(($paid / count($orders)) * 100) : 0;

        echo '<div class="wrap"><h1>CRM Financeiro RMA — Conciliação</h1>';
        echo $this->build_shell_start('rma-finance-crm-conciliacao', 'Conciliação automática de recebíveis com base em status WooCommerce.');
        echo $this->build_kpi_cards([
            ['label' => 'Pedidos conciliados', 'value' => (string) $paid, 'tone' => 'good'],
            ['label' => 'Pedidos pendentes', 'value' => (string) $pending, 'tone' => 'warn'],
            ['label' => 'Total analisado', 'value' => (string) count($orders), 'tone' => 'neutral'],
        ]);
        echo $this->build_progress_strip($coverage);
        echo $this->build_reconciliation_command_center($orders, $coverage);
        echo $this->build_table('Fila crítica de conciliação', $this->build_reconciliation_priority_rows($orders), ['Prioridade', 'Pedido', 'Diagnóstico', 'Ação recomendada']);
        echo $this->build_shell_end();
        echo '</div>';
    }


    private function build_reconciliation_command_center(array $orders, int $coverage): string {
        $critical = 0;
        $watchlist = 0;
        $recoverable = 0;

        foreach ($orders as $order) {
            $status = (string) ($order['status'] ?? '');
            if (in_array($status, ['failed', 'cancelled'], true)) {
                $critical++;
            } elseif (in_array($status, ['pending', 'on-hold'], true)) {
                $watchlist++;
            } elseif (in_array($status, ['processing', 'completed'], true)) {
                $recoverable++;
            }
        }

        $reliability = (int) max(0, min(100, $coverage - ($critical * 5) - ($watchlist * 2)));
        $tone = $reliability >= 80 ? 'is-good' : ($reliability >= 60 ? 'is-warn' : 'is-bad');
        $label = $reliability >= 80 ? 'Conciliação premium estável' : ($reliability >= 60 ? 'Atenção tática de conciliação' : 'Conciliação sob risco crítico');

        $html = '<div class="rma-fin-exec-center">';
        $html .= '<div class="rma-fin-exec-head"><h4>Control Desk de Conciliação</h4><p>Monitoramento executivo para garantir liquidação e baixa com excelência operacional.</p></div>';
        $html .= '<div class="rma-fin-exec-grid">';
        $html .= '<div class="rma-fin-exec-card"><small>Reconciliation Reliability Index</small><strong>' . esc_html((string) $reliability) . '%</strong><span class="rma-fin-badge ' . esc_attr($tone) . '">' . esc_html($label) . '</span></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Pedidos críticos</small><strong>' . esc_html((string) $critical) . '</strong><p>Status failed/cancelled para ação imediata.</p></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Watchlist operacional</small><strong>' . esc_html((string) $watchlist) . '</strong><p>Pedidos pendentes ou on-hold em observação ativa.</p></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Liquidação processável</small><strong>' . esc_html((string) $recoverable) . '</strong><p>Pedidos em processing/completed prontos para fechamento.</p></div>';
        $html .= '</div></div>';

        return $html;
    }

    private function build_reconciliation_priority_rows(array $orders): array {
        $rows = [];

        foreach ($orders as $order) {
            $status = (string) ($order['status'] ?? '');
            $order_id = '#' . (int) ($order['id'] ?? 0);
            $entity = (string) ($order['entity_name'] ?? 'N/I');
            $total = (string) ($order['total'] ?? 'R$ 0,00');
            $year = (string) ($order['due_year'] ?? 'N/D');

            if (in_array($status, ['failed', 'cancelled'], true)) {
                $priority = $this->format_status_badge('error', 'P0');
                $action = 'Abrir tratativa imediata com financeiro e suporte para regularização no mesmo dia.';
            } elseif (in_array($status, ['pending', 'on-hold'], true)) {
                $priority = $this->format_status_badge('warning', 'P1');
                $action = 'Revalidar status de pagamento e executar follow-up com SLA de 4h.';
            } else {
                $priority = $this->format_status_badge('success', 'P2');
                $action = 'Concluir conciliação contábil e registrar baixa no ciclo atual.';
            }

            $rows[] = [
                $priority,
                $order_id,
                $entity . ' · ' . strtoupper($status) . ' · ' . $total . ' · Ano ' . $year,
                $action,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $weight = ['P0' => 3, 'P1' => 2, 'P2' => 1];
            preg_match('/>(P[0-9])</', (string) ($a[0] ?? ''), $ma);
            preg_match('/>(P[0-9])</', (string) ($b[0] ?? ''), $mb);
            return (($weight[$mb[1] ?? 'P2'] ?? 0) <=> ($weight[$ma[1] ?? 'P2'] ?? 0));
        });

        if (empty($rows)) {
            $rows[] = [
                $this->format_status_badge('success', 'P3'),
                'Sem pedidos',
                'Não há pedidos de anuidade para conciliação nesta janela.',
                'Manter monitoramento contínuo e rotina de auditoria semanal.',
            ];
        }

        return array_slice($rows, 0, 12);
    }


    private function build_finance_command_center(array $data, array $orders): string {
        $entities = isset($data['entities']) && is_array($data['entities']) ? $data['entities'] : [];
        $inadimplentes = 0;
        $high_value_open = 0;
        $late_orders = 0;

        foreach ($entities as $entity) {
            $status = (string) ($entity['finance_status'] ?? '');
            $amount = (float) ($entity['estimated_raw'] ?? 0);
            if ($status !== 'adimplente') {
                $inadimplentes++;
                if ($amount >= 1000) {
                    $high_value_open++;
                }
            }
        }

        foreach ($orders as $order) {
            $status = (string) ($order['status'] ?? '');
            if (in_array($status, ['pending', 'on-hold', 'failed', 'cancelled'], true)) {
                $late_orders++;
            }
        }

        $closing_index = (int) max(0, min(100, 100 - ($inadimplentes * 5) - ($late_orders * 3)));
        $index_tone = $closing_index >= 80 ? 'is-good' : ($closing_index >= 60 ? 'is-warn' : 'is-bad');
        $index_label = $closing_index >= 80 ? 'Fechamento em trilho premium' : ($closing_index >= 60 ? 'Risco moderado de fechamento' : 'Fechamento sob pressão');

        $html = '<div class="rma-fin-exec-center">';
        $html .= '<div class="rma-fin-exec-head"><h4>Centro de Fechamento Financeiro</h4><p>Visão premium de cobrança, risco e priorização para fechar o ciclo financeiro.</p></div>';
        $html .= '<div class="rma-fin-exec-grid">';
        $html .= '<div class="rma-fin-exec-card"><small>Closing Readiness Index</small><strong>' . esc_html((string) $closing_index) . '%</strong><span class="rma-fin-badge ' . esc_attr($index_tone) . '">' . esc_html($index_label) . '</span></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Entidades inadimplentes</small><strong>' . esc_html((string) $inadimplentes) . '</strong><p>Foco em reversão para adimplência até D+2.</p></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Backlog de alto valor</small><strong>' . esc_html((string) $high_value_open) . '</strong><p>Títulos abertos com valor estimado >= R$ 1.000.</p></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Pedidos com risco de atraso</small><strong>' . esc_html((string) $late_orders) . '</strong><p>Pedidos pendentes, on-hold, failed ou cancelled.</p></div>';
        $html .= '</div></div>';

        return $html;
    }

    private function build_closing_priority_rows(array $entities, array $orders): array {
        $order_risk_by_entity = [];
        foreach ($orders as $order) {
            $entity_name = (string) ($order['entity_name'] ?? 'N/I');
            $status = (string) ($order['status'] ?? '');
            if (! isset($order_risk_by_entity[$entity_name])) {
                $order_risk_by_entity[$entity_name] = 0;
            }
            if (in_array($status, ['pending', 'on-hold', 'failed', 'cancelled'], true)) {
                $order_risk_by_entity[$entity_name] += 2;
            } elseif (! in_array($status, ['processing', 'completed'], true)) {
                $order_risk_by_entity[$entity_name] += 1;
            }
        }

        $rows = [];
        foreach ($entities as $entity) {
            $name = (string) ($entity['name'] ?? 'Entidade');
            $status = (string) ($entity['finance_status'] ?? 'inadimplente');
            if ($status === 'adimplente') {
                continue;
            }

            $risk = (float) ($entity['estimated_raw'] ?? 0) >= 1000 ? 2 : 1;
            $risk += (int) ($order_risk_by_entity[$name] ?? 0);
            $priority_badge = $risk >= 4
                ? $this->format_status_badge('error', 'P0')
                : ($risk >= 3 ? $this->format_status_badge('warning', 'P1') : $this->format_status_badge('pending', 'P2'));

            $rows[] = [
                $priority_badge,
                $name,
                'Status ' . strtoupper($status) . ' · Vencimento: ' . (string) ($entity['due_date'] ?? 'N/D') . ' · Valor: ' . (string) ($entity['estimated_value'] ?? 'R$ 0,00'),
                $risk >= 4
                    ? 'Contato executivo imediato + proposta de regularização em até 4h.'
                    : ($risk >= 3
                        ? 'Disparar playbook de negociação com follow-up no mesmo dia.'
                        : 'Acionar lembrete automatizado e reconfirmar status em 24h.'),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $weight = ['P0' => 3, 'P1' => 2, 'P2' => 1];
            preg_match('/>(P[0-9])</', (string) ($a[0] ?? ''), $ma);
            preg_match('/>(P[0-9])</', (string) ($b[0] ?? ''), $mb);
            $wa = $weight[$ma[1] ?? 'P2'] ?? 0;
            $wb = $weight[$mb[1] ?? 'P2'] ?? 0;
            return $wb <=> $wa;
        });

        if (empty($rows)) {
            $rows[] = [
                $this->format_status_badge('success', 'P3'),
                'Carteira saudável',
                'Não há entidades inadimplentes na base atual.',
                'Manter cadência premium de relacionamento e prevenção de churn financeiro.',
            ];
        }

        return array_slice($rows, 0, 8);
    }


    private function build_receivables_command_center(array $entities): string {
        $now = gmdate('Y-m-d');
        $total_open = 0;
        $overdue = 0;
        $high_value = 0;
        $critical = 0;

        foreach ($entities as $entity) {
            $status = (string) ($entity['finance_status'] ?? '');
            if ($status === 'adimplente') {
                continue;
            }
            $total_open++;

            $due_sort = (string) ($entity['due_date_sort'] ?? '0000-00-00');
            if ($due_sort !== '0000-00-00' && $due_sort < $now) {
                $overdue++;
            }

            $value = (float) ($entity['estimated_raw'] ?? 0);
            if ($value >= 1000) {
                $high_value++;
            }
            if ($value >= 1000 && $due_sort !== '0000-00-00' && $due_sort < $now) {
                $critical++;
            }
        }

        $recovery_index = (int) max(0, min(100, 100 - ($overdue * 8) - ($critical * 6)));
        $tone = $recovery_index >= 80 ? 'is-good' : ($recovery_index >= 60 ? 'is-warn' : 'is-bad');
        $label = $recovery_index >= 80 ? 'Cobrança sob controle' : ($recovery_index >= 60 ? 'Atenção em recuperação' : 'Recuperação crítica');

        $html = '<div class="rma-fin-exec-center">';
        $html .= '<div class="rma-fin-exec-head"><h4>Control Tower de Cobrança</h4><p>Visão premium para recuperação de recebíveis e foco tático diário.</p></div>';
        $html .= '<div class="rma-fin-exec-grid">';
        $html .= '<div class="rma-fin-exec-card"><small>Recovery Readiness Index</small><strong>' . esc_html((string) $recovery_index) . '%</strong><span class="rma-fin-badge ' . esc_attr($tone) . '">' . esc_html($label) . '</span></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Carteira em aberto</small><strong>' . esc_html((string) $total_open) . '</strong><p>Entidades fora de adimplência.</p></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Títulos vencidos</small><strong>' . esc_html((string) $overdue) . '</strong><p>Demandam contato ativo prioritário.</p></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Risco financeiro crítico</small><strong>' . esc_html((string) $critical) . '</strong><p>Vencidos de alto valor (>= R$ 1.000).</p></div>';
        $html .= '</div></div>';

        return $html;
    }

    private function build_receivables_priority_rows(array $entities): array {
        $rows = [];
        $now_ts = strtotime(gmdate('Y-m-d'));

        foreach ($entities as $entity) {
            $status = (string) ($entity['finance_status'] ?? '');
            if ($status === 'adimplente') {
                continue;
            }

            $due_sort = (string) ($entity['due_date_sort'] ?? '0000-00-00');
            $due_ts = $due_sort !== '0000-00-00' ? strtotime($due_sort . ' UTC') : 0;
            $days_overdue = $due_ts > 0 ? (int) floor(($now_ts - $due_ts) / 86400) : 0;
            $value = (float) ($entity['estimated_raw'] ?? 0);

            $risk = 1;
            if ($days_overdue > 0) {
                $risk += min(3, (int) floor($days_overdue / 10) + 1);
            }
            if ($value >= 1000) {
                $risk += 2;
            }

            $priority = $risk >= 5 ? 'P0' : ($risk >= 3 ? 'P1' : 'P2');
            $priority_badge = $priority === 'P0'
                ? $this->format_status_badge('error', 'P0')
                : ($priority === 'P1' ? $this->format_status_badge('warning', 'P1') : $this->format_status_badge('pending', 'P2'));

            $rows[] = [
                $priority_badge,
                (string) ($entity['name'] ?? 'Entidade'),
                'Vencimento: ' . (string) ($entity['due_date'] ?? 'N/D') . ' · Atraso: ' . ($days_overdue > 0 ? (string) $days_overdue . ' dias' : 'a vencer') . ' · Valor: ' . (string) ($entity['estimated_value'] ?? 'R$ 0,00'),
                $priority === 'P0'
                    ? 'Acionar gestor + trilha de negociação com deadline de regularização hoje.'
                    : ($priority === 'P1'
                        ? 'Contato ativo com proposta de parcelamento e retorno no mesmo dia.'
                        : 'Lembrete preventivo e confirmação de previsão de pagamento.'),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $weight = ['P0' => 3, 'P1' => 2, 'P2' => 1];
            preg_match('/>(P[0-9])</', (string) ($a[0] ?? ''), $ma);
            preg_match('/>(P[0-9])</', (string) ($b[0] ?? ''), $mb);
            return (($weight[$mb[1] ?? 'P2'] ?? 0) <=> ($weight[$ma[1] ?? 'P2'] ?? 0));
        });

        if (empty($rows)) {
            $rows[] = [
                $this->format_status_badge('success', 'P3'),
                'Sem pendências',
                'Não há títulos abertos no momento.',
                'Manter rotina premium de prevenção com lembretes pró-ativos.',
            ];
        }

        return array_slice($rows, 0, 10);
    }


    public function render_admin_observability_page(): void {
        $source_filter = isset($_GET['source']) ? sanitize_key((string) wp_unslash($_GET['source'])) : '';
        $severity_filter = isset($_GET['severity']) ? sanitize_key((string) wp_unslash($_GET['severity'])) : '';
        $entity_search = isset($_GET['entity']) ? sanitize_text_field((string) wp_unslash($_GET['entity'])) : '';
        $export = isset($_GET['export']) ? sanitize_key((string) wp_unslash($_GET['export'])) : '';

        $result = $this->get_observability_rows($source_filter, $severity_filter, $entity_search);

        if ($export === 'csv') {
            $this->download_observability_csv($result['raw_rows']);
            return;
        }

        $rows = $result['rows'];
        $source_counts = $result['source_counts'];
        $severity_counts = $result['severity_counts'];

        $critical_count = 0;
        $warning_count = 0;
        $last_event = '—';
        if (! empty($result['raw_rows'])) {
            $last_event = (string) ($result['raw_rows'][0]['datetime'] ?? '—');
            foreach ($result['raw_rows'] as $event) {
                $sev = (string) ($event['severity'] ?? 'info');
                if ($sev === 'error') {
                    $critical_count++;
                }
                if ($sev === 'warning') {
                    $warning_count++;
                }
            }
        }

        $base_export_url = add_query_arg([
            'page' => 'rma-finance-crm-observability',
            'source' => $source_filter,
            'severity' => $severity_filter,
            'entity' => $entity_search,
            'export' => 'csv',
        ], admin_url('admin.php'));

        echo '<div class="wrap"><h1>CRM Financeiro RMA — Observabilidade</h1>';
        echo $this->build_shell_start('rma-finance-crm-observability', 'Linha do tempo operacional unificada para auditoria, troubleshooting e compliance.');
        echo '<form method="get" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-bottom:12px">';
        echo '<input type="hidden" name="page" value="rma-finance-crm-observability" />';
        echo '<input type="text" name="entity" value="' . esc_attr($entity_search) . '" placeholder="Buscar entidade" style="height:38px;border:1px solid #d5e3ef;border-radius:10px;padding:0 10px" />';
        echo '<select name="source" style="height:38px;border:1px solid #d5e3ef;border-radius:10px;padding:0 10px"><option value="">Todas as origens</option>';
        foreach (array_keys($source_counts) as $source) {
            echo '<option value="' . esc_attr($source) . '" ' . selected($source_filter, $source, false) . '>' . esc_html(strtoupper($source) . ' (' . (int) $source_counts[$source] . ')') . '</option>';
        }
        echo '</select>';
        echo '<select name="severity" style="height:38px;border:1px solid #d5e3ef;border-radius:10px;padding:0 10px"><option value="">Todas as severidades</option>';
        foreach (array_keys($severity_counts) as $severity) {
            echo '<option value="' . esc_attr($severity) . '" ' . selected($severity_filter, $severity, false) . '>' . esc_html(strtoupper($severity) . ' (' . (int) $severity_counts[$severity] . ')') . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" style="height:38px;border:none;border-radius:10px;background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;font-weight:700;cursor:pointer">Aplicar filtros</button>';
        echo '</form>';

        $quick_actions = [
            'Criticidade alta' => add_query_arg(['page' => 'rma-finance-crm-observability', 'severity' => 'error'], admin_url('admin.php')),
            'Warnings operacionais' => add_query_arg(['page' => 'rma-finance-crm-observability', 'severity' => 'warning'], admin_url('admin.php')),
            'Governança' => add_query_arg(['page' => 'rma-finance-crm-observability', 'source' => 'governance'], admin_url('admin.php')),
            'Financeiro' => add_query_arg(['page' => 'rma-finance-crm-observability', 'source' => 'finance'], admin_url('admin.php')),
            'Automações' => add_query_arg(['page' => 'rma-finance-crm-observability', 'source' => 'automation'], admin_url('admin.php')),
            'Reset filtros' => add_query_arg(['page' => 'rma-finance-crm-observability'], admin_url('admin.php')),
        ];

        echo '<div class="rma-fin-quick-actions">';
        foreach ($quick_actions as $label => $url) {
            echo '<a class="rma-fin-quick-action" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</div>';

        echo '<p style="margin:0 0 10px"><a href="' . esc_url($base_export_url) . '" class="button button-primary">Exportar CSV</a></p>';

        $obs_analytics = $this->build_observability_analytics($result['raw_rows']);
        echo $this->build_kpi_cards([
            ['label' => 'Eventos exibidos', 'value' => (string) count($rows), 'tone' => 'neutral'],
            ['label' => 'Erros críticos', 'value' => (string) $critical_count, 'tone' => $critical_count > 0 ? 'warn' : 'good'],
            ['label' => 'Warnings', 'value' => (string) $warning_count, 'tone' => $warning_count > 0 ? 'warn' : 'neutral'],
            ['label' => 'Último evento', 'value' => $last_event, 'tone' => 'neutral'],
            ['label' => 'Global Health Score', 'value' => (string) $obs_analytics['global_health'] . '%', 'tone' => $obs_analytics['global_health'] >= 80 ? 'good' : 'warn'],
        ]);

        $sla_snapshot = $this->build_observability_sla_snapshot($result['raw_rows']);
        echo $this->build_observability_command_center($obs_analytics, $sla_snapshot);
        echo $this->build_table('Alertas inteligentes de operação', $this->build_observability_alert_rows($obs_analytics), ['Prioridade', 'Regra', 'Diagnóstico', 'Ação recomendada']);
        echo $this->build_table('SLA operacional de resposta', $this->build_observability_sla_rows($sla_snapshot), ['Indicador', 'Status', 'Diagnóstico', 'Meta recomendada']);
        echo $this->build_table('Playbook executivo (próximos passos)', $this->build_observability_playbook_rows($obs_analytics, $sla_snapshot), ['Prioridade', 'Objetivo', 'Ação', 'SLA interno']);
        echo $this->build_observability_visuals($obs_analytics);
        echo $this->build_table('Entidades com maior risco (erros críticos)', $obs_analytics['critical_entities_rows'], ['Entidade', 'Erros', 'Warnings', 'Eventos', 'Risk Score']);
        echo $this->build_table('Tendência operacional (últimos 7 dias)', $obs_analytics['trend_rows'], ['Dia', 'Eventos', 'Erros', 'Warnings', 'Health %']);
        echo $this->build_table('Matriz origem × severidade', $obs_analytics['matrix_rows'], ['Origem', 'INFO', 'SUCCESS', 'WARNING', 'ERROR', 'Total']);

        echo $this->build_observability_table($result['raw_rows']);
        echo "<script>(function(){var root=document.querySelector('.rma-fin-shell');if(!root){return;}var modal=root.querySelector('.rma-obs-modal');var code=root.querySelector('.rma-obs-modal-code');var closeBtn=root.querySelector('.rma-obs-modal-close');if(!modal||!code||!closeBtn){return;}root.querySelectorAll('.rma-obs-view-context').forEach(function(btn){btn.addEventListener('click',function(){var payload=btn.getAttribute('data-context')||'';try{code.textContent=payload?JSON.stringify(JSON.parse(payload),null,2):'{}';}catch(e){code.textContent=payload||'{}';}modal.classList.add('is-open');});});closeBtn.addEventListener('click',function(){modal.classList.remove('is-open');});modal.addEventListener('click',function(ev){if(ev.target===modal){modal.classList.remove('is-open');}});})();</script>";
        echo $this->build_shell_end();
        echo '</div>';
    }

    public function render_admin_reports_page(): void {
        $report = $this->build_admin_reports_data();

        $state_rows = [];
        foreach ($report['state_summary'] as $state => $summary) {
            $state_rows[] = [
                $state,
                (string) $summary['total'],
                $this->format_status_badge('adimplente', (string) $summary['adimplentes']),
                $this->format_status_badge('inadimplente', (string) $summary['inadimplentes']),
            ];
        }

        $area_rows = [];
        foreach ($report['area_summary'] as $area => $summary) {
            $area_rows[] = [
                $area,
                (string) $summary['total'],
                $this->format_status_badge('active', (string) $summary['ativas']),
                $this->format_status_badge('inadimplente', (string) $summary['inativas']),
            ];
        }

        $annual_rows = [];
        foreach ($report['annual_revenue'] as $year => $value) {
            $annual_rows[] = [(string) $year, $this->format_money((float) $value)];
        }

        $history_rows = [];
        foreach ($report['history_rows'] as $item) {
            $history_rows[] = [
                $item['entity_name'],
                (string) $item['year'],
                $this->format_status_badge((string) $item['finance_status'], strtoupper((string) $item['finance_status'])),
                $this->format_money((float) $item['total']),
                '#' . (int) $item['order_id'],
                (string) $item['paid_at'],
            ];
        }

        echo '<div class="wrap"><h1>CRM Financeiro RMA — Relatórios</h1>';
        echo $this->build_shell_start('rma-finance-crm-reports', 'Conjunto completo de relatórios administrativos: estado, área, ativos/inativos, receita anual e histórico.');
        echo $this->build_kpi_cards([
            ['label' => 'Entidades ativas', 'value' => (string) $report['active_count'], 'tone' => 'good'],
            ['label' => 'Entidades inativas', 'value' => (string) $report['inactive_count'], 'tone' => 'warn'],
            ['label' => 'Receita acumulada', 'value' => $this->format_money((float) $report['revenue_total']), 'tone' => 'neutral'],
            ['label' => 'Eventos no histórico', 'value' => (string) $report['history_total'], 'tone' => 'neutral'],
        ]);
        echo $this->build_table('Relatório por estado', $state_rows, ['UF', 'Entidades', 'Adimplentes', 'Inadimplentes']);
        echo $this->build_table('Relatório por área de interesse', $area_rows, ['Área', 'Entidades', 'Ativas', 'Inativas']);
        echo $this->build_table('Receita anual', $annual_rows, ['Ano', 'Receita']);
        echo $this->build_table('Histórico geral de movimentações', $history_rows, ['Entidade', 'Ano', 'Status', 'Valor', 'Pedido', 'Data']);
        echo $this->build_shell_end();
        echo '</div>';
    }

    public function render_admin_shortcode(): string {
        if (! current_user_can('manage_options')) {
            return '<p>Acesso restrito.</p>';
        }

        ob_start();
        $this->render_admin_dashboard_page();
        return (string) ob_get_clean();
    }

    public function render_entity_shortcode($atts = []): string {
        if (! is_user_logged_in()) {
            return '<p>Faça login para acessar o CRM financeiro da entidade.</p>';
        }

        $entity_id = $this->get_entity_id_by_user(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada à conta atual.</p>';
        }

        $atts = shortcode_atts([
            'tab' => '',
        ], is_array($atts) ? $atts : [], 'rma_financeiro_entidade_crm');

        $tab = sanitize_key((string) $atts['tab']);
        if ($tab === '') {
            $tab = isset($_GET['finance_tab']) ? sanitize_key((string) wp_unslash($_GET['finance_tab'])) : '';
        }
        if ($tab === '') {
            $tab = 'visao-geral';
        }

        $allowed_tabs = ['visao-geral', 'cobranca', 'faturas', 'historico', 'suporte'];
        if (! in_array($tab, $allowed_tabs, true)) {
            $tab = 'visao-geral';
        }

        $entity = $this->build_entity_finance_row($entity_id);
        $history = $this->get_entity_history_rows($entity_id);
        $audit_rows = $this->get_entity_audit_rows($entity_id);
        $latest_order = $this->get_latest_order_for_entity($entity_id);

        $is_dashboard_context = isset($_GET['ext']) && strpos((string) wp_unslash($_GET['ext']), 'rma-financeiro-') === 0;
        $tab_url = function (string $slug) use ($is_dashboard_context): string {
            if ($is_dashboard_context) {
                return add_query_arg('ext', 'rma-financeiro-' . $slug, home_url('/dashboard/'));
            }
            return add_query_arg('finance_tab', $slug);
        };

        ob_start();
        echo '<div class="rma-fin-shell">';
        echo $this->build_styles();
        echo '<div class="rma-fin-header"><h3>Financeiro da Entidade</h3><p>Menus implementados no dashboard para visão geral, cobrança, minhas faturas e histórico.</p></div>';

        $tabs = [
            'visao-geral' => 'Visão Geral',
            'cobranca' => 'Minha Cobrança',
            'faturas' => 'Minhas Faturas',
            'historico' => 'Histórico',
        ];
        echo '<div class="rma-fin-nav">';
        foreach ($tabs as $slug => $label) {
            $active = $slug === $tab ? ' is-active' : '';
            echo '<a class="rma-fin-tab-link' . esc_attr($active) . '" href="' . esc_url($tab_url($slug)) . '">' . esc_html($label) . '</a>';
        }
        echo '</div>';

        echo '<div class="rma-fin-entity-identity">';
        echo '<div><small>Entidade</small><strong>' . esc_html($entity['name']) . '</strong></div>';
        echo '<div><small>Status atual</small>' . $entity['status_badge'] . '</div>';
        echo '<div><small>Último pedido</small>' . $entity['last_order_badge'] . '</div>';
        echo '</div>';

        if ($tab === 'visao-geral') {
            echo $this->build_kpi_cards([
                ['label' => 'Status Financeiro', 'value' => strtoupper($entity['finance_status']), 'tone' => $entity['finance_status'] === 'adimplente' ? 'good' : 'warn'],
                ['label' => 'Vencimento', 'value' => $entity['due_date'], 'tone' => 'neutral'],
                ['label' => 'Valor Anuidade', 'value' => $entity['estimated_value'], 'tone' => 'neutral'],
                ['label' => 'Ano de referência', 'value' => $entity['due_year'], 'tone' => 'neutral'],
            ]);
        } elseif ($tab === 'cobranca') {
            $charge_rows = [[
                $entity['status_badge'],
                $entity['due_date'],
                $entity['estimated_value'],
                $entity['last_order_badge'],
                $entity['finance_status'] === 'adimplente' ? 'Situação regular.' : 'Regularizar até a data de vencimento para liberar governança completa.',
            ]];
            echo $this->build_table('Minha cobrança', $charge_rows, ['Status', 'Vencimento', 'Valor', 'Último pedido', 'Observação']);
        } elseif ($tab === 'faturas') {
            $checkout_url = apply_filters('rma_checkout_url', home_url('/checkout/'));
            $product_id = (int) get_option('rma_annual_dues_product_id', 0);
            $links = [];
            for ($qty = 1; $qty <= 3; $qty++) {
                $label = 'Gerar ' . $qty . ' anuidade' . ($qty > 1 ? 's' : '');
                $url = $product_id > 0 ? add_query_arg(['add-to-cart' => $product_id, 'quantity' => $qty], $checkout_url) : $checkout_url;
                $links[] = '<a class="rma-fin-quick-action" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
            }
            echo '<div class="rma-fin-obs-panel"><h4>Próximos vencimentos</h4><p style="margin:0 0 10px;color:#475569">Selecione abaixo a quantidade de anuidades para pagar vários anos da filiação.</p><div style="display:flex;flex-wrap:wrap;gap:8px">' . implode('', $links) . '</div></div>';
            $charge_rows = [[
                $entity['due_date'],
                $entity['estimated_value'],
                $entity['status_badge'],
                'Botões acima habilitam pagamento de 1, 2 ou 3 anuidades.',
            ]];
            echo $this->build_table('Minhas Faturas', $charge_rows, ['Próximo vencimento', 'Valor', 'Status', 'Ação']);
        } elseif ($tab === 'historico') {
            echo $this->build_table('Histórico da sua entidade', $history, ['Status', 'Valor', 'Ano', 'Pedido', 'Data']);
        } elseif ($tab === 'suporte') {
            return $this->render_entity_support_panel();
        } else {
            echo $this->build_kpi_cards([
                ['label' => 'Eventos auditáveis', 'value' => (string) count($audit_rows), 'tone' => 'neutral'],
                ['label' => 'Status financeiro', 'value' => strtoupper($entity['finance_status']), 'tone' => $entity['finance_status'] === 'adimplente' ? 'good' : 'warn'],
                ['label' => 'Último pedido', 'value' => strtoupper((string) ($entity['last_order_status'] ?? 'sem pedido')), 'tone' => 'neutral'],
            ]);
            echo $this->build_table('Linha do tempo operacional', $audit_rows, ['Data', 'Origem', 'Evento', 'Severidade', 'Mensagem']);
        }

        echo '</div>';

        return (string) ob_get_clean();
    }

    public function inject_entity_dashboard_documents_menu(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }
        if (class_exists('RMA_Governance')) {
            return;
        }
        ?>
        <script>
        (function(){
            function mountDocsMenu(){
                var docsToggle = Array.prototype.slice.call(document.querySelectorAll('.menu-title')).find(function(node){
                    return (node.textContent || '').trim().toLowerCase() === 'documentos';
                });
                if (!docsToggle) { return false; }

                var navLink = docsToggle.closest('a.nav-link');
                if (!navLink) { return false; }

                var collapseId = navLink.getAttribute('href');
                if (!collapseId || collapseId.charAt(0) !== '#') { return false; }
                var collapse = document.querySelector(collapseId);
                if (!collapse) { return false; }

                var base = window.location.origin + window.location.pathname;
                var url = new URL(window.location.href);
                var activeExt = (url.searchParams.get('ext') || '').toLowerCase();
                var linkClass = function(ext){ return 'nav-link' + (activeExt === ext ? ' active' : ''); };
                var govExts = ['rma-governanca-documentos','rma-governanca-pendencias','rma-governanca-status','rma-governanca-upload'];
                if (govExts.indexOf(activeExt) !== -1) {
                    collapse.classList.add('show');
                    navLink.classList.add('active');
                    navLink.setAttribute('aria-expanded', 'true');
                }

                collapse.innerHTML = [
                    '<ul class="nav flex-column sub-menu">',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-documentos')+'" href="'+base+'?ext=rma-governanca-documentos">Documentos Enviados</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-pendencias')+'" href="'+base+'?ext=rma-governanca-pendencias">Pendências</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-status')+'" href="'+base+'?ext=rma-governanca-status">Status</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-governanca-upload')+'" href="'+base+'?ext=rma-governanca-upload">Enviar Documentos</a></li>',
                    '</ul>'
                ].join('');
                return true;
            }

            var tries = 0;
            var timer = setInterval(function(){
                tries++;
                if (mountDocsMenu() || tries > 20) {
                    clearInterval(timer);
                }
            }, 350);
        })();
        </script>
        <?php
    }


    public function inject_entity_dashboard_finance_menu(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }
        if (class_exists('RMA_Governance')) {
            return;
        }
        ?>
        <script>
        (function(){
            function mountFinanceMenu(){
                var financeToggle = Array.prototype.slice.call(document.querySelectorAll('.menu-title')).find(function(node){
                    var txt = (node.textContent || '').trim().toLowerCase();
                    return txt === 'financeiro';
                });
                if (!financeToggle) { return false; }

                var navLink = financeToggle.closest('a.nav-link');
                if (!navLink) { return false; }

                var collapseId = navLink.getAttribute('href');
                if (!collapseId || collapseId.charAt(0) !== '#') { return false; }
                var collapse = document.querySelector(collapseId);
                if (!collapse) { return false; }

                var base = window.location.origin + window.location.pathname;
                var url = new URL(window.location.href);
                var activeExt = (url.searchParams.get('ext') || '').toLowerCase();
                var linkClass = function(ext){ return 'nav-link' + (activeExt === ext ? ' active' : ''); };
                var finExts = ['rma-financeiro-visao-geral','rma-financeiro-cobranca','rma-financeiro-faturas','rma-financeiro-historico','rma-financeiro-pix','rma-financeiro-relatorios'];
                if (finExts.indexOf(activeExt) !== -1) {
                    collapse.classList.add('show');
                    navLink.classList.add('active');
                    navLink.setAttribute('aria-expanded', 'true');
                }
                collapse.innerHTML = [
                    '<ul class="nav flex-column sub-menu">',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-visao-geral')+'" href="'+base+'?ext=rma-financeiro-visao-geral">Visão Geral</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-cobranca')+'" href="'+base+'?ext=rma-financeiro-cobranca">Minha Cobrança</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-faturas')+'" href="'+base+'?ext=rma-financeiro-faturas">Minhas Faturas</a></li>',
                    '<li class="nav-item"><a class="'+linkClass('rma-financeiro-historico')+'" href="'+base+'?ext=rma-financeiro-historico">Histórico</a></li>',
                    '</ul>'
                ].join('');
                return true;
            }

            var tries = 0;
            var timer = setInterval(function(){
                tries++;
                if (mountFinanceMenu() || tries > 20) {
                    clearInterval(timer);
                }
            }, 350);
        })();
        </script>
        <?php
    }

    public function inject_entity_dashboard_support_menu(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }
        if (class_exists('RMA_Governance')) {
            return;
        }
        ?>
        <script>
        (function(){
            function mountSupportMenu(){
                var supportToggle = Array.prototype.slice.call(document.querySelectorAll('.menu-title')).find(function(node){
                    return (node.textContent || '').trim().toLowerCase() === 'suporte';
                });
                if (!supportToggle) { return false; }

                var navLink = supportToggle.closest('a.nav-link');
                if (!navLink) { return false; }

                var icon = navLink.querySelector('.menu-icon, i');
                if (icon) {
                    icon.className = 'menu-icon rma-support-icon';
                    icon.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:block"><path d="M4 6.5C4 5.67 4.67 5 5.5 5h13c.83 0 1.5.67 1.5 1.5v8c0 .83-.67 1.5-1.5 1.5H9l-4.2 3.1c-.33.24-.8.01-.8-.4V16.5C4 15.67 4.67 15 5.5 15" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 9h8M8 12h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
                }

                var base = window.location.origin + window.location.pathname;
                var url = new URL(window.location.href);
                var activeExt = (url.searchParams.get('ext') || '').toLowerCase();
                var supportExts = ['saved-services','rma-suporte','rma-suporte-novo','rma-suporte-tickets'];

                navLink.setAttribute('href', base + '?ext=saved-services');
                if (supportExts.indexOf(activeExt) !== -1) {
                    navLink.classList.add('active');
                }
                return true;
            }

            var tries = 0;
            var timer = setInterval(function(){
                tries++;
                if (mountSupportMenu() || tries > 20) {
                    clearInterval(timer);
                }
            }, 350);
        })();
        </script>
        <?php
    }

    public function inject_entity_dashboard_finance_content(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }
        if (class_exists('RMA_Governance')) {
            return;
        }

        $ext = isset($_GET['ext']) ? sanitize_key((string) wp_unslash($_GET['ext'])) : '';
        $map = [
            'rma-financeiro-visao-geral' => 'visao-geral',
            'rma-financeiro-cobranca' => 'cobranca',
            'rma-financeiro-faturas' => 'faturas',
            'rma-financeiro-pix' => 'historico',
            'rma-financeiro-historico' => 'historico',
            'rma-financeiro-relatorios' => 'historico',
        ];

        if (! isset($map[$ext])) {
            return;
        }

        $content = $this->render_entity_shortcode(['tab' => $map[$ext]]);
        ?>
        <script>
        (function(){
            var html = <?php echo wp_json_encode($content); ?>;
            var selectors = ['.main-panel .content-wrapper','.main-content .content-wrapper','.main-content','.content-wrapper','.dashboard-content-area','.dashboard-inner .row','.dashboard-wrapper'];

            function mount(){
                var target = null;
                for (var i=0;i<selectors.length;i++) {
                    target = document.querySelector(selectors[i]);
                    if (target) { break; }
                }
                if (!target) { return false; }
                target.innerHTML = html;
                return true;
            }

            var tries = 0;
            var timer = setInterval(function(){
                tries++;
                if (mount() || tries > 20) {
                    clearInterval(timer);
                }
            }, 350);
        })();
        </script>
        <?php
    }


    public function inject_entity_dashboard_home_modules(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }
        if (class_exists('RMA_Governance')) {
            return;
        }

        $ext = isset($_GET['ext']) ? sanitize_key((string) wp_unslash($_GET['ext'])) : '';
        if ($ext !== '' && ! in_array($ext, ['dashboard', 'index'], true)) {
            return;
        }

        $entity_id = $this->get_entity_id_by_user(get_current_user_id());
        if ($entity_id <= 0) {
            return;
        }

        $entity = $this->build_entity_finance_row($entity_id);
        $docs = get_post_meta($entity_id, 'entity_documents', true);
        $docs = is_array($docs) ? $docs : [];
        $documents_count = count($docs);

        $governance_status = sanitize_key((string) get_post_meta($entity_id, 'governance_status', true));
        $governance_pending = $governance_status === 'aprovado' ? 0 : 1;

        $finance_open = ($entity['finance_status'] ?? 'inadimplente') === 'adimplente' ? 0 : 1;

        $tickets = get_post_meta($entity_id, 'rma_support_tickets', true);
        $tickets = is_array($tickets) ? $tickets : [];
        $open_tickets = 0;
        foreach ($tickets as $ticket) {
            if (sanitize_key((string) ($ticket['status'] ?? 'aberto')) === 'aberto') {
                $open_tickets++;
            }
        }

        $cards = [
            [
                'title' => 'Documentos enviados',
                'value' => (string) $documents_count,
                'url' => add_query_arg('ext', 'rma-governanca-documentos', home_url('/dashboard/')),
            ],
            [
                'title' => 'Pendências de governança',
                'value' => (string) $governance_pending,
                'url' => add_query_arg('ext', 'rma-governanca-pendencias', home_url('/dashboard/')),
            ],
            [
                'title' => 'Status financeiro',
                'value' => $finance_open > 0 ? 'Pendente' : 'Em dia',
                'url' => add_query_arg('ext', 'rma-financeiro-cobranca', home_url('/dashboard/')),
            ],
            [
                'title' => 'Tickets de suporte abertos',
                'value' => (string) $open_tickets,
                'url' => add_query_arg('ext', 'rma-suporte-tickets', home_url('/dashboard/')),
            ],
        ];

        $due_sort = (string) ($entity['due_date_sort'] ?? '0000-00-00');
        $days_to_due = null;
        if ($due_sort !== '' && $due_sort !== '0000-00-00') {
            $due_ts = strtotime($due_sort . ' 00:00:00 UTC');
            if ($due_ts !== false) {
                $days_to_due = (int) floor(($due_ts - current_time('timestamp', true)) / DAY_IN_SECONDS);
            }
        }

        $compliance_score = 0;
        $compliance_score += $governance_pending === 0 ? 35 : 10;
        $compliance_score += $finance_open === 0 ? 35 : 10;
        $compliance_score += $documents_count > 0 ? 15 : 5;
        $compliance_score += $open_tickets === 0 ? 15 : 5;

        $due_badge = 'Sem vencimento';
        if ($days_to_due !== null) {
            if ($days_to_due < 0) {
                $due_badge = 'Atrasado há ' . abs($days_to_due) . ' dia(s)';
            } elseif ($days_to_due === 0) {
                $due_badge = 'Vence hoje';
            } else {
                $due_badge = 'Vence em ' . $days_to_due . ' dia(s)';
            }
        }

        $glass_cards = [
            [
                'icon' => '🧭',
                'title' => 'Índice de conformidade',
                'value' => $compliance_score . '%',
                'desc' => 'Saúde geral da entidade no ciclo RMA.',
                'url' => add_query_arg('ext', 'rma-governanca-visao-geral', home_url('/dashboard/')),
            ],
            [
                'icon' => '🗂️',
                'title' => 'Dossiê documental',
                'value' => (string) $documents_count,
                'desc' => 'Arquivos já enviados em Governança.',
                'url' => add_query_arg('ext', 'rma-governanca-documentos', home_url('/dashboard/')),
            ],
            [
                'icon' => '💚',
                'title' => 'Situação financeira',
                'value' => $finance_open > 0 ? 'Pendente' : 'Em dia',
                'desc' => $due_badge,
                'url' => add_query_arg('ext', 'rma-financeiro-cobranca', home_url('/dashboard/')),
            ],
            [
                'icon' => '🎫',
                'title' => 'Canal de suporte',
                'value' => (string) $open_tickets . ' aberto(s)',
                'desc' => 'Acompanhe seus tickets e retornos da Equipe RMA.',
                'url' => add_query_arg('ext', 'rma-suporte-tickets', home_url('/dashboard/')),
            ],
        ];

        ?>
        <script>
        (function(){
            var cards = <?php echo wp_json_encode($cards); ?>;
            var glassCards = <?php echo wp_json_encode($glass_cards); ?>;
            var cardAliases = ['projetos publicados','projetos em destaque','projetos em andamento','projetos concluídos'];

            function mountDashboardCustomizations() {
                var wrappers = document.querySelectorAll('.info-boxes .metric');

                if ((!wrappers || wrappers.length === 0)) {
                    wrappers = Array.prototype.slice.call(document.querySelectorAll('.metric,.counter-box,.card')).filter(function(node){
                        var t = (node.textContent || '').toLowerCase();
                        return cardAliases.some(function(alias){ return t.indexOf(alias) !== -1; });
                    });
                }

                if (wrappers && wrappers.length > 0) {
                    for (var i = 0; i < wrappers.length && i < cards.length; i++) {
                        var item = cards[i] || {};
                        var titleEl = wrappers[i].querySelector('.title,.counter-title,h6,h5,h4');
                        var numEl = wrappers[i].querySelector('.number,.counter-value,strong,h3');
                        var linkEl = wrappers[i].querySelector('a');
                        if (titleEl && item.title) { titleEl.textContent = item.title; }
                        if (numEl && typeof item.value !== 'undefined') { numEl.textContent = String(item.value); }
                        if (linkEl && item.url) { linkEl.setAttribute('href', item.url); }
                    }
                }

                var infoBoxes = document.querySelector('.info-boxes') || (wrappers && wrappers[0] ? wrappers[0].closest('.row') : null);
                if (infoBoxes && !document.getElementById('rma-entity-glass-cards')) {
                    if (!document.getElementById('rma-entity-glass-cards-style')) {
                        var style = document.createElement('style');
                        style.id = 'rma-entity-glass-cards-style';
                        style.textContent = '.rma-glass-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:16px 0 20px}.rma-glass-card{position:relative;display:block;padding:16px 16px 14px;border-radius:16px;border:1px solid rgba(255,255,255,.35);background:linear-gradient(135deg,rgba(123,173,57,.28),rgba(93,218,187,.22) 55%,rgba(30,207,152,.22));box-shadow:0 14px 32px rgba(27,39,59,.14);backdrop-filter:blur(8px);text-decoration:none;color:#17324d;overflow:hidden}.rma-glass-card:before{content:"";position:absolute;inset:-45% auto auto -25%;width:140px;height:140px;background:radial-gradient(circle,rgba(255,255,255,.45),rgba(255,255,255,0));pointer-events:none}.rma-glass-icon{font-size:20px;line-height:1;margin-bottom:10px}.rma-glass-title{margin:0 0 6px;font-size:13px;font-weight:700;letter-spacing:.02em;text-transform:uppercase;color:#24445f}.rma-glass-value{margin:0 0 4px;font-size:24px;font-weight:800;color:#0f2740}.rma-glass-desc{margin:0;font-size:13px;line-height:1.35;color:#315670}';
                        document.head.appendChild(style);
                    }

                    var glassGrid = document.createElement('div');
                    glassGrid.id = 'rma-entity-glass-cards';
                    glassGrid.className = 'rma-glass-grid';
                    glassCards.forEach(function(item){
                        var link = document.createElement('a');
                        link.className = 'rma-glass-card';
                        link.href = item.url || '#';
                        link.innerHTML = '<div class="rma-glass-icon">'+(item.icon || '✨')+'</div><p class="rma-glass-title">'+(item.title || '')+'</p><p class="rma-glass-value">'+(item.value || '0')+'</p><p class="rma-glass-desc">'+(item.desc || '')+'</p>';
                        glassGrid.appendChild(link);
                    });
                    infoBoxes.insertAdjacentElement('afterend', glassGrid);
                }

                var profileVisitsCard = Array.prototype.slice.call(document.querySelectorAll('.card, .dashboard-card, .widget')).find(function(node){
                    var heading = node.querySelector('h2,h3,h4,.card-title');
                    if (!heading) { return false; }
                    var txt = (heading.textContent || '').toLowerCase();
                    return txt.indexOf('visualiza') !== -1 && txt.indexOf('perfil') !== -1;
                });

                if (profileVisitsCard) {
                    var parentRow = profileVisitsCard.closest('.row');
                    if (parentRow) {
                        Array.prototype.slice.call(parentRow.children).forEach(function(col){
                            if (!col.contains(profileVisitsCard)) {
                                col.style.display = 'none';
                            } else {
                                col.classList.remove('col-xl-8','col-lg-8','col-md-8','col-xl-9','col-lg-9','col-md-9');
                                col.classList.add('col-12');
                            }
                        });
                    }
                }

                var host = document.querySelector('.main-panel .content-wrapper,.main-content .content-wrapper,.main-content,.content-wrapper,.dashboard-content-area');
                if ((!wrappers || wrappers.length === 0) && host && !document.getElementById('rma-smart-home-fallback')) {
                    var fallback = document.createElement('section');
                    fallback.id = 'rma-smart-home-fallback';
                    fallback.style.margin = '10px 0 18px';
                    fallback.innerHTML = '<div style="background:linear-gradient(135deg,rgba(123,173,57,.20),rgba(93,218,187,.18));border:1px solid rgba(125,160,190,.25);border-radius:16px;padding:14px 16px;margin:0 0 12px"><h3 style="margin:0 0 5px;color:#17324d">Painel Inteligente da Entidade</h3><p style="margin:0;color:#35556f">Indicadores estratégicos personalizados para governança, financeiro e suporte.</p></div>';
                    var grid = document.createElement('div');
                    grid.className = 'rma-glass-grid';
                    glassCards.forEach(function(item){
                        var link = document.createElement('a');
                        link.className = 'rma-glass-card';
                        link.href = item.url || '#';
                        link.innerHTML = '<div class="rma-glass-icon">'+(item.icon || '✨')+'</div><p class="rma-glass-title">'+(item.title || '')+'</p><p class="rma-glass-value">'+(item.value || '0')+'</p><p class="rma-glass-desc">'+(item.desc || '')+'</p>';
                        grid.appendChild(link);
                    });
                    fallback.appendChild(grid);
                    host.insertAdjacentElement('afterbegin', fallback);
                }

                var breadcrumb = document.querySelector('.breadcrumb-item.active');
                if (breadcrumb) {
                    breadcrumb.textContent = 'Painel de controle inteligente';
                }

                return (wrappers && wrappers.length > 0) || !!profileVisitsCard || !!document.getElementById('rma-smart-home-fallback');
            }

            var attempts = 0;
            var timer = setInterval(function(){
                attempts++;
                var mounted = mountDashboardCustomizations();
                if (mounted || attempts > 20) {
                    clearInterval(timer);
                }
            }, 400);

            var observer = new MutationObserver(function(){
                mountDashboardCustomizations();
            });
            observer.observe(document.body, { childList: true, subtree: true });
            setTimeout(function(){ observer.disconnect(); }, 12000);
        })();
        </script>
        <?php
    }

    public function inject_entity_dashboard_support_content(): void {
        if (is_admin() || ! is_user_logged_in()) {
            return;
        }
        if (class_exists('RMA_Governance')) {
            return;
        }

        $ext = isset($_GET['ext']) ? sanitize_key((string) wp_unslash($_GET['ext'])) : '';
        if (! in_array($ext, ['saved-services', 'rma-suporte', 'rma-suporte-novo', 'rma-suporte-tickets'], true)) {
            return;
        }

        $content = $this->render_entity_support_panel();
        ?>
        <script>
        (function(){
            var html = <?php echo wp_json_encode($content); ?>;
            var selectors = ['.main-panel .content-wrapper','.main-content .content-wrapper','.main-content','.content-wrapper','.dashboard-content-area','.dashboard-inner .row','.dashboard-wrapper'];

            function mount(){
                var target = null;
                for (var i=0;i<selectors.length;i++) {
                    target = document.querySelector(selectors[i]);
                    if (target) { break; }
                }
                if (!target) { return false; }
                target.innerHTML = html;
                return true;
            }

            var tries = 0;
            var timer = setInterval(function(){
                tries++;
                if (mount() || tries > 20) {
                    clearInterval(timer);
                }
            }, 350);
        })();
        </script>
        <?php
    }

    public function handle_entity_support_ticket_submit(): void {
        if (! is_user_logged_in()) {
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $action = isset($_POST['rma_support_action']) ? sanitize_key((string) wp_unslash($_POST['rma_support_action'])) : '';
        if ($action !== 'create_ticket') {
            return;
        }
        check_admin_referer('rma_support_create_ticket');

        $entity_id = $this->get_entity_id_by_user(get_current_user_id());
        if ($entity_id <= 0) {
            wp_safe_redirect(add_query_arg([
                'ext' => 'saved-services',
                'rma_support_notice' => rawurlencode('Entidade não encontrada para abertura de ticket.'),
                'rma_support_type' => 'error',
            ], home_url('/dashboard/')));
            exit;
        }

        $subject = sanitize_text_field((string) wp_unslash($_POST['ticket_subject'] ?? ''));
        $category = sanitize_key((string) wp_unslash($_POST['ticket_category'] ?? 'financeiro'));
        $priority = sanitize_key((string) wp_unslash($_POST['ticket_priority'] ?? 'media'));
        $message = sanitize_textarea_field((string) wp_unslash($_POST['ticket_message'] ?? ''));

        if ($subject === '' || $message === '') {
            wp_safe_redirect(add_query_arg([
                'ext' => 'saved-services',
                'rma_support_notice' => rawurlencode('Preencha assunto e descrição do ticket.'),
                'rma_support_type' => 'error',
            ], home_url('/dashboard/')));
            exit;
        }

        $allowed_categories = ['financeiro', 'governanca', 'tecnico', 'outros'];
        if (! in_array($category, $allowed_categories, true)) {
            $category = 'outros';
        }

        $allowed_priorities = ['baixa', 'media', 'alta'];
        if (! in_array($priority, $allowed_priorities, true)) {
            $priority = 'media';
        }

        $tickets = get_post_meta($entity_id, 'rma_support_tickets', true);
        $tickets = is_array($tickets) ? $tickets : [];

        $ticket_id = 'TCK-' . strtoupper(wp_generate_password(6, false, false));
        $tickets[] = [
            'id' => $ticket_id,
            'subject' => $subject,
            'category' => $category,
            'priority' => $priority,
            'message' => $message,
            'status' => 'aberto',
            'created_at' => current_time('mysql', true),
            'created_by' => get_current_user_id(),
        ];

        update_post_meta($entity_id, 'rma_support_tickets', $tickets);

        $timeline = get_post_meta($entity_id, 'rma_audit_timeline', true);
        $timeline = is_array($timeline) ? $timeline : [];
        $timeline[] = [
            'datetime' => current_time('mysql', true),
            'source' => 'support',
            'event' => 'ticket_opened',
            'severity' => 'info',
            'message' => 'Novo ticket de suporte aberto: ' . $ticket_id,
            'context' => [
                'ticket_id' => $ticket_id,
                'category' => $category,
                'priority' => $priority,
            ],
        ];
        update_post_meta($entity_id, 'rma_audit_timeline', $timeline);

        wp_safe_redirect(add_query_arg([
            'ext' => 'saved-services',
            'rma_support_notice' => rawurlencode('Ticket ' . $ticket_id . ' criado com sucesso.'),
            'rma_support_type' => 'success',
        ], home_url('/dashboard/')));
        exit;
    }

    private function render_entity_support_panel(): string {
        $entity_id = $this->get_entity_id_by_user(get_current_user_id());
        if ($entity_id <= 0) {
            return '<p>Nenhuma entidade vinculada para uso do Suporte.</p>';
        }

        $entity = $this->build_entity_finance_row($entity_id);
        $tickets = get_post_meta($entity_id, 'rma_support_tickets', true);
        $tickets = is_array($tickets) ? array_reverse($tickets) : [];

        $notice = isset($_GET['rma_support_notice']) ? sanitize_text_field(rawurldecode((string) wp_unslash($_GET['rma_support_notice']))) : '';
        $notice_type = isset($_GET['rma_support_type']) ? sanitize_key((string) wp_unslash($_GET['rma_support_type'])) : '';
        $ext = isset($_GET['ext']) ? sanitize_key((string) wp_unslash($_GET['ext'])) : 'saved-services';
        $support_mode = $ext === 'rma-suporte-tickets' ? 'tickets' : ($ext === 'rma-suporte-novo' ? 'novo' : 'all');

        $rows = [];
        foreach (array_slice($tickets, 0, 20) as $t) {
            $rows[] = [
                (string) ($t['id'] ?? '—'),
                strtoupper((string) ($t['category'] ?? 'outros')),
                $this->format_status_badge((string) ($t['priority'] ?? 'media'), strtoupper((string) ($t['priority'] ?? 'media'))),
                $this->format_status_badge((string) ($t['status'] ?? 'aberto'), strtoupper((string) ($t['status'] ?? 'aberto'))),
                (string) ($t['subject'] ?? '—'),
                (string) ($t['created_at'] ?? '—'),
            ];
        }
        if (empty($rows)) {
            $rows[] = ['—', '—', $this->format_status_badge('neutral', '—'), $this->format_status_badge('neutral', '—'), 'Nenhum ticket aberto ainda.', '—'];
        }

        ob_start();
        echo '<div class="rma-fin-shell">';
        echo $this->build_styles();
        echo '<div class="rma-fin-header"><h3>Suporte RMA</h3><p>Use este menu para abrir tickets e acompanhar atendimento da Equipe RMA.</p></div>';
        echo '<div class="rma-fin-entity-identity">';
        echo '<div><small>Entidade</small><strong>' . esc_html($entity['name']) . '</strong></div>';
        echo '<div><small>Status Financeiro</small>' . $entity['status_badge'] . '</div>';
        echo '<div><small>Canal</small><strong>Tickets</strong></div>';
        echo '</div>';

        echo '<div class="rma-fin-nav">';
        echo '<a class="rma-fin-tab-link' . ($support_mode === 'novo' || $support_mode === 'all' ? ' is-active' : '') . '" href="' . esc_url(add_query_arg('ext', 'rma-suporte-novo', home_url('/dashboard/'))) . '">Novo Ticket</a>';
        echo '<a class="rma-fin-tab-link' . ($support_mode === 'tickets' ? ' is-active' : '') . '" href="' . esc_url(add_query_arg('ext', 'rma-suporte-tickets', home_url('/dashboard/'))) . '">Meus Tickets</a>';
        echo '</div>';

        if ($notice !== '') {
            $tone = $notice_type === 'success' ? '#0f9f6f' : '#b42318';
            echo '<div style="margin:0 0 12px;padding:10px 12px;border-radius:10px;background:#fff;border:1px solid ' . esc_attr($tone) . ';color:' . esc_attr($tone) . ';font-weight:700">' . esc_html($notice) . '</div>';
        }

        if ($support_mode !== 'tickets') {
            echo '<div class="rma-fin-table-wrap" style="margin-bottom:12px">';
            echo '<h4>Abrir novo ticket</h4>';
            echo '<form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">';
            wp_nonce_field('rma_support_create_ticket');
            echo '<input type="hidden" name="rma_support_action" value="create_ticket" />';
            echo '<input type="text" name="ticket_subject" required placeholder="Assunto do ticket" style="height:38px;border:1px solid #d5e3ef;border-radius:10px;padding:0 10px" />';
            echo '<select name="ticket_category" style="height:38px;border:1px solid #d5e3ef;border-radius:10px;padding:0 10px"><option value="financeiro">Financeiro</option><option value="governanca">Governança</option><option value="tecnico">Técnico</option><option value="outros">Outros</option></select>';
            echo '<select name="ticket_priority" style="height:38px;border:1px solid #d5e3ef;border-radius:10px;padding:0 10px"><option value="media">Prioridade Média</option><option value="alta">Prioridade Alta</option><option value="baixa">Prioridade Baixa</option></select>';
            echo '<textarea name="ticket_message" required placeholder="Descreva o problema ou solicitação" style="grid-column:1/-1;min-height:120px;border:1px solid #d5e3ef;border-radius:10px;padding:10px"></textarea>';
            echo '<button type="submit" style="height:40px;border:none;border-radius:10px;background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;font-weight:700;cursor:pointer;max-width:220px">Criar ticket</button>';
            echo '</form>';
            echo '</div>';
        }

        if ($support_mode !== 'novo') {
            echo $this->build_table('Meus tickets de suporte', $rows, ['Ticket', 'Categoria', 'Prioridade', 'Status', 'Assunto', 'Abertura']);
        }
        echo '</div>';

        return (string) ob_get_clean();
    }

    private function get_entity_audit_rows(int $entity_id): array {
        $events = get_post_meta($entity_id, 'rma_audit_timeline', true);
        $events = is_array($events) ? array_reverse($events) : [];
        $rows = [];

        foreach (array_slice($events, 0, 20) as $event) {
            $rows[] = [
                (string) ($event['datetime'] ?? '—'),
                strtoupper((string) ($event['source'] ?? 'sistema')),
                (string) ($event['event'] ?? 'evento'),
                $this->format_status_badge((string) ($event['severity'] ?? 'info'), strtoupper((string) ($event['severity'] ?? 'info'))),
                (string) ($event['message'] ?? '—'),
            ];
        }

        if (empty($rows)) {
            $rows[] = ['—', 'SISTEMA', 'sem_eventos', $this->format_status_badge('info', 'INFO'), 'Ainda não existem eventos operacionais consolidados.'];
        }

        return $rows;
    }


    private function get_observability_rows(string $source_filter, string $severity_filter, string $entity_search): array {
        $ids = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft'],
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        $rows = [];
        $raw_rows = [];
        $source_counts = [];
        $severity_counts = [];
        $entity_search_normalized = strtolower(trim($entity_search));

        foreach ($ids as $entity_id) {
            $entity_id = (int) $entity_id;
            $entity_name = (string) get_the_title($entity_id);
            if ($entity_search_normalized !== '' && strpos(strtolower($entity_name), $entity_search_normalized) === false) {
                continue;
            }

            $events = get_post_meta($entity_id, 'rma_audit_timeline', true);
            $events = is_array($events) ? array_reverse($events) : [];

            foreach ($events as $event) {
                $source = sanitize_key((string) ($event['source'] ?? 'sistema'));
                $severity = sanitize_key((string) ($event['severity'] ?? 'info'));
                $message = sanitize_text_field((string) ($event['message'] ?? '—'));
                $datetime = sanitize_text_field((string) ($event['datetime'] ?? '—'));
                $event_name = sanitize_key((string) ($event['event'] ?? 'evento'));
                $context = isset($event['context']) && is_array($event['context']) ? $event['context'] : [];
                $context_json = $context ? wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';

                $source_counts[$source] = isset($source_counts[$source]) ? $source_counts[$source] + 1 : 1;
                $severity_counts[$severity] = isset($severity_counts[$severity]) ? $severity_counts[$severity] + 1 : 1;

                if ($source_filter !== '' && $source_filter !== $source) {
                    continue;
                }
                if ($severity_filter !== '' && $severity_filter !== $severity) {
                    continue;
                }

                $raw_rows[] = [
                    'datetime' => $datetime,
                    'entity_name' => $entity_name,
                    'source' => $source,
                    'event' => $event_name,
                    'severity' => $severity,
                    'message' => $message,
                    'context' => $context_json,
                ];

                if ($context_json !== '') {
                    if (function_exists('mb_substr')) {
                        $context_preview = mb_substr($context_json, 0, 120);
                        $is_long = function_exists('mb_strlen') ? (mb_strlen($context_json) > 120) : (strlen($context_json) > 120);
                    } else {
                        $context_preview = substr($context_json, 0, 120);
                        $is_long = strlen($context_json) > 120;
                    }
                    if ($is_long) {
                        $context_preview .= '...';
                    }
                } else {
                    $context_preview = '—';
                }

                $rows[] = [
                    $datetime,
                    $entity_name,
                    strtoupper($source),
                    $event_name,
                    $this->format_status_badge($severity, strtoupper($severity)),
                    $message,
                    esc_html($context_preview),
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) $b[0], (string) $a[0]);
        });

        usort($raw_rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['datetime'] ?? ''), (string) ($a['datetime'] ?? ''));
        });

        $rows = array_slice($rows, 0, 250);
        ksort($source_counts);
        ksort($severity_counts);

        return [
            'rows' => $rows,
            'raw_rows' => $raw_rows,
            'source_counts' => $source_counts,
            'severity_counts' => $severity_counts,
        ];
    }

    private function build_observability_analytics(array $rows): array {
        $trend = [];
        $matrix = [];
        $entity_risk = [];
        $total_events = 0;
        $total_error = 0;
        $total_warning = 0;

        for ($i = 6; $i >= 0; $i--) {
            $day = gmdate('Y-m-d', strtotime('-' . $i . ' days'));
            $trend[$day] = ['events' => 0, 'error' => 0, 'warning' => 0];
        }

        foreach ($rows as $row) {
            $dt = (string) ($row['datetime'] ?? '');
            $day = $dt !== '' ? substr($dt, 0, 10) : '';
            $source = sanitize_key((string) ($row['source'] ?? 'sistema'));
            $severity = sanitize_key((string) ($row['severity'] ?? 'info'));
            $entity_name = (string) ($row['entity_name'] ?? 'Entidade');

            $total_events++;
            if ($severity === 'error') {
                $total_error++;
            }
            if ($severity === 'warning') {
                $total_warning++;
            }

            if ($day !== '' && isset($trend[$day])) {
                $trend[$day]['events']++;
                if ($severity === 'error') {
                    $trend[$day]['error']++;
                }
                if ($severity === 'warning') {
                    $trend[$day]['warning']++;
                }
            }

            if (! isset($matrix[$source])) {
                $matrix[$source] = ['info' => 0, 'success' => 0, 'warning' => 0, 'error' => 0, 'total' => 0];
            }
            if (! isset($matrix[$source][$severity])) {
                $matrix[$source][$severity] = 0;
            }
            $matrix[$source][$severity]++;
            $matrix[$source]['total']++;

            if (! isset($entity_risk[$entity_name])) {
                $entity_risk[$entity_name] = ['events' => 0, 'error' => 0, 'warning' => 0];
            }
            $entity_risk[$entity_name]['events']++;
            if ($severity === 'error') {
                $entity_risk[$entity_name]['error']++;
            }
            if ($severity === 'warning') {
                $entity_risk[$entity_name]['warning']++;
            }
        }

        $trend_rows = [];
        foreach ($trend as $day => $values) {
            $events = (int) $values['events'];
            $error = (int) $values['error'];
            $warning = (int) $values['warning'];
            $health = $events > 0 ? (int) max(0, min(100, round((($events - ($error * 2) - $warning) / $events) * 100))) : 100;
            $trend_rows[] = [$day, (string) $events, (string) $error, (string) $warning, (string) $health . '%'];
        }

        ksort($matrix);
        $matrix_rows = [];
        foreach ($matrix as $source => $values) {
            $matrix_rows[] = [
                strtoupper((string) $source),
                (string) ((int) ($values['info'] ?? 0)),
                (string) ((int) ($values['success'] ?? 0)),
                (string) ((int) ($values['warning'] ?? 0)),
                (string) ((int) ($values['error'] ?? 0)),
                (string) ((int) ($values['total'] ?? 0)),
            ];
        }

        if (empty($matrix_rows)) {
            $matrix_rows[] = ['SISTEMA', '0', '0', '0', '0', '0'];
        }

        $critical_entities_rows = [];
        foreach ($entity_risk as $entity => $values) {
            $events = (int) $values['events'];
            $errors = (int) $values['error'];
            $warnings = (int) $values['warning'];
            $risk_score = ($errors * 3) + ($warnings * 2) + max(0, $events - ($errors + $warnings));
            $critical_entities_rows[] = [$entity, (string) $errors, (string) $warnings, (string) $events, (string) $risk_score];
        }
        usort($critical_entities_rows, static function (array $a, array $b): int {
            return (int) $b[4] <=> (int) $a[4];
        });
        $critical_entities_rows = array_slice($critical_entities_rows, 0, 8);
        if (empty($critical_entities_rows)) {
            $critical_entities_rows[] = ['Sem dados', '0', '0', '0', '0'];
        }

        $global_health = $total_events > 0
            ? (int) max(0, min(100, round((($total_events - ($total_error * 2) - $total_warning) / $total_events) * 100)))
            : 100;

        $severity_totals = [
            'info' => 0,
            'success' => 0,
            'warning' => 0,
            'error' => 0,
        ];
        foreach ($matrix as $source => $values) {
            $severity_totals['info'] += (int) ($values['info'] ?? 0);
            $severity_totals['success'] += (int) ($values['success'] ?? 0);
            $severity_totals['warning'] += (int) ($values['warning'] ?? 0);
            $severity_totals['error'] += (int) ($values['error'] ?? 0);
        }

        return [
            'trend_rows' => $trend_rows,
            'matrix_rows' => $matrix_rows,
            'critical_entities_rows' => $critical_entities_rows,
            'global_health' => $global_health,
            'severity_totals' => $severity_totals,
        ];
    }

    private function build_observability_alert_rows(array $analytics): array {
        $rows = [];
        $global_health = (int) ($analytics['global_health'] ?? 100);
        $critical_entities = isset($analytics['critical_entities_rows']) && is_array($analytics['critical_entities_rows']) ? $analytics['critical_entities_rows'] : [];
        $severity_totals = isset($analytics['severity_totals']) && is_array($analytics['severity_totals']) ? $analytics['severity_totals'] : [];

        $errors = (int) ($severity_totals['error'] ?? 0);
        $warnings = (int) ($severity_totals['warning'] ?? 0);

        if ($global_health < 70) {
            $rows[] = [
                $this->format_status_badge('error', 'ALTA'),
                'health_score_below_70',
                'Saúde operacional global abaixo do limite seguro (' . $global_health . '%).',
                'Priorizar revisão de fluxos com erro, pausar automações críticas e executar plano de contingência.',
            ];
        } elseif ($global_health < 85) {
            $rows[] = [
                $this->format_status_badge('warning', 'MÉDIA'),
                'health_score_below_85',
                'Saúde operacional em faixa de atenção (' . $global_health . '%).',
                'Ajustar regras com maior ruído (warnings) e monitorar evolução por 72h.',
            ];
        } else {
            $rows[] = [
                $this->format_status_badge('success', 'BAIXA'),
                'health_score_ok',
                'Saúde operacional estável (' . $global_health . '%).',
                'Manter monitoramento contínuo e revisar indicadores semanalmente.',
            ];
        }

        if ($errors >= 10) {
            $rows[] = [
                $this->format_status_badge('error', 'ALTA'),
                'error_volume_spike',
                'Volume elevado de eventos de erro (' . $errors . ').',
                'Abrir incidente técnico, correlacionar origem/evento e aplicar hotfix nos pontos com maior impacto.',
            ];
        }

        if (! empty($critical_entities)) {
            $top = $critical_entities[0];
            $top_entity = (string) ($top[0] ?? 'Entidade');
            $top_errors = (int) ($top[1] ?? 0);
            $top_risk = (int) ($top[4] ?? 0);
            if ($top_errors > 0) {
                $rows[] = [
                    $this->format_status_badge('warning', 'MÉDIA'),
                    'entity_risk_hotspot',
                    'Entidade com maior risco atual: ' . $top_entity . ' (erros: ' . $top_errors . ', risk score: ' . $top_risk . ').',
                    'Executar análise pontual com a entidade e validar trilha de governança/financeiro associada.',
                ];
            }
        }

        return $rows;
    }

    private function build_observability_sla_snapshot(array $raw_rows): array {
        $now_ts = time();
        $last_event_ts = 0;
        $last_error_ts = 0;

        foreach ($raw_rows as $row) {
            $dt = (string) ($row['datetime'] ?? '');
            if ($dt === '') {
                continue;
            }
            $ts = strtotime($dt . ' UTC');
            if (! $ts) {
                continue;
            }
            if ($ts > $last_event_ts) {
                $last_event_ts = $ts;
            }
            if ((string) ($row['severity'] ?? '') === 'error' && $ts > $last_error_ts) {
                $last_error_ts = $ts;
            }
        }

        $mins_since_last_event = $last_event_ts > 0 ? (int) floor(($now_ts - $last_event_ts) / 60) : 99999;
        $mins_since_last_error = $last_error_ts > 0 ? (int) floor(($now_ts - $last_error_ts) / 60) : 99999;

        return [
            'mins_since_last_event' => $mins_since_last_event,
            'mins_since_last_error' => $mins_since_last_error,
            'event_within_target' => $mins_since_last_event <= 180,
            'error_within_target' => $mins_since_last_error > 60,
        ];
    }

    private function build_observability_sla_rows(array $sla_snapshot): array {
        $mins_since_last_event = (int) ($sla_snapshot['mins_since_last_event'] ?? 99999);
        $mins_since_last_error = (int) ($sla_snapshot['mins_since_last_error'] ?? 99999);

        $event_status = $mins_since_last_event <= 180 ? $this->format_status_badge('success', 'OK') : $this->format_status_badge('warning', 'ATENÇÃO');
        $error_status = $mins_since_last_error <= 60 ? $this->format_status_badge('error', 'CRÍTICO') : $this->format_status_badge('success', 'CONTROLADO');

        return [
            [
                'Latência desde último evento',
                $event_status,
                $mins_since_last_event === 99999 ? 'Sem eventos recentes para monitoramento.' : ('Último evento há ' . $mins_since_last_event . ' min.'),
                '<= 180 min',
            ],
            [
                'Tempo desde último erro crítico',
                $error_status,
                $mins_since_last_error === 99999 ? 'Nenhum erro crítico registrado no período observado.' : ('Último erro há ' . $mins_since_last_error . ' min.'),
                '> 60 min sem erro crítico',
            ],
        ];
    }

    private function build_observability_playbook_rows(array $analytics, array $sla_snapshot): array {
        $global_health = (int) ($analytics['global_health'] ?? 100);
        $errors = (int) (($analytics['severity_totals']['error'] ?? 0));
        $warnings = (int) (($analytics['severity_totals']['warning'] ?? 0));
        $mins_since_last_error = (int) ($sla_snapshot['mins_since_last_error'] ?? 99999);
        $rows = [];

        $rows[] = [
            $global_health < 80 ? $this->format_status_badge('error', 'P0') : $this->format_status_badge('success', 'P2'),
            'Defender Health Score global',
            $global_health < 80
                ? 'Executar war-room com times técnicos e donos de processo para atacar as 3 principais origens de erro.'
                : 'Manter rotina de revisão semanal e baseline de qualidade operacional por origem.',
            $global_health < 80 ? 'Iniciar em até 30 min' : 'Revisão em D+7',
        ];

        $rows[] = [
            $errors >= 10 ? $this->format_status_badge('warning', 'P1') : $this->format_status_badge('success', 'P3'),
            'Reduzir pressão de incidentes',
            $errors >= 10
                ? 'Criar plano tático de rollback/hotfix para eventos críticos com owner por stream e checkpoint a cada 2h.'
                : 'Manter trilha preventiva com testes de regressão nas automações com warning recorrente.',
            $errors >= 10 ? 'Plano validado em 2h' : 'Follow-up em 24h',
        ];

        $rows[] = [
            $mins_since_last_error <= 60 ? $this->format_status_badge('error', 'P0') : $this->format_status_badge('success', 'P2'),
            'Garantir janela segura sem erro crítico',
            $mins_since_last_error <= 60
                ? 'Acionar protocolo de contingência e observabilidade reforçada até estabilização contínua por 60+ min.'
                : 'Aproveitar estabilidade para hardening de logs e documentação de lições aprendidas.',
            $mins_since_last_error <= 60 ? 'Resposta imediata' : 'Execução em até 48h',
        ];

        if ($warnings >= 15) {
            $rows[] = [
                $this->format_status_badge('warning', 'P1'),
                'Atacar ruído operacional (warnings)',
                'Priorizar saneamento das regras que mais geram warning para evitar escalada para erro crítico.',
                'Plano publicado em 1 dia útil',
            ];
        }

        return $rows;
    }

    private function build_observability_command_center(array $analytics, array $sla_snapshot): string {
        $global_health = (int) ($analytics['global_health'] ?? 100);
        $errors = (int) (($analytics['severity_totals']['error'] ?? 0));
        $warnings = (int) (($analytics['severity_totals']['warning'] ?? 0));
        $mins_since_last_event = (int) ($sla_snapshot['mins_since_last_event'] ?? 99999);
        $mins_since_last_error = (int) ($sla_snapshot['mins_since_last_error'] ?? 99999);

        $activity_score = $mins_since_last_event === 99999 ? 0 : max(0, min(100, 100 - (int) floor(($mins_since_last_event / 180) * 100)));
        $incident_pressure = max(0, min(100, ($errors * 7) + ($warnings * 2)));
        $stability_score = $mins_since_last_error === 99999 ? 100 : max(0, min(100, (int) floor(($mins_since_last_error / 60) * 100)));
        $exec_index = (int) round(($global_health * 0.5) + ($stability_score * 0.3) + ($activity_score * 0.2));

        $index_tone = $exec_index >= 80 ? 'is-good' : ($exec_index >= 60 ? 'is-warn' : 'is-bad');
        $index_label = $exec_index >= 80 ? 'Operação premium estável' : ($exec_index >= 60 ? 'Atenção executiva' : 'Resposta crítica imediata');

        $html = '<div class="rma-fin-exec-center">';
        $html .= '<div class="rma-fin-exec-head"><h4>Command Center Executivo</h4><p>Painel premium para tomada de decisão em tempo real.</p></div>';
        $html .= '<div class="rma-fin-exec-grid">';
        $html .= '<div class="rma-fin-exec-card"><small>Executive Readiness Index</small><strong>' . esc_html((string) $exec_index) . '%</strong><span class="rma-fin-badge ' . esc_attr($index_tone) . '">' . esc_html($index_label) . '</span></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Health Score</small><strong>' . esc_html((string) $global_health) . '%</strong><p>Meta de governança: &gt;= 85%</p></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Incident Pressure</small><strong>' . esc_html((string) $incident_pressure) . '%</strong><p>Erros: ' . esc_html((string) $errors) . ' · Warnings: ' . esc_html((string) $warnings) . '</p></div>';
        $html .= '<div class="rma-fin-exec-card"><small>Estabilidade sem erro crítico</small><strong>' . esc_html($mins_since_last_error === 99999 ? 'N/A' : (string) $mins_since_last_error . ' min') . '</strong><p>Janela segura recomendada: 60+ min</p></div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    private function build_observability_visuals(array $analytics): string {
        $trend_rows = isset($analytics['trend_rows']) && is_array($analytics['trend_rows']) ? $analytics['trend_rows'] : [];
        $severity_totals = isset($analytics['severity_totals']) && is_array($analytics['severity_totals']) ? $analytics['severity_totals'] : ['info' => 0, 'success' => 0, 'warning' => 0, 'error' => 0];

        $max_events = 1;
        foreach ($trend_rows as $row) {
            $events = isset($row[1]) ? (int) $row[1] : 0;
            if ($events > $max_events) {
                $max_events = $events;
            }
        }

        $total_severity = max(1,
            (int) ($severity_totals['info'] ?? 0)
            + (int) ($severity_totals['success'] ?? 0)
            + (int) ($severity_totals['warning'] ?? 0)
            + (int) ($severity_totals['error'] ?? 0)
        );

        $severity_items = [
            'info' => ['label' => 'INFO', 'color' => '#64748b'],
            'success' => ['label' => 'SUCCESS', 'color' => '#16a34a'],
            'warning' => ['label' => 'WARNING', 'color' => '#d97706'],
            'error' => ['label' => 'ERROR', 'color' => '#dc2626'],
        ];

        $html = '<div class="rma-fin-obs-visuals">';
        $html .= '<div class="rma-fin-obs-panel"><h4>Tendência visual (7 dias)</h4><div class="rma-fin-obs-bars">';
        foreach ($trend_rows as $row) {
            $day = (string) ($row[0] ?? '');
            $events = (int) ($row[1] ?? 0);
            $height = (int) round(($events / $max_events) * 100);
            $height = max(6, min(100, $height));
            $html .= '<div class="rma-fin-obs-bar-item"><div class="rma-fin-obs-bar" style="height:' . $height . '%" title="' . esc_attr($day . ': ' . $events . ' eventos') . '"></div><small>' . esc_html(substr($day, 5)) . '</small></div>';
        }
        $html .= '</div></div>';

        $html .= '<div class="rma-fin-obs-panel"><h4>Distribuição de severidade</h4><div class="rma-fin-obs-severity">';
        foreach ($severity_items as $key => $meta) {
            $value = (int) ($severity_totals[$key] ?? 0);
            $pct = (int) round(($value / $total_severity) * 100);
            $html .= '<div class="rma-fin-obs-sev-item"><span class="rma-fin-obs-sev-dot" style="background:' . esc_attr($meta['color']) . '"></span><strong>' . esc_html($meta['label']) . '</strong><small>' . esc_html((string) $value . ' (' . $pct . '%)') . '</small></div>';
        }
        $html .= '</div></div>';
        $html .= '</div>';

        return $html;
    }

    private function download_observability_csv(array $rows): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="rma-observabilidade-' . gmdate('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        if (! $out) {
            exit;
        }

        fputcsv($out, ['datetime', 'entity', 'source', 'event', 'severity', 'message', 'context']);
        foreach ($rows as $row) {
            fputcsv($out, [
                (string) ($row['datetime'] ?? ''),
                (string) ($row['entity_name'] ?? ''),
                (string) ($row['source'] ?? ''),
                (string) ($row['event'] ?? ''),
                (string) ($row['severity'] ?? ''),
                (string) ($row['message'] ?? ''),
                (string) ($row['context'] ?? ''),
            ]);
        }

        fclose($out);
        exit;
    }

    private function get_admin_financial_data(): array {
        $ids = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft'],
            'fields' => 'ids',
            'posts_per_page' => -1,
        ]);

        $entities = [];
        $ad = 0;
        $inad = 0;
        $sum = 0.0;
        $state_summary = [];

        foreach ($ids as $entity_id) {
            $row = $this->build_entity_finance_row((int) $entity_id);
            $entities[] = $row;
            $sum += (float) $row['estimated_raw'];

            if ($row['finance_status'] === 'adimplente') {
                $ad++;
            } else {
                $inad++;
            }

            $uf = $row['state'] !== '' ? $row['state'] : 'N/I';
            if (! isset($state_summary[$uf])) {
                $state_summary[$uf] = ['total' => 0, 'adimplentes' => 0, 'inadimplentes' => 0];
            }
            $state_summary[$uf]['total']++;
            $state_summary[$uf][$row['finance_status'] === 'adimplente' ? 'adimplentes' : 'inadimplentes']++;
        }

        $total = count($entities);
        $adimplencia_rate = $total > 0 ? (int) round(($ad / $total) * 100) : 0;

        usort($entities, static function (array $a, array $b): int {
            return strcmp($b['due_date_sort'], $a['due_date_sort']);
        });

        return [
            'entities' => $entities,
            'latest_entities' => array_slice($entities, 0, 10),
            'kpis' => [
                ['label' => 'Entidades totais', 'value' => (string) $total, 'tone' => 'neutral'],
                ['label' => 'Adimplentes', 'value' => (string) $ad, 'tone' => 'good'],
                ['label' => 'Inadimplentes', 'value' => (string) $inad, 'tone' => 'warn'],
                ['label' => 'Receita estimada', 'value' => $this->format_money($sum), 'tone' => 'neutral'],
            ],
            'adimplencia_rate' => $adimplencia_rate,
            'state_summary' => $state_summary,
        ];
    }


    private function build_admin_reports_data(): array {
        $entities = $this->get_admin_financial_data()['entities'];

        $state_summary = [];
        $area_summary = [];
        $annual_revenue = [];
        $history_rows = [];
        $active_count = 0;
        $inactive_count = 0;
        $revenue_total = 0.0;

        foreach ($entities as $entity) {
            $entity_id = (int) ($entity['entity_id'] ?? 0);
            $state = (string) ($entity['state'] ?? 'N/I');
            if ($state === '') {
                $state = 'N/I';
            }
            if (! isset($state_summary[$state])) {
                $state_summary[$state] = ['total' => 0, 'adimplentes' => 0, 'inadimplentes' => 0];
            }
            $state_summary[$state]['total']++;
            if (($entity['finance_status'] ?? '') === 'adimplente') {
                $state_summary[$state]['adimplentes']++;
            } else {
                $state_summary[$state]['inadimplentes']++;
            }

            $area = $this->resolve_entity_area_label($entity_id);
            if (! isset($area_summary[$area])) {
                $area_summary[$area] = ['total' => 0, 'ativas' => 0, 'inativas' => 0];
            }
            $area_summary[$area]['total']++;

            $governance = (string) get_post_meta($entity_id, 'governance_status', true);
            $is_active = ($entity['finance_status'] ?? '') === 'adimplente' && $governance === 'aprovado';
            if ($is_active) {
                $active_count++;
                $area_summary[$area]['ativas']++;
            } else {
                $inactive_count++;
                $area_summary[$area]['inativas']++;
            }

            $history = get_post_meta($entity_id, 'finance_history', true);
            $history = is_array($history) ? $history : [];
            foreach ($history as $item) {
                $year = (string) ($item['year'] ?? gmdate('Y'));
                $total = (float) ($item['total'] ?? 0);
                if (! isset($annual_revenue[$year])) {
                    $annual_revenue[$year] = 0.0;
                }
                $annual_revenue[$year] += $total;
                $revenue_total += $total;

                $history_rows[] = [
                    'entity_name' => (string) ($entity['name'] ?? 'Entidade'),
                    'year' => $year,
                    'finance_status' => (string) ($item['finance_status'] ?? 'pendente'),
                    'total' => $total,
                    'order_id' => (int) ($item['order_id'] ?? 0),
                    'paid_at' => (string) ($item['paid_at'] ?? '—'),
                ];
            }
        }

        ksort($annual_revenue);
        usort($history_rows, static function (array $a, array $b): int {
            return strcmp((string) $b['paid_at'], (string) $a['paid_at']);
        });

        return [
            'state_summary' => $state_summary,
            'area_summary' => $area_summary,
            'annual_revenue' => $annual_revenue,
            'history_rows' => array_slice($history_rows, 0, 120),
            'history_total' => count($history_rows),
            'active_count' => $active_count,
            'inactive_count' => $inactive_count,
            'revenue_total' => $revenue_total,
        ];
    }

    private function resolve_entity_area_label(int $entity_id): string {
        if ($entity_id <= 0) {
            return 'Sem área';
        }

        $keys = ['area_interesse', 'area_de_interesse', 'areas_interesse', 'area_atuacao', 'segmento'];
        foreach ($keys as $key) {
            $value = get_post_meta($entity_id, $key, true);
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('strval', $value)));
            }
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return 'Sem área';
    }

    private function build_entity_finance_row(int $entity_id): array {
        $finance_status = (string) get_post_meta($entity_id, 'finance_status', true);
        if ($finance_status === '') {
            $finance_status = 'inadimplente';
        }

        $due_date = (string) get_post_meta($entity_id, 'anuidade_vencimento', true);
        if ($due_date === '') {
            $due_date = (string) get_post_meta($entity_id, 'finance_due_at', true);
        }

        $due_date_display = $due_date !== '' ? wp_date('d/m/Y', strtotime($due_date)) : 'Não definido';
        $raw_value = (float) get_option('rma_annual_due_value', '0');
        $estimated_value = $this->format_money($raw_value);
        $state = (string) get_post_meta($entity_id, 'uf', true);
        if ($state === '') {
            $state = (string) get_post_meta($entity_id, 'estado', true);
        }

        $latest_order = $this->get_latest_order_for_entity($entity_id);

        return [
            'entity_id' => $entity_id,
            'name' => get_the_title($entity_id),
            'finance_status' => $finance_status,
            'due_date' => $due_date_display,
            'due_date_sort' => $due_date !== '' ? gmdate('Y-m-d', strtotime($due_date)) : '0000-00-00',
            'due_year' => $due_date !== '' ? wp_date('Y', strtotime($due_date)) : gmdate('Y'),
            'estimated_value' => $estimated_value,
            'estimated_raw' => $raw_value,
            'state' => strtoupper($state),
            'last_order_status' => $latest_order['status'] ?? 'sem pedido',
            'status_badge' => $this->format_status_badge($finance_status, strtoupper($finance_status)),
            'last_order_badge' => $this->format_status_badge($latest_order['status'] ?? 'sem pedido', strtoupper((string) ($latest_order['status'] ?? 'sem pedido'))),
        ];
    }

    private function get_entity_history_rows(int $entity_id): array {
        $history = get_post_meta($entity_id, 'finance_history', true);
        $history = is_array($history) ? array_reverse($history) : [];
        $rows = [];

        foreach (array_slice($history, 0, 20) as $item) {
            $status = (string) ($item['finance_status'] ?? '-');
            $rows[] = [
                $this->format_status_badge($status, strtoupper($status)),
                $this->format_money((float) ($item['total'] ?? 0)),
                (string) ($item['year'] ?? '-'),
                '#' . (int) ($item['order_id'] ?? 0),
                (string) ($item['paid_at'] ?? '-'),
            ];
        }

        if (empty($rows)) {
            $rows[] = ['-', '-', '-', '-', 'Sem histórico ainda'];
        }

        return $rows;
    }

    private function get_recent_due_orders(): array {
        if (! function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'limit' => 30,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed', 'refunded'],
            'meta_key' => 'rma_is_annual_due',
            'meta_value' => '1',
        ]);

        $rows = [];
        foreach ($orders as $order) {
            if (! $order instanceof WC_Order) {
                continue;
            }
            $entity_id = (int) $order->get_meta('rma_entity_id');
            $status = (string) $order->get_status();
            $rows[] = [
                'id' => (int) $order->get_id(),
                'status' => $status,
                'status_badge' => $this->format_status_badge($status, strtoupper($status)),
                'total' => $this->format_money((float) $order->get_total()),
                'due_year' => (string) $order->get_meta('rma_due_year'),
                'entity_name' => $entity_id > 0 ? get_the_title($entity_id) : 'N/I',
            ];
        }

        return $rows;
    }

    private function get_latest_order_for_entity(int $entity_id): array {
        if (! function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed', 'refunded'],
            'meta_key' => 'rma_entity_id',
            'meta_value' => $entity_id,
        ]);

        if (empty($orders) || ! $orders[0] instanceof WC_Order) {
            return [];
        }

        $order = $orders[0];
        return [
            'id' => (int) $order->get_id(),
            'status' => (string) $order->get_status(),
        ];
    }

    private function get_entity_id_by_user(int $user_id): int {
        if ($user_id <= 0) {
            return 0;
        }

        if (function_exists('rma_get_entity_id_by_author')) {
            return (int) rma_get_entity_id_by_author($user_id);
        }

        $ids = get_posts([
            'post_type' => self::CPT,
            'post_status' => ['publish', 'draft'],
            'fields' => 'ids',
            'author' => $user_id,
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        return (int) ($ids[0] ?? 0);
    }

    private function build_shell_start(string $active_menu, string $subtitle): string {
        $items = [
            'rma-finance-crm' => 'Visão Geral',
            'rma-finance-crm-receber' => 'Contas a Receber',
            'rma-finance-crm-pix' => 'Cobranças PIX',
            'rma-finance-crm-conciliacao' => 'Conciliação',
            'rma-finance-crm-reports' => 'Relatórios',
            'rma-finance-crm-observability' => 'Observabilidade',
        ];

        $nav = '<div class="rma-fin-nav">';
        foreach ($items as $key => $label) {
            $nav .= '<span class="' . ($key === $active_menu ? 'is-active' : '') . '">' . esc_html($label) . '</span>';
        }
        $nav .= '</div>';

        return '<div class="rma-fin-shell">' .
            $this->build_styles() .
            '<div class="rma-fin-header"><h3>CRM Financeiro Completo</h3><p>' . esc_html($subtitle) . '</p></div>' .
            $nav;
    }

    private function build_shell_end(): string {
        return '</div>';
    }

    private function build_kpi_cards(array $cards): string {
        $html = '<div class="rma-fin-cards">';
        foreach ($cards as $card) {
            $tone = sanitize_html_class((string) ($card['tone'] ?? 'neutral'));
            $html .= '<div class="rma-fin-card tone-' . esc_attr($tone) . '">';
            $html .= '<small>' . esc_html((string) ($card['label'] ?? '')) . '</small>';
            $html .= '<strong>' . esc_html((string) ($card['value'] ?? '-')) . '</strong>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function build_progress_strip(int $percent): string {
        $percent = max(0, min(100, $percent));
        return '<div class="rma-fin-progress">'
            . '<div class="rma-fin-progress-meta"><span>Taxa de adimplência</span><strong>' . esc_html((string) $percent) . '%</strong></div>'
            . '<div class="rma-fin-progress-track"><div class="rma-fin-progress-fill" style="width:' . (int) $percent . '%"></div></div>'
            . '</div>';
    }

    private function build_observability_table(array $raw_rows): string {
        $headers = ['Data', 'Entidade', 'Origem', 'Evento', 'Severidade', 'Mensagem', 'Contexto'];
        $html = '<div class="rma-fin-table-wrap"><h4>Timeline operacional consolidada</h4><table class="rma-fin-table"><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . esc_html($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        $slice = array_slice($raw_rows, 0, 250);
        if (empty($slice)) {
            $html .= '<tr><td colspan="7">Sem dados para exibir.</td></tr>';
        } else {
            foreach ($slice as $event) {
                $source = strtoupper((string) ($event['source'] ?? 'sistema'));
                $severity = (string) ($event['severity'] ?? 'info');
                $context = (string) ($event['context'] ?? '');
                $button = '<button type="button" class="button button-small rma-obs-view-context" data-context="' . esc_attr($context) . '">Ver contexto</button>';

                $html .= '<tr>';
                $html .= '<td>' . esc_html((string) ($event['datetime'] ?? '—')) . '</td>';
                $html .= '<td>' . esc_html((string) ($event['entity_name'] ?? 'Entidade')) . '</td>';
                $html .= '<td>' . esc_html($source) . '</td>';
                $html .= '<td>' . esc_html((string) ($event['event'] ?? 'evento')) . '</td>';
                $html .= '<td>' . $this->format_status_badge($severity, strtoupper($severity)) . '</td>';
                $html .= '<td>' . esc_html((string) ($event['message'] ?? '—')) . '</td>';
                $html .= '<td>' . $button . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';
        $html .= '<div class="rma-obs-modal"><div class="rma-obs-modal-card"><div class="rma-obs-modal-head"><strong>Contexto completo do evento</strong><button type="button" class="rma-obs-modal-close">Fechar</button></div><pre class="rma-obs-modal-code">{}</pre></div></div>';

        return $html;
    }

    private function build_table(string $title, array $rows, array $headers): string {
        $html = '<div class="rma-fin-table-wrap"><h4>' . esc_html($title) . '</h4><table class="rma-fin-table"><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . esc_html($header) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        if (empty($rows)) {
            $html .= '<tr><td colspan="' . (int) count($headers) . '">Sem dados para exibir.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . wp_kses($cell, ['span' => ['class' => true]]) . '</td>';
                }
                $html .= '</tr>';
            }
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    private function format_status_badge(string $status, string $label): string {
        $status = sanitize_key($status);
        if (in_array($status, ['processing', 'completed'], true)) {
            $label = 'PAGO';
        }
        $class = 'is-neutral';
        if (in_array($status, ['adimplente', 'completed', 'processing', 'success', 'ok', 'baixa'], true)) {
            $class = 'is-good';
        } elseif (in_array($status, ['inadimplente', 'failed', 'cancelled', 'refunded', 'error', 'critical', 'alta'], true)) {
            $class = 'is-bad';
        } elseif (in_array($status, ['pending', 'on-hold', 'warning', 'warn', 'atencao', 'media', 'aberto'], true)) {
            $class = 'is-warn';
        }

        return '<span class="rma-fin-badge ' . esc_attr($class) . '">' . esc_html($label) . '</span>';
    }

    private function build_styles(): string {
        return '<style>
            .rma-fin-shell{font-family:"Maven Pro","Segoe UI",Arial,sans-serif;background:linear-gradient(180deg,#f8fbfd 0%,#ffffff 100%);padding:22px;border:1px solid #dce7ef;border-radius:18px;box-shadow:0 20px 44px rgba(15,23,42,.07);margin:10px 0}
            .rma-fin-header{background:linear-gradient(135deg,#7bad39,#5ddabb);padding:20px;border-radius:14px;color:#fff;margin-bottom:14px;position:relative;overflow:hidden}
            .rma-fin-header:after{content:"";position:absolute;inset:auto -30px -40px auto;width:120px;height:120px;background:rgba(255,255,255,.15);border-radius:50%}
            .rma-fin-header h3{margin:0 0 6px;font-size:24px;font-weight:800;color:#fff !important}
            .rma-fin-header p{margin:0;opacity:.95;color:#fff !important}
            .dropdown-menu.dropdown-menu-right a.selected{background-color:rgb(129 176 65)}
            .dropdown-item.active,.dropdown-item:active{color:#fff;text-decoration:none;background-color:#7dae3b}
            .text-primary{color:#7dae3b !important}
            .rma-fin-nav{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px}
            .rma-fin-nav span,.rma-fin-tab-link{background:#eef6ff;color:#0f172a;border:1px solid #dbe7f3;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center}
            .rma-fin-nav span.is-active,.rma-fin-tab-link.is-active{background:linear-gradient(135deg,#7bad39,#5ddabb);color:#fff;border-color:transparent;box-shadow:0 8px 18px rgba(93,218,187,.33)}
            .rma-fin-entity-identity{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;background:#fff;border:1px solid #e5eef5;border-radius:12px;padding:12px;margin-bottom:12px}
            .rma-fin-entity-identity small{display:block;color:#64748b}
            .rma-fin-entity-identity strong{display:block;color:#0f172a;font-size:18px;margin-top:2px}
            .rma-fin-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-bottom:14px}
            .rma-fin-card{background:#fff;border:1px solid #e4edf5;border-radius:12px;padding:12px}
            .rma-fin-card small{display:block;color:#64748b;margin-bottom:6px;font-weight:600}
            .rma-fin-card strong{font-size:22px;color:#0f172a;line-height:1.2}
            .rma-fin-card.tone-good{border-color:#b7ebc7;background:#f2fcf5}
            .rma-fin-card.tone-warn{border-color:#ffe3b1;background:#fffaf1}
            .rma-fin-progress{background:#fff;border:1px solid #e4edf5;border-radius:12px;padding:12px;margin-bottom:14px}
            .rma-fin-progress-meta{display:flex;justify-content:space-between;margin-bottom:8px;color:#334155}
            .rma-fin-progress-track{height:10px;background:#eaf2f8;border-radius:999px;overflow:hidden}
            .rma-fin-progress-fill{height:100%;background:linear-gradient(90deg,#7bad39,#5ddabb);border-radius:999px}
            .rma-fin-table-wrap{background:#fff;border:1px solid #e4edf5;border-radius:12px;padding:12px;overflow:auto}
            .rma-fin-table-wrap h4{margin:0 0 10px;color:#0f172a}
            .rma-fin-table{width:100%;border-collapse:separate;border-spacing:0}
            .rma-fin-table th,.rma-fin-table td{padding:10px;border-bottom:1px solid #edf2f7;text-align:left;font-size:13px;white-space:nowrap}
            .rma-fin-table th{background:#f8fafc;color:#334155;font-weight:700;position:sticky;top:0}
            .rma-fin-badge{display:inline-block;border-radius:999px;padding:4px 10px;font-size:11px;font-weight:700;letter-spacing:.02em}
            .rma-fin-badge.is-good{background:#dbf7e5;color:#127a3f}
            .rma-fin-badge.is-bad{background:#ffe2e2;color:#b42318}
            .rma-fin-badge.is-warn{background:#fff2d6;color:#925f00}
            .rma-fin-badge.is-neutral{background:#e8eef5;color:#334155}
            .rma-obs-modal{position:fixed;inset:0;background:rgba(2,6,23,.55);display:none;align-items:center;justify-content:center;z-index:99999;padding:20px}
            .rma-obs-modal.is-open{display:flex}
            .rma-obs-modal-card{width:min(900px,100%);max-height:80vh;overflow:auto;background:#fff;border-radius:14px;border:1px solid #dbe7f3;box-shadow:0 30px 60px rgba(15,23,42,.28)}
            .rma-obs-modal-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid #e2e8f0}
            .rma-obs-modal-code{margin:0;padding:14px;white-space:pre-wrap;word-break:break-word;background:#f8fafc;color:#0f172a}
            .rma-fin-obs-visuals{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:10px;margin-bottom:14px}
            .rma-fin-obs-panel{background:#fff;border:1px solid #e4edf5;border-radius:12px;padding:12px}
            .rma-fin-obs-panel h4{margin:0 0 10px;color:#0f172a}
            .rma-fin-obs-bars{display:flex;align-items:flex-end;gap:8px;height:130px;padding:6px 2px}
            .rma-fin-obs-bar-item{display:flex;flex-direction:column;align-items:center;gap:6px;flex:1;min-width:28px}
            .rma-fin-obs-bar{width:100%;border-radius:8px 8px 4px 4px;background:linear-gradient(180deg,#5ddabb,#7bad39)}
            .rma-fin-obs-bar-item small{font-size:10px;color:#64748b}
            .rma-fin-obs-severity{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
            .rma-fin-obs-sev-item{border:1px solid #e2e8f0;border-radius:10px;padding:8px;display:flex;align-items:center;gap:7px;background:#f8fafc}
            .rma-fin-obs-sev-dot{display:inline-block;width:10px;height:10px;border-radius:50%}
            .rma-fin-obs-sev-item strong{font-size:11px;color:#0f172a}
            .rma-fin-obs-sev-item small{margin-left:auto;color:#475569;font-size:11px}
            .rma-fin-quick-actions{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 10px}
            .rma-fin-quick-action{display:inline-flex;align-items:center;justify-content:center;padding:7px 10px;border-radius:999px;border:1px solid #dbe7f3;background:#eef6ff;color:#0f172a;font-size:12px;font-weight:700;text-decoration:none}
            .rma-fin-quick-action:hover{filter:brightness(1.02);transform:translateY(-1px)}
            .rma-fin-exec-center{background:linear-gradient(135deg,#0f172a,#1e293b);color:#e2e8f0;border-radius:14px;padding:14px;border:1px solid #1f334d;box-shadow:0 12px 30px rgba(15,23,42,.25);margin:0 0 14px}
            .rma-fin-exec-head h4{margin:0;color:#fff;font-size:16px}
            .rma-fin-exec-head p{margin:4px 0 12px;color:#cbd5e1;font-size:12px}
            .rma-fin-exec-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}
            .rma-fin-exec-card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:10px;padding:10px}
            .rma-fin-exec-card small{display:block;font-size:11px;color:#cbd5e1}
            .rma-fin-exec-card strong{display:block;font-size:24px;color:#fff;line-height:1.2;margin:3px 0 7px}
            .rma-fin-exec-card p{margin:6px 0 0;color:#cbd5e1;font-size:11px}
        </style>';
    }

    private function format_money(float $value): string {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}

new RMA_Finance_CRM();
