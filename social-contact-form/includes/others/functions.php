<?php

/**
 * Non-OOP Functions.
 */

if ( ! function_exists('formychat_phone_number_field') ) {
    function formychat_phone_number_field( $args = [] ) {

        $args = wp_parse_args($args, [
            'country_code' => '',
            'number' => '',
            'country_code_name' => 'formychat_country_code',
            'number_name' => 'formychat_number',
        ]);
		?>
        <div class="formychat-phone-field" tabindex="1">
            <div class="formychat-dropdown">
                <!-- input  -->
                <input type="hidden" class="formychat-dropdown-input" 
                name="<?php echo esc_attr( $args['country_code_name'] ); ?>"
                    value="<?php echo esc_attr( $args['country_code'] ); ?>">

                <!-- placeholder  -->
                <div class="formychat-dropdown-placeholder" tabindex="1">
                    <span data-countrycode></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chevron-down" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708" />
                    </svg>
                </div>

                <!-- countries  -->
                <div class="formychat-dropdown-content">

                    <input type="search" 
                    autocomplete="off"
                    class="formychat-dropdown-content-search" placeholder="Search country" />
                    <div class="formychat-dropdown-content-items">
                        <?php
                        $countries = \FormyChat\App::countries();
                        foreach ( $countries as $country ) {
							?>
                            <div class="formychat-dropdown-content-item <?php echo $args['country_code'] === $country['code'] ? 'selected' : ''; ?>"
                                data-placeholder="<?php echo wp_sprintf('%s +%s', esc_attr($country['flag']), esc_attr($country['code'])); ?>"
                                data-value="<?php echo esc_attr($country['code']); ?>"
                                data-tags="<?php echo wp_sprintf('%s %s', esc_attr($country['code']), esc_attr($country['name'])); ?>">
                                <?php echo wp_sprintf('%s (+%s) - %s', esc_attr($country['flag']), esc_attr($country['code']), esc_html($country['name'])); ?>
                            </div>
							<?php
                        }
                        ?>
                    </div>

                </div>
            </div>
            <input type="text" value="<?php echo esc_attr($args['number']); ?>" name="<?php echo esc_attr($args['number_name']); ?>" class="formychat-input-text" pattern="[0-9{8,15}]" placeholder="<?php esc_html_e('Phone number', 'social-contact-form'); ?>">
        </div>
		<?php
    }
}


// Build message.
if ( ! function_exists('formychat_build_message') ) {
    function formychat_build_message( $fields = [], $message = '', $delimiter = '{.+}' ) {
        $message = str_replace(
            [
                '[your-name]',
                '[your-email]',
                '[your-subject]',
                '[your-message]',
            ],
            [
                isset($fields['your-name']) ? $fields['your-name'] : '',
                isset($fields['your-email']) ? $fields['your-email'] : '',
                isset($fields['your-subject']) ? $fields['your-subject'] : '',
                isset($fields['your-message']) ? $fields['your-message'] : '',
            ],
            $message
        );

        return $message;
    }
}

