<?php
/**
 * Runs when the plugin is deleted. Leads are kept unless the site owner chose to delete them.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$lzcrs_settings = get_option( 'lzcrs_settings', array() );
if ( is_array( $lzcrs_settings ) && ! empty( $lzcrs_settings['delete_on_uninstall'] ) ) {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}lzcrs_leads" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	delete_option( 'lzcrs_settings' );
	delete_option( 'lzcrs_db_version' );
	delete_metadata( 'user', 0, 'lzcrs_dismissed_info', '', true );
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_lzcrs\\_%' OR option_name LIKE '\\_transient\\_timeout\\_lzcrs\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
