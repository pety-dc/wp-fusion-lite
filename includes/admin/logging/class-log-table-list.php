<?php

/**
 * WP Fusion Log Table List
 *
 * @author   WooThemes
 * @category Admin
 * @package  WP Fusion/Admin
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPF_Log_Table_List extends WP_List_Table {

	/**
	 * Initialize the log table list.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'wpf-log',
			'plural'   => 'wpf-logs',
			'ajax'     => false,
		) );
	}

	/**
	 * Display level dropdown
	 *
	 * @global wpdb $wpdb
	 */
	public function level_dropdown() {

		$levels = array(
			array( 'value' => 'error',     'label' => __( 'Error',     'wp-fusion' ) ),
			array( 'value' => 'warning',   'label' => __( 'Warning',   'wp-fusion' ) ),
			array( 'value' => 'notice',    'label' => __( 'Notice',    'wp-fusion' ) ),
			array( 'value' => 'info',      'label' => __( 'Info',      'wp-fusion' ) )
		);

		$selected_level = isset( $_REQUEST['level'] ) ? esc_attr( $_REQUEST['level'] ) : '';
		?>
			<label for="filter-by-level" class="screen-reader-text"><?php _e( 'Filter by level', 'wp-fusion' ); ?></label>
			<select name="level" id="filter-by-level">
				<option<?php selected( $selected_level, '' ); ?> value=""><?php _e( 'All levels', 'wp-fusion' ); ?></option>
				<?php foreach ( $levels as $l ) {
					printf( '<option%1$s value="%2$s">%3$s</option>',
						selected( $selected_level, $l['value'], false ),
						esc_attr( $l['value'] ),
						esc_html( $l['label'] )
					);
				} ?>
			</select>
		<?php
	}

	/**
	 * Get list columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'        => '<input type="checkbox" />',
			'timestamp' => __( 'Timestamp', 'wp-fusion' ),
			'level'     => __( 'Level',     'wp-fusion' ),
			'user'     	=> __( 'User',     'wp-fusion' ),
			'message'   => __( 'Message',   'wp-fusion' ),
			'source'    => __( 'Source',    'wp-fusion' ),
		);
	}

	/**
	 * Column cb.
	 *
	 * @param  array $log
	 * @return string
	 */
	public function column_cb( $log ) {
		return sprintf( '<input type="checkbox" name="log[]" value="%1$s" />', esc_attr( $log['log_id'] ) );
	}

	/**
	 * Timestamp column.
	 *
	 * @param  array $log
	 * @return string
	 */
	public function column_timestamp( $log ) {
		return esc_html( mysql2date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$log['timestamp']
		) );
	}

	/**
	 * Level column.
	 *
	 * @param  array $log
	 * @return string
	 */
	public function column_level( $log ) { 
		$level_key = wp_fusion()->logger->get_severity_level( $log['level'] );
		$levels    = array(
			'error'     => __( 'Error',     'wp-fusion' ),
			'warning'   => __( 'Warning',   'wp-fusion' ),
			'notice'    => __( 'Notice',    'wp-fusion' ),
			'info'      => __( 'Info',      'wp-fusion' )
		);

		if ( isset( $levels[ $level_key ] ) ) {
			$level = $levels[ $level_key ];
			$level_class = sanitize_html_class( 'log-level--' . $level_key );
			return '<span class="log-level ' . $level_class . '">' . esc_html( $level ) . '</span>';
		} else {
			return '';
		}
	}

	/**
	 * User column
	 *
	 * @param  array $log
	 * @return string
	 */
	public function column_user( $log ) {

		if( empty( $log['user'] ) || $log['user'] < 1 ) {
			return 'system';
		} elseif( $log['user'] >= 100000000 ) {
			return 'auto-login-link';
		}

		$userdata = get_userdata( $log['user'] );

		// If user deleted
		if( $userdata == false ) {
			return '(deleted user ' . $log['user'] . ')';
		}

		return '<a href="' . get_edit_user_link( $log['user'] ) . '" target="_blank">' . $userdata->data->user_login . '</a>';
	}

	/**
	 * Message column.
	 *
	 * @param  array $log
	 * @return string
	 */
	public function column_message( $log ) {

		$output = $log['message'];

		if( !empty( $log['context'] ) ) {

			$context = maybe_unserialize( $log['context'] );

			if( empty( $context['meta_array'] ) && ! empty( $context['meta_array_nofilter'] ) ) {
				$context['meta_array'] = $context['meta_array_nofilter'];
			}

			if( ! empty( $context['meta_array'] ) ) {
				
				$output .= '<br /><ul class="log-table-meta-fields">';

				foreach($context['meta_array'] as $key => $value) {
					$output .= '<li><strong>' . $key . '</strong>: ';

					if( is_array( $value ) || is_object( $value ) ) {
						$output .= '<pre>' . print_r( $value, true ) . '</pre>';
					} else {
						$output .= $value;
					}

					$output .= '</li>';
				}

				$output .= '</ul>';

			}

			if( ! empty( $context['tag_array'] ) ) {
				
				$output .= '<br /><ul class="log-table-tag-list">';

				foreach($context['tag_array'] as $tag_id) {
					$output .= '<li>' . wp_fusion()->user->get_tag_label( $tag_id ) . '</li>';
				}

				$output .= '</ul>';

			}

			if( ! empty( $context['args'] ) ) {

				$output .= '<pre>' . print_r($context['args'], true ) . '</pre>';

			}

		}

		return $output;
	}

	/**
	 * Source column.
	 *
	 * @param  array $log
	 * @return string
	 */
	public function column_source( $log ) {

		$log['source'] = maybe_unserialize( $log['source'] );

		if ( is_array( $log['source'] ) ) {
			$log['source'] = implode( ' &raquo; ', $log['source'] );
		}

		return $log['source'];
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'wp-fusion' ),
		);
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @param string $which
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' === $which ) {
			echo '<div class="alignleft actions">';
				$this->level_dropdown();
				$this->source_dropdown();
				$this->user_dropdown();
				submit_button( __( 'Filter', 'wp-fusion' ), '', 'filter-action', false );
			echo '</div>';
		}
	}

	/**
	 * Get a list of sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'timestamp' => array( 'timestamp', true ),
			'level'     => array( 'level',     true ),
			'user'    	=> array( 'user',    true ),
			'source'    => array( 'source',    true ),
		);
	}

	/**
	 * Display source dropdown
	 *
	 * @global wpdb $wpdb
	 */
	protected function source_dropdown() {
		global $wpdb;

		$sources_db = $wpdb->get_col( "
			SELECT DISTINCT source
			FROM {$wpdb->prefix}wpf_logging
			WHERE source != ''
			ORDER BY source ASC
		" );

		if ( ! empty( $sources_db ) ) {

			$sources = array();

			foreach ( $sources_db as $source )  {

				$source = maybe_unserialize( $source );

				if ( is_array( $source ) ) {
					$sources = array_merge( $sources, $source );
				} else {
					$sources[] = $source;
				}

			}

			$sources = array_unique( $sources );

			$selected_source = isset( $_REQUEST['source'] ) ? esc_attr( $_REQUEST['source'] ) : '';
			?>
				<label for="filter-by-source" class="screen-reader-text"><?php _e( 'Filter by source', 'wp-fusion' ); ?></label>
				<select name="source" id="filter-by-source">
					<option<?php selected( $selected_source, '' ); ?> value=""><?php _e( 'All sources', 'wp-fusion' ); ?></option>
					<?php foreach ( $sources as $s ) {
						printf( '<option%1$s value="%2$s">%3$s</option>',
							selected( $selected_source, $s, false ),
							esc_attr( $s ),
							esc_html( $s )
						);
					} ?>
				</select>
			<?php
		}
	}

	/**
	 * Display user dropdown
	 *
	 * @global wpdb $wpdb
	 */
	protected function user_dropdown() {
		global $wpdb;

		$users = $wpdb->get_col( "
			SELECT DISTINCT user
			FROM {$wpdb->prefix}wpf_logging
			WHERE user != ''
			ORDER BY user ASC
		" );

		if ( ! empty( $users ) ) {

			$selected_user = isset( $_REQUEST['user'] ) ? intval( $_REQUEST['user'] ) : '';
			$users_list = array();

			foreach ( $users as $u ) {
				$userdata = get_userdata( $u );
				if( is_object( $userdata ) ) {
					$users_list[$u] = esc_html( $userdata->data->user_login );
				}
			}

			natcasesort( $users_list );

			?>
				<label for="filter-by-user" class="screen-reader-text"><?php _e( 'Filter by user', 'wp-fusion' ); ?></label>
				<select name="user" id="filter-by-user">

					<option<?php selected( $selected_user, '' ); ?> value=""><?php _e( 'All users', 'wp-fusion' ); ?></option>

					<?php foreach ( $users_list as $user_id => $user_login ) {

						printf( '<option%1$s value="%2$s">%3$s</option>',
							selected( $selected_user, $user_id, false ),
							esc_attr( $user_id ),
							esc_html( $user_login )
						);
					} ?>
				</select>
			<?php
		}
	}

	/**
	 * Prepare table list items.
	 *
	 * @global wpdb $wpdb
	 */
	public function prepare_items() {
		global $wpdb;

		$this->prepare_column_headers();

		$per_page = $this->get_items_per_page( 'wpf_status_log_items_per_page', 20 );

		$where  = $this->get_items_query_where();
		$order  = $this->get_items_query_order();
		$limit  = $this->get_items_query_limit();
		$offset = $this->get_items_query_offset();

		$query_items = "
			SELECT log_id, timestamp, level, user, message, source, context
			FROM {$wpdb->prefix}wpf_logging
			{$where} {$order} {$limit} {$offset}
		";

		$this->items = $wpdb->get_results( $query_items, ARRAY_A );

		$query_count = "SELECT COUNT(log_id) FROM {$wpdb->prefix}wpf_logging {$where}";
		$total_items = $wpdb->get_var( $query_count );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}

	/**
	 * Get prepared LIMIT clause for items query
	 *
	 * @global wpdb $wpdb
	 *
	 * @return string Prepared LIMIT clause for items query.
	 */
	protected function get_items_query_limit() {
		global $wpdb;

		$per_page = $this->get_items_per_page( 'wpf_status_log_items_per_page', 20 );
		return $wpdb->prepare( 'LIMIT %d', $per_page );
	}

	/**
	 * Get prepared OFFSET clause for items query
	 *
	 * @global wpdb $wpdb
	 *
	 * @return string Prepared OFFSET clause for items query.
	 */
	protected function get_items_query_offset() {
		global $wpdb;

		$per_page = $this->get_items_per_page( 'wpf_status_log_items_per_page', 20 );
		$current_page = $this->get_pagenum();
		if ( 1 < $current_page ) {
			$offset = $per_page * ( $current_page - 1 );
		} else {
			$offset = 0;
		}

		return $wpdb->prepare( 'OFFSET %d', $offset );
	}

	/**
	 * Get prepared ORDER BY clause for items query
	 *
	 * @return string Prepared ORDER BY clause for items query.
	 */
	protected function get_items_query_order() {
		$valid_orders = array( 'level', 'source', 'timestamp', 'user' );
		if ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], $valid_orders ) ) {
			//$by = wc_clean( $_REQUEST['orderby'] );
			$by = esc_attr( $_REQUEST['orderby'] );
		} else {
			$by = 'timestamp';
		}
		$by = esc_sql( $by );

		if ( ! empty( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ) {
			$order = 'ASC';
		} else {
			$order = 'DESC';
		}

		return "ORDER BY {$by} {$order}, log_id {$order}";
	}

	/**
	 * Get prepared WHERE clause for items query
	 *
	 * @global wpdb $wpdb
	 *
	 * @return string Prepared WHERE clause for items query.
	 */
	protected function get_items_query_where() {
		global $wpdb;

		$where_conditions = array();
		$where_values     = array();
		if ( ! empty( $_REQUEST['level'] ) && wp_fusion()->logger->is_valid_level( $_REQUEST['level'] ) ) {
			$where_conditions[] = 'level >= %d';
			$where_values[]     = wp_fusion()->logger->get_level_severity( $_REQUEST['level'] );
		}
		if ( ! empty( $_REQUEST['source'] ) ) {
			$where_conditions[] = 'source LIKE %s';
			$where_values[]     = '%' . $wpdb->esc_like( $_REQUEST['source'] ) . '%';
		}

		if ( ! empty( $_REQUEST['user'] ) ) {
			$where_conditions[] = 'user = %s';
			$where_values[]     = esc_attr( $_REQUEST['user'] );
		}

		if ( ! empty( $where_conditions ) ) {
			return $wpdb->prepare( 'WHERE 1 = 1 AND ' . implode( ' AND ', $where_conditions ), $where_values );
		} else {
			return '';
		}
	}

	/**
	 * Set _column_headers property for table list
	 */
	protected function prepare_column_headers() {
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}
}
