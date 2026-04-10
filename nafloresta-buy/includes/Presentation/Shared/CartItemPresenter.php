<?php

namespace NaFlorestaBuy\Presentation\Shared;

class CartItemPresenter
{
    public function present(array $cartItem): array
    {
        $names = $this->extractStudentNamesFromCartItem($cartItem);
        if ($names === []) {
            return [];
        }

        return [[
            'key' => __('Nomes dos alunos', 'nafloresta-buy'),
            'value' => esc_html(implode(', ', $names)),
        ]];
    }

    /**
     * @return string[]
     */
    private function extractStudentNamesFromCartItem(array $cartItem): array
    {
        $nafb = $cartItem['nafb'] ?? [];
        if (!empty($nafb['fields']['student_names']) && is_array($nafb['fields']['student_names'])) {
            $rows = $nafb['fields']['student_names'];
            return array_values(array_filter(array_map(static fn(array $row) => trim((string) ($row['name'] ?? '')), $rows)));
        }

        $legacy = $cartItem['nafb_snapshot']['student_names'] ?? [];
        if (is_array($legacy)) {
            return array_values(array_filter(array_map(static fn($value) => trim((string) $value), $legacy)));
        }

        return [];
    }
}
