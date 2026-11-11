<?php
/**
 * Public REST endpoint the calculator posts to.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * POST /wp-json/lzcrs/v1/submit — scores the answers, saves the lead, emails the site owner.
 */
class Lzcrs_Rest {
	const RATE_LIMIT  = 10;
	const RATE_WINDOW = 600; // Seconds.

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'lzcrs/v1',
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'submit' ),
				// Public form, like a contact form: protected by validation, a honeypot and a rate limit.
				'permission_callback' => '__return_true',
			)
		);
	}

	private static function error( $code, $message, $status ) {
		return new WP_REST_Response( array( 'code' => $code, 'message' => $message ), $status );
	}

	/** Salted hash of the visitor IP. The raw IP is never stored. */
	public static function ip_hash() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return hash( 'sha256', $ip . wp_salt( 'nonce' ) );
	}

	/** True when this IP is still under the limit. Counts the attempt. */
	public static function within_rate_limit( $ip_hash ) {
		$key   = 'lzcrs_rl_' . substr( $ip_hash, 0, 32 );
		$count = (int) get_transient( $key );
		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, self::RATE_WINDOW );
		return true;
	}

	/**
	 * Validates contact fields.
	 *
	 * @param array $p Request params.
	 * @return array|WP_REST_Response Clean contact or an error response.
	 */
	public static function contact( array $p ) {
		$s     = Lzcrs_Settings::get();
		$name  = isset( $p['name'] ) && is_string( $p['name'] ) ? trim( sanitize_text_field( $p['name'] ) ) : '';
		$email = isset( $p['email'] ) && is_string( $p['email'] ) ? trim( $p['email'] ) : '';
		$phone = isset( $p['phone'] ) && is_string( $p['phone'] ) ? trim( $p['phone'] ) : '';

		if ( '' === $name || mb_strlen( $name ) > 120 ) {
			return self::error( 'lzcrs_name', __( 'Enter your name.', 'lawzeek-crs-calculator' ), 422 );
		}
		if ( ! is_email( $email ) || strlen( $email ) > 200 ) {
			return self::error( 'lzcrs_email', __( 'Enter a valid email address, like name@example.com.', 'lawzeek-crs-calculator' ), 422 );
		}
		if ( 'hidden' === $s['phone'] ) {
			$phone = '';
		} elseif ( '' === $phone && 'required' === $s['phone'] ) {
			return self::error( 'lzcrs_phone', __( 'Enter your phone number.', 'lawzeek-crs-calculator' ), 422 );
		} elseif ( '' !== $phone && ( strlen( $phone ) > 30 || ! preg_match( '/^[+0-9 ()-]+$/', $phone ) ) ) {
			return self::error( 'lzcrs_phone', __( 'Use digits, spaces and + only in the phone number.', 'lawzeek-crs-calculator' ), 422 );
		}
		if ( true !== ( isset( $p['privacy'] ) ? $p['privacy'] : null ) ) {
			return self::error( 'lzcrs_privacy', __( 'Tick the box to agree to be contacted, so we can show your result.', 'lawzeek-crs-calculator' ), 422 );
		}
		return array(
			'name'      => $name,
			'email'     => strtolower( sanitize_email( $email ) ),
			'phone'     => $phone,
			'marketing' => isset( $p['marketing'] ) && true === $p['marketing'],
		);
	}

	/**
	 * The result the visitor sees.
	 *
	 * @param array $a Answers.
	 */
	public static function result( array $a ) {
		$s      = Lzcrs_Settings::get();
		$cutoff = (int) $s['cutoff'];
		$b      = Lzcrs_Scoring::calculate( $a );
		$cta    = ( '' !== $s['cta_text'] && '' !== $s['cta_url'] ) ? array( 'text' => $s['cta_text'], 'url' => $s['cta_url'] ) : null;
		return array(
			'total'     => $b['total'],
			'cutoff'    => $cutoff,
			'breakdown' => array_intersect_key( $b, array_flip( array( 'age', 'education', 'firstLanguage', 'secondLanguage', 'canadianWork', 'core', 'skillTransferability', 'additional' ) ) ),
			'gaps'      => Lzcrs_Scoring::gap_plan( $a, $cutoff ),
			'cta'       => $cta,
		);
	}

	public static function submit( WP_REST_Request $req ) {
		$p = $req->get_json_params();
		if ( ! is_array( $p ) ) {
			return self::error( 'lzcrs_bad_request', __( 'Something went wrong. Please try again.', 'lawzeek-crs-calculator' ), 400 );
		}
		$answers = Lzcrs_Scoring::validate( isset( $p['answers'] ) ? $p['answers'] : null );
		if ( null === $answers ) {
			return self::error( 'lzcrs_answers', __( 'Some answers are not valid. Please check them and try again.', 'lawzeek-crs-calculator' ), 422 );
		}

		$ip_hash = self::ip_hash();
		if ( ! self::within_rate_limit( $ip_hash ) ) {
			return self::error( 'lzcrs_rate_limited', __( 'Too many attempts. Please try again in a few minutes.', 'lawzeek-crs-calculator' ), 429 );
		}

		// Honeypot: a hidden field people never see. Bots that fill it get a normal-looking answer and nothing is saved.
		if ( ! empty( $p['website'] ) ) {
			return new WP_REST_Response( self::result( $answers ), 200 );
		}

		$contact = self::contact( $p );
		if ( $contact instanceof WP_REST_Response ) {
			return $contact;
		}

		$b      = Lzcrs_Scoring::calculate( $answers );
		$cutoff = (int) Lzcrs_Settings::get()['cutoff'];
		$band   = Lzcrs_Scoring::lead_band( $b['total'], $answers, $cutoff );
		$path   = Lzcrs_Scoring::likely_pathway( $answers, $b );
		$page   = isset( $p['page'] ) && is_string( $p['page'] ) ? esc_url_raw( $p['page'], array( 'http', 'https' ) ) : '';
		$now    = gmdate( 'Y-m-d H:i:s' );

		$id = Lzcrs_Db::insert(
			array(
				'created_at'        => $now,
				'name'              => $contact['name'],
				'email'             => $contact['email'],
				'phone'             => '' === $contact['phone'] ? null : $contact['phone'],
				'answers'           => wp_json_encode( $answers ),
				'total'             => $b['total'],
				'band'              => $band,
				'pathway'           => $path,
				'rules_version'     => Lzcrs_Rules::VERSION,
				'status'            => 'new',
				'consent_privacy'   => 1,
				'consent_marketing' => $contact['marketing'] ? 1 : 0,
				'consent_at'        => $now,
				'page_url'          => mb_substr( $page, 0, 255 ),
				'ip_hash'           => $ip_hash,
			)
		);
		if ( ! $id ) {
			return self::error( 'lzcrs_save_failed', __( 'We could not save your details. Please try again.', 'lawzeek-crs-calculator' ), 500 );
		}
		self::notify( $id, $contact, $b['total'], $band, $path );
		return new WP_REST_Response( self::result( $answers ), 200 );
	}

	/** Plain-text email to the site owner. Newlines are stripped from header values. */
	public static function notify( $id, array $c, $total, $band, $pathway ) {
		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$clean   = function ( $v ) { return trim( str_replace( array( "\r", "\n" ), ' ', (string) $v ) ); };
		$subject = sprintf(
			/* translators: 1: site name, 2: visitor name, 3: CRS score, 4: lead band (Hot, Warm or Cold) */
			__( '[%1$s] New CRS lead: %2$s (%3$d, %4$s)', 'lawzeek-crs-calculator' ),
			$clean( $site ),
			$clean( $c['name'] ),
			$total,
			Lzcrs_Labels::band( $band )
		);
		$lines = array(
			/* translators: %s: visitor name */
			sprintf( __( 'Name: %s', 'lawzeek-crs-calculator' ), $c['name'] ),
			/* translators: %s: visitor email */
			sprintf( __( 'Email: %s', 'lawzeek-crs-calculator' ), $c['email'] ),
			/* translators: %s: visitor phone */
			sprintf( __( 'Phone: %s', 'lawzeek-crs-calculator' ), '' === $c['phone'] ? '-' : $c['phone'] ),
			/* translators: %d: CRS score */
			sprintf( __( 'Estimated CRS score: %d', 'lawzeek-crs-calculator' ), $total ),
			/* translators: %s: likely immigration pathway */
			sprintf( __( 'Likely pathway: %s', 'lawzeek-crs-calculator' ), Lzcrs_Labels::pathway( $pathway ) ),
			'',
			/* translators: %s: link to the lead in the WordPress admin */
			sprintf( __( 'Open the lead: %s', 'lawzeek-crs-calculator' ), admin_url( 'admin.php?page=lzcrs-leads&lead=' . (int) $id ) ),
		);
		return wp_mail( Lzcrs_Settings::notification_emails(), $subject, implode( "\n", $lines ) );
	}
}
