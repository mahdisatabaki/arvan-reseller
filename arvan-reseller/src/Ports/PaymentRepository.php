<?php
/**
 * Persistence for wallet top-up attempts and their idempotent settlement.
 *
 * DATA-MODEL.md §6's rule is the design center of this interface: "one
 * succeeded payment creates exactly one Ledger credit." A payment gateway or
 * a mock/manual-receipt flow can call back more than once for the same
 * attempt (BACKLOG T-3.4: "duplicate callback → no duplicate credit"), so
 * both write methods report whether *this* call was the one that actually
 * changed state — the caller only appends a Ledger credit when it was.
 *
 * `findOwnedByCustomer()` follows the same IDOR-safe pattern as
 * ServiceRepository (SECURITY.md §6 lists Payment explicitly).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

use ArvanReseller\Domain\Money;

interface PaymentRepository {

	/**
	 * Record a new payment attempt, or return the existing one if
	 * `$idempotency_key` was already used.
	 *
	 * @return array{id: int, created: bool} `created` is false when this
	 *         idempotency key already had a row — the caller must not treat
	 *         that as a new attempt.
	 */
	public function createPending(
		int $customer_id,
		Money $amount,
		string $method,
		string $idempotency_key
	): array;

	/**
	 * Transition a payment to `succeeded`.
	 *
	 * @return bool True if this call performed the transition; false if the
	 *              payment was already `succeeded` (a duplicate callback).
	 *              The caller must only append a Ledger credit when this is
	 *              true — that is what makes the "exactly one credit" rule
	 *              hold under retries.
	 */
	public function markSucceeded( int $payment_id ): bool;

	/**
	 * Transition a payment to `failed`. Safe to call on an already-failed
	 * payment (no-op); must not be applied to an already-`succeeded` payment.
	 */
	public function markFailed( int $payment_id, ?string $reason = null ): void;

	/**
	 * The IDOR-safe read: a payment belongs to `$customer_id`, or this
	 * returns null.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findOwnedByCustomer( int $payment_id, int $customer_id ): ?array;
}
