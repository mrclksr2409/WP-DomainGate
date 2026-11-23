<?php
/**
 * Checks email addresses against the domain whitelist.
 *
 * Supported entries (one per line):
 *   domain.de     → exactly @domain.de
 *   *@domain.de   → same as above
 *   *.domain.de   → all subdomains, e.g. @mail.domain.de (not @domain.de itself)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DomainGate_Validator {

	/**
	 * @return string[] Normalised whitelist entries.
	 */
	public static function get_allowed_domains() {
		$settings = DomainGate_Settings::get_settings();
		return self::parse_list( $settings['allowed_domains'] );
	}

	/**
	 * @param string $raw One entry per line (commas, semicolons and spaces also separate).
	 * @return string[]
	 */
	public static function parse_list( $raw ) {
		$items = preg_split( '/[\s,;]+/', strtolower( (string) $raw ) );
		$items = array_map(
			function ( $item ) {
				// "*@domain.de" and "@domain.de" → "domain.de".
				return preg_replace( '/^\*?@/', '', trim( $item ) );
			},
			$items
		);
		return array_values( array_unique( array_filter( $items ) ) );
	}

	/**
	 * @param string $entry Normalised entry.
	 * @return bool
	 */
	public static function is_valid_entry( $entry ) {
		$domain = 0 === strpos( $entry, '*.' ) ? substr( $entry, 2 ) : $entry;
		return (bool) preg_match( '/^([a-z0-9-]+\.)+[a-z0-9-]{2,}$/', $domain );
	}

	/**
	 * @param string $email
	 * @return bool
	 */
	public static function is_allowed( $email ) {
		$settings = DomainGate_Settings::get_settings();
		$allowed  = self::parse_list( $settings['allowed_domains'] );

		// Disabled or empty whitelist: nobody is blocked.
		if ( empty( $settings['enabled'] ) || empty( $allowed ) ) {
			return true;
		}

		$at = strrpos( (string) $email, '@' );
		if ( false === $at ) {
			return false;
		}
		$domain = strtolower( substr( $email, $at + 1 ) );

		$result = false;
		foreach ( $allowed as $entry ) {
			if ( 0 === strpos( $entry, '*.' ) ) {
				$suffix = substr( $entry, 1 ); // ".domain.de"
				if ( strlen( $domain ) > strlen( $suffix ) && substr( $domain, -strlen( $suffix ) ) === $suffix ) {
					$result = true;
					break;
				}
			} elseif ( $domain === $entry ) {
				$result = true;
				break;
			}
		}

		return (bool) apply_filters( 'domaingate_is_allowed', $result, $email, $domain, $allowed );
	}
}
