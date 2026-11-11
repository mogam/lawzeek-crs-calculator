<?php
/**
 * Admin: CRS Leads menu (list, detail, status, delete, CSV export), Settings, and a dismissible info box.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LZCRS_DIR . 'includes/class-lzcrs-list-table.php';

/**
 * Everything under the "CRS Leads" admin menu. All actions need manage_options and a nonce.
 */
class Lzcrs_Admin {
	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_lzcrs_status', array( __CLASS__, 'handle_status' ) );
		add_action( 'admin_post_lzcrs_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_lzcrs_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_lzcrs_dismiss', array( __CLASS__, 'handle_dismiss' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( LZCRS_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'load-toplevel_page_lzcrs-leads', array( __CLASS__, 'handle_bulk' ) );
	}

	/** Bulk "Delete" from the list table (top or bottom selector), posted back to the leads screen. */
	public static function handle_bulk() {
		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
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

	public static function menu() {
		add_menu_page(
			__( 'CRS Leads', 'lawzeek-crs-calculator' ),
			__( 'CRS Leads', 'lawzeek-crs-calculator' ),
			self::CAP,
			'lzcrs-leads',
			array( __CLASS__, 'leads_page' ),
			'dashicons-chart-bar',
			26
		);
		add_submenu_page( 'lzcrs-leads', __( 'CRS Leads', 'lawzeek-crs-calculator' ), __( 'All leads', 'lawzeek-crs-calculator' ), self::CAP, 'lzcrs-leads', array( __CLASS__, 'leads_page' ) );
		add_submenu_page( 'lzcrs-leads', __( 'CRS Calculator settings', 'lawzeek-crs-calculator' ), __( 'Settings', 'lawzeek-crs-calculator' ), self::CAP, 'lzcrs-settings', array( __CLASS__, 'settings_page' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=lzcrs-settings' ) ) . '">' . esc_html__( 'Settings', 'lawzeek-crs-calculator' ) . '</a>' );
		return $links;
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'lzcrs' ) ) {
			return;
		}
		wp_register_style( 'lzcrs-admin', false, array(), LZCRS_VERSION );
		wp_enqueue_style( 'lzcrs-admin' );
		wp_add_inline_style(
			'lzcrs-admin',
			'.lzcrs-band{display:inline-block;padding:1px 9px;border-radius:99px;font-size:12px;font-weight:600}
			.lzcrs-band.hot{background:#fde6ea;color:#b0102a}.lzcrs-band.warm{background:#fff1d9;color:#8a5000}.lzcrs-band.cold{background:#e8eef1;color:#40525b}
			.lzcrs-info{background:#fff;border:1px solid #c3c4c7;border-left:4px solid #2271b1;padding:10px 14px;margin:16px 0;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}
			.lzcrs-info p{margin:0}.lzcrs-grid{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);gap:20px;align-items:start;max-width:1100px}
			.lzcrs-box{background:#fff;border:1px solid #c3c4c7;padding:4px 16px 16px}.lzcrs-box table td,.lzcrs-box table th{padding:6px 10px 6px 0;vertical-align:top;text-align:left}
			.lzcrs-total{font-size:32px;font-weight:600;line-height:1.2}@media(max-width:900px){.lzcrs-grid{grid-template-columns:minmax(0,1fr)}}'
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

	/** Small, dismissible box about the full Lawzeek service. Only on this plugin's own pages. */
	private static function info_box() {
		if ( get_user_meta( get_current_user_id(), 'lzcrs_dismissed_info', true ) ) {
			return;
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=lzcrs_dismiss' ), 'lzcrs_dismiss' );
		echo '<div class="lzcrs-info"><p>' . esc_html__( 'Want Express Entry draw alerts for your leads, online booking and a website chatbot? See Lawzeek for immigration consultants.', 'lawzeek-crs-calculator' ) . '</p>';
		echo '<p>';
		if ( LZCRS_SERVICE_URL ) {
			echo '<a href="' . esc_url( LZCRS_SERVICE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Learn more', 'lawzeek-crs-calculator' ) . '</a> · ';
		}
		echo '<a href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'lawzeek-crs-calculator' ) . '</a></p></div>';
	}

	public static function handle_dismiss() {
		self::check( 'lzcrs_dismiss' );
		update_user_meta( get_current_user_id(), 'lzcrs_dismissed_info', 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=lzcrs-leads' ) );
		exit;
	}

	public static function handle_status() {
		$id = isset( $_POST['lead'] ) ? absint( $_POST['lead'] ) : 0;
		self::check( 'lzcrs_status_' . $id );
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$ok     = Lzcrs_Db::set_status( $id, $status );
		self::back( array( 'lead' => $id, 'lzcrs_msg' => $ok ? 'status' : 'error' ) );
	}

	public static function handle_delete() {
		$ids = isset( $_REQUEST['leads'] ) ? array_map( 'absint', (array) wp_unslash( $_REQUEST['leads'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification -- checked in check().
		self::check( 'lzcrs_delete' );
		$n = Lzcrs_Db::delete( $ids );
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
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel shows accents correctly.
		fputcsv( $out, array( 'id', 'date_utc', 'name', 'email', 'phone', 'crs', 'band', 'pathway', 'status', 'marketing_consent', 'page_url' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array_map( array( __CLASS__, 'csv_cell' ), array( $r['id'], $r['created_at'], $r['name'], $r['email'], $r['phone'], $r['total'], $r['band'], $r['pathway'], $r['status'], $r['consent_marketing'] ? 'yes' : 'no', $r['page_url'] ) ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	private static function notice() {
		$msg = isset( $_GET['lzcrs_msg'] ) ? sanitize_key( wp_unslash( $_GET['lzcrs_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$map = array(
			'status'  => __( 'Status saved.', 'lawzeek-crs-calculator' ),
			/* translators: %d: number of deleted leads */
			'deleted' => sprintf( _n( '%d lead deleted.', '%d leads deleted.', isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0, 'lawzeek-crs-calculator' ), isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0 ), // phpcs:ignore WordPress.Security.NonceVerification
			'error'   => __( 'That did not work. Please try again.', 'lawzeek-crs-calculator' ),
		);
		if ( isset( $map[ $msg ] ) ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 'error' === $msg ? 'error' : 'success', esc_html( $map[ $msg ] ) );
		}
	}

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
		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'CRS Leads', 'lawzeek-crs-calculator' ) . '</h1>';
		echo ' <a href="' . esc_url( $export ) . '" class="page-title-action">' . esc_html__( 'Export CSV', 'lawzeek-crs-calculator' ) . '</a><hr class="wp-header-end">';
		self::notice();
		self::info_box();
		if ( 0 === Lzcrs_Db::count() ) {
			echo '<div class="lzcrs-box"><p>' . wp_kses(
				sprintf(
					/* translators: %s: the shortcode */
					__( 'No leads yet. Add the CRS Calculator block, or the shortcode %s, to any page. Every visitor who sees their score will appear here.', 'lawzeek-crs-calculator' ),
					'<code>[lawzeek_crs]</code>'
				),
				array( 'code' => array() )
			) . '</p></div></div>';
			return;
		}
		$table->views();
		echo '<form method="get"><input type="hidden" name="page" value="lzcrs-leads">';
		$table->search_box( __( 'Search leads', 'lawzeek-crs-calculator' ), 'lzcrs-search' );
		echo '</form><form method="post" action="' . esc_url( admin_url( 'admin.php?page=lzcrs-leads' ) ) . '">';
		wp_nonce_field( 'lzcrs_bulk' );
		$table->display();
		echo '</form></div>';
	}

	private static function lead_page( $id ) {
		$lead = Lzcrs_Db::get( $id );
		echo '<div class="wrap"><p><a href="' . esc_url( admin_url( 'admin.php?page=lzcrs-leads' ) ) . '">&larr; ' . esc_html__( 'All leads', 'lawzeek-crs-calculator' ) . '</a></p>';
		if ( ! $lead ) {
			echo '<h1>' . esc_html__( 'Lead not found', 'lawzeek-crs-calculator' ) . '</h1></div>';
			return;
		}
		self::notice();
		$a        = json_decode( $lead['answers'], true );
		$a        = Lzcrs_Scoring::validate( $a );
		$b        = $a ? Lzcrs_Scoring::calculate( $a ) : null;
		$gaps     = $a ? Lzcrs_Scoring::gap_plan( $a, (int) Lzcrs_Settings::get()['cutoff'] ) : array();
		$edu      = Lzcrs_Labels::education();
		$caedu    = Lzcrs_Labels::canadian_education();
		$fr       = Lzcrs_Labels::french();
		$date_fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$when     = wp_date( $date_fmt, strtotime( $lead['created_at'] . ' UTC' ) );

		echo '<h1>' . esc_html( $lead['name'] ) . ' <span class="lzcrs-band ' . esc_attr( $lead['band'] ) . '">' . esc_html( Lzcrs_Labels::band( $lead['band'] ) ) . '</span></h1>';
		echo '<p><a href="' . esc_url( 'mailto:' . $lead['email'] ) . '">' . esc_html( $lead['email'] ) . '</a>' . ( $lead['phone'] ? ' · ' . esc_html( $lead['phone'] ) : '' ) . ' · ' . esc_html( $when ) . '</p>';
		echo '<div class="lzcrs-grid"><div class="lzcrs-box"><h2>' . esc_html__( 'Profile and score', 'lawzeek-crs-calculator' ) . '</h2>';
		echo '<p class="lzcrs-total">' . esc_html( $lead['total'] ) . '</p><table>';
		if ( $a ) {
			$rows = array(
				__( 'Age', 'lawzeek-crs-calculator' )                       => $a['age'],
				__( 'Education', 'lawzeek-crs-calculator' )                 => $edu[ $a['edu'] ],
				__( 'Education in Canada', 'lawzeek-crs-calculator' )       => $caedu[ $a['caEdu'] ],
				__( 'IELTS L / R / W / S', 'lawzeek-crs-calculator' )       => implode( ' / ', array_map( array( 'Lzcrs_Labels', 'ielts' ), array( $a['L'], $a['R'], $a['W'], $a['S'] ) ) ),
				__( 'French', 'lawzeek-crs-calculator' )                    => $fr[ $a['fr'] ],
				__( 'Canadian work (years)', 'lawzeek-crs-calculator' )     => $a['caExp'],
				__( 'Work outside Canada (years)', 'lawzeek-crs-calculator' ) => $a['frExp'],
				__( 'Provincial nomination', 'lawzeek-crs-calculator' )     => $a['pnp'] ? __( 'Yes', 'lawzeek-crs-calculator' ) : __( 'No', 'lawzeek-crs-calculator' ),
				__( 'Sibling in Canada', 'lawzeek-crs-calculator' )         => $a['sibling'] ? __( 'Yes', 'lawzeek-crs-calculator' ) : __( 'No', 'lawzeek-crs-calculator' ),
				__( 'Likely pathway', 'lawzeek-crs-calculator' )            => Lzcrs_Labels::pathway( $lead['pathway'] ),
				__( 'Core human capital', 'lawzeek-crs-calculator' )        => $b['core'],
				__( 'Skill transferability', 'lawzeek-crs-calculator' )     => $b['skillTransferability'],
				__( 'Additional points', 'lawzeek-crs-calculator' )         => $b['additional'],
			);
			foreach ( $rows as $k => $v ) {
				echo '<tr><th scope="row">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
			}
		}
		echo '</table><p class="description">' . esc_html(
			sprintf(
				/* translators: %s: rules version */
				__( 'Scored with rules %s.', 'lawzeek-crs-calculator' ),
				$lead['rules_version']
			)
		) . '</p></div><div>';

		echo '<div class="lzcrs-box"><h2>' . esc_html__( 'Status', 'lawzeek-crs-calculator' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'lzcrs_status_' . $id );
		echo '<input type="hidden" name="action" value="lzcrs_status"><input type="hidden" name="lead" value="' . esc_attr( $id ) . '">';
		echo '<label for="lzcrs-status" class="screen-reader-text">' . esc_html__( 'Lead status', 'lawzeek-crs-calculator' ) . '</label><select id="lzcrs-status" name="status">';
		foreach ( Lzcrs_Labels::statuses() as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $lead['status'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Save status', 'lawzeek-crs-calculator' ), 'primary', 'submit', false );
		echo '</form></div>';

		echo '<div class="lzcrs-box"><h2>' . esc_html__( 'Fastest ways to raise the score', 'lawzeek-crs-calculator' ) . '</h2><ul>';
		foreach ( $gaps as $g ) {
			echo '<li>' . esc_html( $g['label'] ) . ' <strong>+' . esc_html( $g['delta'] ) . '</strong></li>';
		}
		echo '</ul></div>';

		echo '<div class="lzcrs-box"><h2>' . esc_html__( 'Consent record', 'lawzeek-crs-calculator' ) . '</h2><ul>';
		echo '<li>' . esc_html__( 'Agreed to be contacted about their options', 'lawzeek-crs-calculator' ) . ' · ' . esc_html( $when ) . '</li>';
		echo '<li>' . ( $lead['consent_marketing'] ? esc_html__( 'Opted in to updates', 'lawzeek-crs-calculator' ) : esc_html__( 'Did not opt in to updates: do not add to newsletters', 'lawzeek-crs-calculator' ) ) . '</li>';
		if ( $lead['page_url'] ) {
			echo '<li>' . esc_html__( 'Submitted on', 'lawzeek-crs-calculator' ) . ' <a href="' . esc_url( $lead['page_url'] ) . '">' . esc_html( $lead['page_url'] ) . '</a></li>';
		}
		echo '</ul></div>';

		$delete = wp_nonce_url( admin_url( 'admin-post.php?action=lzcrs_delete&leads[]=' . $id ), 'lzcrs_delete' );
		echo '<p><a class="button button-link-delete" href="' . esc_url( $delete ) . '" onclick="return confirm(' . esc_attr( wp_json_encode( __( 'Delete this lead permanently? This cannot be undone.', 'lawzeek-crs-calculator' ) ) ) . ')">' . esc_html__( 'Delete lead', 'lawzeek-crs-calculator' ) . '</a></p>';
		echo '</div></div></div>';
	}

	public static function settings_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$s    = Lzcrs_Settings::get();
		$name = Lzcrs_Settings::OPTION;
		echo '<div class="wrap"><h1>' . esc_html__( 'CRS Calculator settings', 'lawzeek-crs-calculator' ) . '</h1>';
		settings_errors( $name );
		self::info_box();
		echo '<form method="post" action="options.php">';
		settings_fields( 'lzcrs' );
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row"><label for="lzcrs-emails">' . esc_html__( 'Send new leads to', 'lawzeek-crs-calculator' ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="lzcrs-emails" name="' . esc_attr( $name ) . '[emails]" value="' . esc_attr( $s['emails'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '">';
		echo '<p class="description">' . esc_html__( 'One or more emails, separated by commas. Empty means the site admin email.', 'lawzeek-crs-calculator' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="lzcrs-cutoff">' . esc_html__( 'Recent Express Entry cutoff', 'lawzeek-crs-calculator' ) . '</label></th><td>';
		echo '<input type="number" id="lzcrs-cutoff" name="' . esc_attr( $name ) . '[cutoff]" value="' . esc_attr( $s['cutoff'] ) . '" min="200" max="1200" class="small-text">';
		echo '<p class="description">' . esc_html__( 'Shown to visitors ("N above/below the recent cutoff") and used to rate leads Hot, Warm or Cold. Update it after each draw.', 'lawzeek-crs-calculator' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="lzcrs-phone">' . esc_html__( 'Phone number field', 'lawzeek-crs-calculator' ) . '</label></th><td><select id="lzcrs-phone" name="' . esc_attr( $name ) . '[phone]">';
		foreach ( array( 'optional' => __( 'Ask, optional', 'lawzeek-crs-calculator' ), 'required' => __( 'Ask, required', 'lawzeek-crs-calculator' ), 'hidden' => __( 'Do not ask', 'lawzeek-crs-calculator' ) ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $s['phone'], $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="lzcrs-accent">' . esc_html__( 'Button colour', 'lawzeek-crs-calculator' ) . '</label></th><td>';
		echo '<input type="color" id="lzcrs-accent" name="' . esc_attr( $name ) . '[accent]" value="' . esc_attr( $s['accent'] ) . '"></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Button after the result', 'lawzeek-crs-calculator' ) . '</th><td>';
		echo '<label for="lzcrs-cta-text">' . esc_html__( 'Text', 'lawzeek-crs-calculator' ) . '</label><br><input type="text" class="regular-text" id="lzcrs-cta-text" maxlength="40" name="' . esc_attr( $name ) . '[cta_text]" value="' . esc_attr( $s['cta_text'] ) . '" placeholder="' . esc_attr__( 'Book a consultation', 'lawzeek-crs-calculator' ) . '"><br>';
		echo '<label for="lzcrs-cta-url">' . esc_html__( 'Link', 'lawzeek-crs-calculator' ) . '</label><br><input type="url" class="regular-text" id="lzcrs-cta-url" name="' . esc_attr( $name ) . '[cta_url]" value="' . esc_attr( $s['cta_url'] ) . '" placeholder="https://">';
		echo '<p class="description">' . esc_html__( 'Optional. Shown under the score, for example a link to your contact or booking page. Needs both text and link.', 'lawzeek-crs-calculator' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="lzcrs-privacy-page">' . esc_html__( 'Privacy policy page', 'lawzeek-crs-calculator' ) . '</label></th><td>';
		wp_dropdown_pages(
			array(
				'name'              => esc_attr( $name ) . '[privacy_page]',
				'id'                => 'lzcrs-privacy-page',
				'selected'          => (int) $s['privacy_page'],
				'show_option_none'  => esc_html__( 'Use the site privacy policy page', 'lawzeek-crs-calculator' ),
				'option_none_value' => '0',
			)
		);
		echo '<p class="description">' . esc_html__( 'Linked from the consent checkbox.', 'lawzeek-crs-calculator' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Credit link', 'lawzeek-crs-calculator' ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name ) . '[credit]" value="1"' . checked( $s['credit'], true, false ) . '> ' . esc_html__( 'Show a small "CRS calculator by Lawzeek" link under the calculator', 'lawzeek-crs-calculator' ) . '</label></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'When deleting the plugin', 'lawzeek-crs-calculator' ) . '</th><td><label><input type="checkbox" name="' . esc_attr( $name ) . '[delete_on_uninstall]" value="1"' . checked( $s['delete_on_uninstall'], true, false ) . '> ' . esc_html__( 'Also delete all leads and settings', 'lawzeek-crs-calculator' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Off by default, so your leads are not lost by accident.', 'lawzeek-crs-calculator' ) . '</p></td></tr>';

		echo '</table>';
		submit_button();
		echo '</form><h2>' . esc_html__( 'Add the calculator to a page', 'lawzeek-crs-calculator' ) . '</h2><p>' . wp_kses(
			sprintf(
				/* translators: 1: block name, 2: shortcode */
				__( 'In the block editor, add the %1$s block. Or paste the shortcode %2$s into any page or post.', 'lawzeek-crs-calculator' ),
				'<strong>' . esc_html__( 'CRS Calculator', 'lawzeek-crs-calculator' ) . '</strong>',
				'<code>[lawzeek_crs]</code>'
			),
			array( 'strong' => array(), 'code' => array() )
		) . '</p></div>';
	}
}
