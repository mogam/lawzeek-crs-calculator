<?php
/**
 * Admin: the "Lawzeek" menu with Dashboard, Leads (list, detail, status, delete, CSV export) and Settings.
 * Screens use the plugin's own scoped stylesheet (Tailwind CSS, prefixed and limited to .lzcrs-app).
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LZCRS_DIR . 'includes/class-lzcrs-list-table.php';

/**
 * Everything under the "Lawzeek" admin menu. All actions need manage_options and a nonce.
 */
class Lzcrs_Admin {
	const CAP = 'manage_options';

	/**
	 * The Lawzeek mark ("L" with a rising arrow) in one colour, for the admin sidebar.
	 * WordPress recolours SVG menu icons to fit the user's admin colour scheme.
	 */
	const MENU_ICON = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M3 2h3v13h11v3H3z"/><path fill="black" d="M9.2 11.9 7.9 10.6l5-5H10V3.4h6.6V10h-2.2V7.1z"/></svg>';

	/** @var string Screen hook of the Leads page, used for bulk actions. */
	private static $leads_hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_lzcrs_status', array( __CLASS__, 'handle_status' ) );
		add_action( 'admin_post_lzcrs_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_lzcrs_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_lzcrs_dismiss', array( __CLASS__, 'handle_dismiss' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LZCRS_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu_icon_uri() {
		return 'data:image/svg+xml;base64,' . base64_encode( self::MENU_ICON ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding our own static SVG icon, as WordPress requires for menu icons.
	}

	public static function menu() {
		add_menu_page(
			__( 'Lawzeek', 'lawzeek-crs-calculator' ),
			__( 'Lawzeek', 'lawzeek-crs-calculator' ),
			self::CAP,
			'lzcrs-dashboard',
			array( __CLASS__, 'dashboard_page' ),
			self::menu_icon_uri(),
			26
		);
		add_submenu_page( 'lzcrs-dashboard', __( 'Lawzeek dashboard', 'lawzeek-crs-calculator' ), __( 'Dashboard', 'lawzeek-crs-calculator' ), self::CAP, 'lzcrs-dashboard', array( __CLASS__, 'dashboard_page' ) );
		self::$leads_hook = (string) add_submenu_page( 'lzcrs-dashboard', __( 'CRS Leads', 'lawzeek-crs-calculator' ), __( 'Leads', 'lawzeek-crs-calculator' ), self::CAP, 'lzcrs-leads', array( __CLASS__, 'leads_page' ) );
		add_submenu_page( 'lzcrs-dashboard', __( 'CRS Calculator settings', 'lawzeek-crs-calculator' ), __( 'Settings', 'lawzeek-crs-calculator' ), self::CAP, 'lzcrs-settings', array( __CLASS__, 'settings_page' ) );
		if ( self::$leads_hook ) {
			add_action( 'load-' . self::$leads_hook, array( __CLASS__, 'handle_bulk' ) );
		}
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=lzcrs-settings' ) ) . '">' . esc_html__( 'Settings', 'lawzeek-crs-calculator' ) . '</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'lzcrs' ) ) {
			return;
		}
		wp_enqueue_style( 'lzcrs-admin', LZCRS_URL . 'assets/css/admin.css', array(), LZCRS_VERSION );
		wp_register_script( 'lzcrs-admin', false, array(), LZCRS_VERSION, true );
		wp_enqueue_script( 'lzcrs-admin' );
		wp_add_inline_script(
			'lzcrs-admin',
			'document.addEventListener("click",function(e){var b=e.target.closest&&e.target.closest("[data-lzcrs-copy]");if(!b||!navigator.clipboard)return;' .
			'navigator.clipboard.writeText(b.getAttribute("data-lzcrs-copy")).then(function(){var t=b.textContent;b.textContent=' . wp_json_encode( __( 'Copied', 'lawzeek-crs-calculator' ) ) . ';setTimeout(function(){b.textContent=t;},1500);});});' .
			'document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("[data-lzcrs-confirm]");if(a&&!window.confirm(a.getAttribute("data-lzcrs-confirm")))e.preventDefault();});'
		);
	}

	private static function check( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'lawzeek-crs-calculator' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	private static function back( $args ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=lzcrs-leads' ) ) );
		exit;
	}

	/* ------------------------------------------------------------------ Layout */

	/** Opens the app shell: logo, tabs and the page wrapper. */
	private static function open( $current ) {
		$tabs = array(
			'lzcrs-dashboard' => __( 'Dashboard', 'lawzeek-crs-calculator' ),
			'lzcrs-leads'     => __( 'Leads', 'lawzeek-crs-calculator' ),
			'lzcrs-settings'  => __( 'Settings', 'lawzeek-crs-calculator' ),
		);
		echo '<div class="lzcrs-app"><header class="lzcrs-topbar">';
		echo '<a class="lzcrs-brand" href="' . esc_url( admin_url( 'admin.php?page=lzcrs-dashboard' ) ) . '"><img src="' . esc_url( LZCRS_URL . 'assets/images/logo.svg' ) . '" alt="" width="32" height="32"><span><b>' . esc_html__( 'Lawzeek', 'lawzeek-crs-calculator' ) . '</b><small>' . esc_html__( 'CRS Calculator', 'lawzeek-crs-calculator' ) . '</small></span></a>';
		echo '<nav class="lzcrs-tabs" aria-label="' . esc_attr__( 'Lawzeek', 'lawzeek-crs-calculator' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			echo '<a href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '"' . ( $slug === $current ? ' aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		echo '</nav></header><div class="lzcrs-page">';
	}

	private static function close() {
		echo '</div></div>';
	}

	/** Page title row. Notices from WordPress are moved below the hr by core. */
	private static function head( $title, $sub = '', $actions = '' ) {
		echo '<div class="lzcrs-page-head"><div><h1 class="lzcrs-h1">' . esc_html( $title ) . '</h1>';
		if ( $sub ) {
			echo '<p class="lzcrs-sub">' . esc_html( $sub ) . '</p>';
		}
		echo '</div>' . $actions . '</div><hr class="wp-header-end lz-hidden">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $actions is built from escaped parts by the caller.
	}

	private static function badge( $band ) {
		return '<span class="lzcrs-badge ' . esc_attr( in_array( $band, Lzcrs_Db::BANDS, true ) ? $band : 'neutral' ) . '">' . esc_html( Lzcrs_Labels::band( $band ) ) . '</span>';
	}

	private static function when( $utc ) {
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $utc . ' UTC' ) );
	}

	/** Small, dismissible box about the full Lawzeek service. Only on this plugin's own pages. */
	private static function info_box() {
		if ( get_user_meta( get_current_user_id(), 'lzcrs_dismissed_info', true ) ) {
			return;
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=lzcrs_dismiss' ), 'lzcrs_dismiss' );
		echo '<div class="lzcrs-info"><p><b>' . esc_html__( 'Do more with your leads.', 'lawzeek-crs-calculator' ) . '</b> ' . esc_html__( 'Lawzeek for immigration consultants adds Express Entry draw alerts, online booking and a website chatbot.', 'lawzeek-crs-calculator' ) . '</p><p class="lz-flex lz-gap-3">';
		if ( LZCRS_SERVICE_URL ) {
			echo '<a class="lzcrs-btn primary" href="' . esc_url( LZCRS_SERVICE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Learn more', 'lawzeek-crs-calculator' ) . '</a>';
		}
		echo '<a class="lzcrs-btn" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'lawzeek-crs-calculator' ) . '</a></p></div>';
	}

	private static function notice() {
		$msg = isset( $_GET['lzcrs_msg'] ) ? sanitize_key( wp_unslash( $_GET['lzcrs_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$n   = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$map = array(
			'status'  => __( 'Status saved.', 'lawzeek-crs-calculator' ),
			/* translators: %d: number of deleted leads */
			'deleted' => sprintf( _n( '%d lead deleted.', '%d leads deleted.', $n, 'lawzeek-crs-calculator' ), $n ),
			'error'   => __( 'That did not work. Please try again.', 'lawzeek-crs-calculator' ),
		);
		if ( isset( $map[ $msg ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 'error' === $msg ? 'error' : 'success', esc_html( $map[ $msg ] ) );
		}
	}

	/* ------------------------------------------------------------------ Actions */

	public static function handle_dismiss() {
		self::check( 'lzcrs_dismiss' );
		update_user_meta( get_current_user_id(), 'lzcrs_dismissed_info', 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=lzcrs-dashboard' ) );
		exit;
	}

	public static function handle_status() {
		$id = isset( $_POST['lead'] ) ? absint( $_POST['lead'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- checked next.
		self::check( 'lzcrs_status_' . $id );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$ok     = Lzcrs_Db::set_status( $id, $status );
		self::back( array( 'lead' => $id, 'lzcrs_msg' => $ok ? 'status' : 'error' ) );
	}

	public static function handle_delete() {
		self::check( 'lzcrs_delete' );
		$ids = isset( $_REQUEST['leads'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['leads'] ) ) : array();
		$n   = Lzcrs_Db::delete( $ids );
		self::back( array( 'lzcrs_msg' => 'deleted', 'n' => $n ) );
	}

	/** Bulk "Delete" from the list table (top or bottom selector), posted back to the Leads screen. */
	public static function handle_bulk() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification -- verified in check() before anything changes.
		$a1 = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		$a2 = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '';
		// phpcs:enable
		if ( 'lzcrs_delete' !== $a1 && 'lzcrs_delete' !== $a2 ) {
			return;
		}
		self::check( 'lzcrs_bulk' );
		$ids = isset( $_POST['leads'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['leads'] ) ) : array();
		$n   = Lzcrs_Db::delete( $ids );
		self::back( array( 'lzcrs_msg' => 'deleted', 'n' => $n ) );
	}

	/** Spreadsheet apps run cells starting with = + - @ as formulas; prefix them so exports are safe to open. */
	public static function csv_cell( $v ) {
		$v = (string) $v;
		return ( '' !== $v && in_array( $v[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) ? "'" . $v : $v;
	}

	public static function handle_export() {
		self::check( 'lzcrs_export' );
		$band   = isset( $_GET['band'] ) ? sanitize_key( wp_unslash( $_GET['band'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$rows   = Lzcrs_Db::query( array( 'band' => $band, 'search' => $search ), 'created_at', 'desc', 100000, 0 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=crs-leads-' . gmdate( 'Y-m-d' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- UTF-8 BOM so Excel shows accents correctly.
		fputcsv( $out, array( 'id', 'date_utc', 'name', 'email', 'phone', 'crs', 'band', 'pathway', 'status', 'marketing_consent', 'page_url' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), array( $r['id'], $r['created_at'], $r['name'], $r['email'], $r['phone'], $r['total'], $r['band'], $r['pathway'], $r['status'], $r['consent_marketing'] ? 'yes' : 'no', $r['page_url'] ) ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/* ------------------------------------------------------------------ Dashboard */

	/** The first published page or post that shows the calculator, if any. */
	public static function calculator_page() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$id = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post') AND (post_content LIKE '%[lawzeek_crs%' OR post_content LIKE '%wp:lawzeek/crs-calculator%') ORDER BY post_date DESC LIMIT 1" );
		return $id ? (int) $id : 0;
	}

	public static function dashboard_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$month  = gmdate( 'Y-m-01 00:00:00' );
		$stats  = Lzcrs_Db::stats( $month );
		$recent = Lzcrs_Db::query( array(), 'created_at', 'desc', 5, 0 );
		$s      = Lzcrs_Settings::get();
		$page   = self::calculator_page();

		self::open( 'lzcrs-dashboard' );
		self::head( __( 'Dashboard', 'lawzeek-crs-calculator' ), __( 'Your CRS calculator and the leads it brings in.', 'lawzeek-crs-calculator' ) );
		self::info_box();

		$cards = array(
			__( 'Leads this month', 'lawzeek-crs-calculator' ) => number_format_i18n( $stats['recent'] ),
			__( 'All leads', 'lawzeek-crs-calculator' )        => number_format_i18n( $stats['total'] ),
			__( 'Hot leads', 'lawzeek-crs-calculator' )        => number_format_i18n( $stats['hot'] ),
			__( 'Average score', 'lawzeek-crs-calculator' )    => null === $stats['avg'] ? '—' : number_format_i18n( $stats['avg'] ),
		);
		echo '<dl class="lz-grid lz-gap-4 lz-m-0 lz-mb-6 lz-grid-cols-2 lg:lz-grid-cols-4" data-testid="lzcrs-stats">';
		foreach ( $cards as $label => $value ) {
			echo '<div class="lzcrs-stat"><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
		}
		echo '</dl><div class="lz-grid lz-gap-6 lg:lz-grid-cols-3">';

		// Recent leads.
		echo '<section class="lzcrs-card lg:lz-col-span-2"><div class="lzcrs-card-h"><h2 class="lzcrs-h2">' . esc_html__( 'Recent leads', 'lawzeek-crs-calculator' ) . '</h2><a class="lzcrs-btn" href="' . esc_url( admin_url( 'admin.php?page=lzcrs-leads' ) ) . '">' . esc_html__( 'View all', 'lawzeek-crs-calculator' ) . '</a></div>';
		if ( ! $recent ) {
			echo '<div class="lzcrs-card-b lz-text-center lz-py-10"><p class="lz-m-0 lz-text-ink-muted">' . esc_html__( 'No leads yet. When visitors use your calculator, they will appear here.', 'lawzeek-crs-calculator' ) . '</p></div>';
		} else {
			echo '<ul class="lz-list-none lz-m-0 lz-p-0">';
			foreach ( $recent as $r ) {
				echo '<li class="lz-m-0 lz-flex lz-items-center lz-justify-between lz-gap-4 lz-px-5 lz-py-3 lz-border-0 lz-border-b lz-border-solid lz-border-ink-line last:lz-border-b-0">';
				echo '<span class="lz-min-w-0"><a class="lz-font-semibold lz-no-underline lz-text-ink" href="' . esc_url( admin_url( 'admin.php?page=lzcrs-leads&lead=' . (int) $r['id'] ) ) . '">' . esc_html( $r['name'] ) . '</a><span class="lz-block lz-text-[13px] lz-text-ink-muted lz-truncate">' . esc_html( $r['email'] . ' · ' . self::when( $r['created_at'] ) ) . '</span></span>';
				echo '<span class="lz-flex lz-items-center lz-gap-3 lz-shrink-0"><b class="lz-text-[16px]">' . esc_html( $r['total'] ) . '</b>' . self::badge( $r['band'] ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
			}
			echo '</ul>';
		}
		echo '</section>';

		// Getting started.
		$steps = array(
			array( (bool) $page, __( 'Add the calculator to a page', 'lawzeek-crs-calculator' ), $page ? '<a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View page', 'lawzeek-crs-calculator' ) . '</a>' : esc_html__( 'Use the CRS Calculator block or the shortcode below.', 'lawzeek-crs-calculator' ) ),
			array( 500 !== (int) $s['cutoff'], __( 'Set the recent Express Entry cutoff', 'lawzeek-crs-calculator' ), '<a href="' . esc_url( admin_url( 'admin.php?page=lzcrs-settings' ) ) . '">' . esc_html( sprintf( /* translators: %d: cutoff score */ __( 'Now %d. Update after each draw.', 'lawzeek-crs-calculator' ), (int) $s['cutoff'] ) ) . '</a>' ),
			array( '' !== $s['emails'], __( 'Choose who gets lead emails', 'lawzeek-crs-calculator' ), esc_html( implode( ', ', Lzcrs_Settings::notification_emails() ) ) ),
			array( $stats['total'] > 0, __( 'Get your first lead', 'lawzeek-crs-calculator' ), esc_html__( 'Try the calculator yourself to see how it works.', 'lawzeek-crs-calculator' ) ),
		);
		echo '<section class="lzcrs-card"><div class="lzcrs-card-h"><h2 class="lzcrs-h2">' . esc_html__( 'Get started', 'lawzeek-crs-calculator' ) . '</h2></div><div class="lzcrs-card-b"><ol class="lz-list-none lz-m-0 lz-p-0 lz-grid lz-gap-4" data-testid="lzcrs-checklist">';
		foreach ( $steps as $st ) {
			$icon = $st[0]
				? '<span class="lz-flex lz-items-center lz-justify-center lz-w-6 lz-h-6 lz-rounded-full lz-bg-brand-700 lz-text-white lz-text-[13px] lz-shrink-0" aria-label="' . esc_attr__( 'Done', 'lawzeek-crs-calculator' ) . '">&#10003;</span>'
				: '<span class="lz-block lz-w-6 lz-h-6 lz-rounded-full lz-border-2 lz-border-solid lz-border-ink-line lz-shrink-0" aria-label="' . esc_attr__( 'To do', 'lawzeek-crs-calculator' ) . '"></span>';
			echo '<li class="lz-m-0 lz-flex lz-gap-3">' . $icon . '<span><b class="lz-block">' . esc_html( $st[1] ) . '</b><span class="lz-text-[13px] lz-text-ink-muted">' . $st[2] . '</span></span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
		}
		echo '</ol><div class="lz-mt-5 lz-pt-4 lz-border-0 lz-border-t lz-border-solid lz-border-ink-line"><p class="lz-m-0 lz-mb-2 lz-text-[13px] lz-text-ink-muted">' . esc_html__( 'Shortcode', 'lawzeek-crs-calculator' ) . '</p>';
		echo '<div class="lz-flex lz-items-center lz-gap-2"><code class="lzcrs-code">[lawzeek_crs]</code><button type="button" class="lzcrs-btn" data-lzcrs-copy="[lawzeek_crs]">' . esc_html__( 'Copy', 'lawzeek-crs-calculator' ) . '</button></div></div></div></section>';
		echo '</div>';
		self::close();
	}

	/* ------------------------------------------------------------------ Leads */

	public static function leads_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$id = isset( $_GET['lead'] ) ? absint( $_GET['lead'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $id ) {
			self::lead_page( $id );
			return;
		}
		$table = new Lzcrs_List_Table();
		$table->prepare_items();
		$export = wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'lzcrs_export',
					'band'   => isset( $_GET['band'] ) ? sanitize_key( wp_unslash( $_GET['band'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
					's'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
				),
				admin_url( 'admin-post.php' )
			),
			'lzcrs_export'
		);
		self::open( 'lzcrs-leads' );
		self::head(
			__( 'Leads', 'lawzeek-crs-calculator' ),
			__( 'Everyone who saw their score with your calculator.', 'lawzeek-crs-calculator' ),
			Lzcrs_Db::count() ? '<a href="' . esc_url( $export ) . '" class="lzcrs-btn">' . esc_html__( 'Export CSV', 'lawzeek-crs-calculator' ) . '</a>' : ''
		);
		self::notice();
		if ( 0 === Lzcrs_Db::count() ) {
			echo '<div class="lzcrs-card"><div class="lzcrs-card-b lz-text-center lz-py-12"><img src="' . esc_url( LZCRS_URL . 'assets/images/logo.svg' ) . '" alt="" width="48" height="48" class="lz-mx-auto lz-mb-3 lz-block"><h2 class="lzcrs-h2 lz-mb-2">' . esc_html__( 'No leads yet', 'lawzeek-crs-calculator' ) . '</h2><p class="lz-m-0 lz-text-ink-muted">' . wp_kses(
				sprintf(
					/* translators: %s: the shortcode */
					__( 'Add the CRS Calculator block, or the shortcode %s, to any page. Every visitor who sees their score will appear here.', 'lawzeek-crs-calculator' ),
					'<code class="lzcrs-code">[lawzeek_crs]</code>'
				),
				array( 'code' => array( 'class' => array() ) )
			) . '</p></div></div>';
			self::close();
			return;
		}
		echo '<div class="lz-flex lz-items-center lz-justify-between lz-gap-3 lz-flex-wrap lz-mb-4">';
		$table->views();
		echo '<form method="get" class="lz-m-0"><input type="hidden" name="page" value="lzcrs-leads">';
		$table->search_box( __( 'Search leads', 'lawzeek-crs-calculator' ), 'lzcrs-search' );
		echo '</form></div><div class="lzcrs-card lzcrs-table-card lz-overflow-x-auto"><form method="post" action="' . esc_url( admin_url( 'admin.php?page=lzcrs-leads' ) ) . '">';
		wp_nonce_field( 'lzcrs_bulk' );
		$table->display();
		echo '</form></div>';
		self::close();
	}

	private static function lead_page( $id ) {
		$lead = Lzcrs_Db::get( $id );
		self::open( 'lzcrs-leads' );
		echo '<p class="lz-m-0 lz-mb-3"><a class="lz-no-underline" href="' . esc_url( admin_url( 'admin.php?page=lzcrs-leads' ) ) . '">&larr; ' . esc_html__( 'All leads', 'lawzeek-crs-calculator' ) . '</a></p>';
		if ( ! $lead ) {
			self::head( __( 'Lead not found', 'lawzeek-crs-calculator' ) );
			self::close();
			return;
		}
		$a     = Lzcrs_Scoring::validate( json_decode( $lead['answers'], true ) );
		$b     = $a ? Lzcrs_Scoring::calculate( $a ) : null;
		$gaps  = $a ? Lzcrs_Scoring::gap_plan( $a, (int) Lzcrs_Settings::get()['cutoff'] ) : array();
		$edu   = Lzcrs_Labels::education();
		$caedu = Lzcrs_Labels::canadian_education();
		$fr    = Lzcrs_Labels::french();
		$when  = self::when( $lead['created_at'] );

		echo '<div class="lzcrs-page-head"><div><h1 class="lzcrs-h1 lz-flex lz-items-center lz-gap-3">' . esc_html( $lead['name'] ) . ' ' . self::badge( $lead['band'] ) . '</h1>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- badge() escapes.
		echo '<p class="lzcrs-sub"><a href="' . esc_url( 'mailto:' . $lead['email'] ) . '">' . esc_html( $lead['email'] ) . '</a>' . ( $lead['phone'] ? ' · ' . esc_html( $lead['phone'] ) : '' ) . ' · ' . esc_html( $when ) . '</p></div>';
		echo '<div class="lz-text-right"><div class="lz-text-[40px] lz-font-semibold lz-leading-none">' . esc_html( $lead['total'] ) . '</div><div class="lz-text-[13px] lz-text-ink-muted">' . esc_html__( 'Estimated CRS score', 'lawzeek-crs-calculator' ) . '</div></div></div><hr class="wp-header-end lz-hidden">';
		self::notice();

		echo '<div class="lz-grid lz-gap-6 lz-items-start lg:lz-grid-cols-3"><section class="lzcrs-card lg:lz-col-span-2"><div class="lzcrs-card-h"><h2 class="lzcrs-h2">' . esc_html__( 'Profile and score', 'lawzeek-crs-calculator' ) . '</h2><span class="lzcrs-badge neutral">' . esc_html( $lead['rules_version'] ) . '</span></div><div class="lzcrs-card-b"><dl class="lzcrs-kv">';
		if ( $a ) {
			$yes  = __( 'Yes', 'lawzeek-crs-calculator' );
			$no   = __( 'No', 'lawzeek-crs-calculator' );
			$rows = array(
				__( 'Age', 'lawzeek-crs-calculator' )                         => $a['age'],
				__( 'Education', 'lawzeek-crs-calculator' )                   => $edu[ $a['edu'] ],
				__( 'Education in Canada', 'lawzeek-crs-calculator' )         => $caedu[ $a['caEdu'] ],
				__( 'IELTS L / R / W / S', 'lawzeek-crs-calculator' )         => implode( ' / ', array_map( array( 'Lzcrs_Labels', 'ielts' ), array( $a['L'], $a['R'], $a['W'], $a['S'] ) ) ),
				__( 'French', 'lawzeek-crs-calculator' )                      => $fr[ $a['fr'] ],
				__( 'Canadian work (years)', 'lawzeek-crs-calculator' )       => $a['caExp'],
				__( 'Work outside Canada (years)', 'lawzeek-crs-calculator' ) => $a['frExp'],
				__( 'Provincial nomination', 'lawzeek-crs-calculator' )       => $a['pnp'] ? $yes : $no,
				__( 'Sibling in Canada', 'lawzeek-crs-calculator' )           => $a['sibling'] ? $yes : $no,
				__( 'Likely pathway', 'lawzeek-crs-calculator' )              => Lzcrs_Labels::pathway( $lead['pathway'] ),
			);
			foreach ( $rows as $k => $v ) {
				echo '<dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd>';
			}
		}
		echo '</dl>';
		if ( $b ) {
			echo '<div class="lz-grid lz-grid-cols-3 lz-gap-3 lz-mt-5">';
			foreach ( array( __( 'Core human capital', 'lawzeek-crs-calculator' ) => $b['core'], __( 'Skill transferability', 'lawzeek-crs-calculator' ) => $b['skillTransferability'], __( 'Additional points', 'lawzeek-crs-calculator' ) => $b['additional'] ) as $k => $v ) {
				echo '<div class="lz-rounded-lg lz-bg-ink-soft lz-p-3"><div class="lz-text-[12px] lz-text-ink-muted">' . esc_html( $k ) . '</div><div class="lz-text-[20px] lz-font-semibold">' . esc_html( $v ) . '</div></div>';
			}
			echo '</div>';
		}
		echo '</div></section><div class="lz-grid lz-gap-6 lz-content-start">';

		echo '<section class="lzcrs-card"><div class="lzcrs-card-h"><h2 class="lzcrs-h2">' . esc_html__( 'Status', 'lawzeek-crs-calculator' ) . '</h2></div><form class="lzcrs-card-b lz-flex lz-gap-2 lz-items-center" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lzcrs_status_' . $id );
		echo '<input type="hidden" name="action" value="lzcrs_status"><input type="hidden" name="lead" value="' . esc_attr( $id ) . '">';
		echo '<label for="lzcrs-status" class="screen-reader-text">' . esc_html__( 'Lead status', 'lawzeek-crs-calculator' ) . '</label><select id="lzcrs-status" name="status" class="lz-flex-1">';
		foreach ( Lzcrs_Labels::statuses() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $lead['status'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select><button type="submit" class="lzcrs-btn primary">' . esc_html__( 'Save status', 'lawzeek-crs-calculator' ) . '</button></form></section>';

		echo '<section class="lzcrs-card"><div class="lzcrs-card-h"><h2 class="lzcrs-h2">' . esc_html__( 'Fastest ways to raise the score', 'lawzeek-crs-calculator' ) . '</h2></div><ul class="lz-list-none lz-m-0 lz-p-0">';
		foreach ( $gaps as $g ) {
			echo '<li class="lz-m-0 lz-flex lz-justify-between lz-gap-3 lz-px-5 lz-py-2.5 lz-border-0 lz-border-b lz-border-solid lz-border-ink-line last:lz-border-b-0"><span>' . esc_html( $g['label'] ) . '</span><b class="lz-text-brand-700">+' . esc_html( $g['delta'] ) . '</b></li>';
		}
		echo '</ul></section>';

		echo '<section class="lzcrs-card"><div class="lzcrs-card-h"><h2 class="lzcrs-h2">' . esc_html__( 'Consent record', 'lawzeek-crs-calculator' ) . '</h2></div><ul class="lzcrs-card-b lz-m-0 lz-grid lz-gap-2 lz-list-none">';
		echo '<li class="lz-m-0">' . esc_html__( 'Agreed to be contacted about their options', 'lawzeek-crs-calculator' ) . '<span class="lz-block lz-text-[13px] lz-text-ink-muted">' . esc_html( $when ) . '</span></li>';
		echo '<li class="lz-m-0">' . ( $lead['consent_marketing'] ? esc_html__( 'Opted in to updates', 'lawzeek-crs-calculator' ) : esc_html__( 'Did not opt in to updates: do not add to newsletters', 'lawzeek-crs-calculator' ) ) . '</li>';
		if ( $lead['page_url'] ) {
			echo '<li class="lz-m-0 lz-break-all">' . esc_html__( 'Submitted on', 'lawzeek-crs-calculator' ) . ' <a href="' . esc_url( $lead['page_url'] ) . '">' . esc_html( $lead['page_url'] ) . '</a></li>';
		}
		echo '</ul></section>';

		$delete = wp_nonce_url( admin_url( 'admin-post.php?action=lzcrs_delete&leads[]=' . $id ), 'lzcrs_delete' );
		echo '<p class="lz-m-0"><a class="lzcrs-btn danger" href="' . esc_url( $delete ) . '" data-lzcrs-confirm="' . esc_attr__( 'Delete this lead permanently? This cannot be undone.', 'lawzeek-crs-calculator' ) . '">' . esc_html__( 'Delete lead', 'lawzeek-crs-calculator' ) . '</a></p>';
		echo '</div></div>';
		self::close();
	}

	/* ------------------------------------------------------------------ Settings */

	private static function field( $label, $control, $help = '', $for = '' ) {
		echo '<div class="lzcrs-field">';
		echo $for ? '<label for="' . esc_attr( $for ) . '">' . esc_html( $label ) . '</label>' : '<span class="lzcrs-label">' . esc_html( $label ) . '</span>';
		echo $control; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controls are built with esc_attr()/esc_html() by the caller.
		if ( $help ) {
			echo '<p class="lzcrs-help">' . esc_html( $help ) . '</p>';
		}
		echo '</div>';
	}

	private static function section( $title, $sub ) {
		echo '<section class="lzcrs-card lz-mb-6"><div class="lzcrs-card-h"><div><h2 class="lzcrs-h2">' . esc_html( $title ) . '</h2><p class="lzcrs-help lz-mt-1">' . esc_html( $sub ) . '</p></div></div><div class="lzcrs-card-b lz-py-1">';
	}

	public static function settings_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$s = Lzcrs_Settings::get();
		$n = esc_attr( Lzcrs_Settings::OPTION );
		self::open( 'lzcrs-settings' );
		self::head( __( 'Settings', 'lawzeek-crs-calculator' ), __( 'How the calculator looks, scores and sends you leads.', 'lawzeek-crs-calculator' ) );
		settings_errors( Lzcrs_Settings::OPTION );
		self::info_box();
		echo '<form method="post" action="options.php" class="lz-max-w-[860px]">';
		settings_fields( 'lzcrs' );

		self::section( __( 'Leads', 'lawzeek-crs-calculator' ), __( 'Where new-lead emails go.', 'lawzeek-crs-calculator' ) );
		self::field( __( 'Send new leads to', 'lawzeek-crs-calculator' ), '<input type="text" id="lzcrs-emails" name="' . $n . '[emails]" value="' . esc_attr( $s['emails'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '">', __( 'One or more emails, separated by commas. Empty means the site admin email.', 'lawzeek-crs-calculator' ), 'lzcrs-emails' );
		echo '</div></section>';

		self::section( __( 'Scoring', 'lawzeek-crs-calculator' ), __( 'Compared with each visitor\'s score.', 'lawzeek-crs-calculator' ) );
		self::field( __( 'Recent Express Entry cutoff', 'lawzeek-crs-calculator' ), '<input type="number" id="lzcrs-cutoff" name="' . $n . '[cutoff]" value="' . esc_attr( $s['cutoff'] ) . '" min="200" max="1200">', __( 'Shown to visitors ("N above/below the recent cutoff") and used to rate leads Hot, Warm or Cold. Update it after each draw.', 'lawzeek-crs-calculator' ), 'lzcrs-cutoff' );
		echo '</div></section>';

		$phone = '<select id="lzcrs-phone" name="' . $n . '[phone]">';
		foreach ( array( 'optional' => __( 'Ask, optional', 'lawzeek-crs-calculator' ), 'required' => __( 'Ask, required', 'lawzeek-crs-calculator' ), 'hidden' => __( 'Do not ask', 'lawzeek-crs-calculator' ) ) as $k => $l ) {
			$phone .= '<option value="' . esc_attr( $k ) . '"' . selected( $s['phone'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		$phone .= '</select>';
		self::section( __( 'Calculator', 'lawzeek-crs-calculator' ), __( 'What visitors see on your website.', 'lawzeek-crs-calculator' ) );
		self::field( __( 'Button colour', 'lawzeek-crs-calculator' ), '<input type="color" id="lzcrs-accent" name="' . $n . '[accent]" value="' . esc_attr( $s['accent'] ) . '">', __( 'Text on the button turns white or dark automatically so it stays readable.', 'lawzeek-crs-calculator' ), 'lzcrs-accent' );
		self::field( __( 'Phone number field', 'lawzeek-crs-calculator' ), $phone, '', 'lzcrs-phone' );
		self::field( __( 'Button text after the result', 'lawzeek-crs-calculator' ), '<input type="text" id="lzcrs-cta-text" maxlength="40" name="' . $n . '[cta_text]" value="' . esc_attr( $s['cta_text'] ) . '" placeholder="' . esc_attr__( 'Book a consultation', 'lawzeek-crs-calculator' ) . '">', '', 'lzcrs-cta-text' );
		self::field( __( 'Button link', 'lawzeek-crs-calculator' ), '<input type="url" id="lzcrs-cta-url" name="' . $n . '[cta_url]" value="' . esc_attr( $s['cta_url'] ) . '" placeholder="https://">', __( 'Optional. For example your contact or booking page. The button shows only when both text and link are set.', 'lawzeek-crs-calculator' ), 'lzcrs-cta-url' );
		self::field( __( 'Credit link', 'lawzeek-crs-calculator' ), '<label class="lzcrs-toggle"><input type="checkbox" name="' . $n . '[credit]" value="1"' . checked( $s['credit'], true, false ) . '><span>' . esc_html__( 'Show a small "CRS calculator by Lawzeek" link under the calculator', 'lawzeek-crs-calculator' ) . '</span></label>' );
		echo '</div></section>';

		$pages = wp_dropdown_pages(
			array(
				'name'              => Lzcrs_Settings::OPTION . '[privacy_page]',
				'id'                => 'lzcrs-privacy-page',
				'selected'          => (int) $s['privacy_page'],
				'show_option_none'  => esc_html__( 'Use the site privacy policy page', 'lawzeek-crs-calculator' ),
				'option_none_value' => '0',
				'echo'              => 0,
			)
		);
		self::section( __( 'Privacy and data', 'lawzeek-crs-calculator' ), __( 'Consent, and what happens to leads if you remove the plugin.', 'lawzeek-crs-calculator' ) );
		self::field( __( 'Privacy policy page', 'lawzeek-crs-calculator' ), $pages ? $pages : '<p class="lzcrs-help">' . esc_html__( 'No pages yet.', 'lawzeek-crs-calculator' ) . '</p>', __( 'Linked from the consent checkbox.', 'lawzeek-crs-calculator' ), 'lzcrs-privacy-page' );
		self::field( __( 'When deleting the plugin', 'lawzeek-crs-calculator' ), '<label class="lzcrs-toggle"><input type="checkbox" name="' . $n . '[delete_on_uninstall]" value="1"' . checked( $s['delete_on_uninstall'], true, false ) . '><span>' . esc_html__( 'Also delete all leads and settings', 'lawzeek-crs-calculator' ) . '</span></label>', __( 'Off by default, so your leads are not lost by accident.', 'lawzeek-crs-calculator' ) );
		echo '</div></section>';

		echo '<div class="lz-flex lz-gap-3"><button type="submit" class="lzcrs-btn primary">' . esc_html__( 'Save settings', 'lawzeek-crs-calculator' ) . '</button></div></form>';
		self::close();
	}
}
