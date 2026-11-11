<?php
/**
 * Shortcode [lawzeek_crs], the block, and front-end assets (loaded only where the calculator is used).
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the calculator container and loads its script.
 */
class Lzcrs_Frontend {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		wp_register_style( 'lzcrs-calculator', LZCRS_URL . 'assets/css/calculator.css', array(), LZCRS_VERSION );
		wp_register_script( 'lzcrs-calculator', LZCRS_URL . 'assets/js/calculator.js', array(), LZCRS_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		add_shortcode( 'lawzeek_crs', array( __CLASS__, 'shortcode' ) );
		register_block_type(
			LZCRS_DIR . 'blocks/crs-calculator',
			array( 'render_callback' => array( __CLASS__, 'render_block' ) )
		);
		load_plugin_textdomain( 'lawzeek-crs-calculator', false, dirname( plugin_basename( LZCRS_FILE ) ) . '/languages' );
	}

	/** @param array $attrs Block attributes. */
	public static function render_block( $attrs ) {
		return self::shortcode( array( 'title' => isset( $attrs['title'] ) ? (string) $attrs['title'] : '' ) );
	}

	/** Black or white text, whichever reads better on the accent colour. */
	public static function ink_for( $hex ) {
		$n   = hexdec( ltrim( $hex, '#' ) );
		$lin = function ( $v ) {
			$c = $v / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		$l = 0.2126 * $lin( ( $n >> 16 ) & 255 ) + 0.7152 * $lin( ( $n >> 8 ) & 255 ) + 0.0722 * $lin( $n & 255 );
		return $l > 0.4 ? '#13222a' : '#ffffff';
	}

	/** @param array|string $atts Shortcode attributes. */
	public static function shortcode( $atts = array() ) {
		$atts = shortcode_atts( array( 'title' => '' ), is_array( $atts ) ? $atts : array(), 'lawzeek_crs' );
		$s    = Lzcrs_Settings::get();
		self::enqueue();
		$title  = '' !== trim( $atts['title'] ) ? sanitize_text_field( $atts['title'] ) : __( 'Check your CRS score', 'lawzeek-crs-calculator' );
		$accent = sanitize_hex_color( $s['accent'] ) ? $s['accent'] : '#c8102e';
		$style  = sprintf( '--lzcrs-accent:%s;--lzcrs-accent-ink:%s', $accent, self::ink_for( $accent ) );
		$credit = $s['credit']
			? '<p class="lzcrs-credit"><a href="https://wordpress.org/plugins/lawzeek-crs-calculator/" rel="noopener">' . esc_html__( 'CRS calculator by Lawzeek', 'lawzeek-crs-calculator' ) . '</a></p>'
			: '';
		return sprintf(
			'<div class="lzcrs" data-lzcrs data-title="%1$s" style="%2$s"><noscript><p>%3$s</p></noscript></div>%4$s',
			esc_attr( $title ),
			esc_attr( $style ),
			esc_html__( 'Please turn on JavaScript to use the CRS calculator.', 'lawzeek-crs-calculator' ),
			$credit
		);
	}

	public static function enqueue() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		$s    = Lzcrs_Settings::get();
		wp_enqueue_style( 'lzcrs-calculator' );
		wp_enqueue_script( 'lzcrs-calculator' );
		$opts = function ( $map ) {
			$out = array();
			foreach ( $map as $v => $l ) {
				$out[] = array( $v, $l );
			}
			return $out;
		};
		wp_localize_script(
			'lzcrs-calculator',
			'lzcrsData',
			array(
				'endpoint'   => esc_url_raw( rest_url( 'lzcrs/v1/submit' ) ),
				'phone'      => $s['phone'],
				'privacyUrl' => Lzcrs_Settings::privacy_url(),
				'site'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'options'    => array(
					'edu'   => $opts( Lzcrs_Labels::education() ),
					'caEdu' => $opts( Lzcrs_Labels::canadian_education() ),
					'fr'    => $opts( Lzcrs_Labels::french() ),
					'caExp' => array(
						array( 0, __( 'None or under 1 year', 'lawzeek-crs-calculator' ) ),
						array( 1, __( '1 year', 'lawzeek-crs-calculator' ) ),
						array( 2, __( '2 years', 'lawzeek-crs-calculator' ) ),
						array( 3, __( '3 years', 'lawzeek-crs-calculator' ) ),
						array( 4, __( '4 years', 'lawzeek-crs-calculator' ) ),
						array( 5, __( '5 years or more', 'lawzeek-crs-calculator' ) ),
					),
					'frExp' => array(
						array( 0, __( 'None or under 1 year', 'lawzeek-crs-calculator' ) ),
						array( 1, __( '1 year', 'lawzeek-crs-calculator' ) ),
						array( 2, __( '2 years', 'lawzeek-crs-calculator' ) ),
						array( 3, __( '3 years or more', 'lawzeek-crs-calculator' ) ),
					),
				),
				'i18n'       => self::strings(),
			)
		);
	}

