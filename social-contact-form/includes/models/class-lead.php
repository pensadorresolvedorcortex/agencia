<?php
/**
 * Lead model.
 *
 * @package FormyChat
 * @since 1.0.0
 */

namespace FormyChat\Models;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( __NAMESPACE__ . '\\Lead' ) ) {
	/**
	 * Lead model.
	 */
	class Lead {

		/**
		 * Create lead row.
		 *
		 * @param array $data Lead payload.
		 * @return int
		 */
		public static function create( $data ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'scf_leads';

			$data = wp_parse_args(
				is_array( $data ) ? $data : [],
				[
					'widget_id' => 1,
					'field'     => [],
					'meta'      => [],
					'note'      => '',
					'form'      => 'formychat',
					'form_id'   => 0,
				]
			);

			$data['widget_id']  = absint( $data['widget_id'] );
			$data['form_id']    = absint( $data['form_id'] );
			$data['form']       = sanitize_key( (string) $data['form'] );
			$data['note']       = sanitize_textarea_field( (string) $data['note'] );
			$data['created_at'] = current_time( 'mysql' );
			$data['field']      = wp_json_encode( is_array( $data['field'] ) ? $data['field'] : [], JSON_UNESCAPED_UNICODE ) ?: '{}';
			$data['meta']       = wp_json_encode( is_array( $data['meta'] ) ? $data['meta'] : [], JSON_UNESCAPED_UNICODE ) ?: '{}';

			$result = $wpdb->insert(
				$table_name,
				$data,
				[
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
				]
			);

			if ( false === $result ) {
				return 0;
			}

			return (int) $wpdb->insert_id;
		}

		/**
		 * Get leads list.
		 *
		 * @param array $filter Query filter.
		 * @return array
		 */
		public static function get( $filter = [] ) {
			$filter = wp_parse_args(
				is_array( $filter ) ? $filter : [],
				[
					'search'    => '',
					'before'    => '',
					'after'     => '',
					'per_page'  => 20,
					'page'      => 1,
					'order'     => 'DESC',
					'orderby'   => 'created_at',
					'widget_id' => 1,
					'form'      => 'formychat',
					'form_id'   => 0,
				]
			);

			global $wpdb;
			$where = [];

			$search = sanitize_text_field( (string) $filter['search'] );
			if ( '' !== $search ) {
				$where[] = $wpdb->prepare(
					'(field LIKE %s OR meta LIKE %s OR note LIKE %s OR widget_id = %d)',
					'%' . $wpdb->esc_like( $search ) . '%',
					'%' . $wpdb->esc_like( $search ) . '%',
					'%' . $wpdb->esc_like( $search ) . '%',
					absint( $search )
				);
			}

			$after = sanitize_text_field( (string) $filter['after'] );
			if ( '' !== $after ) {
				$where[] = $wpdb->prepare( 'created_at >= %s', $after );
			}

			$before = sanitize_text_field( (string) $filter['before'] );
			if ( '' !== $before ) {
				$where[] = $wpdb->prepare( 'created_at <= %s', $before );
			}

			if ( is_numeric( $filter['widget_id'] ) && absint( $filter['widget_id'] ) > 0 ) {
				$where[] = $wpdb->prepare( 'widget_id = %d', absint( $filter['widget_id'] ) );
			}

			$form = sanitize_key( (string) $filter['form'] );
			if ( '' !== $form ) {
				if ( 'formychat' === $form ) {
					$where[] = $wpdb->prepare( '(form = %s OR form IS NULL)', $form );
				} else {
					$where[] = $wpdb->prepare( 'form = %s', $form );
				}
			}

			if ( is_numeric( $filter['form_id'] ) && absint( $filter['form_id'] ) > 0 ) {
				$where[] = $wpdb->prepare( 'form_id = %d', absint( $filter['form_id'] ) );
			}

			$where[] = 'deleted_at IS NULL';
			$where_sql = implode( ' AND ', $where );

			$allowed_order_by = [ 'id', 'created_at', 'widget_id', 'form', 'form_id' ];
			$order_by         = in_array( $filter['orderby'], $allowed_order_by, true ) ? $filter['orderby'] : 'created_at';
			$order            = ( 'ASC' === strtoupper( (string) $filter['order'] ) ) ? 'ASC' : 'DESC';

			$per_page_raw = $filter['per_page'];
			$is_all       = ( is_string( $per_page_raw ) && 'all' === strtolower( $per_page_raw ) );
			$per_page     = $is_all ? 99999999 : max( 1, absint( $per_page_raw ) );
			$page         = max( 1, absint( $filter['page'] ) );
			$offset       = ( $page - 1 ) * $per_page;

			$leads = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}scf_leads WHERE {$where_sql} ORDER BY {$order_by} {$order} LIMIT %d, %d",
					$offset,
					$per_page
				)
			);

			if ( ! is_array( $leads ) ) {
				return [];
			}

			foreach ( $leads as $lead ) {
				$lead->id        = (int) $lead->id;
				$lead->widget_id = empty( $lead->widget_id ) ? 1 : (int) $lead->widget_id;
				$lead->field     = empty( $lead->field ) ? [] : json_decode( (string) $lead->field );
				$lead->meta      = empty( $lead->meta ) ? [] : json_decode( (string) $lead->meta );
				$lead->note      = empty( $lead->note ) ? '' : (string) $lead->note;
				$lead->form      = empty( $lead->form ) ? 'formychat' : (string) $lead->form;
				$lead->form_id   = empty( $lead->form_id ) ? 0 : (int) $lead->form_id;
			}

			return $leads;
		}

		/**
		 * Soft delete leads.
		 *
		 * @param array $ids Lead IDs.
		 * @param string $form Form key.
		 * @return int
		 */
		public static function delete( $ids, $form = 'formychat' ) {
			global $wpdb;

			$ids = array_values( array_filter( array_map( 'absint', is_array( $ids ) ? $ids : [] ) ) );
			if ( [] === $ids ) {
				return 0;
			}

			$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$form         = sanitize_key( (string) $form );

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}scf_leads SET deleted_at = %s WHERE form = %s AND id IN ({$placeholders})",
					array_merge( [ current_time( 'mysql' ), $form ], $ids )
				)
			);

			return (int) $wpdb->rows_affected;
		}

		/**
		 * Total lead count.
		 *
		 * @return int
		 */
		public static function total() {
			global $wpdb;
			return (int) $wpdb->get_var( "SELECT count(*) FROM {$wpdb->prefix}scf_leads WHERE deleted_at IS NULL" );
		}

		/**
		 * Lead count by form.
		 *
		 * @param string $form Form key.
		 * @return int
		 */
		public static function total_from( $form ) {
			global $wpdb;
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM {$wpdb->prefix}scf_leads WHERE form = %s AND deleted_at IS NULL", sanitize_key( (string) $form ) ) );
		}
	}
}
