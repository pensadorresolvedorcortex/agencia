<?php
/**
 * Plugin Name: RMA Automations
 * Description: Rotinas de e-mail via WP-Cron para renovação e mandato com logs de envio.
 * Version: 0.5.0
 * Author: RMA
 */

if (! defined('ABSPATH')) {
    exit;
}

final class RMA_Automations {
    public function __construct() {
        add_action('init', [$this, 'schedule_cron']);
        add_action('rma_daily_automation', [$this, 'run_daily_automation']);
    }

    public function schedule_cron(): void {
        if (! wp_next_scheduled('rma_daily_automation')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'rma_daily_automation');
        }
    }

    public function run_daily_automation(): void {
        $paged = 1;
        do {
            $query = new WP_Query([
                'post_type' => 'rma_entidade',
                'post_status' => ['publish', 'draft'],
                'posts_per_page' => 500,
                'paged' => $paged,
                'fields' => 'ids',
            ]);

            foreach ($query->posts as $entity_id) {
                $entity_id = (int) $entity_id;
                $author_id = (int) get_post_field('post_author', $entity_id);
                $email = get_the_author_meta('user_email', $author_id);
                if (! is_email($email)) {
                    continue;
                }

                $governance = get_post_meta($entity_id, 'governance_status', true);
                $mandato_fim = (string) get_post_meta($entity_id, 'mandato_fim', true);
                if ($governance === 'aprovado' && $this->is_days_before($mandato_fim, [60, 30, 7])) {
                    $this->notify_once_daily($email, 'Mandato próximo do vencimento', 'Seu mandato está próximo do vencimento.', $entity_id, 'mandato');
                }

                $finance = (string) get_post_meta($entity_id, 'finance_status', true);
                $anuidade_vencimento = (string) get_post_meta($entity_id, 'anuidade_vencimento', true);
                if ($anuidade_vencimento === '') {
                    $anuidade_vencimento = (string) get_post_meta($entity_id, 'finance_due_at', true);
                }

                if ($governance === 'aprovado' && $anuidade_vencimento !== '') {
                    $days_to_due = $this->days_until($anuidade_vencimento);

                    if ($finance === 'adimplente' && $days_to_due === 30) {
                        $this->notify_finance_event($email, $entity_id, 'aviso_renovacao', 'anuidade_30dias', $anuidade_vencimento, $finance);
                    }

                    if ($finance === 'adimplente' && $days_to_due === 7) {
                        $this->notify_finance_event($email, $entity_id, 'lembrete', 'anuidade_7dias', $anuidade_vencimento, $finance);
                    }

                    if ($finance === 'adimplente' && $days_to_due === 1) {
                        $this->notify_finance_event($email, $entity_id, 'ultimo_aviso', 'anuidade_1dia', $anuidade_vencimento, $finance);
                    }

                    if ($days_to_due < 0 && $finance !== 'adimplente') {
                        $this->notify_finance_event($email, $entity_id, 'pos_vencimento', 'anuidade_atraso', $anuidade_vencimento, $finance);
                    }

                    if ($days_to_due <= -5) {
                        update_post_meta($entity_id, 'finance_status', 'inadimplente');
                        update_post_meta($entity_id, 'finance_access_status', 'blocked');
                        $this->notify_once_daily($email, 'Serviços temporariamente bloqueados', 'Sua anuidade está com mais de 5 dias de atraso. O acesso foi temporariamente bloqueado até a regularização.', $entity_id, 'anuidade_bloqueio');
                    }
                }
            }

            $paged++;
        } while ($paged <= (int) $query->max_num_pages);

        wp_reset_postdata();
    }

    private function is_days_before(string $date, array $days): bool {
        if ($date === '') {
            return false;
        }

        $target = strtotime($date);
        if (! $target) {
            return false;
        }

        $today = strtotime(gmdate('Y-m-d'));
        $diff_days = (int) floor(($target - $today) / DAY_IN_SECONDS);

        return in_array($diff_days, $days, true);
    }

    private function days_until(string $date): int {
        $target = strtotime($date);
        if (! $target) {
            return 9999;
        }

        $today = strtotime(gmdate('Y-m-d'));
        return (int) floor(($target - $today) / DAY_IN_SECONDS);
    }

    private function notify_once_daily(string $email, string $subject, string $message, int $entity_id, string $context): void {
        $day_key = 'rma_mail_sent_' . md5($entity_id . '|' . $context . '|' . gmdate('Y-m-d'));
        if (get_transient($day_key)) {
            return;
        }

        $sent = $this->send_email($email, $subject, $message);
        if ($sent) {
            set_transient($day_key, 1, DAY_IN_SECONDS + HOUR_IN_SECONDS);
        }

        $logs = get_post_meta($entity_id, 'automation_logs', true);
        $logs = is_array($logs) ? $logs : [];
        $logs[] = [
            'context' => $context,
            'email' => $email,
            'subject' => $subject,
            'sent' => $sent,
            'datetime' => current_time('mysql', true),
        ];

        $max_logs = 200;
        if (count($logs) > $max_logs) {
            $logs = array_slice($logs, -1 * $max_logs);
        }

        update_post_meta($entity_id, 'automation_logs', $logs);
    }

    private function notify_finance_event(string $email, int $entity_id, string $event_key, string $context, string $vencimento, string $status): void {
        $entity_title = get_the_title($entity_id);
        $author_id = (int) get_post_field('post_author', $entity_id);
        $display_name = (string) get_the_author_meta('display_name', $author_id);
        $due_value = (string) get_option('rma_annual_due_value', '');

        $subject_fallback = [
            'aviso_renovacao' => 'Renovação da anuidade em 30 dias',
            'lembrete' => 'Lembrete: anuidade em 7 dias',
            'ultimo_aviso' => 'Último aviso: anuidade em 1 dia',
            'pos_vencimento' => 'Anuidade em atraso',
        ][$event_key] ?? 'Anuidade RMA';

        $message_fallback = $event_key === 'pos_vencimento'
            ? 'Identificamos pendência na sua anuidade. Vencimento: ' . $vencimento . '. Gere seu PIX no painel para regularizar.'
            : 'Sua anuidade possui evento de cobrança programado. Vencimento: ' . $vencimento;

        $day_key = 'rma_mail_sent_' . md5($entity_id . '|' . $context . '|' . gmdate('Y-m-d'));
        if (get_transient($day_key)) {
            return;
        }

        $sent = false;
        if (function_exists('rma_send_anexo2_email')) {
            $sent = rma_send_anexo2_email($event_key, $email, [
                'nome' => $display_name !== '' ? $display_name : $entity_title,
                'entidade' => $entity_title,
                'vencimento' => $vencimento,
                'valor' => $due_value,
                'status' => $status,
                'link_pagamento' => apply_filters('rma_checkout_url', home_url('/checkout/')),
            ]);
        }

        if (! $sent) {
            $sent = $this->send_email($email, $subject_fallback, $message_fallback);
        }

        if ($sent) {
            set_transient($day_key, 1, DAY_IN_SECONDS + HOUR_IN_SECONDS);
        }

        $logs = get_post_meta($entity_id, 'automation_logs', true);
        $logs = is_array($logs) ? $logs : [];
        $logs[] = [
            'context' => $context,
            'email' => $email,
            'subject' => $subject_fallback,
            'sent' => $sent,
            'datetime' => current_time('mysql', true),
        ];
        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }
        update_post_meta($entity_id, 'automation_logs', $logs);
    }

    private function send_email(string $email, string $subject, string $message): bool {
        $sender_mode = (string) get_option('rma_email_sender_mode', 'wp_mail');

        if ($sender_mode === 'woo_mail' && function_exists('WC') && WC() && method_exists(WC(), 'mailer')) {
            $mailer = WC()->mailer();
            if ($mailer) {
                $wrapped = method_exists($mailer, 'wrap_message') ? $mailer->wrap_message($subject, nl2br(esc_html($message))) : $message;
                $headers = ['Content-Type: text/html; charset=UTF-8'];

                return (bool) $mailer->send($email, $subject, $wrapped, $headers, []);
            }
        }

        return (bool) wp_mail($email, $subject, $message);
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook('rma_daily_automation');
    }
}

register_deactivation_hook(__FILE__, ['RMA_Automations', 'deactivate']);
new RMA_Automations();
