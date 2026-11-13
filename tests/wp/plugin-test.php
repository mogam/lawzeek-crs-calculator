<?php
/**
 * Tests for Lawzeek CRS Calculator, run inside a real WordPress.
 *
 * With wp-env (CI and local Docker):  npm run test:wp
 * With any other local WordPress:     php tests/wp/plugin-test.php /path/to/wordpress
 *
 * Creates leads with emails ending in @lzcrs.test and removes them at the end.
 *
 * @package LawzeekCrsCalculator
 */

// phpcs:ignoreFile -- test script, not shipped in the plugin.

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}
if ( ! defined( 'ABSPATH' ) ) {
	// Run directly with PHP: load the WordPress given as the first argument.
	$_SERVER['HTTP_HOST']   = getenv( 'LZCRS_TEST_HOST' ) ? getenv( 'LZCRS_TEST_HOST' ) : 'localhost';
	$_SERVER['REQUEST_URI'] = '/';
	define( 'WP_USE_THEMES', false );
	require $argv[1] . '/wp-load.php';
}
global $wpdb; // When run by "wp eval-file" this file executes inside a function.
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once LZCRS_DIR . 'includes/class-lzcrs-admin.php'; // Normally loaded only inside wp-admin.

$GLOBALS['lzcrs_passed'] = 0;
$GLOBALS['lzcrs_failed'] = 0;
function check( $name, $cond ) {
	if ( $cond ) {
		$GLOBALS['lzcrs_passed']++;
		echo "  ✓ $name\n";
	} else {
		$GLOBALS['lzcrs_failed']++;
		echo "  ✗ $name\n";
	}
}
$GLOBALS['lzcrs_host'] = wp_parse_url( home_url(), PHP_URL_HOST );
function cleanup() {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Lzcrs_Db::table() . ' WHERE email LIKE %s', '%@lzcrs.test' ) );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%lzcrs\\_rl\\_%'" );
}

$base = array( 'age' => 29, 'edu' => 'bachelor', 'caEdu' => 'none', 'L' => 7.5, 'R' => 6.5, 'W' => 6.5, 'S' => 6.5, 'fr' => 'none', 'caExp' => 1, 'frExp' => 3, 'sibling' => false, 'pnp' => false );
function submit( $body, $ip = '10.0.0.1' ) {
	$_SERVER['REMOTE_ADDR'] = $ip;
	$req = new WP_REST_Request( 'POST', '/lzcrs/v1/submit' );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( $body ) );
	return rest_do_request( $req );
}
function contact( $extra = array() ) {
	return array_merge( array( 'name' => 'Priya Test', 'email' => 'priya@lzcrs.test', 'phone' => '+1 604 555 0101', 'privacy' => true, 'marketing' => true, 'website' => '', 'page' => home_url( '/crs-calculator-test/' ) ), $extra );
}

// Capture emails instead of sending them.
$mails = array();
add_filter(
	'pre_wp_mail',
	function ( $null, $atts ) use ( &$mails ) {
		$mails[] = $atts;
		return true;
	},
	10,
	2
);

$saved_settings = get_option( 'lzcrs_settings' );
update_option( 'lzcrs_settings', Lzcrs_Settings::defaults() );
cleanup();

echo "Install\n";
check( 'plugin is active', is_plugin_active( 'lawzeek-crs-calculator/lawzeek-crs-calculator.php' ) );
check( 'leads table exists', (bool) $wpdb->get_var( "SHOW TABLES LIKE '" . Lzcrs_Db::table() . "'" ) );
check( 'db version stored', Lzcrs_Db::DB_VERSION === get_option( 'lzcrs_db_version' ) );
check( 'REST route registered', isset( rest_get_server()->get_routes()['/lzcrs/v1/submit'] ) );
check( 'block registered', WP_Block_Type_Registry::get_instance()->is_registered( 'lawzeek/crs-calculator' ) );
check( 'shortcode registered', shortcode_exists( 'lawzeek_crs' ) );

