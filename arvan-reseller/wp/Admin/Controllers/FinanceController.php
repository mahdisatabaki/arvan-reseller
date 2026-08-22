<?php
/**
 * Admin Finance — BACKLOG T-8.4, SCREEN-SPECS.md §6, DESIGN.md §14.
 *
 * One page, three tabs (Payments/Ledger/Settlements) switched via a
 * server-rendered `?tab=` query string, the exact same full-page-reload
 * convention `wp/Frontend/templates/account.php` established for the
 * frontend and `SettingsController`/`settings.php` reused for admin
 * (T-7.6/T-8.5): a whitelist check against `$_GET['tab']`, `add_query_arg()`
 * for the nav links, `is-active` for the current tab.
 *
 * Every tab reads an already-admin-wide, unscoped repository method
 * (`PaymentRepository::allRecent()`, `LedgerRepository::allRecent()`,
 * `SettlementRepository::allRecent()`) — none of them take a customer id,
 * because this is a reseller-capability-gated screen, not a per-customer
 * request (SECURITY.md §6's IDOR concern does not apply here, same reasoning
 * `CustomerRepository::all()`'s own docblock gives for the Admin Customers
 * list). The Payments status filter is applied in PHP against the already
 * fetched `allRecent()` result rather than a new filtered repository method
 * — the task is explicit that adding one is out of scope at this scale.
 *
 * Read-only screen: no admin_post.php action anywhere on this page
 * (DESIGN.md §14 calls the Ledger tab an "Immutable transaction view", and
 * the Settlements tab is a read-only display of a table T-9.1 has not
 * started writing to yet — an empty Settlements list here is the expected
 * state, not a bug).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Admin\Controllers;

use ArvanReseller\Ports\CustomerRepository;
use ArvanReseller\Ports\LedgerRepository;
use ArvanReseller\Ports\PaymentRepository;
use ArvanReseller\Ports\SettlementRepository;
use ArvanReseller\Wp\Admin\AdminMenu;
use ArvanReseller\Wp\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class FinanceController {

	public function __construct(
		private readonly PaymentRepository $payments,
		private readonly LedgerRepository $ledger,
		private readonly SettlementRepository $settlements,
		private readonly CustomerRepository $customers
	) {}

	/**
	 * Read-only screen: no admin_post.php handlers to hook. Kept as a no-op
	 * for interface consistency with ServicesController/SettingsController,
	 * which Plugin.php calls the same way for every admin controller.
	 */
	public function register(): void {}

	public function render(): void {
		if ( ! Capabilities::currentUserCanView() ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این صفحه را ندارید.', 'arvan-reseller' ) );
		}

		$activeSlug  = AdminMenu::SLUG_FINANCE;
		$allowedTabs = [ 'payments', 'ledger', 'settlements' ];
		$activeTab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'payments'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $activeTab, $allowedTabs, true ) ) {
			$activeTab = 'payments';
		}

		$customerNames = [];
		foreach ( $this->customers->all() as $customer ) {
			$customerNames[ (int) $customer['id'] ] = (string) $customer['display_name'];
		}

		$paymentRows         = [];
		$paymentStatusFilter = '';
		$ledgerRows          = [];
		$settlementRows      = [];

		if ( 'payments' === $activeTab ) {
			$allowedStatuses      = [ 'pending', 'succeeded', 'failed' ];
			$paymentStatusFilter  = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! in_array( $paymentStatusFilter, $allowedStatuses, true ) ) {
				$paymentStatusFilter = '';
			}

			$paymentRows = $this->payments->allRecent( 50 );

			if ( '' !== $paymentStatusFilter ) {
				$paymentRows = array_values(
					array_filter(
						$paymentRows,
						static fn( array $row ): bool => $paymentStatusFilter === (string) $row['status']
					)
				);
			}
		} elseif ( 'ledger' === $activeTab ) {
			$ledgerRows = $this->ledger->allRecent( 50 );
		} else {
			$settlementRows = $this->settlements->allRecent( 50 );
		}

		require __DIR__ . '/../templates/finance.php';
	}
}
