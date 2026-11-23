<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prüft E-Mail-Adressen gegen die Domain-Whitelist.
 *
 * Unterstützte Einträge (einer pro Zeile):
 *   domain.de        → nur exakt @domain.de
 *   *@domain.de      → wie oben
 *   *.domain.de      → alle Subdomains, z. B. @mail.domain.de (nicht @domain.de selbst)
 */
class DomainGate_Validator {

	/**
	 * @return string[] Normalisierte Whitelist-Einträge.
	 */
	public static function get_allowed_domains() {
		$raw = get_option( DOMAINGATE_OPTION, '' );
		return self::parse_list( $raw );
	}

	/**
	 * @param string $raw Text mit einem Eintrag pro Zeile (oder komma-getrennt).
	 * @return string[]
	 */
	public static function parse_list( $raw ) {
		$items = preg_split( '/[\s,;]+/', strtolower( (string) $raw ) );
		$items = array_map(
			function ( $item ) {
				$item = trim( $item );
				// "*@domain.de" und "@domain.de" → "domain.de"
				return preg_replace( '/^\*?@/', '', $item );
			},
			$items
		);
		return array_values( array_unique( array_filter( $items ) ) );
	}

	/**
	 * @param string $email
	 * @return bool
	 */
	public static function is_allowed( $email ) {
		$allowed = self::get_allowed_domains();

		// Leere Whitelist = Plugin inaktiv, niemand wird blockiert.
		if ( empty( $allowed ) ) {
			return true;
		}

		$at = strrpos( (string) $email, '@' );
		if ( false === $at ) {
			return false;
		}
		$domain = strtolower( substr( $email, $at + 1 ) );

		foreach ( $allowed as $entry ) {
			if ( 0 === strpos( $entry, '*.' ) ) {
				$suffix = substr( $entry, 1 ); // ".domain.de"
				if ( strlen( $domain ) > strlen( $suffix ) && substr( $domain, -strlen( $suffix ) ) === $suffix ) {
					return true;
				}
			} elseif ( $domain === $entry ) {
				return true;
			}
		}

		return (bool) apply_filters( 'domaingate_is_allowed', false, $email, $domain, $allowed );
	}
}
