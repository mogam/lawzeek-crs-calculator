<?php
/**
 * Plugin Name:       Lawzeek CRS Calculator
 * Plugin URI:        https://wordpress.org/plugins/lawzeek-crs-calculator/
 * Description:       A Canada Express Entry CRS score calculator for immigration consultants. Visitors see their estimated score and how to raise it; you get every result as a lead in WordPress.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Lawzeek
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lawzeek-crs-calculator
 * Domain Path:       /languages
 *
 * @package LawzeekCrsCalculator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LZCRS_VERSION', '1.0.0' );
define( 'LZCRS_FILE', __FILE__ );
define( 'LZCRS_DIR', plugin_dir_path( __FILE__ ) );
define( 'LZCRS_URL', plugin_dir_url( __FILE__ ) );
/** Website of the full Lawzeek service, linked from the admin info box. Empty = no link (set once the domain is live). */
define( 'LZCRS_SERVICE_URL', '' );

require_once LZCRS_DIR . 'includes/class-lzcrs-rules.php';
require_once LZCRS_DIR . 'includes/class-lzcrs-scoring.php';
require_once LZCRS_DIR . 'includes/class-lzcrs-settings.php';
require_once LZCRS_DIR . 'includes/class-lzcrs-db.php';
require_once LZCRS_DIR . 'includes/class-lzcrs-labels.php';
require_once LZCRS_DIR . 'includes/class-lzcrs-rest.php';
require_once LZCRS_DIR . 'includes/class-lzcrs-frontend.php';
require_once LZCRS_DIR . 'includes/class-lzcrs-privacy.php';

require_once LZCRS_DIR . 'includes/class-lzcrs-onboarding.php';

/** Creates the leads table and asks for the setup screen once. */
function lzcrs_activate() {
	Lzcrs_Db::install();
	Lzcrs_Onboarding::activate();
}
register_activation_hook( __FILE__, 'lzcrs_activate' );

add_action( 'plugins_loaded', array( 'Lzcrs_Db', 'maybe_upgrade' ) );
Lzcrs_Settings::init();
Lzcrs_Rest::init();
Lzcrs_Frontend::init();
Lzcrs_Privacy::init();

if ( is_admin() ) {
	require_once LZCRS_DIR . 'includes/class-lzcrs-admin.php';
	Lzcrs_Admin::init();
	Lzcrs_Onboarding::init();
}
