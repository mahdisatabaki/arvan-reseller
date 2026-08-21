<?php
/**
 * Plugin bootstrap.
 *
 * Everything WordPress-shaped hangs off this class. The domain layer under
 * `src/` is constructed here and handed its adapters, so nothing in `src/` ever
 * has to know WordPress exists.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp;

use ArvanReseller\Arvan\ApiKeyConnectionTester;
use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Admin\SetupWizard;
use ArvanReseller\Wp\Cron\Scheduler;
use ArvanReseller\Wp\Installation\Installer;
use ArvanReseller\Wp\Persistence\WpApiKeyRepository;
use ArvanReseller\Wp\Security\AccessTokenGate;
use ArvanReseller\Wp\Security\WordPressSecretStore;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?self $instance = null;

	private bool $booted = false;

	private function __construct() {}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		add_action( 'init', [ $this, 'loadTextdomain' ] );
		add_action( 'init', [ Installer::class, 'migrate' ], 1 );

		add_filter( 'cron_schedules', [ Scheduler::class, 'addIntervals' ] );

		if ( is_admin() ) {
			$this->bootAdmin();
		}
	}

	/**
	 * Wires the admin-only object graph. Every dependency SetupWizard needs
	 * is built once, here — the composition root — so SetupWizard itself
	 * never constructs its own collaborators (T-2.4 design).
	 */
	private function bootAdmin(): void {
		global $wpdb;

		$wizard = new SetupWizard(
			new AccessTokenGate(),
			new WordPressSecretStore(),
			new WpApiKeyRepository( $wpdb ),
			new ApiKeyConnectionTester(),
			new ResellerSettings()
		);

		$wizard->register();
	}

	public function loadTextdomain(): void {
		load_plugin_textdomain(
			'arvan-reseller',
			false,
			dirname( ARVAN_RESELLER_BASENAME ) . '/languages'
		);
	}
}
