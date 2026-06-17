<?php

/**
 * Custom Tab Integration for Fluent Forms
 *
 * @package     YourPlugin
 * @author      Your Name
 * @version     1.0.0
 */

namespace FormyChat\Forms\FluentForm;

if ( ! defined('ABSPATH') ) {
    exit;
}

use FluentForm\App\Helpers\Helper;

if ( ! class_exists(__NAMESPACE__ . '\Admin') ) {
    class Admin extends \FormyChat\Base {

        /**
         * Initialize the integration
         */
        public function __construct() {
            // Priority set to 11 to ensure base settings are loaded first
            add_action('fluentform/form_settings_menu', [ $this, 'add_custom_tab' ], 11);

            // Hook into Fluent Forms settings renderer
            add_action('fluentform/form_settings_container_form_settings', [ $this, 'render_tab_content' ]);

            // Save settings
            add_action('wp_ajax_ff_save_formychat_settings', [ $this, 'save_settings' ]);
        }

        /**
         * Add custom tab to the form settings menu
         *
         * @param array $menu_items Current menu items
         * @return array Modified menu items
         */
        public function add_custom_tab( $menu_items ) {
            $menu_items['formychat'] = [
                'title' => __('Whatsapp (Formychat)', 'social-contact-form'),
                'slug' => 'formychat',
                'hash' => 'formychat',
                'route' => '/formychat',
            ];

            return $menu_items;
        }

        /**
         * Add custom scripts and styles
         */
        public function add_custom_scripts() {
			?>
            <script>
                jQuery(document).ready(function($) {

                    document.addEventListener('formychat_dropdown_initialized', function() {
                        console.log('formychat_dropdown_initialized');
                    });

                    // Handle tab click
                    $(document).on('click', 'a.ff_list_button_link', function() {
                        const routeKey = $(this).data('route_key');

                        // Hide all wrappers and settings
                        $('.settings_app').hide();
                        $('#ff_formychat_wrapper').hide();

                        // Show the appropriate content based on the route key
                        if (routeKey === '/formychat') {
                            $('#ff_formychat_wrapper').show();
                        } else {
                            $('.settings_app').show();
                        }
                    });


                    // on load, if formychat is active, show the formychat wrapper
                    if (window.location.hash === '#/formychat') {
                        $('#ff_formychat_wrapper').show();
                        $('.settings_app').hide();
                    }

                    // Handle switch. 
                    $(document).on('click', '#ff_formychat_settings_form  [role="switch"]', function() {
                        var switchInput = $(this).find('input[type="checkbox"]');

                        // Toggle the checkbox state first
                        switchInput.prop('checked', !switchInput.is(':checked'));

                        // Now update classes and values based on new state
                        if (switchInput.is(':checked')) {
                            $(this).addClass('is-checked');
                            switchInput.val('1');
                        } else {
                            $(this).removeClass('is-checked');
                            switchInput.val('0');
                        }
                    });

                    // On load, initialize switch states
                    $(document).ready(function() {
                        var switchInputs = $('#ff_formychat_settings_form input[type="checkbox"]');

                        switchInputs.each(function() {
                            var $switch = $(this);
                            var $switchContainer = $switch.closest('[role="switch"]');

                            if ($switch.val() === '1') {
                                $switch.prop('checked', true);
                                $switchContainer.addClass('is-checked');
                            } else {
                                $switch.prop('checked', false);
                                $switchContainer.removeClass('is-checked');
                            }
                        });
                    });

                    // Handle form submission
                    $(document).on('submit', '#ff_formychat_settings_form', function(e) {
                        e.preventDefault();

                        $('#ff_formychat_success_message').hide();
                        $('#ff_formychat_error_message').hide();

                        var form = $(this);
                        var submitBtn = form.find('button[type="submit"]');
                        submitBtn.prop('disabled', true);

                        var formData = new FormData(this);
                        formData.append('action', 'ff_save_formychat_settings');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                $('#ff_formychat_success_message').show();

                                setTimeout(function() {
                                    $('#ff_formychat_success_message').hide();
                                }, 3000);
                            },
                            error: function() {
                                $('#ff_formychat_error_message').show();

                                setTimeout(function() {
                                    $('#ff_formychat_error_message').hide();
                                }, 3000);
                            },
                            complete: function() {
                                submitBtn.prop('disabled', false);
                            }
                        });
                    });
                });
            </script>
            <style>
                .ff_formychat_wrapper {
                    padding: 20px;
                    background: #fff;
                    border: 1px solid #ddd;
                    margin: 20px 0;
                }

                .ff_settings_block {
                    margin-bottom: 15px;
                }

                .ff_setting_label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 500;
                }

                .ff_settings_input {
                    width: 100%;
                    max-width: 400px;
                    padding: 8px;
                }

                .el-switch input[type="checkbox"] {
                    display: none;
                }

                .el-switch {
                    cursor: pointer;
                }

                .el-switch span:last-child {
                    margin-left: 10px;
                }
            </style>
			<?php
        }

        /**
         * Render the content for custom tab
         *
         * @param string $route Current route/tab
         * @return void
         */
        public function render_tab_content( $route ) {

            $form_id = isset($_GET['form_id']) ? intval($_GET['form_id']) : 0;
            if ( ! $form_id ) {
                return;
            }

            $form_api = fluentFormApi('forms')->form($form_id);
            // Fields exists in the form.
            $form_fields = $form_api->fields();

            $fields = [];
            foreach ( $form_fields['fields'] as $field ) {
                if ( 'input_name' === $field['element'] ) {
                    // For name fields, get all sub-field names
                    foreach ( $field['fields'] as $name_key => $name_field ) {
                        $fields[] = $name_key; // This will add first_name, middle_name, last_name
                    }
                } else {
                    $fields[] = isset($field['attributes']['name']) ? $field['attributes']['name'] : ''; // This will add email, subject, message etc if exists
                }
            }

            $formychat_status = Helper::getFormMeta($form_id, 'formychat_status', false);
            $formychat_phone_code = Helper::getFormMeta($form_id, 'formychat_phone_code', '');
            $formychat_whatsapp_number = Helper::getFormMeta($form_id, 'formychat_whatsapp_number', '');

            // Default values should be from available fields.
            $default_message_template = '';
            foreach ( $fields as $field ) {
                $default_message_template .= str_replace('_', ' ', ucfirst($field)) . ": {{$field}}\n";
            }

            $formychat_message_template = Helper::getFormMeta($form_id, 'formychat_message_template', $default_message_template);
            $formychat_new_tab = Helper::getFormMeta($form_id, 'formychat_new_tab', false);
            $formychat_web_version = Helper::getFormMeta($form_id, 'formychat_web_version', false);

            // Create nonce for security
            $nonce = wp_create_nonce('ff_formychat_nonce');

			?>
            <div id="ff_formychat_wrapper" class="ff_formychat_wrapper ff_settings_wrapper" style="<?php echo 'formychat' !== $route ? 'display:none;' : ''; ?>">
                <h3><?php echo esc_html__('Whatsapp (Formychat) Settings', 'social-contact-form'); ?></h3>

                <br>


                    <!-- Success  -->
                    <div id="ff_formychat_success_message" class="ff_formychat_message ff_formychat_message_success" style="display:none;">
                        <i class="el-icon-success"></i>
                        <?php echo esc_html__('Settings saved successfully', 'social-contact-form'); ?>
                    </div>

                    <!-- Error  -->
                    <div id="ff_formychat_error_message" class="ff_formychat_message ff_formychat_message_error" style="display:none;">
                        <i class="el-icon-error"></i>
                        <?php echo esc_html__('Error saving settings', 'social-contact-form'); ?>
                    </div>


                <form id="ff_formychat_settings_form" class="ff_form">
                    <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">
                    <input type="hidden" name="_nonce" value="<?php echo esc_attr($nonce); ?>">


                    <div class="ff_settings_block">
                        <div class="ff_setting_input">
                            <div role="switch" class="el-switch el-switch-lg">
                                <input type="checkbox" name="formychat_status" value="<?php echo wp_validate_boolean($formychat_status) ? '1' : '0'; ?>" <?php checked($formychat_status, '1'); ?>>
                                <span class="el-switch__core" style="width: 40px;"></span>
                                <span><?php echo esc_html__('Connect WhatsApp', 'social-contact-form'); ?></span>
                            </div>
                        </div>
                    </div>


                    <div class="ff_settings_block">
                        <label class="ff_setting_label">
                            <?php echo esc_html__('WhatsApp Number', 'social-contact-form'); ?>
                        </label>
                        <?php
                        formychat_phone_number_field([
                            'country_code' => $formychat_phone_code,
                            'number' => $formychat_whatsapp_number,
                            'country_code_name' => 'formychat_phone_code',
                            'number_name' => 'formychat_whatsapp_number',
                        ]);
                        ?>
                    </div>


                    <div class="ff_settings_block">
                        <label class="ff_setting_label">
                            <?php echo esc_html__('WhatsApp Message Body', 'social-contact-form'); ?>
                        </label>
                        <div class="ff_setting_input">
                            <textarea name="formychat_message_template" rows="6" cols="42.5"><?php echo esc_textarea($formychat_message_template); ?></textarea>
                        </div>
                        <p style="margin: 10px 0 20px 0">
                            <?php
                            if ( ! empty($fields) ) {
                                $all_fields = array_merge($fields, array_keys(\FormyChat\App::custom_tags()));
                                echo esc_html__('You can use the following placeholders:', 'social-contact-form');
                                foreach ( $all_fields as $field ) {
									?>
                                    <code>{<?php echo esc_attr($field); ?>}</code>,
									<?php
                                }
                            } else {
                                echo esc_html__('No fields found', 'social-contact-form');
                            }
                            ?>
                        </p>
                    </div>

                    <div class="ff_settings_block">
                        <div class="ff_setting_input">
                            <div role="switch" class="el-switch el-switch-lg">
                                <input type="checkbox" name="formychat_new_tab" value="<?php echo wp_validate_boolean($formychat_new_tab) ? '1' : '0'; ?>" <?php checked($formychat_new_tab, '1'); ?>>
                                <span class="el-switch__core" style="width: 40px;"></span>
                                <span><?php echo esc_html__('Open WhatsApp in New Tab', 'social-contact-form'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="ff_settings_block">
                        <div class="ff_setting_input">
                            <div role="switch" class="el-switch el-switch-lg">
                                <input type="checkbox" name="formychat_web_version" value="<?php echo wp_validate_boolean($formychat_web_version) ? '1' : '0'; ?>" <?php checked($formychat_web_version, '1'); ?>>
                                <span class="el-switch__core" style="width: 40px;"></span>
                                <span><?php echo esc_html__('Navigate to WhatsApp Web from desktop', 'social-contact-form'); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit  -->
                    <div>
                    <button type="submit" class="el-button el-button--primary el-button--medium">
                            <i class="el-icon-success"></i>
                            <span><?php echo esc_html__('Save Settings', 'social-contact-form'); ?></span>
                        </button>
                    </div>

                </form>
            </div>

            <style>
                .ff_formychat_message {
                    padding: 15px 20px;
                    font-size: 16px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                }

                .ff_formychat_message_success {
                    background-color: #dff0d8;
                    color: #3c763d;
                }

                .ff_formychat_message_error {
                    background-color: #f2dede;
                    color: #a94442;
                }
            </style>
			<?php
            wp_enqueue_script('formychat-admin-common', FORMYCHAT_PUBLIC . '/js/admin.common.js', [ 'jquery' ], FORMYCHAT_VERSION, false);

            $this->add_custom_scripts();
        }

        /**
         * Save custom settings via AJAX
         */
        public function save_settings() {
            // Verify nonce
            if ( isset ($_POST['_nonce'] ) && ! wp_verify_nonce(sanitize_text_field ( wp_unslash($_POST['_nonce'] ) ), 'ff_formychat_nonce') ) {
                wp_send_json_error([
                    'message' => esc_html__('Invalid security token', 'social-contact-form'),
                ]);
            }

            $form_id = isset( $_POST['form_id'] ) && ! empty( $_POST['form_id'] ) ? intval( sanitize_text_field( wp_unslash($_POST['form_id'] ) ) ) : 0;
            if ( ! $form_id ) {
                wp_send_json_error([
                    'message' => esc_html__('Invalid form ID', 'social-contact-form'),
                ]);
            }

            $formychat_status = isset( $_POST['formychat_status'] ) && ! empty( $_POST['formychat_status'] ) ? '1' : '0';
            $formychat_phone_code = isset( $_POST['formychat_phone_code'] ) && ! empty( $_POST['formychat_phone_code'] ) ? sanitize_text_field( wp_unslash($_POST['formychat_phone_code'] ) ) : '';
            $formychat_whatsapp_number = isset( $_POST['formychat_whatsapp_number'] ) && ! empty( $_POST['formychat_whatsapp_number'] ) ? sanitize_text_field( wp_unslash($_POST['formychat_whatsapp_number'] ) ) : '';
            $formychat_message_template = isset( $_POST['formychat_message_template'] ) && ! empty( $_POST['formychat_message_template'] ) ? sanitize_textarea_field( wp_unslash($_POST['formychat_message_template'] ) ) : '';
            $formychat_new_tab = isset( $_POST['formychat_new_tab'] ) && ! empty( $_POST['formychat_new_tab'] ) ? '1' : '0';
            $formychat_web_version = isset( $_POST['formychat_web_version'] ) && ! empty( $_POST['formychat_web_version'] ) ? '1' : '0';

            Helper::setFormMeta($form_id, 'formychat_status', $formychat_status);
            Helper::setFormMeta($form_id, 'formychat_phone_code', $formychat_phone_code);
            Helper::setFormMeta($form_id, 'formychat_whatsapp_number', $formychat_whatsapp_number);
            Helper::setFormMeta($form_id, 'formychat_message_template', $formychat_message_template);
            Helper::setFormMeta($form_id, 'formychat_new_tab', $formychat_new_tab);
            Helper::setFormMeta($form_id, 'formychat_web_version', $formychat_web_version);

            wp_send_json_success([
                'message' => esc_html__('Settings saved successfully', 'social-contact-form'),
            ]);
        }
    }
}

// Initialize .
Admin::init();