echo "Front end\n";
$html = do_shortcode( '[lawzeek_crs title="<b>Hi</b> & welcome"]' );
check( 'shortcode prints the container', false !== strpos( $html, 'data-lzcrs' ) );
check( 'title is escaped', false !== strpos( $html, 'data-title="Hi &amp; welcome"' ) || false !== strpos( $html, 'data-title="Hi &amp;amp; welcome"' ) );
check( 'accent colour applied with readable text', false !== strpos( $html, '--lzcrs-accent:#c8102e;--lzcrs-accent-ink:#ffffff' ) );
check( 'script enqueued only once used', wp_script_is( 'lzcrs-calculator', 'enqueued' ) );
check( 'no credit link by default', false === strpos( $html, 'lzcrs-credit' ) );
check( 'light accent gets dark text', '#13222a' === Lzcrs_Frontend::ink_for( '#ffe066' ) );
check( 'dark accent gets white text', '#ffffff' === Lzcrs_Frontend::ink_for( '#0f4c5c' ) );
$block = render_block( array( 'blockName' => 'lawzeek/crs-calculator', 'attrs' => array( 'title' => 'Block title' ), 'innerHTML' => '', 'innerBlocks' => array(), 'innerContent' => array() ) );
check( 'block renders with its heading', false !== strpos( $block, 'data-title="Block title"' ) );

echo "Submit\n";
$mails = array();
$r     = submit( array( 'answers' => $base ) + contact() );
$d     = $r->get_data();
check( 'valid submission returns 200', 200 === $r->get_status() );
check( 'returns the reference score 438', isset( $d['total'] ) && 438 === $d['total'] );
check( 'returns cutoff and breakdown', 500 === $d['cutoff'] && 362 === $d['breakdown']['core'] && 76 === $d['breakdown']['skillTransferability'] );
check( 'returns the gap plan with French first and PNP last', 'french' === $d['gaps'][0]['id'] && 'pnp' === end( $d['gaps'] )['id'] );
check( 'no call-to-action unless set', null === $d['cta'] );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Lzcrs_Db::table() . ' WHERE email = %s', 'priya@lzcrs.test' ), ARRAY_A );
check( 'lead saved', $row && 438 === (int) $row['total'] && 'cold' === $row['band'] && 'Canadian Experience Class' === $row['pathway'] );
check( 'consents saved', $row && '1' === $row['consent_privacy'] && '1' === $row['consent_marketing'] );
check( 'raw IP is never stored', $row && 64 === strlen( $row['ip_hash'] ) && false === strpos( wp_json_encode( $row ), '10.0.0.1' ) );
check( 'answers stored as JSON', $row && 29 === json_decode( $row['answers'], true )['age'] );
check( 'one alert email sent to the admin', 1 === count( $mails ) && get_option( 'admin_email' ) === $mails[0]['to'][0] );
check( 'email subject names lead and score', isset( $mails[0] ) && false !== strpos( $mails[0]['subject'], 'New CRS lead: Priya Test (438, Cold)' ) );
check( 'email links to the lead in wp-admin', isset( $mails[0] ) && false !== strpos( $mails[0]['message'], 'admin.php?page=lzcrs-leads&lead=' . $row['id'] ) );

$mails = array();
$r     = submit( array( 'answers' => $base ) + contact( array( 'name' => "Evil\r\nBcc: x@y.z", 'email' => 'evil@lzcrs.test' ) ), '10.0.0.2' );
check( 'newlines cannot inject email headers', isset( $mails[0] ) && false === strpos( $mails[0]['subject'], "\n" ) && false === strpos( $mails[0]['subject'], "\r" ) );

echo "Validation\n";
$cases = array(
	'missing name'        => array( contact( array( 'name' => '' ) ), 'lzcrs_name' ),
	'bad email'           => array( contact( array( 'email' => 'not-an-email' ) ), 'lzcrs_email' ),
	'bad phone'           => array( contact( array( 'phone' => 'call me <script>' ) ), 'lzcrs_phone' ),
	'privacy not ticked'  => array( contact( array( 'privacy' => false ) ), 'lzcrs_privacy' ),
	'privacy as a string' => array( contact( array( 'privacy' => 'true' ) ), 'lzcrs_privacy' ),
);
$i = 10;
foreach ( $cases as $name => $c ) {
	$r = submit( array( 'answers' => $base ) + $c[0], '10.0.1.' . ( $i++ ) );
	check( "$name → 422 $c[1]", 422 === $r->get_status() && $c[1] === $r->get_data()['code'] );
}
$r = submit( array( 'answers' => array_merge( $base, array( 'age' => 12 ) ) ) + contact(), '10.0.1.50' );
check( 'invalid answers → 422', 422 === $r->get_status() && 'lzcrs_answers' === $r->get_data()['code'] );
$r = submit( array( 'answers' => $base + array( 'job' => 50 ) ) + contact(), '10.0.1.51' );
check( 'unknown answer field → 422', 422 === $r->get_status() );
check( 'nothing saved for invalid submissions', 2 === Lzcrs_Db::count( array( 'search' => '@lzcrs.test' ) ) );

