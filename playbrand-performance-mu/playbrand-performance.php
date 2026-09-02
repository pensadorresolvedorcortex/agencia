<?php
/**
 * Plugin Name: PlayBrand Performance (MU Loader)
 * Description: Carregador opcional do PlayBrand Performance para instalações must-use.
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;
$plugin = WP_CONTENT_DIR . '/plugins/playbrand-performance/playbrand-performance.php';
if (is_readable($plugin)) require_once $plugin;
