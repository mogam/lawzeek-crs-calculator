<?php
/**
 * First-run onboarding: after activation the site owner is taken once to a short three-step setup
 * (welcome, benefits, basic details). Everything is saved in this site's own settings; nothing is sent anywhere.
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The onboarding screen, the one-time redirect after activation and the form handlers.
 */
class Lzcrs_Onboarding {
	const SLUG      = 'lzcrs-onboarding';
	const DONE      = 'lzcrs_onboarded';
	const REDIRECT  = 'lzcrs_activation_redirect';
	const IRCC_URL  = 'https://www.canada.ca/en/immigration-refugees-citizenship/corporate/mandate/policies-operational-instructions-agreements/ministerial-instructions/express-entry-rounds.html';
	const ICON_TICK = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 10.5 3.2 3L15 6.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ), 1 );
		add_action( 'admin_post_lzcrs_onboarding', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_lzcrs_onboarding_skip', array( __CLASS__, 'handle_skip' ) );
	}

	/** Called on activation: ask for the setup screen once, unless setup was already done on this site. */
	public static function activate() {
		if ( ! get_option( self::DONE ) ) {
			set_transient( self::REDIRECT, 1, 5 * MINUTE_IN_SECONDS );
		}
	}

	public static function url( $step = 1 ) {
		return admin_url( 'admin.php?page=' . self::SLUG . ( $step > 1 ? '&step=' . (int) $step : '' ) );
	}

	/** Not in the menu: reached after activation, or from the dashboard's setup card. */
	public static function menu() {
		add_submenu_page( '', __( 'Welcome to Lawzeek', 'lawzeek-crs-calculator' ), '', Lzcrs_Admin::CAP, self::SLUG, array( __CLASS__, 'page' ) );
	}

	/**
	 * Where to send the user after activation, or '' to stay. Clears the one-time flag.
	 * No redirect for bulk activation, network admin, AJAX, WP-CLI, users who cannot manage the plugin, or when setup is done.
	 */
	public static function redirect_url() {
		if ( ! get_transient( self::REDIRECT ) ) {
			return '';
		}
		delete_transient( self::REDIRECT );
		$bulk = isset( $_GET['activate-multi'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of WordPress's own bulk-activation flag.
		if ( $bulk || wp_doing_ajax() || is_network_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) || ! current_user_can( Lzcrs_Admin::CAP ) || get_option( self::DONE ) ) {
			return '';
		}
		return self::url();
	}

	public static function maybe_redirect() {
		$url = self::redirect_url();
		if ( $url ) {
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Validates and saves the setup form. Only the three fields shown are changed; other settings stay as they are.
	 *
	 * @param array $raw Slashed form input, as in $_POST.
	 * @return string[] Error messages; empty when saved.
	 */
	public static function save( array $raw ) {
		$fields = array();
		foreach ( array( 'emails', 'cta_url', 'cutoff' ) as $key ) {
			$fields[ $key ] = isset( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
		}
		global $wp_settings_errors;
		$wp_settings_errors = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- collect only this form's messages.
		$clean              = Lzcrs_Settings::sanitize( array_merge( wp_slash( Lzcrs_Settings::get() ), $fields ) );
		$errors             = wp_list_pluck( get_settings_errors( Lzcrs_Settings::OPTION ), 'message' );
		$wp_settings_errors = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $errors ) {
			return $errors;
		}
		update_option( Lzcrs_Settings::OPTION, $clean );
		update_option( self::DONE, time(), false );
		return array();
	}

	public static function handle_save() {
		if ( ! current_user_can( Lzcrs_Admin::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'lawzeek-crs-calculator' ), 403 );
		}
		check_admin_referer( 'lzcrs_onboarding' );
		$errors = self::save( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated field by field in Lzcrs_Settings::sanitize().
		if ( $errors ) {
			set_transient( 'lzcrs_onboarding_errors_' . get_current_user_id(), $errors, MINUTE_IN_SECONDS );
			wp_safe_redirect( self::url( 3 ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=lzcrs-dashboard&lzcrs_msg=welcome' ) );
		exit;
	}

	public static function handle_skip() {
		if ( ! current_user_can( Lzcrs_Admin::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'lawzeek-crs-calculator' ), 403 );
		}
		check_admin_referer( 'lzcrs_onboarding_skip' );
		update_option( self::DONE, time(), false );
		wp_safe_redirect( admin_url( 'admin.php?page=lzcrs-dashboard' ) );
		exit;
	}

	/* ------------------------------------------------------------------ Screen */

	const CHEVRON_R = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m8 5 5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	const CHEVRON_L = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m12 5-5 5 5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	const ICON_X    = '<svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 5 10 10M15 5 5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';

	private static function skip_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=lzcrs_onboarding_skip' ), 'lzcrs_onboarding_skip' );
	}

	/** Back (outlined), Skip (text) and the primary button, as in the footer of each step. */
	private static function actions( $back_step, $primary ) {
		echo '<div class="lzcrs-onboard-actions">';
		if ( $back_step ) {
			echo '<a class="lzcrs-btn lzcrs-btn-lg" href="' . esc_url( self::url( $back_step ) ) . '">' . self::CHEVRON_L . esc_html__( 'Back', 'lawzeek-crs-calculator' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '<span class="lz-flex-1"></span>';
		if ( $back_step ) {
			echo '<a class="lzcrs-text-btn" href="' . esc_url( self::skip_url() ) . '">' . esc_html__( 'Skip', 'lawzeek-crs-calculator' ) . '</a>';
		}
		echo $primary . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts by the caller.
	}

	public static function page() {
		if ( ! current_user_can( Lzcrs_Admin::CAP ) ) {
			return;
		}
		$step = isset( $_GET['step'] ) ? absint( $_GET['step'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- which step to show; changes nothing.
		$step = min( 3, max( 1, $step ) );
		$names = array(
			1 => __( 'Welcome', 'lawzeek-crs-calculator' ),
			2 => __( 'Benefits', 'lawzeek-crs-calculator' ),
			3 => __( 'Your details', 'lawzeek-crs-calculator' ),
		);

		echo '<div class="lzcrs-app lzcrs-onboard" data-testid="lzcrs-onboarding"><header class="lzcrs-onboard-bar">';
		echo '<span class="lzcrs-brand"><img src="' . esc_url( LZCRS_URL . 'assets/images/logo.svg' ) . '" alt="" width="32" height="32"><span><b>' . esc_html__( 'Lawzeek', 'lawzeek-crs-calculator' ) . '</b><small>' . esc_html__( 'CRS Calculator', 'lawzeek-crs-calculator' ) . '</small></span></span>';
		echo '<ol class="lzcrs-stepper" aria-label="' . esc_attr__( 'Setup steps', 'lawzeek-crs-calculator' ) . '">';
		foreach ( $names as $n => $label ) {
			$state = $n < $step ? 'done' : ( $n === $step ? 'current' : 'todo' );
			/* translators: 1: step number, 2: step name */
			$sr = sprintf( __( 'Step %1$d: %2$s', 'lawzeek-crs-calculator' ), $n, $label );
			echo '<li class="' . esc_attr( $state ) . '"' . ( 'current' === $state ? ' aria-current="step"' : '' ) . '><span class="lzcrs-step-dot" aria-hidden="true">' . esc_html( (string) $n ) . '</span><span class="screen-reader-text">' . esc_html( $sr ) . '</span></li>';
		}
		echo '</ol>';
		echo '<a class="lzcrs-exit" href="' . esc_url( self::skip_url() ) . '">' . esc_html__( 'Exit setup', 'lawzeek-crs-calculator' ) . self::ICON_X . '</a></header>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.

		echo '<main class="lzcrs-onboard-wrap"><h1 class="screen-reader-text">' . esc_html__( 'Set up Lawzeek CRS Calculator', 'lawzeek-crs-calculator' ) . '</h1><hr class="wp-header-end lz-hidden"><section class="lzcrs-onboard-card">';
		if ( 1 === $step ) {
			self::step_welcome();
		} elseif ( 2 === $step ) {
			self::step_benefits();
		} else {
			self::step_details();
		}
		echo '</section></main></div>';
	}

	private static function step_welcome() {
		echo '<div data-testid="lzcrs-step-welcome"><h2 class="lzcrs-onboard-title">' . esc_html__( 'Welcome to Lawzeek', 'lawzeek-crs-calculator' ) . '</h2>';
		echo '<p class="lzcrs-onboard-sub">' . esc_html__( 'Turn Express Entry questions into booked consultations.', 'lawzeek-crs-calculator' ) . '</p>';

		// Illustration: what a visitor sees, drawn with HTML so it follows the admin font and stays crisp.
		echo '<div class="lzcrs-onboard-visual" aria-hidden="true"><div class="lzcrs-ov-copy"><img src="' . esc_url( LZCRS_URL . 'assets/images/logo.svg' ) . '" alt="" width="56" height="56"><b>' . esc_html__( 'Getting started with your CRS calculator', 'lawzeek-crs-calculator' ) . '</b></div>';
		echo '<div class="lzcrs-ov-card"><small>' . esc_html__( 'Estimated CRS score', 'lawzeek-crs-calculator' ) . '</small><strong>498</strong><span class="lzcrs-ov-pill">' . esc_html__( '+62 with a French test', 'lawzeek-crs-calculator' ) . '</span><span class="lzcrs-ov-btn">' . esc_html__( 'Book a consultation', 'lawzeek-crs-calculator' ) . '</span></div></div>';

		$facts = array(
			__( 'A free CRS calculator on any page, in 2 minutes', 'lawzeek-crs-calculator' ),
			__( 'Every visitor result saved as a lead', 'lawzeek-crs-calculator' ),
			__( 'Matches your theme and works on phones', 'lawzeek-crs-calculator' ),
			__( 'No account or API key. Your leads stay on your site.', 'lawzeek-crs-calculator' ),
		);
		echo '<ul class="lzcrs-checklist">';
		foreach ( $facts as $f ) {
			echo '<li>' . self::ICON_TICK . '<span>' . esc_html( $f ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
		}
		echo '</ul></div>';
		echo '<p class="lz-m-0 lz-mt-8"><a class="lzcrs-btn primary lzcrs-btn-lg" href="' . esc_url( self::url( 2 ) ) . '">' . esc_html__( 'Let\'s get started', 'lawzeek-crs-calculator' ) . self::CHEVRON_R . '</a></p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
	}

	private static function step_benefits() {
		$items = array(
			array( 'indigo', 'M4 15V9m5 6V5m5 10v-4', __( 'Every result becomes a lead', 'lawzeek-crs-calculator' ), __( 'Name, email, answers and score are saved in WordPress the moment a visitor sees their result.', 'lawzeek-crs-calculator' ) ),
			array( 'sun', 'M10 3v4m0 6v4M3 10h4m6 0h4', __( 'Know who to call first', 'lawzeek-crs-calculator' ), __( 'Each lead is rated Hot, Warm or Cold against the latest Express Entry cutoff.', 'lawzeek-crs-calculator' ) ),
			array( 'mint', 'M4 6h12v10H4zM4 9h12M8 4v3m4-3v3', __( 'Book consultations', 'lawzeek-crs-calculator' ), __( 'Your Calendly or booking link appears right after the score, when interest is highest.', 'lawzeek-crs-calculator' ) ),
			array( 'pink', 'M3 5h14v10H3zM7 18h6', __( 'Fits your website', 'lawzeek-crs-calculator' ), __( 'It uses your theme\'s fonts and colours and works well on phones.', 'lawzeek-crs-calculator' ) ),
			array( 'ink', 'M10 3 4 5.5v4.2c0 3.6 2.5 6.4 6 7.3 3.5-.9 6-3.7 6-7.3V5.5z', __( 'Private by design', 'lawzeek-crs-calculator' ), __( 'Data stays in your database, consent is recorded, and WordPress export and erase tools work.', 'lawzeek-crs-calculator' ) ),
		);
		echo '<div data-testid="lzcrs-step-benefits"><h2 class="lzcrs-onboard-title">' . esc_html__( 'Built for immigration consultants', 'lawzeek-crs-calculator' ) . '</h2>';
		echo '<p class="lzcrs-onboard-sub">' . esc_html__( 'Everything in this plugin is free, with no trial and no limit on leads.', 'lawzeek-crs-calculator' ) . '</p>';
		echo '<ul class="lzcrs-benefits">';
		foreach ( $items as $it ) {
			echo '<li><span class="lzcrs-benefit-icon ' . esc_attr( $it[0] ) . '"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="' . esc_attr( $it[1] ) . '" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span><b>' . esc_html( $it[2] ) . '</b><span>' . esc_html( $it[3] ) . '</span></span></li>';
		}
		echo '</ul></div>';
		self::actions( 1, '<a class="lzcrs-btn primary lzcrs-btn-lg" href="' . esc_url( self::url( 3 ) ) . '">' . esc_html__( 'Continue', 'lawzeek-crs-calculator' ) . self::CHEVRON_R . '</a>' );
	}

	private static function step_details() {
		$s      = Lzcrs_Settings::get();
		$key    = 'lzcrs_onboarding_errors_' . get_current_user_id();
		$errors = get_transient( $key );
		delete_transient( $key );

		echo '<div data-testid="lzcrs-step-details"><h2 class="lzcrs-onboard-title">' . esc_html__( 'Get the best start', 'lawzeek-crs-calculator' ) . '</h2>';
		echo '<p class="lzcrs-onboard-sub">' . esc_html__( 'Three quick details for your calculator. They are saved on this site only, and you can change them any time in Settings.', 'lawzeek-crs-calculator' ) . '</p>';
		if ( is_array( $errors ) && $errors ) {
			echo '<div class="notice notice-error inline lzcrs-onboard-error" role="alert"><p>' . implode( '<br>', array_map( 'esc_html', $errors ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each message is escaped.
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="lzcrs-onboard-form">';
		echo '<input type="hidden" name="action" value="lzcrs_onboarding">';
		wp_nonce_field( 'lzcrs_onboarding' );

		$emails = '' !== $s['emails'] ? $s['emails'] : (string) get_option( 'admin_email' );
		echo '<div class="lzcrs-ob-field"><label for="lzcrs-ob-emails">' . esc_html__( 'Email for new leads', 'lawzeek-crs-calculator' ) . '</label><input type="text" id="lzcrs-ob-emails" name="emails" value="' . esc_attr( $emails ) . '" autocomplete="email" aria-describedby="lzcrs-ob-emails-help" required><p class="lzcrs-help" id="lzcrs-ob-emails-help">' . esc_html__( 'Each new lead is emailed here. Add more addresses separated by commas.', 'lawzeek-crs-calculator' ) . '</p></div>';

		echo '<div class="lzcrs-ob-field"><label for="lzcrs-ob-url">' . esc_html__( 'Booking link', 'lawzeek-crs-calculator' ) . ' <span class="lzcrs-optional">' . esc_html__( 'Optional', 'lawzeek-crs-calculator' ) . '</span></label><input type="url" id="lzcrs-ob-url" name="cta_url" value="' . esc_attr( $s['cta_url'] ) . '" placeholder="https://calendly.com/your-firm/consultation" aria-describedby="lzcrs-ob-url-help"><p class="lzcrs-help" id="lzcrs-ob-url-help">' . esc_html__( 'Calendly, Cal.com, Acuity or your contact page. Visitors see a "Book a consultation" button after their score.', 'lawzeek-crs-calculator' ) . '</p></div>';

		echo '<div class="lzcrs-ob-field"><label for="lzcrs-ob-cutoff">' . esc_html__( 'Recent Express Entry cutoff', 'lawzeek-crs-calculator' ) . '</label><input type="number" id="lzcrs-ob-cutoff" name="cutoff" min="200" max="1200" step="1" value="' . esc_attr( (string) $s['cutoff'] ) . '" aria-describedby="lzcrs-ob-cutoff-help" required><p class="lzcrs-help" id="lzcrs-ob-cutoff-help">' . esc_html__( 'Used to rate leads Hot, Warm or Cold.', 'lawzeek-crs-calculator' ) . ' <a href="' . esc_url( self::IRCC_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'See the latest draws on canada.ca', 'lawzeek-crs-calculator' ) . '</a></p></div>';

		self::actions( 2, '<button type="submit" class="lzcrs-btn primary lzcrs-btn-lg">' . esc_html__( 'Finish setup', 'lawzeek-crs-calculator' ) . self::CHEVRON_R . '</button>' );
		echo '</form></div>';
	}
}
