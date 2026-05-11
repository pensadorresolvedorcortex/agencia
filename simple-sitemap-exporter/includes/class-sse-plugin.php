<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'class-sse-storage.php';
require_once plugin_dir_path(__FILE__) . 'class-sse-scanner.php';
require_once plugin_dir_path(__FILE__) . 'class-sse-sitemap.php';
require_once plugin_dir_path(__FILE__) . '../admin/class-sse-admin.php';

class SSE_Plugin
{
    const REWRITE_VERSION = '1.1.0';
    /** @var SSE_Plugin|null */
    private static $instance = null;

    /** @var SSE_Storage */
    private $storage;

    /** @var SSE_Scanner */
    private $scanner;

    /** @var SSE_Sitemap */
    private $sitemap;

    /** @var SSE_Admin */
    private $admin;

    public static function instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->storage = new SSE_Storage();
        $this->scanner = new SSE_Scanner();
        $this->sitemap = new SSE_Sitemap($this->storage);
        $this->admin   = new SSE_Admin($this->scanner, $this->sitemap, $this->storage);

        add_action('init', array($this, 'register_rewrite_rule'));
        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 20);
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('template_redirect', array($this->sitemap, 'maybe_render_public_sitemap'));
        add_filter('robots_txt', array($this, 'append_robots_sitemap_line'), 10, 2);
    }

    public static function activate()
    {
        $plugin = self::instance();
        $plugin->register_rewrite_rule();
        flush_rewrite_rules();
    }

    public static function deactivate()
    {
        flush_rewrite_rules();
    }

    public function register_rewrite_rule()
    {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?sse_sitemap=1', 'top');
        add_rewrite_rule('^sitemap-([0-9]+)\.xml$', 'index.php?sse_sitemap=1&sse_sitemap_part=$matches[1]', 'top');
    }

    public function register_query_vars($vars)
    {
        $vars[] = 'sse_sitemap';
        $vars[] = 'sse_sitemap_part';
        return $vars;
    }


    public function maybe_flush_rewrite_rules()
    {
        $stored = get_option('sse_rewrite_version', '');
        if (self::REWRITE_VERSION === $stored) {
            return;
        }

        $this->register_rewrite_rule();
        flush_rewrite_rules(false);
        update_option('sse_rewrite_version', self::REWRITE_VERSION, false);
    }

    public function append_robots_sitemap_line($output, $public)
    {
        if ('0' === (string) $public) {
            return $output;
        }

        $line = 'Sitemap: ' . home_url('/sitemap.xml');

        if (false !== stripos((string) $output, $line)) {
            return $output;
        }

        $output = rtrim((string) $output);
        if ('' !== $output) {
            $output .= "\n";
        }

        return $output . $line . "\n";
    }
}
