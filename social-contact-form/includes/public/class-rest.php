<?php
/**
 * Public REST.
 * Handles requests from the public side.
 *
 * @package FormyChat
 * @since 1.0.0
 */

namespace FormyChat\Publics;

use FormyChat\Models\Lead;
use FormyChat\Models\Widget;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( __NAMESPACE__ . '\\REST' ) ) {
	/**
	 * Public REST controller.
	 */
	class REST extends \FormyChat\Base {

		/**
		 * Register hooks.
		 *
		 * @return void
		 */
		public function actions() {
			add_action( 'rest_api_init', [ $this, 'register_routes' ] );
			add_action( 'formychat_lead_created', [ $this, 'formychat_lead_created' ], 10, 3 );
		}

		/**
		 * Register REST routes.
		 *
		 * @return void
		 */
		public function register_routes() {
			register_rest_route(
				'formychat/v1',
				'/submit-form',
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'handle_form_submission' ],
					'permission_callback' => '__return_true',
				]
			);

			register_rest_route(
				'formychat/v1',
				'/get-form',
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_form' ],
					'permission_callback' => '__return_true',
				]
			);
		}

		/**
		 * Handle form submission.
		 *
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response
		 */
		public function handle_form_submission( $request ) {
			$form_data = [
				'field'     => $this->sanitize_field_pairs( $request->get_param( 'field' ) ),
				'meta'      => $this->sanitize_meta_payload( $request->get_param( 'meta' ) ),
				'widget_id' => absint( $request->get_param( 'widget_id' ) ),
				'form_id'   => absint( $request->get_param( 'form_id' ) ),
				'form'      => sanitize_key( (string) $request->get_param( 'form' ) ),
			];

			if ( '' === $form_data['form'] ) {
				$form_data['form'] = 'formychat';
			}

			do_action( 'formychat_form_submitted', $form_data, $request );
			$form_data = apply_filters( 'formychat_form_data', $form_data );

			$lead_id = Lead::create( $form_data );
			do_action( 'formychat_lead_created', $form_data, $lead_id, $request );

			return new \WP_REST_Response(
				[
					'success' => $lead_id > 0,
					'lead_id' => (int) $lead_id,
				]
			);
		}

		/**
		 * Process lead created hook.
		 *
		 * @param array            $form_data Form payload.
		 * @param int              $lead_id Lead ID.
		 * @param \WP_REST_Request $request Request.
		 * @return void
		 */
		public function formychat_lead_created( $form_data, $lead_id, $request ) {
			$widget_id = isset( $form_data['widget_id'] ) ? absint( $form_data['widget_id'] ) : 0;
			if ( $widget_id < 1 ) {
				return;
			}

			$widget = Widget::find( $widget_id );
			if ( ! $widget || empty( $widget->config['email'] ) || ! is_array( $widget->config['email'] ) ) {
				return;
			}

			$settings = $widget->config['email'];
			if ( empty( $settings['enabled'] ) || ! wp_validate_boolean( $settings['enabled'] ) ) {
				return;
			}

			$to = ( ! empty( $settings['admin_email'] ) && wp_validate_boolean( $settings['admin_email'] ) )
				? get_option( 'admin_email' )
				: sanitize_email( isset( $settings['address'] ) ? (string) $settings['address'] : '' );

			if ( ! is_email( $to ) ) {
				return;
			}

			$field_data = isset( $form_data['field'] ) && is_array( $form_data['field'] ) ? $form_data['field'] : [];
			$rows       = [];
			foreach ( $field_data as $key => $value ) {
				$rows[] = wp_sprintf(
					'<strong>%s</strong>: %s',
					esc_html( ucfirst( sanitize_text_field( (string) $key ) ) ),
					esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) )
				);
			}

			$data = implode( '<br/>', $rows );

			$subject = apply_filters(
				'formychat_email_subject',
				wp_sprintf( 'New Lead from %s', get_bloginfo( 'name' ) ),
				$form_data,
				$lead_id,
				$request
			);

			$body = apply_filters(
				'formychat_email_body',
				wp_sprintf(
					'Hi,<br/><br/>You have received a new lead from %s.<br/><br/>Please check the details below:<br/>%s<br/><br/>Sent at %s<br/>Thank you.',
					esc_html( get_bloginfo( 'name' ) ),
					$data,
					gmdate( 'Y-m-d H:i:s' )
				),
				$form_data,
				$lead_id,
				$request
			);

			$headers = apply_filters(
				'formychat_email_headers',
				[
					'Content-Type: text/html; charset=UTF-8',
				],
				$form_data,
				$lead_id,
				$request
			);

			wp_mail( $to, wp_strip_all_tags( (string) $subject ), (string) $body, $headers );
		}

		/**
		 * Sanitize submitted fields.
		 *
		 * @param mixed $field Raw field payload.
		 * @return array
		 */
		private function sanitize_field_pairs( $field ) {
			if ( ! is_array( $field ) ) {
				return [];
			}

			$sanitized = [];
			foreach ( $field as $key => $value ) {
				$sanitized_key = sanitize_key( (string) $key );
				if ( '' === $sanitized_key ) {
					continue;
				}

				if ( is_array( $value ) ) {
					$sanitized[ $sanitized_key ] = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $value ) );
				} else {
					$sanitized[ $sanitized_key ] = sanitize_text_field( wp_unslash( (string) $value ) );
				}
			}

			return $sanitized;
		}

		/**
		 * Sanitize metadata payload.
		 *
		 * @param mixed $meta Raw metadata.
		 * @return array
		 */
		private function sanitize_meta_payload( $meta ) {
			if ( ! is_array( $meta ) ) {
				return [];
			}

			$sanitized = [];
			foreach ( $meta as $key => $value ) {
				$sanitized_key = sanitize_key( (string) $key );
				if ( '' === $sanitized_key ) {
					continue;
				}

				$sanitized[ $sanitized_key ] = is_scalar( $value )
					? sanitize_text_field( wp_unslash( (string) $value ) )
					: wp_json_encode( $value );
			}

			return $sanitized;
		}
	}

	REST::init();
}
