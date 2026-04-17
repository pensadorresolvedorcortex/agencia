<?php

/**
 * Admin class.
 * Handles all Admin requests.
 *
 * @package FormyChat
 * @since 1.0.0
 */

// Namespace.
namespace FormyChat\Ninja;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


/**
 * WhatsAppAction class.
 * Handles WhatsApp integration for Ninja Forms.
 *
 * @package FormyChat
 * @since 1.0.0
 */
class WhatsAppAction extends \NF_Abstracts_Action {

    /**
     * Name of the action
     *
     * @var string
     */
    protected $_name = 'formychat';

    /**
     * Timing of the action
     *
     * @var string
     */
    protected $_timing = 'late';

    /**
     * Priority of the action
     *
     * @var int
     */
    protected $_priority = '10';

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();

        $this->_nicename = esc_html__( 'WhatsApp (FormyChat)', 'social-contact-form' );

        $this->_settings = array(
            'formychat_number' => array(
                'name' => 'formychat_number',
                'type' => 'textbox',
                'label' => esc_html__( 'WhatsApp Number (with country code)', 'social-contact-form' ),
                'help' => esc_html__( 'WhatsApp number must be in international format with country code (e.g. 447123456789)', 'social-contact-form' ),
                'group' => 'primary',
                'width' => 'full',
            ),
            'formychat_message' => array(
                'name' => 'formychat_message',
                'type' => 'textarea',
                'label' => esc_html__( 'WhatsApp Message', 'social-contact-form' ),
                'width' => 'full',
                'group' => 'primary',
                'value' => '',
                'rows' => '6',
                'help' => $this->get_merge_tags_help(),
            ),
            'formychat_new_tab' => array(
                'name' => 'formychat_new_tab',
                'type' => 'toggle',
                'label' => esc_html__( 'Open WhatsApp in new tab', 'social-contact-form' ),
                'width' => 'full',
                'group' => 'primary',
                'value' => '0',
            ),
            'formychat_web_version' => array(
                'name' => 'formychat_web_version',
                'type' => 'toggle',
                'label' => esc_html__( 'Navigate to WhatsApp Web for Desktop', 'social-contact-form' ),
                'width' => 'full',
                'group' => 'primary',
                'value' => '0',
            ),
        );
    }
    /**
     * Get merge tags help text - simplified version
     *
     * @return string
     */
    private function get_merge_tags_help() {
        $available_tags = [];

         // Get form ID.
         $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
        if ( ! $form_id ) {
            return '';
        }
         // Get form fields - simplified approach.
         $fields = Ninja_Forms()->form($form_id)->get_fields();

         // Merge tags
		foreach ( $fields as $field ) {
            // If field is not a submit button.
            if ( $field->get_setting('type') !== 'submit' ) {
                $available_tags[] = '{' . $field->get_setting('key') . '}';
            }
		}

        if ( class_exists('\FormyChat\App') && method_exists('\FormyChat\App', 'custom_tags') ) {
            $custom_tags = \FormyChat\App::custom_tags();
            if ( ! empty($custom_tags) ) {
                foreach ( $custom_tags as $tag => $value ) {
                    $available_tags[] = '{' . $tag . '}';
                }
            }
        }

        // Basic help text.
        $help = '';
        if ( ! empty($available_tags) ) {
            $help = esc_html__('Available tags:', 'social-contact-form') . ' ' . esc_html(implode(', ', $available_tags));
        }

        return $help;
    }


    /**
     * Save action settings - simplified.
     *
     * @param array $action_settings
     * @return array
     */
    public function save( $action_settings ) {

        // Sanitize number, and message.
        $action_settings['formychat_number'] = sanitize_text_field($action_settings['formychat_number']);
        $action_settings['formychat_message'] = sanitize_textarea_field($action_settings['formychat_message']);

        return $action_settings;
    }

    /**
     * Process the action
     *
     * @param array $action_settings
     * @param int $form_id
     * @param array $data
     * @return array
     */
    public function process( $action_settings, $form_id, $data ) {
        // Get phone number
		$number = isset($action_settings['formychat_number']) ? $action_settings['formychat_number'] : '';
        // Get message
        $message = isset($action_settings['formychat_message']) ? $action_settings['formychat_message'] : '';

        // Options
        $new_tab = ! empty($action_settings['formychat_new_tab']);
        $web_version = ! empty($action_settings['formychat_web_version']);

        // Add WhatsApp URL to form data (can be used by success messages or redirects)
        $data['actions']['formychat'] = array(
            'new_tab' => $new_tab,
            'whatsapp_number' => $number,
            'message' => $message,
            'web_version' => $web_version,
        );

        return $data;
    }
}

// Register the action
add_filter('ninja_forms_register_actions', function ( $actions ) {
    $actions['formychat'] = new WhatsAppAction();
    return $actions;
});

add_filter('ninja_forms_action_formychat_settings', function ( $settings ) {
    $settings['formychat_message']['help'] = 'dsfdfdf';
    return $settings;
});
