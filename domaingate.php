<?php
/**
 * Plugin Name:       DomainGate – Restrict Registration by Email Domain
 * Plugin URI:        https://github.com/mrclksr2409/WP-DomainGate
 * Description:       Erlaubt die Registrierung nur für E-Mail-Adressen aus freigegebenen Domains (z. B. *@domain.de).
 * Version:           0.2.0
 * Requires at least: 5.5
 * Requires PHP:      7.2
 * Author:            Marcel Kaiser
 * Author URI:        https://github.com/mrclksr2409
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       domaingate
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DOMAINGATE_VERSION', '0.2.0' );
define( 'DOMAINGATE_FILE', __FILE__ );
define( 'DOMAINGATE_PATH', plugin_dir_path( __FILE__ ) );

// Shared admin design system (bundled library, newest copy across plugins wins).
require_once DOMAINGATE_PATH . 'lib/wp-backend-ui/wp-backend-ui.php';

require_once DOMAINGATE_PATH . 'includes/class-domaingate-settings.php';
require_once DOMAINGATE_PATH . 'includes/class-domaingate-validator.php';
require_once DOMAINGATE_PATH . 'includes/class-domaingate-updater.php';
require_once DOMAINGATE_PATH . 'includes/class-domaingate.php';

wpb_admin_ui_register( array( 'pages' => array( DomainGate_Settings::PAGE_SLUG ) ) );

register_activation_hook( __FILE__, array( 'DomainGate_Settings', 'set_defaults_on_activate' ) );

add_action( 'plugins_loaded', array( 'DomainGate', 'instance' ) );
