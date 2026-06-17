<?php

/**
 * WPForms Settings.
 *
 * @since 1.0.0
 */
// Namespace .
namespace FormyChat\Forms\WPForms;

// Exit if accessed directly.
defined('ABSPATH') || exit;

class Admin extends \FormyChat\Base {


    /**
     * Actions.
     *
     * @since 1.0.0
     */
    public function actions() {
        add_filter('wpforms_builder_settings_sections', [ $this, 'add_settings_section' ]);
        add_action('wpforms_form_settings_panel_content', [ $this, 'add_settings' ]);
    }

    /**
     * Add settings sidebar.
     *
     * @return void
     */
    public function add_settings_section( $sections ) {
        $sections['formychat'] = esc_html__('WhatsApp (FormyChat)', 'social-contact-form');
        return $sections;
    }

    /**
     * Add settings.
     *
     * @return void
     */
    public function add_settings( $settings ) {
        // Output the section title
		echo '<div class="wpforms-panel-content-section wpforms-panel-content-section-formychat" data-panel="formychat" style="display: none;">';
        echo '<div class="wpforms-panel-content-section-title">';
        echo '<span id="wpforms-builder-settings-notifications-title">';
        esc_html_e('FormyChat Settings', 'social-contact-form');
        echo '</span>';

        echo '</div>';
        echo 'Send messages through this form while submitting directly to your WhatsApp account account while submitting';

        echo '<br/><br/><br/>';

        wpforms_panel_field(
            'toggle',
            'settings',
            'formychat_status',
            $settings->form_data,
            esc_html__('Connect WhatsApp', 'social-contact-form'),
            [
                'tooltip' => esc_html__('Enable WhatsApp notifications for this form.', 'social-contact-form'),
            ]
        );

        $args = [
            'country_code' => array_key_exists( 'formychat_country_code', $settings->form_data['settings'] ) ? $settings->form_data['settings']['formychat_country_code'] : '',
            'number' => array_key_exists( 'formychat_number', $settings->form_data['settings'] ) ? $settings->form_data['settings']['formychat_number'] : '',
            'country_code_name' => 'settings[formychat_country_code]',
            'number_name' => 'settings[formychat_number]',
        ];

        formychat_phone_number_field( $args );

        echo '<br/>';

        $tags = [];

        if ( ! array_key_exists( 'fields', $settings->form_data ) ) {
            $settings->form_data['fields'] = [];
        }

        $default_message = 'Thank you for contacting us. We will get back to you soon.';

        if ( $settings->form_data['fields'] && ! empty( $settings->form_data['fields'] ) ) {

            $form_tags = array_column( $settings->form_data['fields'], 'label' );

            $tags = [];
            $default_message = '';
            foreach ( $form_tags as $tag ) {
                $tags[] = '<strong>{' . $tag . '}</strong>';
                $default_message .= $tag . ': {' . $tag . '}' . PHP_EOL;
            }

            // Merge if not empty.
            $custom_tags = array_keys( \FormyChat\App::custom_tags() );
            if ( is_array( $custom_tags ) && ! empty( $custom_tags ) ) {
                $tags = array_merge( $tags, array_map( function ( $tag ) {
                    return '<strong>{' . $tag . '}</strong>';
                }, $custom_tags) );
            }
        }

        // Message body.
        wpforms_panel_field(
            'textarea',
            'settings',
            'formychat_message',
            $settings->form_data,
            esc_html__('WhatsApp Message Body', 'social-contact-form'),
            [
                'tooltip' => esc_html__('Enter the message that will be sent. Note: File Upload field will not support on WhatsApp message body. Use {FIELD_NAME} for dynamic field value.', 'social-contact-form'),
                'default' => $default_message,
            ]
        );

        if ( ! empty( $tags ) ) {

            echo '<div class="wpforms-panel-content-section-field-description">';
            printf(
                esc_html('Available tags: %s', 'social-contact-form'), // translators: %s - tags.
                implode( ', ', $tags ) // phpcs:ignore
            );
            echo '</div><br/>';
        }

        // Open in a new tab.
        wpforms_panel_field(
            'toggle',
            'settings',
            'formychat_new_tab',
            $settings->form_data,
            esc_html__('Open in a new tab', 'social-contact-form'),
            [
                'tooltip' => esc_html__('Enable to open whatsapp in new tab. Note: This option is for only desktop devices, It will be useful for WhatsApp web on desktop devices.', 'social-contact-form'),
            ]
        );

        echo '</div>';
    }
}


// Init.
Admin::init();
