<?php
/**
 * REST API.
 * Handles all rest related functionality.
 *
 * @package FormyChat
 * @since 1.0.0
 */

// Namespace .
namespace FormyChat\Admin;

// Load Widget Model.
require_once FORMYCHAT_INCLUDES . '/models/class-widget.php';

// Use Widget Model.
use FormyChat\Models\Widget;

// Exit if accessed directly.
defined('ABSPATH') || exit;


if ( ! class_exists ( __NAMESPACE__ . '\Rest') ) {
	/**
	 * REST API.
	 * Handles all rest related functionality.
	 *
	 * @package FormyChat
	 * @since 3.0.0
	 */
	class Rest extends \FormyChat\Base {
		/**
		 * Actions.
		 */
		public function actions() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );

			add_filter('formychat_form_fields_cf7', [ $this, 'formychat_form_fields_cf7' ], 10, 2);
			add_filter('formychat_form_fields_gravity', [ $this, 'formychat_form_fields_gravity' ], 10, 2);
			add_filter('formychat_form_fields_wpforms', [ $this, 'formychat_form_fields_wpforms' ], 10, 2);
			add_filter('formychat_form_fields_fluentform', [ $this, 'formychat_form_fields_fluentform' ], 10, 2);
			add_filter('formychat_form_fields_forminator', [ $this, 'formychat_form_fields_forminator' ], 10, 2);
			add_filter('formychat_form_fields_formidable', [ $this, 'formychat_form_fields_formidable' ], 10, 2);
			add_filter('formychat_form_fields_ninja', [ $this, 'formychat_form_fields_ninja' ], 10, 2);
		}

		/**
		 * Register routes.
		 */
		public function register_routes() {
			$routes = apply_filters('formychat_admin_rest_routes', [
				'widgets' => [
					[
						'methods' => 'GET',
						'callback' => [ $this, 'get_widgets' ],
					],
					[
						'methods' => 'DELETE',
						'callback' => [ $this, 'delete_widgets' ],
					],
				],
				'widget' => [
					'methods' => 'POST',
					'callback' => [ $this, 'create_widget' ],
				],
				'widget/(?P<id>[\d]+)' => [
					[
						'methods' => 'GET',
						'callback' => [ $this, 'get_widget' ],
					],
					[
						'methods' => 'PUT',
						'callback' => [ $this, 'update_widget' ],
					],
				],
				// Leads.
				'leads' => [
					[
						'methods' => 'GET',
						'callback' => [ $this, 'get_leads' ],
					],
					[
						'methods' => 'DELETE',
						'callback' => [ $this, 'delete_leads' ],
					],
				],
				'contents' => [
					'methods' => 'GET',
					'callback' => [ $this, 'get_contents' ],
				],
				'action' => [
					[
						'methods' => 'GET',
						'callback' => [ $this, 'perform_action' ],
					],
				],
				'form_fields' => [
					'methods' => 'GET',
					'callback' => [ $this, 'get_form_fields' ],
				],
			]);

			if ( ! empty($routes) ) {
				foreach ( $routes as $route => $args ) {
					if ( isset($args[0]) ) {
						foreach ( $args as $arg ) {

							$arg['permission_callback'] = function () {
								return current_user_can('manage_options');
							};

							register_rest_route('formychat', $route, $arg);
						}
					} else {

						$args['permission_callback'] = function () {
							return current_user_can('manage_options');
						};

						register_rest_route('formychat', $route, $args);
					}
				}
			}
		}

		/**
		 * Get widgets.
		 *
		 * @param \WP_REST_Request $request Request object.
		 */
		public function get_widgets( $request ) {
			$widgets = Widget::get_all();

			$widgets = apply_filters( 'formychat_get_widgets', $widgets );

			return new \WP_REST_Response( $widgets );
		}

		/**
		 * Get widget.
		 *
		 * @param \WP_REST_Request $request Request object.
		 */
		public function get_widget( $request ) {
			$widget_id = $request->get_param( 'id' );

			$widget = Widget::find( $widget_id );

			if ( $widget ) {
				$widget = apply_filters( 'formychat_get_widget', $widget );

				return new \WP_REST_Response( [
					'success' => true,
					'data' => $widget,
				]);
			}

			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Widget not found.', 'social-contact-form' ),
			]);
		}

		/**
		 * Create widget.
		 *
		 * @param \WP_REST_Request $request Request object.
		 */
		public function create_widget( $request ) {

			$name = $request->get_param( 'name' ) ? $request->get_param( 'name' ) : 'Untitled';
			$is_active = $request->get_param( 'is_active' ) ? wp_validate_boolean( $request->get_param( 'is_active' ) ) : 1;
			$config = $request->get_param( 'config' ) ? $request->get_param( 'config' ) : [];

			$widget_id = Widget::create( [
				'name' => $name,
				'is_active' => $is_active,
				'config' => $config,
			] );

			if ( $widget_id ) {
				$widget = apply_filters( 'formychat_get_widget', Widget::find( $widget_id ) );

				return new \WP_REST_Response( [
					'success' => true,
					'id' => $widget_id,
					'data' => $widget,
				]);
			}

			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Widget not created.', 'social-contact-form' ),
			]);
		}

		/**
		 * Update widget.
		 *
		 * @param \WP_REST_Request $request Request object.
		 */
		public function update_widget( $request ) {
			$widget_id = $request->get_param( 'id' );

			// Bail if widget not found.
			$widget = Widget::find( $widget_id );

			if ( ! $widget ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => __( 'Widget not found.', 'social-contact-form' ),
				]);
			}

			$data = [];

			$allowed = [ 'name', 'is_active', 'config' ];

			foreach ( $allowed as $key ) {
				if ( $request->has_param( $key ) ) {
					$data[ $key ] = $request->get_param( $key );
				}
			}

			// Bail if no data.
			if ( empty( $data ) ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => __( 'No data to update.', 'social-contact-form' ),
				]);
			}

			$updated = Widget::update( $widget_id, $data );

			if ( $updated ) {
				return new \WP_REST_Response( [
					'success' => true,
					'data' => Widget::find( $widget_id ),
				]);
			}

			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Widget not updated.', 'social-contact-form' ),
			] );
		}

		/**
		 * Delete widget.
		 *
		 * @param \WP_REST_Request $request Request object.
		 */
		public function delete_widgets( $request ) {
			$id = $request->get_param( 'id' );

			if ( ! $id ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => __( 'No widget ID provided.', 'social-contact-form' ),
				]);
			}

			// If not array.
			$ids = is_array( $id ) ? $id : [ $id ];

			$deleted = Widget::delete( $ids );

			if ( $deleted ) {
				return new \WP_REST_Response( [
					'success' => true,
					'message' => __( 'Widget deleted.', 'social-contact-form' ),
				]);
			}

			return new \WP_REST_Response( [
				'success' => false,
				'message' => __( 'Widget not deleted.', 'social-contact-form' ),
			]);
		}

		/**
		 * Perform action.
		 *
		 * @param \WP_REST_Request $request Request object.
		 * @return \WP_REST_Response
		 */
		public function perform_action( $request ) {
			$action = $request->get_param( 'action' );

			if ( ! $action || ! method_exists( $this, 'action_' . $action ) ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => __( 'Action not found.', 'social-contact-form' ),
				]);
			}

			return $this->{'action_' . $action}( $request );
		}

		/**
		 * Activate plugin.
		 *
		 * @param \WP_REST_Request $request Request object.
		 * @return \WP_REST_Response
		 */
		public function action_activate_plugin( $request ) {
			$plugin = $request->has_param( 'plugin' ) ? $request->get_param( 'plugin' ) : 'cf7';

			$plugins = [
				'cf7' => [
					'file' => 'contact-form-7/wp-contact-form-7.php',
					'slug' => 'contact-form-7',
				],
				'gravity' => [
					'file' => 'gravityforms/gravityforms.php',
					'slug' => 'gravityforms',
				],
				'wpforms' => [
					'file' => 'wpforms-lite/wpforms.php',
					'slug' => 'wpforms-lite',
				],
				'fluentform' => [
					'file' => 'fluentform/fluentform.php',
					'slug' => 'fluentform',
				],
				'forminator' => [
					'file' => 'forminator/forminator.php',
					'slug' => 'forminator',
				],
				'formidable' => [
					'file' => 'formidable/formidable.php',
					'slug' => 'formidable',
				],
				'ninja' => [
					'file' => 'ninja-forms/ninja-forms.php',
					'slug' => 'ninja-forms',
				],
			];

			$plugins = apply_filters( 'formychat_form_plugins', $plugins );

			if ( ! isset( $plugins[ $plugin ] ) ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => __( 'Plugin not found.', 'social-contact-form' ),
				]);
			}

			$plugin = $plugins[ $plugin ];

			// Include plugin.php for get_plugin_data() function.
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			// Check if plugin is installed.
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin['file'] ) ) {

				// Include necessary WordPress files for installing and activating plugins.
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
				require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/misc.php';

				// Request filesystem credentials if necessary.
				$creds = request_filesystem_credentials('', '', false, false, null);

				// Check if we can use the filesystem, if not, throw an error.
				if ( ! WP_Filesystem( $creds ) ) {
					return new \WP_REST_Response( [
						'success' => false,
						'message' => __( 'Could not access filesystem.', 'social-contact-form' ),
					], 500 );
				}

				$api = plugins_api( 'plugin_information', [ 'slug' => $plugin['slug'] ] );

				if ( is_wp_error( $api ) ) {
					return new \WP_REST_Response( [
						'success' => false,
						'message' => $api->get_error_message(),
					], 500 );
				}

				try {
					$upgrader = new \Plugin_Upgrader( new \WP_Upgrader_Skin() );
					$install = $upgrader->install( $api->download_link );

					if ( is_wp_error( $install ) ) {
						return new \WP_REST_Response( [
							'success' => false,
							'message' => $install->get_error_message(),
						], 500 );
					}
				} catch ( \Exception $e ) {
					return new \WP_REST_Response( [
						'success' => false,
						'message' => $e->getMessage(),
					], 500 );
				}
			}

			// Activate plugin.
			$activated = activate_plugin( $plugin['file'] );

			if ( is_wp_error( $activated ) ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => $activated->get_error_message(),
				], 500 );
			}

			return new \WP_REST_Response( [
				'success' => true,
				'message' => wp_sprintf( '%s plugin activated.', ucfirst( $plugin['slug'] ) ),
			]);
		}

		/**
		 * Action save_country_code.
		 *
		 * @param \WP_REST_Request $request Request object.
		 * @return \WP_REST_Response
		 */
		public function action_save_country_code( $request ) {
			$code = $request->get_param( 'code' );

			if ( ! $code ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => __( 'No country code provided.', 'social-contact-form' ),
				]);
			}

			update_option( 'formychat_country_code', $code );

			return new \WP_REST_Response( [
				'success' => true,
				'message' => __( 'Country code saved.', 'social-contact-form' ),
			]);
		}

		/**
		 * Get leads.
		 *
		 * @param \WP_REST_Request $request Request object.
		 * @return \WP_REST_Response
		 */
		public function get_leads( $request ) {

			$mode = $request->has_param( 'mode' ) ? $request->get_param( 'mode' ) : 'formychat';
			$form_id = $request->has_param( 'form_id' ) ? $request->get_param( 'form_id' ) : '';

			$after = $request->has_param( 'after' ) ? $request->get_param( 'after' ) : '';
			$before = $request->has_param( 'before' ) ? $request->get_param( 'before' ) : '';

			// Before is the first moment of the day.
			if ( $after ) {
				$after = gmdate( 'Y-m-d 00:00:00', strtotime( $after ) );
			}

			// After is the last moment of the day.
			if ( $before ) {
				$before = gmdate( 'Y-m-d 23:59:59', strtotime( $before ) );
			}

			$filter = [
				'search' => $request->has_param( 'search' ) ? $request->get_param( 'search' ) : '',
				'order' => $request->has_param( 'order' ) ? $request->get_param( 'order' ) : 'DESC',
				'per_page' => $request->has_param( 'per_page' ) ? $request->get_param( 'per_page' ) : 10,
				'page' => $request->has_param( 'page' ) ? intval( $request->get_param( 'page' ) ) : 1,
				'order_by' => $request->has_param( 'order_by' ) ? $request->get_param( 'order_by' ) : 'created_at',
				'widget_id' => $request->has_param( 'widget_id' ) ? $request->get_param( 'widget_id' ) : '',
				'before' => $before,
				'after' => $after,

				'form' => $mode,
				'form_id' => $form_id,
			];

			$leads = \FormyChat\Models\Lead::get( $filter );

			// If no leads.
			if ( empty( $leads ) ) {
				return new \WP_REST_Response([]);
			}

			return new \WP_REST_Response( $leads );
		}

		/**
		 * Delete leads.
		 *
		 * @param \WP_REST_Request $request Request object.
		 * @return \WP_REST_Response
		 */
		public function delete_leads( $request ) {
			$id = $request->get_param( 'id' );

			if ( ! $id ) {
				return new \WP_REST_Response( [
					'success' => false,
					'message' => __( 'No lead ID provided.', 'social-contact-form' ),
				]);
			}

			// If not array.
			$ids = is_array( $id ) ? $id : [ $id ];

			$form = $request->has_param( 'form' ) ? $request->get_param( 'form' ) : 'formychat';

			\FormyChat\Models\Lead::delete( $ids, $form );

			return new \WP_REST_Response( [
				'success' => true,
				'message' => __( 'Leads deleted.', 'social-contact-form' ),
			]);
		}

		/**
		 * Get contents.
		 *
		 * @param \WP_REST_Request $request Request object.
		 * @return \WP_REST_Response
		 */
		public function get_contents( $request ) {
			$contents = [
				'countries' => \FormyChat\App::countries(),
				'fonts' => \FormyChat\App::fonts(),
				'pages' => $this->get_pages(),
				'widgets' => Widget::get_names(),
				'forms' => $this->get_forms(),
			];

			$contents = apply_filters( 'formychat_contents_response', $contents );

			return new \WP_REST_Response( $contents );
		}


		/**
		 * Get all pages.
		 *
		 * @return array
		 */
		public function get_pages() {
			global $wpdb;

			$pages = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'" ); // db call ok; no-cache ok.

			if ( empty( $pages ) ) {
				return [];
			}

			return $pages;
		}

		/**
		 * Get all forms.
		 *
		 * @return array
		 */
		public function get_forms() {
			$forms = [
				'cf7'   => $this->get_cf7_forms(),
				'gravity' => $this->get_gravity_forms(),
				'wpforms' => $this->get_wpforms_forms(),
				'fluentform' => $this->get_fluentform_forms(),
				'forminator' => $this->get_forminator_forms(),
				'formidable' => $this->get_formidable_forms(),
				'ninja' => $this->get_ninja_forms(),
			];

			return apply_filters( 'formychat_forms', $forms );
		}



		/**
		 * Get all CF7 forms.
		 *
		 * @return array
		 */
		public function get_cf7_forms() {
			$forms = [];
			if ( ! class_exists('WPCF7') ) {
				return $forms;
			}

			$args = [
				'post_type' => 'wpcf7_contact_form',
				'posts_per_page' => -1,
			];

			$query = new \WP_Query($args);

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$forms[] = [
						'value' => get_the_ID(),
						'name' => get_the_title(),
						'label' => get_the_title(),
					];
				}
			}

			wp_reset_postdata();

			return apply_filters( 'formychat_cf7_forms', $forms );
		}

		/**
		 * Get all Gravity Forms.
		 *
		 * @return array
		 */
		public function get_gravity_forms() {
			$forms = [];
			if ( ! class_exists('GFAPI') ) {
				return $forms;
			}

			$forms = \GFAPI::get_forms();

			if ( empty( $forms ) ) {
				return [];
			}

			$gravity_forms = [];

			foreach ( $forms as $form ) {
				$gravity_forms[] = [
					'value' => $form['id'],
					'name' => $form['title'],
					'label' => $form['title'],
				];
			}

			return apply_filters( 'formychat_gravity_forms', $gravity_forms );
		}

		/**
		 * Get all WPForms.
		 *
		 * @return array
		 */
		public function get_wpforms_forms() {

			// Bail if wpforms is not active.
			if ( ! class_exists( 'WPForms' ) ) {
				return [];
			}

			// Use wpdb to get all forms.
			global $wpdb;

			$forms = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'wpforms' AND post_status = 'publish'" ); // db call ok; no-cache ok.

			if ( empty( $forms ) ) {
				return [];
			}

			$wpforms = [];

			foreach ( $forms as $form ) {
				$wpforms[] = [
					'value' => $form->ID,
					'name' => $form->post_title,
					'label' => $form->post_title,
				];
			}

			return apply_filters( 'formychat_wpforms_forms', $wpforms );
		}

		/**
		 * Get all FluentForms.
		 *
		 * @return array
		 */
		public function get_fluentform_forms() {

			// Bail if fluentform is not active.
			if ( ! function_exists( 'fluentFormApi' ) ) {
				return [];
			}

			global $wpdb;

			$forms = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}fluentform_forms WHERE status = 'published'"); // db call ok; no-cache ok.

			if ( empty($forms) ) {
				return [];
			}

			$fluentform_forms = [];

			foreach ( $forms as $form ) {
				$fluentform_forms[] = [
					'value' => $form->id,
					'name' => $form->title,
					'label' => $form->title,
				];
			}

			return $fluentform_forms;
		}

		/**
		 * Get all forminator forms.
		 *
		 * @return array
		 */
		public function get_forminator_forms() {
			// Bail if forminator is not active.
			if ( ! class_exists( '\Forminator_API' ) ) {
				return [];
			}

			$forms = \Forminator_API::get_forms();

			if ( empty( $forms ) ) {
				return [];
			}

			$forminator_forms = [];

			foreach ( $forms as $form ) {
				$forminator_forms[] = [
					'value' => $form->id,
					'name' => $form->name,
					'label' => $form->name,
				];
			}

			return $forminator_forms;
		}


		/**
		 * Get all Formidable forms.
		 *
		 * @param \WP_REST_Request $request Request object.
		 * @return array
		 */
		public function get_formidable_forms() {
			$forms = [];

			if ( ! class_exists( 'FrmForm' ) ) {
				return [];
			}

			$forms = \FrmForm::getAll();

			if ( empty( $forms ) ) {
				return [];
			}

			$formidable_forms = [];

			foreach ( $forms as $form ) {
				$formidable_forms[] = [
					'value' => $form->id,
					'name' => $form->name,
					'label' => $form->name,
				];
			}

			return $formidable_forms;
		}

		/**
		 * Get all Ninja Forms.
		 *
		 * @return array
		 */
		public function get_ninja_forms() {
			$forms = [];

			if ( ! class_exists( 'Ninja_Forms' ) ) {
				return $forms;
			}

			global $wpdb;

			$forms = $wpdb->get_results( "SELECT id, title, form_title FROM {$wpdb->prefix}nf3_forms" ); // db call ok; no-cache ok.

			if ( empty( $forms ) ) {
				return [];
			}

			$ninja_forms = [];

			foreach ( $forms as $form ) {
				$ninja_forms[] = [
					'value' => $form->id,
					'name' => $form->form_title,
					'label' => $form->form_title,
				];
			}

			return $ninja_forms;
		}

		/**
		 * Get form fields.
		 *
		 * @param \WP_REST_Request $request Request object.
		 * @return \WP_REST_Response
		 */
		public function get_form_fields( $request ) {
			$form = $request->has_param( 'mode' ) ? $request->get_param( 'mode' ) : 'formychat';
			$form_id = $request->has_param( 'form_id' ) ? $request->get_param( 'form_id' ) : '';

			$fields = apply_filters( 'formychat_form_fields', [], $form, $form_id );

			// If formychat.
			if ( 'formychat' === $form ) {
				$fields = [
					'name' => __( 'Name', 'social-contact-form' ),
					'email' => __( 'Email', 'social-contact-form' ),
					'phone' => __( 'Phone', 'social-contact-form' ),
					'message' => __( 'Message', 'social-contact-form' ),
				];
			}

			$fields = apply_filters( 'formychat_form_fields_' . $form, $fields, $form_id );

			return new \WP_REST_Response( [
				'success' => true,
				'data' => $fields,
			] );
		}


		/**
		 * List of fields used in the form.
         *
		 * @param mixed $fields
		 * @param mixed $form_id
		 */
		public function formychat_form_fields_cf7( $fields, $form_id ) {
			// Bail if cf7 is not active.
			if ( ! class_exists( 'WPCF7' ) ) {
				return [];
			}

			// Get form by id.
			$form = \WPCF7_ContactForm::get_instance($form_id);

			// Bail if form is not found.
			if ( ! $form ) {
				return [];
			}

			$tags = $form->scan_form_tags();

			$fields = [];

			foreach ( $tags as $tag ) {
				// If name is empty, continue.
				if ( empty( $tag->name ) ) {
					continue;
				}

				$fields[ $tag->name ] = ucfirst($tag->name);
			}

			return $fields;
		}

		/**
		 * List of fields used in the form.
         *
		 * @param mixed $fields
		 * @param mixed $form_id
		 */
		public function formychat_form_fields_gravity( $fields, $form_id ) {
			// Bail if gravity is not active.
			if ( ! class_exists( 'GFAPI' ) ) {
				return [];
			}

			$form = \GFAPI::get_form($form_id);

			$fields = [];

			foreach ( $form['fields'] as $field ) {
				$fields[ $field->label ] = $field->label;
			}

			return $fields;
		}

		/**
		 * List of fields used in the form.
         *
		 * @param mixed $fields Array of form fields
		 * @param mixed $form_id Form ID
		 * @return array Modified fields array
		 */
		public function formychat_form_fields_wpforms( $fields, $form_id ) {
			// Bail if wpforms is not active.
			if ( ! class_exists( 'WPForms' ) ) {
				return [];
			}

			// Ensure we have valid input
			if ( empty($form_id) || ! is_array($fields) ) {
				return $fields;
			}

			// Get the form object
			$form = wpforms()->form->get($form_id);
			if ( empty($form) ) {
				return $fields;
			}

			// Get form data
			$form_data = wpforms_decode($form->post_content);
			if ( empty($form_data['fields']) ) {
				return $fields;
			}

			// Initialize array to store field information
			$fields = [];

			// Loop through each field in the form
			foreach ( $form_data['fields'] as $field ) {
				$field_label = isset($field['label']) ? $field['label'] : '';

				// Store field information
				$fields[ $field_label ] = $field_label;
			}

			return $fields;
		}

		/**
		 * List of fields used in the form.
         *
		 * @param mixed $fields
		 * @param mixed $form_id
		 */
		public function formychat_form_fields_fluentform( $fields, $form_id ) {
			// Bail if fluentform is not active.
			if ( ! function_exists( 'fluentFormApi' ) ) {
				return [];
			}

			$form_api = fluentFormApi('forms')->form($form_id);
            // Fields exists in the form.
            $form_fields = $form_api->fields();

			$fields = [];
            foreach ( $form_fields['fields'] as $field ) {
                if ( 'input_name' === $field['element'] ) {
                    // For name fields, get all sub-field names
                    foreach ( $field['fields'] as $name_key => $name_field ) {
                        $fields[ $name_key ] = $name_key;
                    }
                } else {
                    $fields[ $field['attributes']['name'] ] = $field['attributes']['name'];
                }
            }

			return $fields;
		}

		/**
		 * List of fields used in the form.
         *
		 * @param mixed $fields
		 * @param mixed $form_id
		 */
		public function formychat_form_fields_forminator( $fields, $form_id ) {
			// Bail if forminator is not active.
			if ( ! class_exists( '\Forminator_API' ) ) {
				return [];
			}

			$form = \Forminator_API::get_form($form_id);

			$fields = [];

			foreach ( $form->fields as $field ) {
				$fields[ $field->raw['element_id'] ] = $field->raw['field_label'];
			}

			return $fields;
		}

		/**
		 * Get all fields from a specific Formidable form.
		 *
		 * @param array  $fields  Initial fields array.
		 * @param string $form_id Form ID to get fields from.
		 * @return array Array of form fields with field ID as key and field name as value.
		 */
		public function formychat_form_fields_formidable( $fields, $form_id ) {
			// Initialize empty array for storing fields
			$forms = array();

			// Check if Formidable Forms is active
			if ( ! class_exists( 'FrmField' ) ) {
				return $forms;
			}

			// Get all fields for the specified form using FrmField::get_all_for_form()
			$form_fields = \FrmField::get_all_for_form( $form_id );

			// Loop through each field and add to the array
			foreach ( $form_fields as $field ) {

				// If button field, continue.
				if ( 'submit' === $field->type ) {
					continue;
				}

				$fields[ $field->name ] = $field->name;
			}

			return $fields;
		}

		/**
		 * List of fields used in the form.
         *
		 * @param mixed $fields
		 * @param mixed $form_id
		 */
		public function formychat_form_fields_ninja( $fields, $form_id ) {
			// Bail if ninja is not active.
			if ( ! class_exists( 'Ninja_Forms' ) ) {
				return [];
			}

			global $wpdb;

			$form = $wpdb->get_results( $wpdb->prepare( "SELECT `key`, `label` FROM {$wpdb->prefix}nf3_fields WHERE parent_id = %d AND type != %s", $form_id, 'submit' ) ); // db call ok; no-cache ok.

			if ( ! $form ) {
				return [];
			}

			$fields = [];

			foreach ( $form as $field ) {
				$fields[ $field->key ] = $field->label;
			}

			return $fields;
		}
	}

	// Initialize the plugin.
	Rest::init();
}
