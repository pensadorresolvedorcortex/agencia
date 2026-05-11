<?php

namespace NaFlorestaBuy\Application;

use NaFlorestaBuy\Infrastructure\Support\DataIntegrityGuard;

class OrderMetaPersisterService
{
    private DataIntegrityGuard $guard;

    public function __construct(?DataIntegrityGuard $guard = null)
    {
        $this->guard = $guard ?? new DataIntegrityGuard();
    }

    public function persist(\WC_Order_Item_Product $item, array $values): void
    {
        $nafb = $values['nafb'] ?? null;
        if (is_array($nafb) && $nafb !== []) {
            $normalized = $this->guard->normalizeNafb($nafb, $values);
            $item->add_meta_data('_nafb', $normalized, true);

            $studentNames = array_values(array_filter(array_map(static fn(array $row) => sanitize_text_field($row['name'] ?? ''), $normalized['fields']['student_names'] ?? [])));
            $item->add_meta_data('_nafb_snapshot', [
                'variation_label' => $normalized['variation_label'] ?? '',
                'student_names' => $studentNames,
            ], true);
            return;
        }

        if (!empty($values['nafb_snapshot'])) {
            $item->add_meta_data('_nafb_snapshot', $this->guard->normalizeSnapshot($values['nafb_snapshot']), true);
        }
    }
}
