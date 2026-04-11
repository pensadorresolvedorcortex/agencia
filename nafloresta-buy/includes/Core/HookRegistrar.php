<?php

namespace NaFlorestaBuy\Core;

class HookRegistrar
{
    public function register(array $hookables): void
    {
        foreach ($hookables as $hookable) {
            if (is_object($hookable) && method_exists($hookable, 'register')) {
                $hookable->register();
            }
        }
    }
}
