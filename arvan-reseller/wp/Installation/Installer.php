<?php
/**
 * Activation, deactivation and schema migration.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Installation;

use ArvanReseller\Wp\Support\Capabilities;
use ArvanReseller\Wp\Cron\Scheduler;

defined( 'ABSPATH' ) || exit;

final class Installer {

	public const DB_VERSION_OPTION = 'arvan_reseller_db_version';
	public const INSTALLED_AT_OPTION = 'arvan_reseller_installed_at';
	public const ACTIVATION_REDIRECT_OPTION = 'arvan_reseller_do_activation_redirect';

	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::migrate();

		Capabilities::grant();
		Scheduler::schedule();

		if ( ! get_option( self::INSTALLED_AT_OPTION ) ) {
			add_option( self::INSTALLED_AT_OPTION, gmdate( 'Y-m-d H:i:s' ) );
			// The wizard is the first thing a reseller should see.
			add_option( self::ACTIVATION_REDIRECT_OPTION, '1' );
		}

		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		Scheduler::unschedule();
		flush_rewrite_rules();
	}

	/**
	 * Bring the schema up to date. Safe to call on every request; it returns
	 * early unless the stored version is behind.
	 */
	public static function migrate( bool $force = false ): void {
		$installed = (int) get_option( self::DB_VERSION_OPTION, 0 );

		if ( ! $force && $installed >= ARVAN_RESELLER_DB_VERSION ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::statements() as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::DB_VERSION_OPTION, ARVAN_RESELLER_DB_VERSION, false );
	}

	/**
	 * Every table this plugin created, fully prefixed. Used by uninstall.php.
	 *
	 * @return string[]
	 */
	public static function tables(): array {
		return array_map(
			static fn( string $name ): string => Schema::table( $name ),
			Schema::TABLES
		);
	}
}
