<?php
/**
 * WordPress privacy tools: export and erase a visitor's leads by email, and suggested policy text.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks into Tools → Export / Erase Personal Data.
 */
class Lzcrs_Privacy {
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy_text' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['lawzeek-crs-calculator'] = array(
			'exporter_friendly_name' => __( 'CRS Calculator leads', 'lawzeek-crs-calculator' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['lawzeek-crs-calculator'] = array(
			'eraser_friendly_name' => __( 'CRS Calculator leads', 'lawzeek-crs-calculator' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function export( $email, $page = 1 ) {
		$items = array();
		foreach ( Lzcrs_Db::by_email( $email ) as $row ) {
			$data = array();
			foreach ( array( 'created_at', 'name', 'email', 'phone', 'answers', 'total', 'band', 'pathway', 'status', 'consent_privacy', 'consent_marketing', 'consent_at', 'page_url' ) as $k ) {
				$data[] = array( 'name' => $k, 'value' => (string) $row[ $k ] );
			}
			$items[] = array(
				'group_id'    => 'lzcrs-leads',
				'group_label' => __( 'CRS Calculator leads', 'lawzeek-crs-calculator' ),
				'item_id'     => 'lzcrs-lead-' . $row['id'],
				'data'        => $data,
			);
		}
		return array( 'data' => $items, 'done' => true );
	}

	public static function erase( $email, $page = 1 ) {
		$ids     = wp_list_pluck( Lzcrs_Db::by_email( $email ), 'id' );
		$removed = Lzcrs_Db::delete( $ids );
		return array( 'items_removed' => $removed > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}

	public static function policy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p>' . esc_html__( 'When you use the CRS calculator on this website, we collect your answers (age, education, language test scores, work experience and similar), your name, email address, and optionally your phone number. We use them to calculate your estimated CRS score and to contact you about your immigration options. If you opt in, we may also send you updates. We store a one-way coded version of your IP address to block spam; we do not store the IP address itself. Your data is stored on this website only and is not sent to Lawzeek or anyone else by the calculator. You can ask us to export or delete your data at any time.', 'lawzeek-crs-calculator' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Lawzeek CRS Calculator', 'lawzeek-crs-calculator' ), wp_kses_post( $text ) );
	}
}
