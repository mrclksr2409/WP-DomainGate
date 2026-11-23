<?php
/**
 * Plugin Name:       DomainGate – Restrict Registration by Email Domain
 * Description:       Erlaubt die Registrierung nur für E-Mail-Adressen aus freigegebenen Domains (z. B. *@domain.de).
 * Version:           0.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       domaingate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DOMAINGATE_VERSION', '0.1.0' );
define( 'DOMAINGATE_OPTION', 'domaingate_allowed_domains' );

require_once __DIR__ . '/includes/class-domaingate-validator.php';
require_once __DIR__ . '/includes/class-domaingate-settings.php';

/**
 * Prüft die E-Mail bei der Standard-Registrierung (wp-login.php?action=register).
 */
add_filter(
	'registration_errors',
	function ( $errors, $sanitized_user_login, $user_email ) {
		if ( ! DomainGate_Validator::is_allowed( $user_email ) ) {
			$errors->add(
				'domaingate_not_allowed',
				__( '<strong>Fehler:</strong> Registrierungen sind nur mit einer E-Mail-Adresse aus einer freigegebenen Domain möglich.', 'domaingate' )
			);
		}
		return $errors;
	},
	10,
	3
);

/**
 * Prüft die E-Mail bei Multisite-Registrierungen (wp-signup.php).
 */
add_filter(
	'wpmu_validate_user_signup',
	function ( $result ) {
		if ( ! DomainGate_Validator::is_allowed( $result['user_email'] ) ) {
			$result['errors']->add(
				'user_email',
				__( 'Registrierungen sind nur mit einer E-Mail-Adresse aus einer freigegebenen Domain möglich.', 'domaingate' )
			);
		}
		return $result;
	}
);

if ( is_admin() ) {
	new DomainGate_Settings();
}
