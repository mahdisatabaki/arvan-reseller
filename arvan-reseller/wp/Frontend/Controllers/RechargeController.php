<?php
/**
 * Mock Payment recharge from `/arvan/recharge` (BACKLOG T-7.4,
 * SCREEN-SPECS.md §10, USER-FLOWS.md §5, ADR-014).
 *
 * Three `admin-post.php` actions — initiate, confirm, fail — mirroring
 * OrderController's shape: nonce + CSRF, logged-in-customer resolved through
 * `CurrentCustomer`, never a bare posted id trusted for a customer-owned
 * lookup. `confirm`/`fail` both re-resolve the payment inside
 * `PaymentService` via `PaymentRepository::findOwnedByCustomer()` before
 * acting (SECURITY.md §6) — a payment id that does not belong to the
 * current customer throws, which this controller turns into a 403 rather
 * than a fatal error.
 *
 * A successful confirm (`confirmSucceeded()` returns non-null, i.e. this
 * call actually credited the wallet, not a duplicate) also gives any
 * wallet-suspended service of this customer a chance to Resume
 * (BILLING.md, ADR-012, USER-FLOWS.md §5/§10) — the same billing-workflow
 * pairing `SuspensionEngine`/`ThresholdPolicy` already use in the cron path
 * (`MeteringCronHandler`), just triggered from a recharge instead of a
 * debit.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Frontend\Controllers;

use ArvanReseller\Domain\Money;
use ArvanReseller\Lifecycle\SuspensionEngine;
use ArvanReseller\Ports\WalletRepository;
use ArvanReseller\Wallet\PaymentService;
use ArvanReseller\Wp\Frontend\CurrentCustomer;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

final class RechargeController {

	public const ACTION_INITIATE = 'arvan_recharge_initiate';
	public const ACTION_CONFIRM  = 'arvan_recharge_confirm';
	public const ACTION_FAIL     = 'arvan_recharge_fail';

	public function __construct(
		private readonly CurrentCustomer $currentCustomer,
		private readonly PaymentService $payments,
		private readonly WalletRepository $wallets,
		private readonly SuspensionEngine $suspension
	) {}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_INITIATE, [ $this, 'handleInitiate' ] );
		add_action( 'admin_post_' . self::ACTION_CONFIRM, [ $this, 'handleConfirm' ] );
		add_action( 'admin_post_' . self::ACTION_FAIL, [ $this, 'handleFail' ] );
	}

	public function handleInitiate(): void {
		$customer = $this->requireCustomerOrRedirect();

		check_admin_referer( self::ACTION_INITIATE );

		$customerId  = (int) $customer['id'];
		$amountToman = isset( $_POST['amount_toman'] ) ? absint( wp_unslash( $_POST['amount_toman'] ) ) : 0;

		if ( $amountToman <= 0 ) {
			wp_safe_redirect( add_query_arg( 'arvan_error', 'invalid_amount', home_url( '/arvan/recharge' ) ) );
			exit;
		}

		// A fresh idempotency key per attempt — this is a new submission,
		// not a replayed callback, so a new key (not a stable derived one)
		// is correct here.
		$idempotencyKey = 'recharge-' . $customerId . '-' . wp_generate_password( 12, false );

		$result = $this->payments->initiate( $customerId, Money::fromToman( $amountToman ), 'mock', $idempotencyKey );

		wp_safe_redirect( home_url( '/arvan/recharge?payment_id=' . $result['id'] ) );
		exit;
	}

	public function handleConfirm(): void {
		$customer = $this->requireCustomerOrRedirect();

		check_admin_referer( self::ACTION_CONFIRM );

		$customerId = (int) $customer['id'];
		$paymentId  = isset( $_POST['payment_id'] ) ? absint( wp_unslash( $_POST['payment_id'] ) ) : 0;

		try {
			$newBalance = $this->payments->confirmSucceeded( $paymentId, $customerId );
		} catch ( RuntimeException $e ) {
			wp_die( esc_html__( 'این پرداخت متعلق به شما نیست یا یافت نشد.', 'arvan-reseller' ), '', [ 'response' => 403 ] );
		}

		if ( null !== $newBalance ) {
			$resumeThreshold = $this->wallets->resumeThreshold( $customerId );
			$this->suspension->resumeEligibleForCustomer( $customerId, $newBalance, $resumeThreshold, get_current_user_id() );
		}

		// Drop `payment_id` so a reload cannot re-show confirm/fail buttons
		// for an already-settled payment.
		wp_safe_redirect( home_url( '/arvan/recharge?arvan_success=1' ) );
		exit;
	}

	public function handleFail(): void {
		$customer = $this->requireCustomerOrRedirect();

		check_admin_referer( self::ACTION_FAIL );

		$customerId = (int) $customer['id'];
		$paymentId  = isset( $_POST['payment_id'] ) ? absint( wp_unslash( $_POST['payment_id'] ) ) : 0;

		try {
			$this->payments->markAsFailed( $paymentId, $customerId );
		} catch ( RuntimeException $e ) {
			wp_die( esc_html__( 'این پرداخت متعلق به شما نیست یا یافت نشد.', 'arvan-reseller' ), '', [ 'response' => 403 ] );
		}

		wp_safe_redirect( home_url( '/arvan/recharge?arvan_error=payment_failed' ) );
		exit;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function requireCustomerOrRedirect(): array {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/arvan/auth' ) );
			exit;
		}

		$customer = $this->currentCustomer->resolve();

		if ( null === $customer ) {
			wp_die( esc_html__( 'حساب مشتری شما یافت نشد.', 'arvan-reseller' ) );
		}

		return $customer;
	}
}
