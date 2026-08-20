<?php
/**
 * Minimal PSR-4 autoloader.
 *
 * The plugin ships with zero Composer dependencies, so it carries its own loader
 * rather than a vendor directory.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Support;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	/**
	 * Namespace prefix => base directory, sorted longest prefix first.
	 *
	 * @var array<string, string>
	 */
	private static array $prefixes = [];

	private static bool $registered = false;

	/**
	 * @param array<string, string> $prefixes Namespace prefix => absolute base directory.
	 */
	public static function register( array $prefixes ): void {
		foreach ( $prefixes as $prefix => $dir ) {
			self::$prefixes[ ltrim( $prefix, '\\' ) ] = rtrim( $dir, '/\\' ) . DIRECTORY_SEPARATOR;
		}

		// A longer prefix is more specific and must be tested first, so that
		// "ArvanReseller\Wp\" never gets swallowed by "ArvanReseller\".
		uksort( self::$prefixes, static fn( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );

		if ( ! self::$registered ) {
			spl_autoload_register( [ self::class, 'load' ] );
			self::$registered = true;
		}
	}

	public static function load( string $class ): void {
		foreach ( self::$prefixes as $prefix => $dir ) {
			if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
				continue;
			}

			$relative = substr( $class, strlen( $prefix ) );
			$path     = $dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

			// Guard against a crafted class name escaping the base directory.
			$real = realpath( $path );
			$base = realpath( $dir );

			if ( false === $real || false === $base || 0 !== strncmp( $real, $base, strlen( $base ) ) ) {
				continue;
			}

			require_once $real;

			return;
		}
	}
}
