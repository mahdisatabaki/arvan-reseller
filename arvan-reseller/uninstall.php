<?php
/**
 * Uninstall routine.
 *
 * Deleting a billing plugin should not silently destroy a ledger, so table
 * removal is opt-in: the reseller has to tick "also delete all data" in the
 * settings screen first. Secrets are a different matter — encrypted API keys and
 * the access token are always wiped, because leaving credentials behind in an
 * abandoned database is the worse failure.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Always removed, regardless of the purge setting.
 */
$secret_options = [
	'arvan_reseller_access_token',
	'arvan_reseller_encryption_check',
];

foreach ( $secret_options as $option ) {
	delete_option( $option );
}

// API keys live in their own table; drop the ciphertext even when data is kept.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . 'arvan_api_keys' ) );

if ( '1' !== get_option( 'arvan_reseller_purge_on_uninstall' ) ) {
	return;
}

// Children before parents.
$tables = [
	'audit_log',
	'notifications',
	'settlements',
	'api_keys',
	'usage_log',
	'services',
	'orders',
	'payments',
	'ledger',
	'wallets',
	'customers',
];

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'arvan_' . $table ) );
}

$options = [
	'arvan_reseller_db_version',
	'arvan_reseller_installed_at',
	'arvan_reseller_do_activation_redirect',
	'arvan_reseller_settings',
	'arvan_reseller_branding',
	'arvan_reseller_pricing',
	'arvan_reseller_limits',
	'arvan_reseller_purge_on_uninstall',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

$hooks = [
	'arvan_meter_usage',
	'arvan_enforce_limits',
	'arvan_settlement',
	'arvan_sync_resources',
	'arvan_health_check',
];

foreach ( $hooks as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

remove_role( 'arvan_customer' );
