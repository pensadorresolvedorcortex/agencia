<?php

namespace NaFlorestaBuy\Presentation\Shared;

class OrderItemPresenter
{
    public function renderAdminHtml(\WC_Order_Item_Product $item): string
    {
        $names = $this->extractStudentNamesFromOrderItem($item);
        if ($names === []) {
            return '';
        }

        return sprintf(
            '<p><strong>%s:</strong> %s</p>',
            esc_html__('Nomes dos alunos', 'nafloresta-buy'),
            esc_html(implode(', ', $names))
        );
    }

    /**
     * @return string[]
     */
    private function extractStudentNamesFromOrderItem(\WC_Order_Item_Product $item): array
    {
        $nafb = $item->get_meta('_nafb', true);
        if (is_array($nafb) && !empty($nafb['fields']['student_names']) && is_array($nafb['fields']['student_names'])) {
            return array_values(array_filter(array_map(static fn(array $row) => trim((string) ($row['name'] ?? '')), $nafb['fields']['student_names'])));
        }

        $legacy = $item->get_meta('_nafb_snapshot', true);
        if (is_array($legacy) && !empty($legacy['student_names']) && is_array($legacy['student_names'])) {
            return array_values(array_filter(array_map(static fn($value) => trim((string) $value), $legacy['student_names'])));
        }

        return [];
    }
}
