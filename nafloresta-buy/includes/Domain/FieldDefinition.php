<?php

namespace NaFlorestaBuy\Domain;

class FieldDefinition
{
    public string $id;
    public string $type;
    public string $label;
    public bool $required;

    public function __construct(string $id, string $type, string $label, bool $required = false)
    {
        $this->id = $id;
        $this->type = $type;
        $this->label = $label;
        $this->required = $required;
    }
}
