<?php

namespace FormyChat\Forms\GravityForms;

// Exit if accessed directly.
defined('ABSPATH') || exit;

use Gravity_Forms\Gravity_Forms\Settings\Settings;

/**
 * Gravity Forms Settings.
 *
 * @since 1.0.0
 */
class Admin extends \FormyChat\Base {

    /**
     * Actions.
     *
     * @since 1.0.0
     */
    public function actions() {
        add_filter('gform_form_settings_menu', [ $this, 'settings_tabs' ]);
        add_action('gform_form_settings_page_formychat', [ $this, 'settings' ]);
        add_action('admin_footer', [ $this, 'footer' ]);
        add_action('gform_form_settings_save', [ $this, 'save' ]); // Add this line to hook into form settings save

        add_action('gform_after_save_form', [ $this, 'after_save' ], 10, 2); // Add this line to hook into form creation
    }

    /**
     * Add settings sidebar.
     *
     * @return void
     */
    public function settings_tabs( $menu_items ) {
        // Add a custom tab
        $menu_items[] = [
            'name' => 'formychat',
            'label' => esc_html__('WhatsApp (FormyChat)', 'social-contact-form'),
            'icon' => 'gform-icon--formychat',
            'query' => [
				'cid' => null,
				'nid' => null,
				'fid' => null,
			],
            'capabilities' => [ 'gravityforms_edit_forms' ],
        ];

        return $menu_items;
    }

    /**
     * Add settings.
     *
     * @return void
     */
    public function settings( $form ) {
        \GFFormSettings::page_header(__('WhatsApp (FormyChat)', 'social-contact-form'));

        $form_id = absint(rgget('id'));

        $form = \GFAPI::get_form($form_id);

        $fields = array_map(function ( $field ) {
            return $field->label;
        }, $form['fields']);

        $custom_tags = \FormyChat\App::custom_tags();
        if ( is_array ( $custom_tags ) && ! empty( $custom_tags ) ) {
            $fields = array_merge( $fields, array_keys( $custom_tags ) );
        }

        $html = wp_sprintf('<p>Use the tags bellow to customize your message: %s</p>', wp_sprintf('<strong>{%s}<strong>', implode('}, {', array_map(function ( $field ) {
            return $field;
        }, $fields))));

        $initial_values = [
            'status' => gform_get_meta($form_id, 'formychat_status'),
            'country_code' => gform_get_meta($form_id, 'formychat_country_code'),
            'number' => gform_get_meta($form_id, 'formychat_number'),
            'message' => null !== gform_get_meta($form_id, 'formychat_message') ? gform_get_meta($form_id, 'formychat_message') : 'Name: {Name}
Email: {Email}
Comments: {Comments}',
            'new_tab' => gform_get_meta($form_id, 'formychat_new_tab'),
        ];

        $args = [
            'country_code' => $initial_values['country_code'],
            'number' => $initial_values['number'],
            'country_code_name' => '_gform_setting_formychat_country_code',
            'number_name' => '_gform_setting_formychat_number',
        ];

        ob_start();
        formychat_phone_number_field( $args );

        $phone_number_field = ob_get_clean();

        // Add custom fields to the settings
        $args = [
            'fields' => [
                'formychat' => [
                    'title'  => esc_html__('WhatsApp (FormyChat)', 'social-contact-form'),
                    'fields' => [
                        [
                            'name'       => 'status',
                            'type'       => 'toggle',
                            'label'      => esc_html__('Connect WhatsApp', 'social-contact-form'),
                            'tooltip'    => esc_html__('Enable WhatsApp notifications for this form.', 'social-contact-form'),
                        ],
                        [
                            'type'       => 'html',
                            'label'      => esc_html__('WhatsApp Number', 'social-contact-form'),
                            'tooltip'    => esc_html__('Enter your WhatsApp number.', 'social-contact-form'),
                            'html'       => $phone_number_field,
                        ],
                        [
                            'name'       => 'message',
                            'type'       => 'textarea',
                            'label'      => esc_html__('WhatsApp Message Body', 'social-contact-form'),
                            'tooltip'    => esc_html__('Enter your message.', 'social-contact-form'),
                        ],
                        // HTML.
                        [
                            'type'       => 'html',
                            'html'       => $html,
                        ],
                        [
                            'name'       => 'new_tab',
                            'type'       => 'toggle',
                            'label'      => esc_html__('Open in new tab', 'social-contact-form'),
                            'tooltip'    => esc_html__('Open the WhatsApp link in a new tab.', 'social-contact-form'),
                        ],
                    ],
                ],
            ],
            'save_callback' => [ $this, 'save' ],
            'initial_values' => $initial_values,
        ];

        // Print the settings fields.
        $settings = new Settings($args);
        $settings->render();

        \GFFormSettings::page_footer();
    }

    /**
     * Footer.
     *
     * @return void
     */
    public function footer() {
        $css = wp_sprintf('.gform-icon--formychat { 
            content: " ";
            background: url( %s ) no-repeat center center;
            background-size: 100%% 100%%;
            display: inline-block;
            width: 30px;
            height: 35px;
            transform: scale(1.2)
        }', FORMYCHAT_PUBLIC . '/images/whatsapp.svg');
        echo '<style>' . esc_html($css) . '</style>'; // phpcs:ignore
    }

    /**
     * Save custom settings.
     *
     * @param array $form The form data.
     * @param int $form_id The ID of the form.
     * @return array The modified form data.
     */
    public function save( $values ) {

        $_wp_http_referer = rgpost('_wp_http_referer'); // /wp-admin/admin.php?subview=formychat&page=gf_edit_forms&id=1&view=settings
        $form_id = preg_match('/id=(\d+)/', $_wp_http_referer, $matches) ? absint($matches[1]) : 0;

        if ( $values ) {
            foreach ( $values as $key => $value ) {

                if ( 'formychat_country_code' === $key ) {
                    gform_update_meta($form_id, 'formychat_country_code', $value, $form_id);
                    continue;
                }

                if ( 'formychat_number' === $key ) {
                    gform_update_meta($form_id, 'formychat_number', $value, $form_id);
                    continue;
                }

                gform_update_meta($form_id, 'formychat_' . $key, $value, $form_id);
            }
        }

        return $values;
    }

    /**
     * Form saved.
     *
     * @param array $form The form data.
     * @param bool $is_new If the form is new.
     */
    public function after_save( $form, $is_new ) {

        $form_id = rgars($form, 'id');

        if ( ! $form_id ) {
            return;
        }

		if ( $is_new ) {

            $message = 'Thanks for contacting us! We will get back to you shortly.';

            $fields = $form['fields'];

            if ( $fields && is_array($fields) ) {
                $message = '';
                $message = implode("\n", array_map(function ( $field ) {
                    return $field->label . ': {' . $field->label . '}';
                }, $fields));
			}

            gform_update_meta( $form_id, 'formychat_message', $message, $form_id );
		}
    }
}

// Init.
Admin::init();
