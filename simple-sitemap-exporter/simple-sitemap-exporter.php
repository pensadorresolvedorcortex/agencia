<?php
/**
 * Plugin Name:       Simple Sitemap Exporter
 * Description:       Escaneia URLs públicas/indexáveis e exporta um único sitemap.xml.
 * Version:           1.0.0
 * Author:            Simple Sitemap Exporter
 * License:           GPL-2.0-or-later
 * Text Domain:       simple-sitemap-exporter
 */

if (! defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/class-sse-plugin.php';

register_activation_hook(__FILE__, array('SSE_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('SSE_Plugin', 'deactivate'));

SSE_Plugin::instance();