echo "Settings affect the form\n";
update_option( 'lzcrs_settings', array_merge( Lzcrs_Settings::defaults(), array( 'phone' => 'required' ) ) );
$r = submit( array( 'answers' => $base ) + contact( array( 'phone' => '', 'email' => 'nophone@lzcrs.test' ) ), '10.0.2.1' );
check( 'required phone is enforced on the server', 422 === $r->get_status() && 'lzcrs_phone' === $r->get_data()['code'] );
update_option( 'lzcrs_settings', array_merge( Lzcrs_Settings::defaults(), array( 'phone' => 'hidden', 'cutoff' => 430, 'cta_text' => 'Book a call', 'cta_url' => 'https://example.com/book' ) ) );
$r = submit( array( 'answers' => $base ) + contact( array( 'email' => 'hidden@lzcrs.test' ) ), '10.0.2.2' );
$d = $r->get_data();
check( 'hidden phone is not stored', null === Lzcrs_Db::by_email( 'hidden@lzcrs.test' )[0]['phone'] );
check( 'cutoff setting used', 430 === $d['cutoff'] && 'hot' === Lzcrs_Db::by_email( 'hidden@lzcrs.test' )[0]['band'] );
check( 'booking button returned when set', array( 'text' => 'Book a call', 'url' => 'https://example.com/book', 'mode' => 'button', 'embed' => null ) === $d['cta'] );
update_option( 'lzcrs_settings', Lzcrs_Settings::defaults() );

echo "Booking step\n";
update_option( 'lzcrs_settings', array_merge( Lzcrs_Settings::defaults(), array( 'cta_url' => 'https://calendly.com/northshore/30min', 'cta_mode' => 'inline' ) ) );
$b = Lzcrs_Settings::booking();
check( 'empty button text defaults to "Book a consultation"', 'Book a consultation' === $b['text'] );
check( 'Calendly link can be shown inline', 'inline' === $b['mode'] && 0 === strpos( $b['embed'], 'https://calendly.com/northshore/30min?' ) && false !== strpos( $b['embed'], 'embed_type=Inline' ) && false !== strpos( $b['embed'], 'embed_domain=' . $GLOBALS['lzcrs_host'] ) );
update_option( 'lzcrs_settings', array_merge( Lzcrs_Settings::defaults(), array( 'cta_url' => 'https://cal.com/northshore', 'cta_mode' => 'inline' ) ) );
check( 'non-Calendly links fall back to a button', 'button' === Lzcrs_Settings::booking()['mode'] && null === Lzcrs_Settings::booking()['embed'] );
check( 'calendly look-alike hosts are not trusted', ! Lzcrs_Settings::is_calendly( 'https://calendly.com.evil.example/x' ) && ! Lzcrs_Settings::is_calendly( 'http://calendly.com/x' ) && Lzcrs_Settings::is_calendly( 'https://www.calendly.com/x' ) );
update_option( 'lzcrs_settings', Lzcrs_Settings::defaults() );
check( 'no booking step without a link', null === Lzcrs_Settings::booking() );
$s3 = Lzcrs_Settings::sanitize( array( 'cta_url' => 'https://cal.com/x', 'cta_mode' => 'inline', 'cutoff' => 500, 'emails' => '', 'colors' => 'nope' ) );
check( 'saving inline mode with a non-Calendly link switches to button', 'button' === $s3['cta_mode'] );
check( 'unknown colour mode keeps the previous one', 'theme' === $s3['colors'] );
$s4 = Lzcrs_Settings::sanitize( array( 'colors' => 'custom', 'cutoff' => 500, 'emails' => '' ) );
check( 'custom colour mode can be chosen', 'custom' === $s4['colors'] );
check( 'calculator follows the theme by default', false !== strpos( Lzcrs_Frontend::shortcode(), 'data-lzcrs-colors="theme"' ) );
update_option( 'lzcrs_settings', array_merge( Lzcrs_Settings::defaults(), array( 'colors' => 'custom' ) ) );
check( 'custom colour mode is passed to the calculator', false !== strpos( Lzcrs_Frontend::shortcode(), 'data-lzcrs-colors="custom"' ) );
update_option( 'lzcrs_settings', Lzcrs_Settings::defaults() );

