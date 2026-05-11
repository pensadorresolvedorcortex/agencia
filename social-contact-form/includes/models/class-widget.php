<?php
/**
 * Widget model.
 *
 * @package FormyChat
 * @since 1.0.0
 */

namespace FormyChat\Models;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( __NAMESPACE__ . '\\Widget' ) ) {
	/**
	 * Widget model.
	 */
	class Widget {

		/**
		 * Get all widgets.
		 *
		 * @return array
		 */
		public static function get_all() {
			global $wpdb;

			$widgets = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}scf_widgets WHERE `deleted_at` IS NULL ORDER BY id DESC" );
			if ( ! is_array( $widgets ) || empty( $widgets ) ) {
				return [];
			}

			foreach ( $widgets as &$widget ) {
				$widget = self::wrap( $widget );
			}
			unset( $widget );

			return $widgets;
		}

		/**
		 * Active widgets.
		 *
		 * @return array
		 */
		public static function get_active_widgets() {
			global $wpdb;
			$active_widgets = $wpdb->get_results( "SELECT `id`, `name`, `config` FROM {$wpdb->prefix}scf_widgets WHERE `is_active` IS TRUE AND `deleted_at` IS NULL" );

			if ( ! is_array( $active_widgets ) || empty( $active_widgets ) ) {
				return [];
			}

			$widgets = [];
			foreach ( $active_widgets as $widget ) {
				$widgets[] = [
					'id'     => (int) $widget->id,
					'name'   => (string) $widget->name,
					'config' => json_decode( (string) $widget->config ),
				];
			}

			return $widgets;
		}

		/**
		 * Get widget names.
		 *
		 * @return array
		 */
		public static function get_names() {
			global $wpdb;
			$widgets = $wpdb->get_results( "SELECT `id`, `name` FROM {$wpdb->prefix}scf_widgets WHERE `deleted_at` IS NULL ORDER BY id DESC" );
			return is_array( $widgets ) ? $widgets : [];
		}

		/**
		 * Find widget by ID.
		 *
		 * @param int $id Widget ID.
		 * @return object|null
		 */
		public static function find( $id ) {
			$id = absint( $id );
			if ( $id < 1 ) {
				return null;
			}

			global $wpdb;
			$widget = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}scf_widgets WHERE id = %d", $id ) );

			if ( ! $widget ) {
				return null;
			}

			return self::wrap( $widget );
		}

		/**
		 * Normalize widget object.
		 *
		 * @param object $object Raw object.
		 * @return object
		 */
		public static function wrap( $object ) {
			$object->id        = (int) $object->id;
			$object->is_active = wp_validate_boolean( $object->is_active );
			$object->config    = empty( $object->config ) ? [] : json_decode( (string) $object->config, true );
			return $object;
		}

		/**
		 * Get widget count.
		 *
		 * @return int
		 */
		public static function total() {
			global $wpdb;
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}scf_widgets WHERE `deleted_at` IS NULL" );
		}

		/**
		 * Create widget.
		 *
		 * @param array $data Widget payload.
		 * @return int
		 */
		public static function create( $data ) {
			global $wpdb;

			$data = wp_parse_args(
				is_array( $data ) ? $data : [],
				[
					'name'      => 'Untitled',
					'is_active' => 1,
					'config'    => [],
				]
			);

			$payload = [
				'name'       => sanitize_text_field( (string) $data['name'] ),
				'is_active'  => wp_validate_boolean( $data['is_active'] ) ? 1 : 0,
				'config'     => wp_json_encode( is_array( $data['config'] ) ? $data['config'] : [] ),
				'updated_at' => current_time( 'mysql' ),
			];

			$wpdb->insert( $wpdb->prefix . 'scf_widgets', $payload );
			return (int) $wpdb->insert_id;
		}

		/**
		 * Update widget.
		 *
		 * @param int   $id Widget ID.
		 * @param array $data Widget payload.
		 * @return int
		 */
		public static function update( $id, $data ) {
			global $wpdb;

			$id = absint( $id );
			if ( $id < 1 || ! is_array( $data ) ) {
				return 0;
			}

			$payload = [ 'updated_at' => current_time( 'mysql' ) ];

			if ( array_key_exists( 'name', $data ) ) {
				$payload['name'] = sanitize_text_field( (string) $data['name'] );
			}

			if ( array_key_exists( 'is_active', $data ) ) {
				$payload['is_active'] = wp_validate_boolean( $data['is_active'] ) ? 1 : 0;
			}

			if ( array_key_exists( 'config', $data ) ) {
				$payload['config'] = wp_json_encode( is_array( $data['config'] ) ? $data['config'] : [] );
			}

			$wpdb->update( $wpdb->prefix . 'scf_widgets', $payload, [ 'id' => $id ] );
			return (int) $wpdb->rows_affected;
		}

		/**
		 * Soft delete widgets.
		 *
		 * @param array $ids Widget IDs.
		 * @return int
		 */
		public static function delete( $ids ) {
			global $wpdb;

			$ids = array_values( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : [] ) ) );
			if ( [] === $ids ) {
				return 0;
			}

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}scf_widgets SET `deleted_at` = %s WHERE id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					array_merge( [ current_time( 'mysql' ) ], $ids )
				)
			);

			return (int) $wpdb->rows_affected;
		}
	}
}
