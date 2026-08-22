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
use ArvanReseller\Billing\BillingService;
use ArvanReseller\Lifecycle\SuspensionEngine;
use ArvanReseller\Lifecycle\ThresholdPolicyResolver;
use ArvanReseller\Metering\MeteringService;
use ArvanReseller\Metering\UsagePricingAdapter;
use ArvanReseller\Provisioning\ProvisioningService;
use ArvanReseller\Wallet\LowBalanceNotifier;
use ArvanReseller\Wallet\ManualAdjustmentService;
use ArvanReseller\Wallet\PaymentService;
use ArvanReseller\Wp\Admin\AdminMenu;
use ArvanReseller\Wp\Admin\Controllers\CustomersController;
use ArvanReseller\Wp\Admin\Controllers\DashboardController;
use ArvanReseller\Wp\Admin\Controllers\FinanceController;
use ArvanReseller\Wp\Admin\Controllers\ServicesController;
use ArvanReseller\Wp\Admin\Controllers\SettingsController;
use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Admin\SetupWizard;
use ArvanReseller\Wp\Arvan\CdnClientResolver;
use ArvanReseller\Wp\Cron\MeteringCronHandler;
use ArvanReseller\Wp\Cron\Scheduler;
use ArvanReseller\Wp\Customer\CustomerRegistration;
use ArvanReseller\Wp\Frontend\Assets;
use ArvanReseller\Wp\Frontend\Controllers\AuthController;
use ArvanReseller\Wp\Frontend\Controllers\OrderController;
use ArvanReseller\Wp\Frontend\Controllers\RechargeController;
use ArvanReseller\Wp\Frontend\CurrentCustomer;
use ArvanReseller\Wp\Frontend\RouteRegistrar;
use ArvanReseller\Wp\Frontend\TemplateRouter;
use ArvanReseller\Wp\Installation\Installer;
use ArvanReseller\Wp\Persistence\WpApiKeyRepository;
use ArvanReseller\Wp\Persistence\WpAuditLogger;
use ArvanReseller\Wp\Persistence\WpCustomerRepository;
use ArvanReseller\Wp\Persistence\WpLedgerRepository;
use ArvanReseller\Wp\Persistence\WpNotificationRepository;
use ArvanReseller\Wp\Persistence\WpOrderRepository;
use ArvanReseller\Wp\Persistence\WpPaymentRepository;
use ArvanReseller\Wp\Persistence\WpServiceRepository;
use ArvanReseller\Wp\Persistence\WpSettlementRepository;
use ArvanReseller\Wp\Persistence\WpUsageLogRepository;
use ArvanReseller\Wp\Persistence\WpWalletRepository;
use ArvanReseller\Wp\Security\AccessTokenGate;
use ArvanReseller\Wp\Security\WordPressMailer;
use ArvanReseller\Wp\Security\WordPressSecretStore;
use ArvanReseller\Wp\Support\SystemClock;

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

		$this->bootCustomer();
		$this->bootCron();
		$this->bootFrontend();

		if ( is_admin() ) {
			$this->bootAdmin();
		}
	}

	/**
	 * Registration can happen on the public-facing site, not just wp-admin,
	 * so this is wired unconditionally rather than behind the `is_admin()`
	 * gate that guards `bootAdmin()`.
	 */
	private function bootCustomer(): void {
		global $wpdb;

		$registration = new CustomerRegistration(
			new WpCustomerRepository( $wpdb ),
			new WpWalletRepository( $wpdb ),
			new ResellerSettings()
		);

		$registration->register();
	}

	/**
	 * WP-Cron fires `Scheduler::HOOK_METER` regardless of admin context (and
	 * the manual "Run Billing Cycle Now" trigger lives on `admin-post.php`,
	 * which is not gated by `is_admin()` either), so this is unconditional
	 * like `bootCustomer()`, not folded into `bootAdmin()`.
	 */
	private function bootCron(): void {
		global $wpdb;

		$handler = new MeteringCronHandler(
			new WpServiceRepository( $wpdb ),
			new CdnClientResolver( new WpApiKeyRepository( $wpdb ), new WordPressSecretStore() ),
			new MeteringService( new SystemClock() ),
			new BillingService(
				new UsagePricingAdapter(),
				new WpLedgerRepository( $wpdb ),
				new WpUsageLogRepository( $wpdb ),
				new WpServiceRepository( $wpdb )
			),
			new ResellerSettings(),
			new SystemClock(),
			new WpWalletRepository( $wpdb ),
			new WpCustomerRepository( $wpdb ),
			new SuspensionEngine( new WpServiceRepository( $wpdb ), new WpAuditLogger( $wpdb ) ),
			new ThresholdPolicyResolver( new WpWalletRepository( $wpdb ) ),
			new LowBalanceNotifier( new WordPressMailer(), new WpNotificationRepository( $wpdb ) )
		);

		$handler->register();
	}

	/**
	 * Plugin-owned public routes (`/arvan/cdn`, `/arvan/account`, etc.) must
	 * resolve on the public-facing site, not just wp-admin, so this is wired
	 * unconditionally like `bootCustomer()`/`bootCron()`, not folded into
	 * `bootAdmin()`. RouteRegistrar/TemplateRouter/Assets need no
	 * repository/port — pure WordPress rewrite/query-var/template_include
	 * plumbing. OrderController (T-7.3) is the first state-changing
	 * frontend action, so it gets the same object-graph treatment as
	 * `bootCron()`'s handler.
	 */
	private function bootFrontend(): void {
		global $wpdb;

		( new RouteRegistrar() )->register();
		( new TemplateRouter() )->register();
		( new Assets() )->register();

		$orders = new OrderController(
			new CurrentCustomer( new WpCustomerRepository( $wpdb ) ),
			new WpWalletRepository( $wpdb ),
			new CdnClientResolver( new WpApiKeyRepository( $wpdb ), new WordPressSecretStore() ),
			new ProvisioningService(
				new WpOrderRepository( $wpdb ),
				new WpServiceRepository( $wpdb ),
				new SystemClock()
			),
			new ResellerSettings()
		);

		$orders->register();

		$auth = new AuthController();
		$auth->register();

		$recharge = new RechargeController(
			new CurrentCustomer( new WpCustomerRepository( $wpdb ) ),
			new PaymentService( new WpPaymentRepository( $wpdb ), new WpLedgerRepository( $wpdb ) ),
			new WpWalletRepository( $wpdb ),
			new SuspensionEngine( new WpServiceRepository( $wpdb ), new WpAuditLogger( $wpdb ) )
		);
		$recharge->register();
	}

	/**
	 * Wires the admin-only object graph. Every dependency a controller needs
	 * is built once, here — the composition root — so no admin controller
	 * ever constructs its own collaborators (T-2.4 design, extended to every
	 * Block 8 controller). AdminMenu owns page/menu *structure* only; each
	 * controller still hooks its own `admin_post_{action}` handlers via its
	 * own `register()`, called separately below — the same split
	 * OrderController/AuthController/RechargeController established for the
	 * frontend in bootFrontend().
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

		$dashboard = new DashboardController(
			new WpCustomerRepository( $wpdb ),
			new WpServiceRepository( $wpdb ),
			new WpWalletRepository( $wpdb ),
			new WpUsageLogRepository( $wpdb )
		);

		$customers = new CustomersController(
			new WpCustomerRepository( $wpdb ),
			new WpWalletRepository( $wpdb ),
			new WpServiceRepository( $wpdb ),
			new WpPaymentRepository( $wpdb ),
			new WpLedgerRepository( $wpdb ),
			new WpUsageLogRepository( $wpdb ),
			new ManualAdjustmentService( new WpLedgerRepository( $wpdb ), new WpAuditLogger( $wpdb ) )
		);
		$customers->register();

		$services = new ServicesController(
			new WpServiceRepository( $wpdb ),
			new WpCustomerRepository( $wpdb ),
			new WpApiKeyRepository( $wpdb ),
			new CdnClientResolver( new WpApiKeyRepository( $wpdb ), new WordPressSecretStore() ),
			new ProvisioningService(
				new WpOrderRepository( $wpdb ),
				new WpServiceRepository( $wpdb ),
				new SystemClock()
			)
		);
		$services->register();

		$finance = new FinanceController(
			new WpPaymentRepository( $wpdb ),
			new WpLedgerRepository( $wpdb ),
			new WpSettlementRepository( $wpdb ),
			new WpCustomerRepository( $wpdb )
		);
		$finance->register();

		$settings = new SettingsController(
			new WordPressSecretStore(),
			new WpApiKeyRepository( $wpdb ),
			new ApiKeyConnectionTester(),
			new ResellerSettings()
		);
		$settings->register();

		( new AdminMenu( $dashboard, $customers, $services, $finance, $settings ) )->register();
	}

	public function loadTextdomain(): void {
		load_plugin_textdomain(
			'arvan-reseller',
			false,
			dirname( ARVAN_RESELLER_BASENAME ) . '/languages'
		);
	}
}
