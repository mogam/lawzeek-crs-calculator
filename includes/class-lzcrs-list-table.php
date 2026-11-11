<?php
/**
 * The leads table on the CRS Leads screen.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Leads list with band filter, search, sorting, pagination and bulk delete.
 */
class Lzcrs_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'lead',
				'plural'   => 'leads',
				'ajax'     => false,
			)
		);
	}

	private function filters() {
		// phpcs:disable WordPress.Security.NonceVerification -- read-only list filters.
		return array(
			'band'   => isset( $_GET['band'] ) ? sanitize_key( wp_unslash( $_GET['band'] ) ) : '',
			'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);
		// phpcs:enable
	}

	public function get_columns() {
		return array(
			'cb'      => '<input type="checkbox" />',
			'name'    => __( 'Name', 'lawzeek-crs-calculator' ),
			'email'   => __( 'Email', 'lawzeek-crs-calculator' ),
			'phone'   => __( 'Phone', 'lawzeek-crs-calculator' ),
			'total'   => __( 'CRS', 'lawzeek-crs-calculator' ),
			'band'    => __( 'Lead', 'lawzeek-crs-calculator' ),
			'pathway' => __( 'Likely pathway', 'lawzeek-crs-calculator' ),
			'status'  => __( 'Status', 'lawzeek-crs-calculator' ),
			'created' => __( 'Received', 'lawzeek-crs-calculator' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'total'   => array( 'total', true ),
			'created' => array( 'created_at', true ),
		);
	}

	protected function get_bulk_actions() {
		return array( 'lzcrs_delete' => __( 'Delete', 'lawzeek-crs-calculator' ) );
	}

	protected function get_views() {
		$current = $this->filters()['band'];
		$base    = admin_url( 'admin.php?page=lzcrs-leads' );
		$views   = array();
		$all     = array( '' => __( 'All', 'lawzeek-crs-calculator' ) );
		foreach ( $all + array_combine( Lzcrs_Db::BANDS, array_map( array( 'Lzcrs_Labels', 'band' ), Lzcrs_Db::BANDS ) ) as $band => $label ) {
			$count          = Lzcrs_Db::count( array( 'band' => $band ) );
			$url            = $band ? add_query_arg( 'band', $band, $base ) : $base;
			$views[ $band ? $band : 'all' ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				$current === $band ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				$count
			);
		}
		return $views;
	}

	public function prepare_items() {
		$per_page = 20;
		$f        = $this->filters();
		// phpcs:disable WordPress.Security.NonceVerification -- read-only sorting.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
		// phpcs:enable
		$total       = Lzcrs_Db::count( $f );
		$this->items = Lzcrs_Db::query( $f, $orderby, $order, $per_page, ( $this->get_pagenum() - 1 ) * $per_page );
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="leads[]" value="%d" />', (int) $item['id'] );
	}

	protected function column_name( $item ) {
		$view    = admin_url( 'admin.php?page=lzcrs-leads&lead=' . (int) $item['id'] );
		$delete  = wp_nonce_url( admin_url( 'admin-post.php?action=lzcrs_delete&leads[]=' . (int) $item['id'] ), 'lzcrs_delete' );
		$actions = array(
			'view'   => '<a href="' . esc_url( $view ) . '">' . esc_html__( 'View', 'lawzeek-crs-calculator' ) . '</a>',
			'delete' => '<a href="' . esc_url( $delete ) . '" class="submitdelete" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Delete this lead permanently?', 'lawzeek-crs-calculator' ) ) ) . ')">' . esc_html__( 'Delete', 'lawzeek-crs-calculator' ) . '</a>',
		);
		return '<strong><a class="row-title" href="' . esc_url( $view ) . '">' . esc_html( $item['name'] ) . '</a></strong>' . $this->row_actions( $actions );
	}

	protected function column_default( $item, $col ) {
		switch ( $col ) {
			case 'email':
				return '<a href="' . esc_url( 'mailto:' . $item['email'] ) . '">' . esc_html( $item['email'] ) . '</a>';
			case 'phone':
				return $item['phone'] ? esc_html( $item['phone'] ) : '&mdash;';
			case 'total':
				return '<strong>' . esc_html( $item['total'] ) . '</strong>';
			case 'band':
				return '<span class="lzcrs-band ' . esc_attr( $item['band'] ) . '">' . esc_html( Lzcrs_Labels::band( $item['band'] ) ) . '</span>';
			case 'pathway':
				return esc_html( Lzcrs_Labels::pathway( $item['pathway'] ) );
			case 'status':
				$s = Lzcrs_Labels::statuses();
				return esc_html( isset( $s[ $item['status'] ] ) ? $s[ $item['status'] ] : $item['status'] );
			case 'created':
				return esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item['created_at'] . ' UTC' ) ) );
		}
		return '';
	}

	public function no_items() {
		esc_html_e( 'No leads match this filter.', 'lawzeek-crs-calculator' );
	}
}
