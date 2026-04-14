<?php

namespace NaFlorestaBuy\Domain;

class VariationConfig
{
    public int $variationId;
    public string $displayLabel;

    public function __construct(int $variationId, string $displayLabel)
    {
        $this->variationId = $variationId;
        $this->displayLabel = $displayLabel;
    }
}