echo "Spam protection\n";
$before = Lzcrs_Db::count();
$mails  = array();
$r      = submit( array( 'answers' => $base ) + contact( array( 'website' => 'http://spam.example', 'email' => 'bot@lzcrs.test' ) ), '10.0.3.1' );
check( 'honeypot gets a normal-looking 200', 200 === $r->get_status() && 438 === $r->get_data()['total'] );
check( 'honeypot saves nothing and sends no email', Lzcrs_Db::count() === $before && 0 === count( $mails ) );
$codes = array();
for ( $k = 0; $k < 11; $k++ ) {
	$codes[] = submit( array( 'answers' => $base ) + contact( array( 'email' => "rl$k@lzcrs.test" ) ), '10.0.4.1' )->get_status();
}
check( '10 submissions allowed per IP, the 11th gets 429', 10 === count( array_keys( $codes, 200, true ) ) && 429 === end( $codes ) );
check( 'a different IP is not affected', 200 === submit( array( 'answers' => $base ) + contact( array( 'email' => 'other@lzcrs.test' ) ), '10.0.4.2' )->get_status() );

echo "Settings sanitize\n";
$s = Lzcrs_Settings::sanitize( array( 'emails' => 'a@lzcrs.test, b@lzcrs.test', 'cutoff' => '480', 'phone' => 'required', 'accent' => '#0F4C5C', 'cta_text' => str_repeat( 'x', 60 ), 'cta_url' => 'https://example.com', 'credit' => '1' ) );
check( 'keeps valid values', 'a@lzcrs.test, b@lzcrs.test' === $s['emails'] && 480 === $s['cutoff'] && 'required' === $s['phone'] && '#0f4c5c' === $s['accent'] && true === $s['credit'] );
check( 'trims button text to 40 characters', 40 === mb_strlen( $s['cta_text'] ) );
update_option( 'lzcrs_settings', $s );
$s2 = Lzcrs_Settings::sanitize( array( 'emails' => 'nope', 'cutoff' => '50', 'phone' => 'sometimes', 'accent' => 'red', 'cta_url' => 'javascript:alert(1)' ) );
check( 'invalid values keep the previous ones', 'a@lzcrs.test, b@lzcrs.test' === $s2['emails'] && 480 === $s2['cutoff'] && 'required' === $s2['phone'] && '#0f4c5c' === $s2['accent'] );
check( 'javascript: link is refused', 'javascript:alert(1)' !== $s2['cta_url'] && 0 !== strpos( (string) $s2['cta_url'], 'javascript' ) );
check( 'notification emails come from settings', array( 'a@lzcrs.test', 'b@lzcrs.test' ) === Lzcrs_Settings::notification_emails() );
update_option( 'lzcrs_settings', Lzcrs_Settings::defaults() );
check( 'empty notification list falls back to the admin email', array( get_option( 'admin_email' ) ) === Lzcrs_Settings::notification_emails() );
check( 'non-array input is handled', is_array( Lzcrs_Settings::sanitize( 'junk' ) ) );

echo "Leads data\n";
check( 'search finds by email', 1 === Lzcrs_Db::count( array( 'search' => 'priya@lzcrs' ) ) );
check( 'band filter works', Lzcrs_Db::count( array( 'band' => 'hot', 'search' => '@lzcrs.test' ) ) >= 1 );
check( 'unknown band filter is ignored', Lzcrs_Db::count( array( 'band' => "x' OR 1=1 --" ) ) === Lzcrs_Db::count() );
check( 'search with SQL characters is safe', 0 === Lzcrs_Db::count( array( 'search' => "%' OR '1'='1" ) ) );
check( 'sort by score asc returns lowest first', (int) Lzcrs_Db::query( array( 'search' => '@lzcrs.test' ), 'total', 'asc', 1 )[0]['total'] <= 438 );
check( 'unknown sort column falls back safely', is_array( Lzcrs_Db::query( array(), 'email; DROP TABLE x', 'sideways', 5 ) ) );
$id = (int) $row['id'];
check( 'status can be changed', Lzcrs_Db::set_status( $id, 'booked' ) && 'booked' === Lzcrs_Db::get( $id )['status'] );
check( 'unknown status is refused', false === Lzcrs_Db::set_status( $id, 'deleted' ) && 'booked' === Lzcrs_Db::get( $id )['status'] );
check( 'CSV cells that look like formulas are neutralised', "'=HYPERLINK(1)" === Lzcrs_Admin::csv_cell( '=HYPERLINK(1)' ) && "'+1" === Lzcrs_Admin::csv_cell( '+1' ) && 'Priya' === Lzcrs_Admin::csv_cell( 'Priya' ) );

