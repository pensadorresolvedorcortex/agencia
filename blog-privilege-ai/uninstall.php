<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('bpv_blog_privilege_generate_post');
wp_clear_scheduled_hook('bpv_blog_privilege_cleanup_generation_lock');

delete_option('bpv_blog_privilege_enabled');
delete_option('bpv_blog_privilege_topic_index');
delete_option('bpv_blog_privilege_total_posts');
delete_option('bpv_blog_privilege_last_run');
delete_option('bpv_blog_privilege_last_post_id');
delete_option('bpv_blog_privilege_last_error');
delete_option('bpv_blog_privilege_content_hashes');
delete_option('bpv_blog_privilege_phrase_hashes');
delete_option('bpv_blog_privilege_title_hashes');
delete_option('bpv_blog_privilege_image_log');
delete_option('bpv_blog_privilege_diagnostic_log');
delete_option('bpv_ai_art_style');
delete_option('bpv_ai_brand_profile');
delete_option('bpv_ai_avoid_elements');
delete_option('bpv_editorial_photography_engine');
delete_option('bpv_seo_engine_v4');

delete_transient('bpv_blog_privilege_generation_lock');
delete_transient('bpv_blog_privilege_ai_image_backoff');
