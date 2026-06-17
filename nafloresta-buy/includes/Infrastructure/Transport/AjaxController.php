<?php

namespace NaFlorestaBuy\Infrastructure\Transport;

use NaFlorestaBuy\Application\BatchAddToCartService;
use NaFlorestaBuy\Core\Logger;

class AjaxController
{
    private BatchAddToCartService $service;
    private Logger $logger;

    public function __construct(BatchAddToCartService $service, ?Logger $logger = null)
    {
        $this->service = $service;
        $this->logger = $logger ?? new Logger();
    }

    public function register(): void
    {
        add_action('wp_ajax_nafb_batch_add_to_cart', [$this, 'handle']);
        add_action('wp_ajax_nopriv_nafb_batch_add_to_cart', [$this, 'handle']);
        add_action('wp_ajax_nafb_track_event', [$this, 'handleTrackEvent']);
        add_action('wp_ajax_nopriv_nafb_track_event', [$this, 'handleTrackEvent']);
    }

    public function handle(): void
    {
        check_ajax_referer('nafb_batch_add_to_cart', 'nonce');

        $raw = wp_unslash($_POST['payload'] ?? '');
        $payload = json_decode((string) $raw, true);

        if (!is_array($payload)) {
            $this->logger->error('nafb.ajax.invalid_payload');
            do_action('nafb_ajax_error', ['code' => 'invalid_payload']);
            wp_send_json_error(['success' => false, 'code' => 'invalid_payload', 'errors' => [['code' => 'invalid_payload', 'message' => __('Payload inválido.', 'nafloresta-buy')]]], 400);
        }

        $result = $this->service->execute($payload);
        if (!is_array($result) || !array_key_exists('success', $result)) {
            $this->logger->error('nafb.ajax.unexpected_result');
            do_action('nafb_unexpected_state', ['reason' => 'unexpected_ajax_result']);
            do_action('nafb_ajax_error', ['code' => 'unexpected_error']);
            wp_send_json_error(['success' => false, 'code' => 'unexpected_error', 'errors' => [['code' => 'unexpected_error', 'message' => __('Erro inesperado ao processar requisição.', 'nafloresta-buy')]]], 500);
        }

        if (!$result['success']) {
            $result['errors'] = $this->normalizeErrors($result['errors'] ?? []);
            do_action('nafb_ajax_error', ['code' => (string) ($result['code'] ?? 'unknown_error'), 'errors' => $result['errors']]);
            wp_send_json_error($result, 422);
        }

        wp_send_json_success($result);
    }

    public function handleTrackEvent(): void
    {
        check_ajax_referer('nafb_track_event', 'nonce');

        $action = sanitize_key((string) ($_POST['event_action'] ?? 'unknown'));
        $insights = get_option('nafb_insights', [
            'started' => 0,
            'completed' => 0,
            'errors' => 0,
            'events' => 0,
        ]);

        $insights['events'] = (int) ($insights['events'] ?? 0) + 1;
        if (in_array($action, ['variation_selected', 'quantity_changed', 'name_input_started'], true)) {
            $insights['started'] = (int) ($insights['started'] ?? 0) + 1;
        }

        if (in_array($action, ['add_to_cart_success', 'configuration_completed'], true)) {
            $insights['completed'] = (int) ($insights['completed'] ?? 0) + 1;
        }

        if (in_array($action, ['validation_error', 'add_to_cart_failure'], true)) {
            $insights['errors'] = (int) ($insights['errors'] ?? 0) + 1;
        }

        update_option('nafb_insights', $insights, false);
        wp_send_json_success(['ok' => true]);
    }

    private function normalizeErrors(array $errors): array
    {
        return array_values(array_map(static function ($error): array {
            if (!is_array($error)) {
                return ['code' => 'unknown_error', 'message' => __('Erro desconhecido.', 'nafloresta-buy')];
            }

            return [
                'code' => sanitize_key((string) ($error['code'] ?? 'unknown_error')),
                'message' => sanitize_text_field((string) ($error['message'] ?? __('Erro desconhecido.', 'nafloresta-buy'))),
            ];
        }, $errors));
    }
}
