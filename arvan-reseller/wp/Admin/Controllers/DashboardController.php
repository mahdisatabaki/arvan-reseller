<?php
/**
 * Admin Dashboard — BACKLOG T-8.1 + T-9.2, SCREEN-SPECS.md §2, DESIGN.md §12.
 *
 * Read-only: no admin-post.php action here besides linking to
 * MeteringCronHandler's/SettlementCronHandler's already-built manual
 * triggers — this controller does not duplicate either handler, it only
 * renders buttons pointing at them.
 *
 * Every T-8.1 figure comes from an admin-wide (unscoped) repository read —
 * `CustomerRepository::all()`, `ServiceRepository::allForAdmin()`,
 * `WalletRepository::allBalances()`/`countLowBalance()`,
 * `UsageLogRepository::totalsSince()` — none of these existed before Block 8
 * because every repository built through Block 7 was customer-scoped by
 * design (SECURITY.md §6).
 *
 * The System Status section (T-9.2) is folded into this same Dashboard page
 * rather than a sixth standalone admin page: DESIGN.md §6's Reseller Admin
 * navigation lists exactly five pages (Dashboard/Customers/Services/
 * Finance/Settings), and BACKLOG Block 8's own header note — "avoid 12
 * independent pages" — applies just as much here. "Resource Sync" and
 * "Demo Mode" from T-9.2's bullet list are intentionally not built: Resource
 * Sync UI is on DEMO.md's own sacrifice list, and Demo Mode is not defined
 * anywhere in this project's spec set — inventing a toggle for an undefined
 * concept would be exactly the kind of guess CLAUDE.md's Work Protocol §7
 * forbids for API details, applied to the same standard here.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Admin\Controllers;

use ArvanReseller\Domain\Money;
use ArvanReseller\Lifecycle\ServiceStatus;
use ArvanReseller\Ports\ApiKeyRepository;
use ArvanReseller\Ports\CustomerRepository;
use ArvanReseller\Ports\ServiceRepository;
use ArvanReseller\Ports\SettlementRepository;
use ArvanReseller\Ports\UsageLogRepository;
use ArvanReseller\Ports\WalletRepository;
use ArvanReseller\Wp\Admin\AdminMenu;
use ArvanReseller\Wp\Cron\MeteringCronHandler;
use ArvanReseller\Wp\Cron\Scheduler;
use ArvanReseller\Wp\Cron\SettlementCronHandler;
use ArvanReseller\Wp\Support\Capabilities;
use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

final class DashboardController {

	public function __construct(
		private readonly CustomerRepository $customers,
		private readonly ServiceRepository $services,
		private readonly WalletRepository $wallets,
		private readonly UsageLogRepository $usageLog,
		private readonly ApiKeyRepository $apiKeys,
		private readonly SettlementRepository $settlements
	) {}

	public function render(): void {
		if ( ! Capabilities::currentUserCanView() ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این صفحه را ندارید.', 'arvan-reseller' ) );
		}

		$activeSlug = AdminMenu::SLUG_DASHBOARD;

		$allServices  = $this->services->allForAdmin();
		$statusCounts = array_count_values( array_column( $allServices, 'status' ) );

		$totalCustomers = count( $this->customers->all() );

		$totalBalance = Money::zero();
		foreach ( $this->wallets->allBalances() as $balance ) {
			$totalBalance = $totalBalance->plus( $balance );
		}

		$lowBalanceWarnings = $this->wallets->countLowBalance();

		$utc        = new DateTimeZone( 'UTC' );
		$todayStart = new DateTimeImmutable( 'today', $utc );
		$epoch      = new DateTimeImmutable( '@0' );

		$todayTotals   = $this->usageLog->totalsSince( $todayStart );
		$allTimeTotals = $this->usageLog->totalsSince( $epoch );

		$activeServices     = $statusCounts[ ServiceStatus::ACTIVE ] ?? 0;
		$suspendedServices  = $statusCounts[ ServiceStatus::SUSPENDED ] ?? 0;
		$runBillingAction   = MeteringCronHandler::MANUAL_ACTION;

		// System Status (T-9.2).
		$cronHealthy       = Scheduler::isHealthy();
		$missingCronJobs   = Scheduler::missingJobs();
		$defaultApiKey     = $this->apiKeys->findDefault( 'cdn' );
		$lastMeteringRunAt = get_option( MeteringCronHandler::LAST_RUN_OPTION, '' );
		$lastSettlement    = $this->settlements->allRecent( 1 )[0] ?? null;
		$runSettlementAction = SettlementCronHandler::MANUAL_ACTION;

		require __DIR__ . '/../templates/dashboard.php';
	}
}
