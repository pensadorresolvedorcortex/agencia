<?php
/**
 * Message class.
 * Handles all Message requests.
 *
 * @package FormyChat
 * @since 1.0.0
 */

// Namespace.
namespace FormyChat\CF7;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;


if ( ! class_exists( __NAMESPACE__ . '\Frontend' ) ) {
	/**
	 * Message class.
	 * Handles all Message requests.
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
			add_action( 'wpcf7_skip_mail', [ $this, 'skip_mail' ] );
			// To support CF7.
			add_filter('wpcf7_feedback_response', [ $this, 'feedback_response' ], 10, 2);
		}

		/**
		 * Get tab form id and disable mail.
		 *
		 * @param array $skip_mail CF7 Id and disable mail.
		 *
		 * @return mixed
		 */
		public function skip_mail( $skip_mail ) {

			$contact_form = \WPCF7_ContactForm::get_current();
			$form_id = $contact_form->id();

			$formychat_config = get_post_meta( $form_id, '_formy_chat_configuration', true );

			// Try checking skip_email, if not found, check formy_chat_mail_status.
			if ( isset( $formychat_config['skip_email'] ) && 'on' === $formychat_config['skip_email'] ) {
				$skip_mail = true;
			} elseif ( isset( $formychat_config['formy_chat_mail_status'] ) && 'on' === $formychat_config['formy_chat_mail_status'] ) {
				$skip_mail = true;
			}

			return $skip_mail;
		}


		/**
		 * Manipulate CF7 response.
		 *
		 * @return void
		 */
		public function feedback_response( $response, $result ) {

			$submission = \WPCF7_Submission::get_instance();
			if ( ! $submission ) {
				return;
			}

			$formychat = get_post_meta($response['contact_form_id'], '_formy_chat_configuration', true);

			$form_options = [
				'status' => [ 'formy_chat_status', 'on' ],
				'country_code' => [ 'formy_chat_country_code', '44' ],
				'number' => [ 'formy_chat_number', '' ],
				'message' => [ 'formy_chat_message_fields', '*Name*: [your-name] ' . "\n" . '*Email*: [your-email]' . "\n" . '*Subject*: [your-subject]' . "\n" . '*Message*:' . "\n" . '[your-message]' . "\n" ],
				'new_tab' => [ 'formy_chat_tabs_status', 'off' ],
			];

			// Adjust the config, if new not set, try to get old.
			foreach ( $form_options as $key => $option ) {
				if ( ! isset( $formychat[ $key ] ) ) {
					$formychat[ $key ] = isset( $formychat[ $option[0] ]) ? $formychat[ $option[0] ] : $option[1];
				}
			}

			// Combine country code and number.
			$formychat['whatsapp_number'] = $formychat['country_code'] . $formychat['number'];
			unset( $formychat['country_code'] );
			unset( $formychat['number'] );

			$response['formychat'] = apply_filters( 'formychat_cf7_posted_data', $formychat );

			unset($formychat);
			return $response;
		}
	}

	// Initialize Message class. Only if doing Message.
	Frontend::init();
}