	/** Every text the script shows, translatable from PHP. */
	public static function strings() {
		return array(
			'titles'     => array(
				__( 'About you', 'lawzeek-crs-calculator' ),
				__( 'Education', 'lawzeek-crs-calculator' ),
				__( 'Language tests', 'lawzeek-crs-calculator' ),
				__( 'Work experience', 'lawzeek-crs-calculator' ),
				__( 'A few extras', 'lawzeek-crs-calculator' ),
				__( 'Your result is ready', 'lawzeek-crs-calculator' ),
				__( 'Your CRS estimate', 'lawzeek-crs-calculator' ),
			),
			/* translators: %d: step number from 1 to 5 */
			'stepOf'     => __( 'Step %d of 5', 'lawzeek-crs-calculator' ),
			'almost'     => __( 'Almost done', 'lawzeek-crs-calculator' ),
			'done'       => __( 'Done', 'lawzeek-crs-calculator' ),
			'back'       => __( 'Back', 'lawzeek-crs-calculator' ),
			'next'       => __( 'Next', 'lawzeek-crs-calculator' ),
			'showScore'  => __( 'Show my score', 'lawzeek-crs-calculator' ),
			'saving'     => __( 'Calculating…', 'lawzeek-crs-calculator' ),
			'restart'    => __( 'Start over', 'lawzeek-crs-calculator' ),
			'age'        => __( 'Your age', 'lawzeek-crs-calculator' ),
			'ageHint'    => __( 'Points peak between 20 and 29.', 'lawzeek-crs-calculator' ),
			'single'     => __( 'This calculator scores single applicants. A consultant can review spouse factors with you.', 'lawzeek-crs-calculator' ),
			'edu'        => __( 'Highest education', 'lawzeek-crs-calculator' ),
			'caEdu'      => __( 'Education completed in Canada', 'lawzeek-crs-calculator' ),
			'ielts'      => __( 'IELTS General Training scores', 'lawzeek-crs-calculator' ),
			'L'          => __( 'Listening', 'lawzeek-crs-calculator' ),
			'R'          => __( 'Reading', 'lawzeek-crs-calculator' ),
			'W'          => __( 'Writing', 'lawzeek-crs-calculator' ),
			'S'          => __( 'Speaking', 'lawzeek-crs-calculator' ),
			'below4'     => __( 'Below 4', 'lawzeek-crs-calculator' ),
			'fr'         => __( 'French test', 'lawzeek-crs-calculator' ),
			'caExp'      => __( 'Skilled work experience in Canada', 'lawzeek-crs-calculator' ),
			'frExp'      => __( 'Skilled work experience outside Canada', 'lawzeek-crs-calculator' ),
			'frExpHint'  => __( 'Last 10 years, full-time, TEER 0–3 jobs.', 'lawzeek-crs-calculator' ),
			'pnp'        => __( 'I have a provincial nomination', 'lawzeek-crs-calculator' ),
			'pnpHint'    => __( 'A PNP certificate from a province or territory', 'lawzeek-crs-calculator' ),
			'sibling'    => __( 'I have a brother or sister in Canada', 'lawzeek-crs-calculator' ),
			'siblingHint' => __( 'Citizen or permanent resident, 18 or older', 'lawzeek-crs-calculator' ),
			'teaser'     => __( 'Enter your details to see your score, a full breakdown and the fastest ways to raise it.', 'lawzeek-crs-calculator' ),
			'name'       => __( 'Full name', 'lawzeek-crs-calculator' ),
			'email'      => __( 'Email', 'lawzeek-crs-calculator' ),
			'phone'      => __( 'Phone number', 'lawzeek-crs-calculator' ),
			'optional'   => __( '(optional)', 'lawzeek-crs-calculator' ),
			/* translators: %s: website name */
			'privacy'    => __( '%s may use my answers to assess my options and contact me about them.', 'lawzeek-crs-calculator' ),
			'privacyLink' => __( 'Privacy policy', 'lawzeek-crs-calculator' ),
			'marketing'  => __( 'Send me updates that could affect my chances.', 'lawzeek-crs-calculator' ),
			'marketingHint' => __( 'Optional. You can stop them at any time.', 'lawzeek-crs-calculator' ),
			'website'    => __( 'Leave this field empty', 'lawzeek-crs-calculator' ),
			'estimate'   => __( 'Estimated CRS score', 'lawzeek-crs-calculator' ),
			/* translators: 1: points difference, 2: recent cutoff score */
			'above'      => __( '%1$d above the recent cutoff of %2$d', 'lawzeek-crs-calculator' ),
			/* translators: 1: points difference, 2: recent cutoff score */
			'below'      => __( '%1$d below the recent cutoff of %2$d', 'lawzeek-crs-calculator' ),
			'fastest'    => __( 'Fastest ways to raise your score', 'lawzeek-crs-calculator' ),
			'reaches'    => __( 'reaches the cutoff', 'lawzeek-crs-calculator' ),
			'breakdown'  => __( 'Full breakdown', 'lawzeek-crs-calculator' ),
			'rows'       => array(
				'age'                  => __( 'Age', 'lawzeek-crs-calculator' ),
				'education'            => __( 'Education', 'lawzeek-crs-calculator' ),
				'firstLanguage'        => __( 'English', 'lawzeek-crs-calculator' ),
				'secondLanguage'       => __( 'French', 'lawzeek-crs-calculator' ),
				'canadianWork'         => __( 'Canadian work experience', 'lawzeek-crs-calculator' ),
				'core'                 => __( 'Core human capital', 'lawzeek-crs-calculator' ),
				'skillTransferability' => __( 'Skill transferability', 'lawzeek-crs-calculator' ),
				'additional'           => __( 'Additional points', 'lawzeek-crs-calculator' ),
				'total'                => __( 'Total', 'lawzeek-crs-calculator' ),
			),
			/* translators: %s: website name */
			'disclaimer' => __( 'This is an estimate, not legal advice. Your official score is set by IRCC when you submit an Express Entry profile. Provided by %s.', 'lawzeek-crs-calculator' ),
			'errAge'     => __( 'Enter an age between 16 and 80.', 'lawzeek-crs-calculator' ),
			'errName'    => __( 'Enter your name.', 'lawzeek-crs-calculator' ),
			'errEmail'   => __( 'Enter a valid email address, like name@example.com.', 'lawzeek-crs-calculator' ),
			'errPhone'   => __( 'Enter your phone number.', 'lawzeek-crs-calculator' ),
			'errPrivacy' => __( 'Tick the box to agree to be contacted, so we can show your result.', 'lawzeek-crs-calculator' ),
			'errServer'  => __( 'Something went wrong. Please try again.', 'lawzeek-crs-calculator' ),
			'errNetwork' => __( 'Could not reach the website. Check your connection and try again.', 'lawzeek-crs-calculator' ),
		);
	}
}
