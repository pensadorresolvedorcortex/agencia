<?php
/**
 * Base abstract class for FormyChat
 *
 * @package FormyChat
 */

// FormyChat namespace.
namespace FormyChat;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit( 1 );

if ( ! class_exists( __NAMESPACE__ . 'Base' ) ) {

	/**
	 * Base abstract class for FormyChat
	 *
	 * @package FormyChat
	 * @since 1.0.0
	 */
	abstract class Base {

		/**
		 * Contains the instances of the child classes.
		 *
		 * @since 1.0.0
		 * @var array<object>
		 */
		private static $instances = array();

		/**
		 * Returns the instance of the child class.
		 *
		 * @since 1.0.0
		 * @return object
		 */
		public static function get_instance() {
			$class_name = get_called_class();

			if ( ! isset( self::$instances[ $class_name ] ) ) {
				self::$instances[ $class_name ] = new $class_name();
			}

			return self::$instances[ $class_name ];
		}

		/**
		 * Initializes the child class.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public static function init() {
			$instance = static::get_instance();

			$instance->actions();
			$instance->filters();
		}
		/**
		 * Executes the actions hooks for the child class.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function actions() {}

		/**
		 * Executes the filter hooks for the child class.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function filters() {}

		/**
		 * Is ultimate license active.
		 *
		 * @since 1.0.0
		 * @return bool
		 */
		public function is_ultimate_active() {
			return apply_filters( 'is_scf_ultimate', false );
		}
	}
}
