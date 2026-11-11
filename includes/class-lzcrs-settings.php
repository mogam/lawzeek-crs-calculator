<?php
/**
 * Plugin settings: stored in one option, validated on save.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings defaults, reading and sanitizing.
 */
class Lzcrs_Settings {
	const OPTION = 'lzcrs_settings';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/** @return array */
	public static function defaults() {
		return array(
			'emails'         => '',
			'cutoff'         => 500,
			'phone'          => 'optional',
			'accent'         => '#c8102e',
			'cta_text'       => '',
			'cta_url'        => '',
			'privacy_page'   => 0,
			'credit'         => false,
			'delete_on_uninstall' => false,
		);
	}

	/** Saved settings merged with defaults. */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	/** Email addresses that get new-lead alerts; falls back to the site admin. */
	public static function notification_emails() {
		$list = array_filter( array_map( 'trim', explode( ',', (string) self::get()['emails'] ) ), 'is_email' );
		return $list ? array_values( $list ) : array( get_option( 'admin_email' ) );
	}

	/** URL of the privacy policy linked from the consent checkbox. */
	public static function privacy_url() {
		$id = (int) self::get()['privacy_page'];
		if ( ! $id ) {
			$id = (int) get_option( 'wp_page_for_privacy_policy' );
		}
		return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : '';
	}

	public static function register() {
		register_setting(
			'lzcrs',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Keeps only valid values. Anything invalid keeps the previous value and shows a message.
	 *
	 * @param mixed $in Raw form input.
	 * @return array
	 */
	public static function sanitize( $in ) {
		$in   = is_array( $in ) ? wp_unslash( $in ) : array();
		$old  = self::get();
		$out  = $old;
		$warn = function ( $code, $msg ) {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( self::OPTION, $code, $msg );
			}
		};

		$emails = isset( $in['emails'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $in['emails'] ) ) ) : array();
		$bad    = array_filter( $emails, function ( $e ) { return ! is_email( $e ); } );
		if ( $bad ) {
			$warn( 'emails', __( 'Some notification emails are not valid. Use addresses like name@example.com, separated by commas.', 'lawzeek-crs-calculator' ) );
		} else {
			$out['emails'] = implode( ', ', array_map( 'sanitize_email', $emails ) );
		}

		$cutoff = isset( $in['cutoff'] ) ? filter_var( $in['cutoff'], FILTER_VALIDATE_INT ) : false;
		if ( false === $cutoff || $cutoff < 200 || $cutoff > 1200 ) {
			$warn( 'cutoff', __( 'The cutoff must be a whole number between 200 and 1200.', 'lawzeek-crs-calculator' ) );
		} else {
			$out['cutoff'] = $cutoff;
		}

		$out['phone'] = isset( $in['phone'] ) && in_array( $in['phone'], array( 'optional', 'required', 'hidden' ), true ) ? $in['phone'] : $old['phone'];

		$accent = isset( $in['accent'] ) ? sanitize_hex_color( $in['accent'] ) : null;
		if ( $accent && 7 === strlen( $accent ) ) {
			$out['accent'] = strtolower( $accent );
		}

		$out['cta_text'] = isset( $in['cta_text'] ) ? mb_substr( sanitize_text_field( $in['cta_text'] ), 0, 40 ) : '';
		$url             = isset( $in['cta_url'] ) ? trim( (string) $in['cta_url'] ) : '';
		$clean           = $url ? esc_url_raw( $url, array( 'http', 'https' ) ) : '';
		if ( $url && ! $clean ) {
			$warn( 'cta_url', __( 'The button link must start with http:// or https://.', 'lawzeek-crs-calculator' ) );
		} else {
			$out['cta_url'] = $clean;
		}

		$page                       = isset( $in['privacy_page'] ) ? absint( $in['privacy_page'] ) : 0;
		$out['privacy_page']        = $page && 'page' === get_post_type( $page ) ? $page : 0;
		$out['credit']              = ! empty( $in['credit'] );
		$out['delete_on_uninstall'] = ! empty( $in['delete_on_uninstall'] );
		return $out;
	}
}
