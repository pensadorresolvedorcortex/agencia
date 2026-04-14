<?php

/**
 * Frontend class.
 * Handles all Frontend requests.
 *
 * @package FormyChat
 * @since 1.0.0
 */

// Namespace.
namespace FormyChat\Formidable;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


if ( ! class_exists( __NAMESPACE__ . '\Frontend' ) ) {
	/**
	 * Frontend class.
	 * Handles all Frontend requests.
	 *
	 * @package FormyChat
	 * @since 1.0.0
	 */
	class Frontend extends \FormyChat\Base {

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */
		public function actions() {
			// Ajax.
			add_action( 'wp_ajax_formychat_get_formidable_entry', [ $this, 'get_formidable_entry' ] );
			add_action( 'wp_ajax_nopriv_formychat_get_formidable_entry', [ $this, 'get_formidable_entry' ] );

			add_filter( 'frm_success_filter', [ $this, 'on_success' ], 10, 3 );
			add_action('formychat_footer', [ $this, 'footer' ], 10, 3);
		}


		public function get_formidable_entry() {

			// Nonce.
			// check_ajax_referer( 'formychat_submitted_formidable_form', 'nonce' );

			// Get form data.
			$form_id = isset( $_REQUEST['form_id'] ) ? intval( wp_unslash( $_REQUEST['form_id'] ) ) : 0;

			$fields = \FrmField::get_all_for_form( $form_id );

			// To key => name.
			$fields_key_name = [];
			foreach ( $fields as $field ) {
				$fields_key_name[ $field->field_key ] = $field->name;
			}

			$form = \FrmForm::getOne( $form_id );

			if ( ! $form ) {
				wp_send_json_error( [ 'message' => 'Form not found' ] );
				wp_die();
			}

			global $wpdb;

			// Get the last entry ID for the specific form
			$entry_id = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}frm_items WHERE form_id = %d ORDER BY created_at DESC LIMIT 1",
				$form_id
			));

			if ( ! $entry_id ) {
				echo 'No entries found.';
				return;
			}

			// Get all field values for the last entry
			$entry_meta = $wpdb->get_results($wpdb->prepare(
				"SELECT field_id, meta_value FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d",
				$entry_id
			));

			// Convert to key-value pair (field_key => value)
			$entry = [];
			foreach ( $entry_meta as $meta ) {

				$field_key = $wpdb->get_var($wpdb->prepare(
					"SELECT field_key FROM {$wpdb->prefix}frm_fields WHERE id = %d",
					$meta->field_id
				));

				if ( ! isset( $fields_key_name[ $field_key ] ) ) {
					continue;
				}

				$entry[ $fields_key_name[ $field_key ] ] = $meta->meta_value;

			}

			// Settings.
			$options = $form->options;

			$number = isset( $options['formychat_phone_code'] ) ? $options['formychat_phone_code'] : '';
			$number .= isset( $options['formychat_whatsapp_number'] ) ? $options['formychat_whatsapp_number'] : '';

			$data = [
				'form_id' => $form_id,
				'inputs' => $entry,
				'formychat' => [
					'status' => isset( $options['formychat_status'] ) ? wp_validate_boolean( $options['formychat_status'] ) : 0,
					'number' => $number,
					'message' => isset( $options['formychat_message'] ) ? $options['formychat_message'] : '',
					'new_tab' => isset( $options['formychat_new_tab'] ) ? wp_validate_boolean( $options['formychat_new_tab'] ) : 0,
					'navigate_to_web' => isset( $options['formychat_navigate_to_web'] ) ? \wp_validate_boolean( $options['formychat_navigate_to_web'] ) : '',
				],
			];

			wp_send_json_success( $data );
			wp_die();
		}

		public function on_success( $method, $form, $action ) {

			// Get form data.
			$form_id = isset( $_REQUEST['form_id'] ) ? intval( wp_unslash( $_REQUEST['form_id'] ) ) : 0;

			$form = \FrmForm::getOne( $form_id );

			if ( ! $form ) {
				wp_send_json_error( [ 'message' => 'Form not found' ] );
				wp_die();
			}
			// Settings.
			$options = $form->options;
			if ( isset( $options['ajax_submit'] ) && $options['ajax_submit'] ) {
				return $method;
			}

			$fields = \FrmField::get_all_for_form( $form_id );

			// To key => name.
			$fields_key_name = [];
			foreach ( $fields as $field ) {
				$fields_key_name[ $field->field_key ] = $field->name;
			}

			global $wpdb;

			// Get the last entry ID for the specific form
			$entry_id = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}frm_items WHERE form_id = %d ORDER BY created_at DESC LIMIT 1",
				$form_id
			));

			if ( ! $entry_id ) {
				echo 'No entries found.';
				return;
			}

			// Get all field values for the last entry
			$entry_meta = $wpdb->get_results($wpdb->prepare(
				"SELECT field_id, meta_value FROM {$wpdb->prefix}frm_item_metas WHERE item_id = %d",
				$entry_id
			));

			// Convert to key-value pair (field_key => value)
			$entry = [];
			foreach ( $entry_meta as $meta ) {

				$field_key = $wpdb->get_var($wpdb->prepare(
					"SELECT field_key FROM {$wpdb->prefix}frm_fields WHERE id = %d",
					$meta->field_id
				));

				if ( ! isset( $fields_key_name[ $field_key ] ) ) {
					continue;
				}

				$entry[ $fields_key_name[ $field_key ] ] = $meta->meta_value;

			}

			$number = isset( $options['formychat_phone_code'] ) ? $options['formychat_phone_code'] : '';
			$number .= isset( $options['formychat_whatsapp_number'] ) ? $options['formychat_whatsapp_number'] : '';

			$data = [
				'inputs' => $entry,
				'formychat' => [
					'status' => isset( $options['formychat_status'] ) ? wp_validate_boolean( $options['formychat_status'] ) : 0,
					'number' => $number,
					'message' => isset( $options['formychat_message'] ) ? $options['formychat_message'] : '',
					'new_tab' => isset( $options['formychat_new_tab'] ) ? wp_validate_boolean( $options['formychat_new_tab'] ) : 0,
					'navigate_to_web' => isset( $options['formychat_navigate_to_web'] ) ? \wp_validate_boolean( $options['formychat_navigate_to_web'] ) : '',
				],
				'form_id' => $form_id,
			];

			// Print Inline_script.
			echo '<script> 
    
			if ( window.formychat_formidable_submit ) {
				window.formychat_formidable_submit(' . json_encode( $data ) . ');
			} else {
				document.addEventListener("formychat_loaded", (event) => {
					if ( window.formychat_formidable_loaded ) return;
					window.formychat_formidable_submit(' . json_encode( $data ) . ');
					window.formychat_formidable_loaded = true;
				});
			}
			
			</script>';

			return $method;
		}

		public function footer( $form, $form_id, $widget ) {
			if ( 'formidable' === $form ) {
				echo '<script>
				document.addEventListener("DOMContentLoaded", (event) => {
					const frmShowForm = document.querySelector(".frm-show-form");
					if ( frmShowForm ) {
						frmShowForm.classList.add("frm_ajax_submit");
					}
				});
				</script>';
			}
		}
	}

	// Initialize Message class. Only if doing Message.
	Frontend::init();
}
