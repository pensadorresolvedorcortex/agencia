<?php
/*
Plugin Name: Elfsight Age Verification CC
Description: A simple popup tool to verify the age of your visitors and allow or reject access to your materials.
Plugin URI: https://elfsight.com/age-verification-widget/codecanyon/?utm_source=markets&utm_medium=codecanyon&utm_campaign=age-verification&utm_content=plugin-site
Version: 1.1.1
Author: Elfsight
Author URI: https://elfsight.com/?utm_source=markets&utm_medium=codecanyon&utm_campaign=age-verification&utm_content=plugins-list
*/

if (!defined('ABSPATH')) exit;


require_once('core/elfsight-plugin.php');

$elfsight_age_verification_config_path = plugin_dir_path(__FILE__) . 'config.json';
$elfsight_age_verification_config = json_decode(file_get_contents($elfsight_age_verification_config_path), true);

new ElfsightAgeVerificationPlugin(
    array(
        'name' => esc_html__('Age Verification'),
        'description' => esc_html__('A simple popup tool to verify the age of your visitors and allow or reject access to your materials.'),
        'slug' => 'elfsight-age-verification',
        'version' => '1.1.1',
        'text_domain' => 'elfsight-age-verification',
        'editor_settings' => $elfsight_age_verification_config['settings'],
        'editor_preferences' => $elfsight_age_verification_config['preferences'],

        'plugin_name' => esc_html__('Elfsight Age Verification'),
        'plugin_file' => __FILE__,
        'plugin_slug' => plugin_basename(__FILE__),

        'vc_icon' => plugins_url('assets/img/vc-icon.png', __FILE__),
        'menu_icon' => plugins_url('assets/img/menu-icon.svg', __FILE__),

        'update_url' => esc_url('https://a.elfsight.com/updates/v1/'),
        'product_url' => esc_url('https://codecanyon.net/item/elfsight-age-verification/24088592?ref=Elfsight'),
        'helpscout_plugin_id' => 110700
    )
);

?>