echo "Privacy tools\n";
$exp = Lzcrs_Privacy::export( 'priya@lzcrs.test' );
check( 'exporter returns the lead', 1 === count( $exp['data'] ) && $exp['done'] );
check( 'exporter is registered with WordPress', isset( apply_filters( 'wp_privacy_personal_data_exporters', array() )['lawzeek-crs-calculator'] ) );
$er = Lzcrs_Privacy::erase( 'priya@lzcrs.test' );
check( 'eraser removes the lead', $er['items_removed'] && 0 === count( Lzcrs_Db::by_email( 'priya@lzcrs.test' ) ) );
check( 'eraser is registered with WordPress', isset( apply_filters( 'wp_privacy_personal_data_erasers', array() )['lawzeek-crs-calculator'] ) );

echo "Admin screens\n";
$admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
wp_set_current_user( $admins ? $admins[0]->ID : 0 );
set_current_screen( 'toplevel_page_lzcrs-leads' );
$lead = Lzcrs_Db::by_email( 'hidden@lzcrs.test' )[0];
ob_start();
Lzcrs_Admin::leads_page();
$list = ob_get_clean();
check( 'leads screen lists leads', false !== strpos( $list, 'hidden@lzcrs.test' ) && false !== strpos( $list, 'Export CSV' ) );
check( 'leads screen has a bulk-delete nonce', false !== strpos( $list, 'name="_wpnonce"' ) );
$_GET['lead'] = $lead['id'];
ob_start();
Lzcrs_Admin::leads_page();
$detail = ob_get_clean();
unset( $_GET['lead'] );
check( 'lead screen shows profile, status form and gap plan', false !== strpos( $detail, 'Profile and score' ) && false !== strpos( $detail, 'name="status"' ) && false !== strpos( $detail, 'Fastest ways' ) );
check( 'lead screen escapes the name', false !== strpos( $detail, esc_html( $lead['name'] ) ) );
ob_start();
Lzcrs_Admin::settings_page();
$settings = ob_get_clean();
check( 'settings has numbered sections incl. Booking with both display options', false !== strpos( $settings, 'id="lzcrs-booking"' ) && false !== strpos( $settings, 'lzcrs_settings[cta_mode]" value="inline"' ) && false !== strpos( $settings, 'lzcrs_settings[colors]" value="theme"' ) && false !== strpos( $settings, '>03</span>' ) );
check( 'settings screen renders all fields', false !== strpos( $settings, 'lzcrs_settings[cutoff]' ) && false !== strpos( $settings, 'lzcrs_settings[delete_on_uninstall]' ) );
check( 'info box shows on plugin pages', false !== strpos( $settings, 'lzcrs-info' ) );
update_user_meta( get_current_user_id(), 'lzcrs_dismissed_info', 1 );
ob_start();
Lzcrs_Admin::settings_page();
check( 'info box stays hidden after dismissing', false === strpos( ob_get_clean(), 'lzcrs-info' ) );
delete_user_meta( get_current_user_id(), 'lzcrs_dismissed_info' );
wp_set_current_user( 0 );
ob_start();
Lzcrs_Admin::leads_page();
check( 'visitors without permission see nothing', '' === ob_get_clean() );

