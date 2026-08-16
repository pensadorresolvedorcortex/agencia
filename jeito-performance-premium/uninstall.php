<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'jpp_cache_flush_pending' );
delete_option( 'jpp_cache_flush_attempts' );
delete_option( 'jpp_last_cache_flush' );
delete_option( 'jpp_installed_version' );
delete_option( 'jpp_features' );
