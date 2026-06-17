<?php
/**
 * Boot file.
 * Loads all the required files.
 *
 * @package FormyChat
 * @since 1.0.0
 */

// Namespace.
namespace FormyChat;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( __NAMESPACE__ . '\Boot' ) ) {

	class Boot {
		/**
		 * Constructor.
		 */
		public function run() {
			$this->define_constants();
			$this->includes();
		}

		/**
		 * Define constants.
		 */
		private function define_constants() {
			// Other constants.
			define( 'FORMYCHAT_INCLUDES', plugin_dir_path( FORMYCHAT_FILE ) . '/includes' );
			define( 'FORMYCHAT_PUBLIC', plugin_dir_url( FORMYCHAT_FILE ) . 'public' );
		}

		/**
		 * Include files.
		 */
		private function includes() {
			$this->include_libs();
			$this->include_common_file();
			$this->include_admin_files();
			$this->include_public_files();
		}

		/**
		 * Include libraries.
		 */
		private function include_libs() {
			// Require files.
			if ( file_exists( FORMYCHAT_INCLUDES . '/wppool/class-plugin.php' ) ) {
				require_once FORMYCHAT_INCLUDES . '/wppool/class-plugin.php';
			}
		}

		/**
		 * Include common files.
		 */
		private function include_common_file() {

			// Load deprecated class.
			require_once FORMYCHAT_INCLUDES . '/others/class-admin.php';

			// Base.
			require_once FORMYCHAT_INCLUDES . '/core/class-base.php';
			require_once FORMYCHAT_INCLUDES . '/core/class-app.php';

			// Models.
			require_once FORMYCHAT_INCLUDES . '/core/class-database.php';

			require_once FORMYCHAT_INCLUDES . '/models/class-widget.php';
			require_once FORMYCHAT_INCLUDES . '/models/class-lead.php';
			// Rest.
			require_once FORMYCHAT_INCLUDES . '/admin/class-admin-rest.php';
			// Rest.
			require_once FORMYCHAT_INCLUDES . '/compatibility/class-compatibility.php';

			// Load deprecated class.
			require_once FORMYCHAT_INCLUDES . '/others/functions.php';
		}

		/**
		 * Include admin files.
		 */
		private function include_admin_files() {
			// Bail if not in admin.
			if ( ! is_admin() ) {
				return;
			}

			require_once FORMYCHAT_INCLUDES . '/admin/class-admin-assets.php';

			require_once FORMYCHAT_INCLUDES . '/admin/class-admin-hooks.php';

			// Contact Form 7.
			require_once FORMYCHAT_INCLUDES . '/forms/contact-form/class-cf7-admin.php';

			// WPForms.
			require_once FORMYCHAT_INCLUDES . '/forms/wpforms/class-wpforms-admin.php';

			// Gravity Forms.
			require_once FORMYCHAT_INCLUDES . '/forms/gravity-forms/class-gf-admin.php';

			// FluentForm.
			require_once FORMYCHAT_INCLUDES . '/forms/fluentform/class-fluentform-admin.php';

			// Formidable.
			require_once FORMYCHAT_INCLUDES . '/forms/formidable/class-formidable-admin.php';

			// Ninja.
			if ( class_exists( '\NF_Abstracts_Action' ) ) {
				require_once FORMYCHAT_INCLUDES . '/forms/ninjaforms/class-ninjaforms-admin.php';
			}
		}

		/**
		 * Include public files.
		 */
		private function include_public_files() {
			require_once FORMYCHAT_INCLUDES . '/public/class-assets.php';
			require_once FORMYCHAT_INCLUDES . '/public/class-widget-form.php';
			require_once FORMYCHAT_INCLUDES . '/public/class-rest.php';

			// Contact Form 7.
			require_once FORMYCHAT_INCLUDES . '/forms/contact-form/class-cf7-frontend.php';

			// WPForms.
			require_once FORMYCHAT_INCLUDES . '/forms/wpforms/class-wpforms-frontend.php';

			// Gravity Forms.
			require_once FORMYCHAT_INCLUDES . '/forms/gravity-forms/class-gf-frontend.php';

			// FluentForm.
			require_once FORMYCHAT_INCLUDES . '/forms/fluentform/class-fluentform-frontend.php';

			// Formidable.
			require_once FORMYCHAT_INCLUDES . '/forms/formidable/class-formidable-frontend.php';
		}
	}

	// Go go go.
	$formychat = new Boot();
	$formychat->run();

}
