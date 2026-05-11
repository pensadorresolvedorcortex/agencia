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
        add_action('wp_ajax_nafb_get_cart_summary', [$this, 'handleCartSummary']);
        add_action('wp_ajax_nopriv_nafb_get_cart_summary', [$this, 'handleCartSummary']);
        add_action('wp_ajax_nafb_remove_cart_item', [$this, 'handleRemoveCartItem']);
        add_action('wp_ajax_nopriv_nafb_remove_cart_item', [$this, 'handleRemoveCartItem']);
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

    public function handleCartSummary(): void
    {
        check_ajax_referer('nafb_get_cart_summary', 'nonce');

        $productId = absint($_POST['product_id'] ?? 0);
        if ($productId <= 0 || !function_exists('WC') || !WC()->cart) {
            wp_send_json_success(['items' => [], 'subtotal' => 0, 'subtotal_html' => wc_price(0)]);
        }

        $items = [];
        $subtotal = 0.0;

        foreach (WC()->cart->get_cart() as $cartKey => $cartItem) {
            $nafb = is_array($cartItem['nafb'] ?? null) ? $cartItem['nafb'] : null;
            if (!$nafb || (int) ($nafb['product_id'] ?? 0) !== $productId) {
                continue;
            }

            $lineSubtotal = (float) ($cartItem['line_subtotal'] ?? 0);
            $subtotal += $lineSubtotal;
            $names = array_values(array_filter(array_map(static fn(array $row): string => sanitize_text_field($row['name'] ?? ''), (array) ($nafb['fields']['student_names'] ?? []))));

            $items[] = [
                'cart_key' => sanitize_text_field((string) $cartKey),
                'variation_label' => sanitize_text_field((string) ($nafb['variation_label'] ?? '')),
                'quantity' => absint($nafb['quantity'] ?? 0),
                'student_names' => $names,
                'line_subtotal' => $lineSubtotal,
                'line_subtotal_html' => wc_price($lineSubtotal),
            ];
        }

        wp_send_json_success([
            'items' => $items,
            'subtotal' => $subtotal,
            'subtotal_html' => wc_price($subtotal),
        ]);
    }

    public function handleRemoveCartItem(): void
    {
        check_ajax_referer('nafb_get_cart_summary', 'nonce');

        $cartKey = sanitize_text_field((string) ($_POST['cart_key'] ?? ''));
        if ($cartKey === '' || !function_exists('WC') || !WC()->cart) {
            wp_send_json_error(['success' => false, 'message' => __('Não foi possível remover este item.', 'nafloresta-buy')], 400);
        }

        $removed = WC()->cart->remove_cart_item($cartKey);
        if (!$removed) {
            wp_send_json_error(['success' => false, 'message' => __('Não foi possível remover este item.', 'nafloresta-buy')], 422);
        }

        wp_send_json_success(['success' => true]);
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
