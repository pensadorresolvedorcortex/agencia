<?php

namespace NaFlorestaBuy\Infrastructure\Support;

class Nonce
{
    public function verify(string $action, string $nonce): bool
    {
        return (bool) wp_verify_nonce($nonce, $action);
    }
}
