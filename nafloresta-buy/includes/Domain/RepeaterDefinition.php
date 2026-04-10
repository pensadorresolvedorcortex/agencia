<?php

namespace NaFlorestaBuy\Domain;

class RepeaterDefinition extends FieldDefinition
{
    public string $source;

    public function __construct(string $id, string $label, string $source = 'quantity')
    {
        parent::__construct($id, 'repeater', $label, true);
        $this->source = $source;
    }
}
