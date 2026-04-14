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

class Frontend extends \FormyChat\Base {


    /**
     * Actions.
     *
     * @since 1.0.0
     */
    public function actions() {
        // Filter.
        add_filter('wpforms_ajax_submit_success_response', [ $this, 'submit_success_response' ], 10, 3);
    }

    /**
     * Submit success response.
     *
     * @return void
     */
    public function submit_success_response( $output, $form_id, $form_data ) {

        // Check nonce.
        if ( isset( $_REQUEST['wpforms'] ) && isset( $_REQUEST['wpforms']['nonce'] ) ) {
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['wpforms']['nonce'] ) ), 'wpforms::form_' . $form_id ) ) {
                return $output;
            }
        }

        $settings = array_key_exists('settings', $form_data) ? $form_data['settings'] : [];

        $message = array_key_exists('formychat_message', $settings) ? $settings['formychat_message'] : '';

        if ( isset( $_POST['wpforms']['complete'] ) && ! empty( $_POST['wpforms']['complete'] ) ) {
            foreach ( $_POST['wpforms']['complete'] as $field ) { // phpcs:ignore
                // If found {field->name} replace with the value
                $message = str_replace('{' . $field['name'] . '}', $field['value'], $message);
            }
        }

        $formychat = [
            'status' => array_key_exists('formychat_status', $settings) ? $settings['formychat_status'] : false,
            'whatsapp_number' => ( array_key_exists('formychat_country_code', $settings) ? $settings['formychat_country_code'] : '' ) . ( array_key_exists('formychat_number', $settings) ? $settings['formychat_number'] : '' ),
            'new_tab' => array_key_exists('formychat_new_tab', $settings) ? $settings['formychat_new_tab'] : false,
            'message' => array_key_exists('formychat_message', $settings) ? $settings['formychat_message'] : '',
            'fields' => isset( $_POST['wpforms']['complete'] ) ? array_values( $_POST['wpforms']['complete'] ) : [], // phpcs:ignore
        ];

        $output['formychat'] = $formychat;

        return $output;
    }
}


// Init.
Frontend::init();
