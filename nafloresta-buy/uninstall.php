<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('nafb_presets');
delete_option('nafb_insights');
