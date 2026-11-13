<?php
/**
 * Prepares a WordPress for the tests: plugin active, leads table present, a page with the calculator block,
 * and rate-limit counters cleared (every test run comes from the same IP).
 * Run: npm run test:setup   (or: php tests/bin/setup.php /path/to/wordpress)
 *
 * @package LawzeekCrsCalculator
 */

// phpcs:ignoreFile -- test script, not shipped in the plugin.

if ( ! defined( 'ABSPATH' ) ) {
	$_SERVER['HTTP_HOST']   = getenv( 'LZCRS_TEST_HOST' ) ? getenv( 'LZCRS_TEST_HOST' ) : 'localhost';
	$_SERVER['REQUEST_URI'] = '/';
	define( 'WP_USE_THEMES', false );
	require $argv[1] . '/wp-load.php';
}
global $wpdb;
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! class_exists( 'Lzcrs_Db' ) ) {
	fwrite( STDERR, "Lawzeek CRS Calculator is not active.\n" );
	exit( 1 );
}
Lzcrs_Db::maybe_upgrade();
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%lzcrs\\_rl\\_%'" );
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
$page = get_page_by_path( 'crs-calculator-test' );
$data = array(
	'post_title'   => 'CRS calculator test',
	'post_name'    => 'crs-calculator-test',
	'post_content' => "<!-- wp:paragraph --><p>Plugin test page.</p><!-- /wp:paragraph -->\n<!-- wp:lawzeek/crs-calculator {\"title\":\"Check your Canada PR chances\"} /-->",
	'post_status'  => 'publish',
	'post_type'    => 'page',
);
if ( $page ) {
	$data['ID'] = $page->ID;
	wp_update_post( $data );
} else {
	wp_insert_post( $data );
}
echo 'Test page: ' . get_permalink( get_page_by_path( 'crs-calculator-test' ) ) . "\n";
