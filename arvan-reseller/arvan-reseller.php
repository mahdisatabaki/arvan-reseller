<?php
/**
 * Plugin Name:       Arvan Reseller
 * Plugin URI:        https://github.com/mahdisatabaki/arvan-reseller
 * Description:       Sell ArvanCloud services from your own WordPress site. Prepaid customer wallets, hourly metering, automatic suspension — with zero third-party dependencies.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Mahdis Atabaki
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       arvan-reseller
 * Domain Path:       /languages
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

const ARVAN_RESELLER_VERSION = '0.1.0';
const ARVAN_RESELLER_DB_VERSION = 1;
const ARVAN_RESELLER_MIN_PHP = '8.1';

define( 'ARVAN_RESELLER_FILE', __FILE__ );
define( 'ARVAN_RESELLER_DIR', plugin_dir_path( __FILE__ ) );
define( 'ARVAN_RESELLER_URL', plugin_dir_url( __FILE__ ) );
define( 'ARVAN_RESELLER_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Refuse to boot on an unsupported PHP version rather than fataling later.
 */
if ( version_compare( PHP_VERSION, ARVAN_RESELLER_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'Arvan Reseller requires PHP %1$s or newer. This server runs PHP %2$s.', 'arvan-reseller' ),
						ARVAN_RESELLER_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);

	return;
}

require_once ARVAN_RESELLER_DIR . 'wp/Support/Autoloader.php';

\ArvanReseller\Wp\Support\Autoloader::register(
	[
		// Framework-agnostic core. Nothing under this namespace may call a WordPress function.
		'ArvanReseller\\Wp\\' => ARVAN_RESELLER_DIR . 'wp/',
		'ArvanReseller\\'     => ARVAN_RESELLER_DIR . 'src/',
	]
);

register_activation_hook( __FILE__, [ \ArvanReseller\Wp\Installation\Installer::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \ArvanReseller\Wp\Installation\Installer::class, 'deactivate' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		\ArvanReseller\Wp\Plugin::instance()->boot();
	},
	5
);
