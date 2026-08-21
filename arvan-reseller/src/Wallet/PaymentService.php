<?php
/**
 * Mock Payment lifecycle: pending → succeeded/failed, with the wallet
 * credit that a success produces.
 *
 * DATA-MODEL.md §6's rule is why `confirmSucceeded()` calls
 * `PaymentRepository::markSucceeded()` before ever touching the ledger:
 * "one succeeded payment creates exactly one Ledger credit" — a duplicate
 * callback for the same payment gets `false` back from that call and this
 * service stops there instead of crediting a second time. The ledger
 * entry's own idempotency key (`payment-{id}`) is a second, independent
 * guard against the same failure mode (BILLING.md §11), not a redundant
 * one: even if a caller somehow invoked this twice concurrently,
 * `LedgerRepository::append()` still cannot double-credit.
 *
 * This is a Mock Payment gateway (CLAUDE.md: "Payment: Mock Payment /
 * manual receipt only") — there is no real external gateway callback here,
 * only `initiate()`/`confirmSucceeded()`/`markAsFailed()` called directly
 * by whatever demo trigger ends up calling them (T-5.4/T-7.4, not built
 * yet).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wallet;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\LedgerRepository;
use ArvanReseller\Ports\PaymentRepository;
use RuntimeException;

final class PaymentService {

	public function __construct(
		private readonly PaymentRepository $payments,
		private readonly LedgerRepository $ledger
	) {}

	/**
	 * @return array{id: int, created: bool}
	 */
	public function initiate(
		int $customer_id,
		Money $amount,
		string $method,
		string $idempotency_key
	): array {
		return $this->payments->createPending( $customer_id, $amount, $method, $idempotency_key );
	}

	/**
	 * Returns the resulting wallet balance if this call actually credited
	 * the wallet, or null if it was a no-op duplicate (the payment had
	 * already succeeded).
	 */
	public function confirmSucceeded( int $payment_id, int $customer_id ): ?Money {
		$payment = $this->payments->findOwnedByCustomer( $payment_id, $customer_id );

		if ( null === $payment ) {
			throw new RuntimeException( "Payment {$payment_id} does not belong to customer {$customer_id}." );
		}

		if ( ! $this->payments->markSucceeded( $payment_id ) ) {
			return null;
		}

		return $this->ledger->append(
			$customer_id,
			'wallet_credit',
			Money::fromRial( (int) $payment['amount_rial'] ),
			'payment-' . $payment_id,
			'payment',
			$payment_id,
			'Wallet top-up'
		);
	}

	public function markAsFailed( int $payment_id, int $customer_id, ?string $reason = null ): void {
		$payment = $this->payments->findOwnedByCustomer( $payment_id, $customer_id );

		if ( null === $payment ) {
			throw new RuntimeException( "Payment {$payment_id} does not belong to customer {$customer_id}." );
		}

		$this->payments->markFailed( $payment_id, $reason );
	}
}
