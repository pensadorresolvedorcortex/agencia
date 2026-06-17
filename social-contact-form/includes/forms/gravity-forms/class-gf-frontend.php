<?php

/**
 * GravityForms Frontend.
 *
 * @since 1.0.0
 */
// Namespace .
namespace FormyChat\Forms\GravityForms;

// Exit if accessed directly.
defined('ABSPATH') || exit;

class Frontend extends \FormyChat\Base {


    /**
     * Actions.
     *
     * @since 1.0.0
     */
    public function actions() {
        // Add ajax.
        add_action('wp_ajax_formychat_get_gf_entry', [ $this, 'get_entry' ]);
        add_action('wp_ajax_nopriv_formychat_get_gf_entry', [ $this, 'get_entry' ]);

        add_filter('gform_confirmation', [ $this, 'form_confirmation' ], 10, 3);
    }


    /**
     * Submit form.
     *
     * @return void
     */
    public function get_entry() {

        if ( ! function_exists('rgget') ) {
            wp_send_json_error();
            wp_die();
        }

        $form_id = rgget('id');

        if ( ! $form_id ) {
            wp_send_json_error();
            wp_die();
        }

        $form = \GFAPI::get_form($form_id);

        $entries = \GFAPI::get_entries( $form_id );
        $entry = count( $entries ) > 0 ? $entries[0] : null;

        if ( ! $entry ) {
            return;
        }

        // Merge entry value acc
        $merged_entry = [];

        // Loop through the original array
        foreach ( $entry as $key => $value ) {
            // Skip empty values
            if ( trim($value) === '' ) {
                continue;
            }

            // Extract the base key before the dot (or the full key if no dot exists)
            $base_key = explode('.', $key)[0];

            // Merge values with the same base key
            if ( ! isset($merged_entry[ $base_key ]) ) {
                $merged_entry[ $base_key ] = $value;
            } else {
                $merged_entry[ $base_key ] .= ' ' . $value;
            }
        }

        $values = [];
        foreach ( $form['fields'] as $field ) {
            $id = $field->id;
            $label = $field->label;

            if ( array_key_exists($id, $merged_entry) ) {
                $values[ $label ] = $merged_entry[ $id ];
            }
        }

        if ( ! $form ) {
            wp_send_json_error();
            wp_die();
        }

        wp_send_json_success([
            'form' => $form,
            'formychat' => [
                'status' => gform_get_meta($form_id, 'formychat_status'),
                'whatsapp_number' => gform_get_meta($form_id, 'formychat_country_code') . gform_get_meta($form_id, 'formychat_number'),
                'message' => gform_get_meta($form_id, 'formychat_message'),
                'new_tab' => gform_get_meta($form_id, 'formychat_new_tab'),
                'values' => $values,
            ],
        ]);
    }

    /**
     * After Form confirmation.
     *
     * @param array $confirmation
     * @param array $form
     * @param array $entry
     * @return array
     */
    public function form_confirmation( $confirmation, $form, $entry ) {

        // Bail if form is not enabled.
        if ( ! gform_get_meta($form['id'], 'formychat_status') ) {
            return $confirmation;
        }

        $settings = [
            'whatsapp_number' => gform_get_meta($form['id'], 'formychat_country_code') . gform_get_meta($form['id'], 'formychat_number'),
            'message' => gform_get_meta($form['id'], 'formychat_message'),
            'new_tab' => gform_get_meta($form['id'], 'formychat_new_tab'),
        ];

        /**
         * Enqueue the formychat script in the footer.
         *
         * @param array $form The form data.
         * @param array $entry The entry data.
         * @param array $settings The settings data.
         */
        function enqueue_formychat_script( $form, $entry, $settings ) {
            // Create a unique ID to prevent potential script collision
            $script_id = 'formychat-script-' . uniqid();
            ?>
            <script id="<?php echo esc_attr( $script_id ); ?>" type="text/javascript">
            (function () {
                function submit(){
                    
                    window.gform_formychat(
                        <?php echo wp_json_encode( $form ); ?>, 
                        <?php echo wp_json_encode( $entry ); ?>, 
                        <?php echo wp_json_encode( $settings ); ?>
                    );
                }
                if ( window.gform_formychat ) {
                    submit();
                } else {
                    document.addEventListener("formychat_gf_loaded", function (e) {
                        submit();
                    });
                }
                
            })();
            </script>
            <?php
        }

        add_action( 'wp_footer', function () use ( $form, $entry, $settings ) {
            enqueue_formychat_script( $form, $entry, $settings );
        });

        return $confirmation;
    }
}


// Init.
Frontend::init();
