<?php

namespace NaFlorestaBuy\Domain;

class SelectionBatch
{
    public int $productId;
    /** @var SelectionLine[] */
    public array $items;

    public function __construct(int $productId, array $items)
    {
        $this->productId = $productId;
        $this->items = $items;
    }
}
