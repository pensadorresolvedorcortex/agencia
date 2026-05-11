<?php

namespace NaFlorestaBuy\Infrastructure\Support;

class Sanitizer
{
    public function text(string $value): string
    {
        return sanitize_text_field($value);
    }
}
