<?php

namespace NaFlorestaBuy\Core;

class Logger
{
    public function info(string $message, array $context = []): void
    {
        if (!$this->enabled() || !function_exists('wc_get_logger')) {
            return;
        }

        wc_get_logger()->info($message . ' ' . wp_json_encode($this->sanitizeContext($context)), ['source' => 'nafloresta-buy']);
    }

    public function error(string $message, array $context = []): void
    {
        if (!$this->enabled() || !function_exists('wc_get_logger')) {
            return;
        }

        wc_get_logger()->error($message . ' ' . wp_json_encode($this->sanitizeContext($context)), ['source' => 'nafloresta-buy']);
    }

    private function enabled(): bool
    {
        return (bool) get_option('nafb_debug_mode', false);
    }

    private function sanitizeContext(array $context): array
    {
        if (isset($context['payload'])) {
            unset($context['payload']['nonce']);
        }

        return $context;
    }
}
