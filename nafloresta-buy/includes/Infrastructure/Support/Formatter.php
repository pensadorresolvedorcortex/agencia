<?php

namespace NaFlorestaBuy\Infrastructure\Support;

class Formatter
{
    public function money(float $value): string
    {
        return function_exists('wc_price') ? wc_price($value) : number_format($value, 2);
    }
}
