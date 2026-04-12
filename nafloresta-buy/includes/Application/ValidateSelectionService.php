<?php

namespace NaFlorestaBuy\Application;

use NaFlorestaBuy\Domain\ValidationResult;
use NaFlorestaBuy\Core\Logger;
use NaFlorestaBuy\Infrastructure\Repository\ConfigRepositoryInterface;

class ValidateSelectionService
{
    private ConfigRepositoryInterface $repository;
    private Logger $logger;

    public function __construct(ConfigRepositoryInterface $repository, ?Logger $logger = null)
    {
        $this->repository = $repository;
        $this->logger = $logger ?? new Logger();
    }

    public function validate(array $payload): ValidationResult
    {
        $startedAt = microtime(true);
        $result = new ValidationResult();

        $productId = absint($payload['product_id'] ?? 0);
        if ($productId <= 0) {
            $result->addError('invalid_product', __('Produto inválido.', 'nafloresta-buy'));
            return $this->finalize($result, $startedAt, $payload);
        }

        $mode = sanitize_key((string) ($payload['mode'] ?? 'matrix'));
        if ($mode !== 'matrix') {
            $result->addError('invalid_mode', __('Apenas modo matrix está disponível no MVP.', 'nafloresta-buy'));
            return $this->finalize($result, $startedAt, $payload);
        }

        $config = $this->repository->getProductConfig($productId);
        if (empty($config['enabled'])) {
            $result->addError('disabled', __('Builder desativado para este produto.', 'nafloresta-buy'));
            return $this->finalize($result, $startedAt, $payload);
        }

        $allowedVariations = array_map('intval', $config['variation_ids'] ?? []);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];

        if ($items === []) {
            $result->addError('empty_items', __('Selecione ao menos uma variação com quantidade.', 'nafloresta-buy'));
            return $this->finalize($result, $startedAt, $payload);
        }

        foreach ($items as $index => $item) {
            $lineNumber = $index + 1;
            $variationId = absint($item['variation_id'] ?? 0);
            $quantity = absint($item['quantity'] ?? 0);
            $students = $item['fields']['student_names'] ?? [];

            if (!in_array($variationId, $allowedVariations, true)) {
                $result->addError('invalid_variation', sprintf(__('Variação inválida na linha %d.', 'nafloresta-buy'), $lineNumber));
                continue;
            }

            $variation = wc_get_product($variationId);
            if (!$variation || !$variation->is_type('variation') || (int) $variation->get_parent_id() !== $productId) {
                $result->addError('variation_not_found', sprintf(__('Variação inexistente na linha %d.', 'nafloresta-buy'), $lineNumber));
                continue;
            }

            if ($quantity <= 0) {
                $result->addError('invalid_quantity', sprintf(__('Quantidade inválida na linha %d.', 'nafloresta-buy'), $lineNumber));
                continue;
            }

            if (!$variation->is_in_stock()) {
                $result->addError('out_of_stock', sprintf(__('Variação sem estoque na linha %d.', 'nafloresta-buy'), $lineNumber));
            }

            if ($variation->managing_stock() && $variation->get_stock_quantity() !== null && $quantity > (int) $variation->get_stock_quantity()) {
                $result->addError('insufficient_stock', sprintf(__('Quantidade maior que o estoque na linha %d.', 'nafloresta-buy'), $lineNumber));
            }

            if (!is_array($students) || count($students) !== $quantity) {
                $result->addError('student_count_mismatch', sprintf(__('Quantidade de nomes deve ser igual à quantidade na linha %d.', 'nafloresta-buy'), $lineNumber));
                continue;
            }

            foreach ($students as $studentIndex => $student) {
                $name = trim((string) ($student['name'] ?? ''));
                if ($name === '') {
                    $result->addError('student_name_required', sprintf(__('Nome obrigatório na linha %1$d item %2$d.', 'nafloresta-buy'), $lineNumber, $studentIndex + 1));
                }
            }
        }

        return $this->finalize($result, $startedAt, $payload);
    }

    private function finalize(ValidationResult $result, float $startedAt, array $payload): ValidationResult
    {
        if ($result->hasErrors()) {
            do_action('nafb_validation_failed', [
                'errors' => $result->errors(),
                'product_id' => absint($payload['product_id'] ?? 0),
            ]);
        }

        $this->logger->info('nafb.metrics.validation', [
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'has_errors' => $result->hasErrors(),
        ]);

        return $result;
    }
}
