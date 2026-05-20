<?php

namespace NaFlorestaBuy\Application;

use NaFlorestaBuy\Core\Logger;

class BatchAddToCartService
{
    private ValidateSelectionService $validator;
    private Logger $logger;

    public function __construct(ValidateSelectionService $validator, ?Logger $logger = null)
    {
        $this->validator = $validator;
        $this->logger = $logger ?? new Logger();
    }

    public function execute(array $payload): array
    {
        $startedAt = microtime(true);
        $this->logger->info('nafb.batch.start', ['payload' => ['product_id' => $payload['product_id'] ?? 0, 'items_count' => is_array($payload['items'] ?? null) ? count($payload['items']) : 0]]);

        $validation = $this->validator->validate($payload);
        if ($validation->hasErrors()) {
            $this->logger->error('nafb.batch.validation_failed', ['errors' => $validation->errors()]);
            return [
                'success' => false,
                'code' => 'validation_failed',
                'errors' => $validation->errors(),
                'lines' => [],
                'added' => [],
            ];
        }

        $productId = absint($payload['product_id']);
        $lines = [];
        $added = [];
        $runtimeErrors = [];
        $seenUniqueKeys = [];

        foreach ($payload['items'] as $index => $item) {
            $lineNumber = $index + 1;
            $variationId = absint($item['variation_id']);
            $quantity = absint($item['quantity']);
            $studentRows = is_array($item['fields']['student_names'] ?? null) ? $item['fields']['student_names'] : [];
            $studentFields = array_values(array_map(static fn(array $row) => ['name' => sanitize_text_field($row['name'] ?? '')], $studentRows));
            $studentNames = array_values(array_filter(array_map(static fn(array $row) => $row['name'], $studentFields)));
            $uniqueSeed = [$productId, $variationId, $quantity, $studentFields, sanitize_key((string) ($item['unique_key'] ?? ($payload['request_key'] ?? '')))];
            $uniqueKey = sanitize_key((string) ($item['unique_key'] ?? md5(wp_json_encode($uniqueSeed))));

            if (isset($seenUniqueKeys[$uniqueKey]) || $this->isRecentDuplicate($uniqueKey)) {
                $lines[] = [
                    'line' => $lineNumber,
                    'variation_id' => $variationId,
                    'quantity' => $quantity,
                    'success' => true,
                    'cart_key' => null,
                    'errors' => [],
                    'deduplicated' => true,
                ];
                continue;
            }
            $seenUniqueKeys[$uniqueKey] = true;
            $this->markUniqueKey($uniqueKey);

            do_action('nafb_before_add_to_cart', [
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $quantity,
                'fields' => $studentFields,
                'unique_key' => $uniqueKey,
            ]);

            $product = wc_get_product($variationId);
            if (!$product) {
                $error = [
                    'code' => 'add_to_cart_failed',
                    'message' => sprintf(__('Falha ao adicionar variação %d ao carrinho.', 'nafloresta-buy'), $variationId),
                ];

                $runtimeErrors[] = $error;
                do_action('nafb_unexpected_state', ['reason' => 'missing_variation', 'variation_id' => $variationId]);
                $lines[] = [
                    'line' => $lineNumber,
                    'variation_id' => $variationId,
                    'quantity' => $quantity,
                    'success' => false,
                    'cart_key' => null,
                    'errors' => [$error],
                ];
                continue;
            }

            $nafb = [
                'version' => '1.0.0',
                'unique_key' => $uniqueKey,
                'product_id' => $productId,
                'variation_id' => $variationId,
                'variation_label' => $product->get_name(),
                'quantity' => $quantity,
                'fields' => [
                    'student_names' => $studentFields,
                ],
            ];

            $cartItemData = [
                'nafb' => $nafb,
                'nafb_builder' => true,
                'nafb_snapshot' => [
                    'variation_label' => $nafb['variation_label'],
                    'student_names' => $studentNames,
                ],
                'nafb_unique_key' => $uniqueKey,
            ];

            $variationAttributes = method_exists($product, 'get_variation_attributes') ? $product->get_variation_attributes() : [];
            $cartKey = WC()->cart->add_to_cart($productId, $quantity, $variationId, $variationAttributes, $cartItemData);

            if (!$cartKey) {
                $error = [
                    'code' => 'add_to_cart_failed',
                    'message' => sprintf(__('Falha ao adicionar variação %d ao carrinho.', 'nafloresta-buy'), $variationId),
                ];

                $runtimeErrors[] = $error;
                do_action('nafb_unexpected_state', ['reason' => 'add_to_cart_failed', 'variation_id' => $variationId, 'unique_key' => $uniqueKey]);
                $lines[] = [
                    'line' => $lineNumber,
                    'variation_id' => $variationId,
                    'quantity' => $quantity,
                    'success' => false,
                    'cart_key' => null,
                    'errors' => [$error],
                ];
                continue;
            }

            do_action('nafb_after_add_to_cart', [
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $quantity,
                'cart_key' => $cartKey,
                'unique_key' => $uniqueKey,
            ]);

            $added[] = $cartKey;
            $lines[] = [
                'line' => $lineNumber,
                'variation_id' => $variationId,
                'quantity' => $quantity,
                'success' => true,
                'cart_key' => $cartKey,
                'errors' => [],
            ];
        }

        $this->logger->info('nafb.metrics.add_to_cart', [
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'items_count' => count($payload['items']),
            'added_count' => count($added),
        ]);

        if ($runtimeErrors !== []) {
            $this->logger->error('nafb.batch.partial_failure', ['errors' => $runtimeErrors]);
            return [
                'success' => false,
                'code' => 'partial_failure',
                'errors' => $runtimeErrors,
                'lines' => $lines,
                'added' => $added,
            ];
        }

        $this->logger->info('nafb.batch.success', ['added_count' => count($added)]);

        return [
            'success' => true,
            'code' => 'ok',
            'errors' => [],
            'lines' => $lines,
            'added' => $added,
        ];
    }

    private function isRecentDuplicate(string $uniqueKey): bool
    {
        return (bool) get_transient($this->lockKey($uniqueKey));
    }

    private function markUniqueKey(string $uniqueKey): void
    {
        set_transient($this->lockKey($uniqueKey), 1, 15);
    }

    private function lockKey(string $uniqueKey): string
    {
        return 'nafb_lock_' . $uniqueKey;
    }
}
