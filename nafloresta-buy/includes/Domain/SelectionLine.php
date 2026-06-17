<?php

namespace NaFlorestaBuy\Domain;

class SelectionLine
{
    public int $variationId;
    public int $quantity;
    public array $fields;

    public function __construct(int $variationId, int $quantity, array $fields)
    {
        $this->variationId = $variationId;
        $this->quantity = $quantity;
        $this->fields = $fields;
    }
}
