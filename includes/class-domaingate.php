<?php
/**
 * Bootstrap: hooks the registration checks, settings and updater.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DomainGate {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		new DomainGate_Updater();

		if ( is_admin() ) {
			new DomainGate_Settings();
		}

		// Standard registration (wp-login.php?action=register).
		add_filter( 'registration_errors', array( $this, 'check_registration' ), 10, 3 );

		// Multisite registration (wp-signup.php).
		add_filter( 'wpmu_validate_user_signup', array( $this, 'check_multisite_signup' ) );
	}

	public static function error_message() {
		return __( 'Registrierungen sind nur mit einer E-Mail-Adresse aus einer freigegebenen Domain möglich.', 'domaingate' );
	}

	/**
	 * @param WP_Error $errors
	 * @param string   $sanitized_user_login
	 * @param string   $user_email
	 * @return WP_Error
	 */
	public function check_registration( $errors, $sanitized_user_login, $user_email ) {
		if ( ! DomainGate_Validator::is_allowed( $user_email ) ) {
			$errors->add( 'domaingate_not_allowed', '<strong>' . esc_html__( 'Fehler:', 'domaingate' ) . '</strong> ' . esc_html( self::error_message() ) );
		}
		return $errors;
	}

	/**
	 * @param array $result
	 * @return array
	 */
	public function check_multisite_signup( $result ) {
		if ( ! DomainGate_Validator::is_allowed( $result['user_email'] ) ) {
			$result['errors']->add( 'user_email', self::error_message() );
		}
		return $result;
	}
}
