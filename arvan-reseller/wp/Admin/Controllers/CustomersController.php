<?php
/**
 * Admin Customers — list + detail (BACKLOG T-8.2, SCREEN-SPECS.md §3-4,
 * DESIGN.md §13).
 *
 * One controller, one template, two states — the same composition shape as
 * the customer-facing account.php (see that template's docblock), just with
 * `?customer_id=` as the state switch instead of `?tab=`. This class does
 * the state-appropriate repository reads (bulk list reads vs. one
 * customer's detail reads) and hands everything to customers.php in a
 * single `require`; the template itself only branches on whether `$customer`
 * came back non-null.
 *
 * List reads use the bulk primitives built this block —
 * `WalletRepository::allBalances()` and `ServiceRepository::allForAdmin()`
 * (grouped in PHP into a per-customer count) — instead of one query per
 * customer (TECH.md §13). `UsageLogRepository` has no equivalent
 * "most-recent-period-per-customer" bulk read, so the "recent usage/charge"
 * column falls back to one bounded per-customer lookup; see the inline note
 * on that loop for why this one is acceptable where the other two are not.
 *
 * The only state-changing action here is the manual wallet adjustment
 * (SCREEN-SPECS.md §4): amount + direction + mandatory reason + a
 * confirmation checkbox (backed by a plain confirm() dialog, not a JS
 * framework — the same pattern WP core list tables use for destructive row
 * actions). All of it is delegated to the already-built
 * ManualAdjustmentService, which itself writes the ledger entry and the
 * audit log entry atomically; this controller only validates the raw input
 * and turns a thrown InvalidArgumentException into a Persian redirect-back
 * error instead of a fatal.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Admin\Controllers;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\CustomerRepository;
use ArvanReseller\Ports\LedgerRepository;
use ArvanReseller\Ports\PaymentRepository;
use ArvanReseller\Ports\ServiceRepository;
use ArvanReseller\Ports\UsageLogRepository;
use ArvanReseller\Ports\WalletRepository;
use ArvanReseller\Wallet\ManualAdjustmentService;
use ArvanReseller\Wp\Admin\AdminMenu;
use ArvanReseller\Wp\Support\Capabilities;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

final class CustomersController {

	public const ACTION_ADJUST = 'arvan_customer_wallet_adjustment';

	public function __construct(
		private readonly CustomerRepository $customers,
		private readonly WalletRepository $wallets,
		private readonly ServiceRepository $services,
		private readonly PaymentRepository $payments,
		private readonly LedgerRepository $ledger,
		private readonly UsageLogRepository $usageLog,
		private readonly ManualAdjustmentService $adjustments
	) {}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_ADJUST, [ $this, 'handleAdjustment' ] );
	}

	/**
	 * The `add_submenu_page()` callback. Branches list vs. detail on
	 * `$_GET['customer_id']` (SCREEN-SPECS.md §3's "Action: Open Customer
	 * Detail" is a plain link carrying this query arg, no JS/AJAX).
	 */
	public function render(): void {
		if ( ! Capabilities::currentUserCanView() ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این صفحه را ندارید.', 'arvan-reseller' ) );
		}

		$activeSlug = AdminMenu::SLUG_CUSTOMERS;
		$customerId = isset( $_GET['customer_id'] ) ? absint( wp_unslash( $_GET['customer_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state, not a state-changing request.

		$adjustError   = isset( $_GET['arvan_adjust_error'] ) ? sanitize_key( wp_unslash( $_GET['arvan_adjust_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$adjustSuccess = isset( $_GET['arvan_adjust_success'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $customerId > 0 ) {
			$this->renderDetail( $customerId, $activeSlug, $adjustError, $adjustSuccess );
			return;
		}

		$this->renderList( $activeSlug, $adjustError, $adjustSuccess );
	}

	private function renderList( string $activeSlug, string $adjustError, bool $adjustSuccess ): void {
		$customerRows = $this->customers->all();
		$balances     = $this->wallets->allBalances();

		$serviceCounts = [];
		foreach ( $this->services->allForAdmin() as $service ) {
			$cid                   = (int) $service['customer_id'];
			$serviceCounts[ $cid ] = ( $serviceCounts[ $cid ] ?? 0 ) + 1;
		}

		// No bulk "most recent billed period per customer" primitive exists
		// on UsageLogRepository (unlike wallets/services, which have
		// allBalances()/allForAdmin() specifically so this screen would not
		// need per-customer queries — see this class's docblock). This loop
		// is bounded by the customer count, which stays small for this
		// MVP's admin list, so it is acceptable here even though the same
		// pattern is explicitly disallowed for the balance/service-count
		// columns above.
		$recentUsageByCustomer = [];
		foreach ( $customerRows as $row ) {
			$cid                           = (int) $row['id'];
			$recentUsageByCustomer[ $cid ] = $this->usageLog->historyForCustomer( $cid, null, 1 )[0] ?? null;
		}

		$customer   = null;
		$customerId = 0;

		require __DIR__ . '/../templates/customers.php';
	}

	private function renderDetail( int $customerId, string $activeSlug, string $adjustError, bool $adjustSuccess ): void {
		$customer = $this->customers->find( $customerId );

		if ( null === $customer ) {
			require __DIR__ . '/../templates/customers.php';
			return;
		}

		$walletBalance   = $this->wallets->currentBalance( $customerId );
		$lowThreshold    = $this->wallets->lowBalanceThreshold( $customerId );
		$resumeThreshold = $this->wallets->resumeThreshold( $customerId );

		$customerServices = $this->services->allForCustomer( $customerId );
		$paymentRows      = $this->payments->historyForCustomer( $customerId, 20 );
		$ledgerRows       = $this->ledger->historyForCustomer( $customerId, 50 );
		$usageRows        = $this->usageLog->historyForCustomer( $customerId, null, 20 );

		$adjustAction      = self::ACTION_ADJUST;
		$adjustNonceAction = $this->nonceAction( $customerId );

		require __DIR__ . '/../templates/customers.php';
	}

	/**
	 * `Capabilities::MANAGE` — moving money is the plugin's most-privileged
	 * tier (Capabilities.php's own docblock), stricter than the
	 * `VIEW_REPORTS` that gates render() above.
	 */
	public function handleAdjustment(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این عملیات را ندارید.', 'arvan-reseller' ) );
		}

		$customerId = isset( $_POST['customer_id'] ) ? absint( wp_unslash( $_POST['customer_id'] ) ) : 0;

		check_admin_referer( $this->nonceAction( $customerId ) );

		if ( null === $this->customers->find( $customerId ) ) {
			wp_safe_redirect( add_query_arg( 'arvan_adjust_error', 'customer_not_found', admin_url( 'admin.php?page=' . AdminMenu::SLUG_CUSTOMERS ) ) );
			exit;
		}

		$detailUrl = admin_url( 'admin.php?page=' . AdminMenu::SLUG_CUSTOMERS . '&customer_id=' . $customerId );

		$direction   = isset( $_POST['direction'] ) ? sanitize_key( wp_unslash( $_POST['direction'] ) ) : '';
		$amountToman = isset( $_POST['amount_toman'] ) ? absint( wp_unslash( $_POST['amount_toman'] ) ) : 0;
		$reason      = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$confirmed   = isset( $_POST['confirm_adjustment'] ) && '1' === (string) wp_unslash( $_POST['confirm_adjustment'] );

		if ( ! in_array( $direction, [ 'credit', 'debit' ], true ) ) {
			wp_safe_redirect( add_query_arg( 'arvan_adjust_error', 'invalid_direction', $detailUrl ) );
			exit;
		}

		if ( $amountToman <= 0 ) {
			wp_safe_redirect( add_query_arg( 'arvan_adjust_error', 'invalid_amount', $detailUrl ) );
			exit;
		}

		if ( '' === trim( $reason ) ) {
			wp_safe_redirect( add_query_arg( 'arvan_adjust_error', 'reason_required', $detailUrl ) );
			exit;
		}

		if ( ! $confirmed ) {
			wp_safe_redirect( add_query_arg( 'arvan_adjust_error', 'not_confirmed', $detailUrl ) );
			exit;
		}

		$amount = 'credit' === $direction
			? Money::fromToman( $amountToman )
			: Money::fromToman( $amountToman )->negated();

		// A fresh idempotency key per submission — this is a new admin
		// action, not a replayed callback (same reasoning as
		// RechargeController::handleInitiate()).
		$idempotencyKey = wp_generate_uuid4();

		try {
			$this->adjustments->adjust( $customerId, $amount, $reason, (int) get_current_user_id(), $idempotencyKey );
		} catch ( InvalidArgumentException $e ) {
			wp_safe_redirect( add_query_arg( 'arvan_adjust_error', 'adjustment_failed', $detailUrl ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'arvan_adjust_success', '1', $detailUrl ) );
		exit;
	}

	private function nonceAction( int $customerId ): string {
		return self::ACTION_ADJUST . '_' . $customerId;
	}
}
