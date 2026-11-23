<?php
/**
 * Removes all DomainGate data on uninstall.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'domaingate_settings' );
delete_site_option( 'external_updates-domaingate' );