echo "Branding and dashboard\n";
wp_set_current_user( $admins ? $admins[0]->ID : 0 ); // menus and screens are only built for admins
global $menu, $submenu;
$menu    = array();
$submenu = array();
Lzcrs_Admin::menu();
$top = null;
foreach ( $menu as $m ) {
	if ( 'lzcrs-dashboard' === $m[2] ) {
		$top = $m;
	}
}
check( 'sidebar menu is called "Lawzeek"', $top && 'Lawzeek' === $top[0] );
check( 'sidebar menu uses the Lawzeek SVG icon', $top && 0 === strpos( $top[6], 'data:image/svg+xml;base64,' ) && false !== strpos( base64_decode( substr( $top[6], 26 ) ), '<svg' ) );
check( 'submenu: Dashboard, Leads, Settings', array( 'Dashboard', 'Leads', 'Settings' ) === wp_list_pluck( $submenu['lzcrs-dashboard'], 0 ) );
check( 'logo file is in the plugin', false !== strpos( (string) file_get_contents( LZCRS_DIR . 'assets/images/logo.svg' ), 'aria-label="Lawzeek"' ) );
ob_start();
Lzcrs_Admin::dashboard_page();
$dash = ob_get_clean();
check( 'dashboard shows the app shell with logo and tabs', false !== strpos( $dash, 'class="lzcrs-app"' ) && false !== strpos( $dash, 'assets/images/logo.svg' ) && false !== strpos( $dash, 'aria-current="page">Dashboard' ) );
check( 'dashboard opens with the highlighted shortcode', strpos( $dash, 'data-testid="lzcrs-shortcode"' ) !== false && strpos( $dash, 'data-testid="lzcrs-shortcode"' ) < strpos( $dash, 'data-testid="lzcrs-stats"' ) && false !== strpos( $dash, 'data-lzcrs-copy="[lawzeek_crs]"' ) );
check( 'top bar shows the calculator is live', false !== strpos( $dash, 'lzcrs-live on' ) && false !== strpos( $dash, 'Live on your site' ) );
check( 'dashboard shows stats and the checklist', false !== strpos( $dash, 'data-testid="lzcrs-stats"' ) && false !== strpos( $dash, 'Leads this month' ) && false !== strpos( $dash, 'data-testid="lzcrs-checklist"' ) );
check( 'dashboard knows a page shows the calculator', Lzcrs_Admin::calculator_page() > 0 && false !== strpos( $dash, 'View page' ) );
$stats = Lzcrs_Db::stats( gmdate( 'Y-m-01 00:00:00' ) );
check( 'stats add up', $stats['total'] >= $stats['recent'] && $stats['total'] >= $stats['hot'] && ( null === $stats['avg'] || $stats['avg'] > 0 ) );
$css = (string) file_get_contents( LZCRS_DIR . 'assets/css/admin.css' );
$css_rules = preg_replace( '/@keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', preg_replace( '#/\*.*?\*/#s', '', $css ) );
preg_match_all( '/(^|\})\s*([^{}@]+)\{/', $css_rules, $sel );
$unscoped = array_filter( array_map( 'trim', explode( ',', implode( ',', $sel[2] ) ) ), function ( $x ) { return '' !== $x && 0 !== strpos( $x, '.lzcrs-app' ) && ! preg_match( '/^:is\(\s*\.lzcrs-app\s/', $x ); } );
check( 'every admin CSS rule is scoped to .lzcrs-app (WordPress styles untouched)', strlen( $css ) > 5000 && ! $unscoped );
wp_set_current_user( 0 );
if ( $unscoped ) {
	echo '    unscoped: ' . implode( ' | ', array_slice( $unscoped, 0, 5 ) ) . "\n";
}

echo "Uninstall\n";
cleanup();
update_option( 'lzcrs_settings', Lzcrs_Settings::defaults() );
$uninstall = LZCRS_DIR . 'uninstall.php';
$code      = file_get_contents( $uninstall );
check( 'uninstall refuses to run when called directly', false !== strpos( $code, "defined( 'WP_UNINSTALL_PLUGIN' )" ) );
define( 'WP_UNINSTALL_PLUGIN', 'lawzeek-crs-calculator/lawzeek-crs-calculator.php' );
include $uninstall;
check( 'by default, uninstall keeps leads and settings', (bool) $wpdb->get_var( "SHOW TABLES LIKE '" . Lzcrs_Db::table() . "'" ) && false !== get_option( 'lzcrs_settings', false ) );
update_option( 'lzcrs_settings', array_merge( Lzcrs_Settings::defaults(), array( 'delete_on_uninstall' => true ) ) );
include $uninstall;
check( 'with "delete all data", uninstall drops the table and options', ! $wpdb->get_var( "SHOW TABLES LIKE '" . Lzcrs_Db::table() . "'" ) && false === get_option( 'lzcrs_settings', false ) );
// Put everything back for the next run.
Lzcrs_Db::install();
update_option( 'lzcrs_settings', is_array( $saved_settings ) ? $saved_settings : Lzcrs_Settings::defaults() );
check( 'table restored after the uninstall test', (bool) $wpdb->get_var( "SHOW TABLES LIKE '" . Lzcrs_Db::table() . "'" ) );

echo "\n{$GLOBALS['lzcrs_passed']} passed, {$GLOBALS['lzcrs_failed']} failed\n";
exit( $GLOBALS['lzcrs_failed'] ? 1 : 0 );
