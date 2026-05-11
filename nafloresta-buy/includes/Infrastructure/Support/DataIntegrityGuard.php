<?php

namespace NaFlorestaBuy\Infrastructure\Support;

class DataIntegrityGuard
{
    public function normalizeSnapshot($snapshot): array
    {
        if (!is_array($snapshot)) {
            return [
                'variation_label' => '',
                'student_names' => [],
            ];
        }

        $names = [];
        foreach ((array) ($snapshot['student_names'] ?? []) as $name) {
            $value = is_array($name) ? (string) ($name['name'] ?? '') : (string) $name;
            $value = sanitize_text_field($value);
            if ($value !== '') {
                $names[] = $value;
            }
        }

        return [
            'variation_label' => sanitize_text_field((string) ($snapshot['variation_label'] ?? '')),
            'student_names' => array_values($names),
        ];
    }

    public function normalizeNafb($nafb, array $fallback = []): array
    {
        if (!is_array($nafb)) {
            $nafb = [];
        }

        $studentRows = [];
        foreach ((array) ($nafb['fields']['student_names'] ?? []) as $row) {
            $name = sanitize_text_field((string) (is_array($row) ? ($row['name'] ?? '') : $row));
            if ($name !== '') {
                $studentRows[] = ['name' => $name];
            }
        }

        return [
            'version' => sanitize_text_field((string) ($nafb['version'] ?? '1.0.0')),
            'unique_key' => sanitize_key((string) ($nafb['unique_key'] ?? ($fallback['unique_key'] ?? ''))),
            'product_id' => absint($nafb['product_id'] ?? ($fallback['product_id'] ?? 0)),
            'variation_id' => absint($nafb['variation_id'] ?? ($fallback['variation_id'] ?? 0)),
            'variation_label' => sanitize_text_field((string) ($nafb['variation_label'] ?? ($fallback['variation_label'] ?? ''))),
            'quantity' => max(0, absint($nafb['quantity'] ?? ($fallback['quantity'] ?? 0))),
            'fields' => [
                'student_names' => array_values($studentRows),
            ],
        ];
    }

    public function normalizeProductConfig($raw): array
    {
        if (!is_array($raw)) {
            $raw = [];
        }

        return [
            'enabled' => !empty($raw['enabled']),
            'builder_mode' => sanitize_key((string) ($raw['builder_mode'] ?? 'matrix')),
            'ui_mode' => sanitize_key((string) ($raw['ui_mode'] ?? 'drawer')),
            'variation_ids' => array_values(array_map('absint', (array) ($raw['variation_ids'] ?? []))),
            'fields_schema' => array_values(is_array($raw['fields_schema'] ?? null) ? $raw['fields_schema'] : []),
        ];
    }
}
