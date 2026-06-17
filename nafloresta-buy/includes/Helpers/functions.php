<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('nafb_require_directory')) {
    function nafb_require_directory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            require_once $file->getPathname();
        }
    }
}
