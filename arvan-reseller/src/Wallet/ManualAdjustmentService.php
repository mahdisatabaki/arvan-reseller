<?php
/**
 * Admin-triggered direct ledger adjustment, not tied to any payment row.
 *
 * SCREEN-SPECS.md §4's "Manual Wallet adjustment" admin action: amount,
 * direction, mandatory reason, confirmation, audit. Direction is folded into
 * `$amount`'s sign (LedgerRepository's convention: positive credits, negative
 * debits) rather than a separate parameter, for the same reason PaymentService
 * has no credit()/debit() split — one arithmetic path.
 *
 * Audit is recorded only after the ledger append succeeds: if `append()`
 * throws or is a no-op duplicate for an already-used idempotency key, there is
 * nothing new to audit.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wallet;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\AuditLogger;
use ArvanReseller\Ports\LedgerRepository;
use InvalidArgumentException;

final class ManualAdjustmentService {

	public function __construct(
		private readonly LedgerRepository $ledger,
		private readonly AuditLogger $auditLog
	) {}

	public function adjust(
		int $customer_id,
		Money $amount,
		string $reason,
		?int $actor_wp_user_id,
		string $idempotency_key
	): Money {
		if ( '' === trim( $reason ) ) {
			throw new InvalidArgumentException( 'A reason is required for a manual wallet adjustment.' );
		}

		if ( $amount->isZero() ) {
			throw new InvalidArgumentException( 'A manual wallet adjustment amount cannot be zero.' );
		}

		$balance = $this->ledger->append(
			$customer_id,
			'manual_adjustment',
			$amount,
			$idempotency_key,
			null,
			null,
			$reason
		);

		$this->auditLog->record(
			'wallet.manual_adjustment',
			$actor_wp_user_id,
			$customer_id,
			null,
			null,
			'ok',
			[
				'amount_rial' => $amount->toRial(),
				'reason'      => $reason,
			]
		);

		return $balance;
	}
}
