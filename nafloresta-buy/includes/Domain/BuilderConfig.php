<?php

namespace NaFlorestaBuy\Domain;

class BuilderConfig
{
    public ProductConfig $product;

    public function __construct(ProductConfig $product)
    {
        $this->product = $product;
    }
}
