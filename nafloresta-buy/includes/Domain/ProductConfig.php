<?php

namespace NaFlorestaBuy\Domain;

class ProductConfig
{
    public int $productId;
    public bool $enabled;
    public string $builderMode;
    public string $uiMode;
    public array $variationIds;

    public function __construct(int $productId, bool $enabled, string $builderMode, string $uiMode, array $variationIds)
    {
        $this->productId = $productId;
        $this->enabled = $enabled;
        $this->builderMode = $builderMode;
        $this->uiMode = $uiMode;
        $this->variationIds = $variationIds;
    }

    public static function fromArray(int $productId, array $data): self
    {
        return new self(
            $productId,
            !empty($data['enabled']),
            in_array(($data['builder_mode'] ?? 'matrix'), ['matrix', 'single'], true) ? $data['builder_mode'] : 'matrix',
            in_array(($data['ui_mode'] ?? 'drawer'), ['drawer', 'modal', 'inline'], true) ? $data['ui_mode'] : 'drawer',
            array_map('intval', $data['variation_ids'] ?? [])
        );
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'enabled' => $this->enabled,
            'builder_mode' => $this->builderMode,
            'ui_mode' => $this->uiMode,
            'variation_ids' => $this->variationIds,
        ];
    }
}
