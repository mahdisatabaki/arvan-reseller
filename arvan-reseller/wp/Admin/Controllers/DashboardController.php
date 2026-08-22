<?php
/**
 * Admin Dashboard — BACKLOG T-8.1, SCREEN-SPECS.md §2, DESIGN.md §12.
 *
 * Read-only: no admin-post.php action here besides linking to
 * MeteringCronHandler's already-built "Run Billing Cycle Now" trigger
 * (T-5.4) — this controller does not duplicate that handler, it only
 * renders a button pointing at it.
 *
 * Every figure comes from an admin-wide (unscoped) repository read added
 * this block — `CustomerRepository::all()`, `ServiceRepository::allForAdmin()`,
 * `WalletRepository::allBalances()`/`countLowBalance()`,
 * `UsageLogRepository::totalsSince()` — none of these existed before Block 8
 * because every repository built through Block 7 was customer-scoped by
 * design (SECURITY.md §6). "System/API status summary" (DESIGN.md §12) is
 * deliberately not rendered here: BACKLOG T-8.1's own content list omits it,
 * and T-9.2 "System Status" is where API connectivity/last metering
 * run/last settlement/Demo Mode actually belong.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Admin\Controllers;

use ArvanReseller\Domain\Money;
use ArvanReseller\Lifecycle\ServiceStatus;
use ArvanReseller\Ports\CustomerRepository;
use ArvanReseller\Ports\ServiceRepository;
use ArvanReseller\Ports\UsageLogRepository;
use ArvanReseller\Ports\WalletRepository;
use ArvanReseller\Wp\Admin\AdminMenu;
use ArvanReseller\Wp\Cron\MeteringCronHandler;
use ArvanReseller\Wp\Support\Capabilities;
use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

final class DashboardController {

	public function __construct(
		private readonly CustomerRepository $customers,
		private readonly ServiceRepository $services,
		private readonly WalletRepository $wallets,
		private readonly UsageLogRepository $usageLog
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

		require __DIR__ . '/../templates/dashboard.php';
	}
}
