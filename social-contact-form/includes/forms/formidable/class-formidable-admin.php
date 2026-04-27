<?php

/**
 * Form class.
 * Handles all Form requests.
 *
 * @package FormyChat
 * @since 1.0.0
 */

// Namespace.
namespace FormyChat\Formidable;

// Exit if accessed directly.
defined('ABSPATH') || exit;


if ( ! class_exists(__NAMESPACE__ . '\Admin') ) {
	/**
	 * Form class.
	 * Handles all Form requests.
	 *
	 * @package FormyChat
	 * @since 1.0.0
	 */
	class Admin extends \FormyChat\Base {

		/**
		 * Register actions.
		 *
		 * @since 1.0.0
		 */
		public function actions() {
			add_filter('frm_add_form_settings_section', [ $this, 'add_settings_tab' ], 10, 2);
			add_filter('frm_form_options_before_update', [ $this, 'add_formychat_field_options' ], 10, 2);

			add_action('frm_page_footer', [ $this, 'add_formychat_icon' ]);
		}

		/**
		 * Add a new tab to the Formidable admin menu.
		 *
		 * @since 1.0.0
		 */
		public function add_settings_tab( $sections, $values ) {
			$sections['formychat'] = [
				'name'       => __( 'WhatsApp (FormyChat)', 'formychat' ),
				'icon'       => 'formidable_formychat_icon',
				'html_class'      => 'formychat-formidable-nav',
				'function'   => function () use ( $values ) {
					$this->render_formychat_tab( $values );
				},
			];
			return $sections;
		}

		/**
		 * Render the FormyChat tab.
		 *
		 * @since 1.0.0
		 */
		public function render_formychat_tab( $values ) {

			$form_id = $values['id'];

			$keys = [];
			$default_message = '';
			$fields = \FrmField::get_all_for_form( $form_id );
			if ( ! empty( $fields ) ) {
				foreach ( $fields as $field ) {
					if ( 'submit' === $field->type ) {
						continue;
					}
					$keys[] = '{' . $field->name . '}';
					$default_message .= $field->name . ': {' . $field->name . "}\n";
				}
			}

			// If has custom tags, add them to the keys.
			$custom_tags = \FormyChat\App::custom_tags();
			if ( ! empty( $custom_tags ) ) {
				foreach ( $custom_tags as $tag => $value ) {
					$keys[] = '{' . $tag . '}';
				}
			}

			// Form Options.
			$form_meta = \FrmForm::getOne( $form_id );
			$form_options = $form_meta->options;
			$form_options = maybe_unserialize( $form_options );

			$status = isset( $form_options['formychat_status'] ) ? wp_validate_boolean($form_options['formychat_status']) : false;
			$country_code = isset( $form_options['formychat_phone_code'] ) ? $form_options['formychat_phone_code'] : '';
			$number = isset( $form_options['formychat_whatsapp_number'] ) ? $form_options['formychat_whatsapp_number'] : '';
			$message = isset( $form_options['formychat_message'] ) ? $form_options['formychat_message'] : '';
			$new_tab = isset( $form_options['formychat_new_tab'] ) ? wp_validate_boolean($form_options['formychat_new_tab']) : false;
			$navigate_to_web = isset( $form_options['formychat_navigate_to_web'] ) ? wp_validate_boolean($form_options['formychat_navigate_to_web']) : false;

			?>

<div class="frm-form-settings-section formychat-formidable-settings" id="html_settings">
	<fieldset>
		<label>Connect to WhatsApp <input type="checkbox" name="formychat_status" value="1" <?php checked( $status, true ); ?> /></label>
	</fieldset>

	<fieldset>
		<label>WhatsApp Phone</label>
		<div style="position: absolute">
			<?php
			formychat_phone_number_field([
				'country_code' => $country_code,
				'number' => $number,
				'country_code_name' => 'formychat_phone_code',
				'number_name' => 'formychat_whatsapp_number',
			]);
			?>
		</div>
	</fieldset>

	<fieldset>
		<label>WhatsApp Message</label>
		<textarea name="formychat_message" rows="6" ><?php echo esc_textarea($message); ?></textarea>
		<p>
			<?php

			if ( ! empty( $keys ) ) {
				echo 'Available fields: ' . esc_html( implode(', ', $keys) );
			} else {
				echo 'No fields available';
			}
			?>
        </p>
	</fieldset>

	<!-- New Tab -->
	<fieldset>
		<label> Open WhatsApp in new tab <input type="checkbox" name="formychat_new_tab" value="1" <?php checked( $new_tab, true ); ?> /></label>
	</fieldset>

	<!-- Navigate to WhatsApp Web  -->
	<fieldset>
		<label> Navigate to WhatsApp Web <input type="checkbox" name="formychat_navigate_to_web" value="1" <?php checked( $navigate_to_web, true ); ?> /></label>
	</fieldset>
</div>



			<?php
		}


		/**
		 * Add the FormyChat field options.
		 *
		 * @since 1.0.0
		 */
		public function add_formychat_field_options( $options, $values ) {

			$options['formychat_status'] = isset( $values['formychat_status'] ) ? $values['formychat_status'] : '';
			$options['formychat_phone_code'] = isset( $values['formychat_phone_code'] ) ? $values['formychat_phone_code'] : '';
			$options['formychat_whatsapp_number'] = isset( $values['formychat_whatsapp_number'] ) ? $values['formychat_whatsapp_number'] : '';
			$options['formychat_message'] = isset( $values['formychat_message'] ) ? $values['formychat_message'] : '';
			$options['formychat_new_tab'] = isset( $values['formychat_new_tab'] ) ? $values['formychat_new_tab'] : false;
			$options['formychat_navigate_to_web'] = isset( $values['formychat_navigate_to_web'] ) ? $values['formychat_navigate_to_web'] : false;

			return $options;
		}

		/**
		 * Add the FormyChat icon.
		 *
		 * @since 1.0.0
		 */
		public function add_formychat_icon() {
			echo '<style>
				.formidable_formychat_icon {
					background: url(' . esc_url( FORMYCHAT_PUBLIC ) . '/images/whatsapp.svg) no-repeat center center !important; 
					background-size: 111% !important;
					width: 20px !important;
					margin-left: -1px !important;
				}
				a.formychat-formidable-nav {
					gap: 0 !important;
				}
			</style>';
		}
	}

	// Initialize Form class. Only if doing Form.
	Admin::init();
}
