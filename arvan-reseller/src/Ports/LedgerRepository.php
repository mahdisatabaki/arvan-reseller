<?php
/**
 * The single write path into the append-only financial ledger.
 *
 * DATA-MODEL.md §5: ledger rows are never updated or deleted after creation;
 * a correction is a new, compensating entry. TECH.md §8 requires the ledger
 * insert and the wallet's cached balance update to happen in one atomic
 * transaction — this is that transaction's entry point, which is also why
 * WalletRepository has no write method of its own (see WalletRepository.php).
 *
 * Sign convention (DATA-MODEL.md §5): a credit is a positive amount, a debit
 * is a negative amount. Callers pass `$amount->negated()` for a debit rather
 * than this interface having separate credit()/debit() methods, so there is
 * exactly one code path to get the arithmetic right.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

use ArvanReseller\Domain\Money;

interface LedgerRepository {

	/**
	 * Append one ledger entry and atomically update the wallet's cached
	 * balance, then return the resulting balance. Returning it directly is
	 * what lets a caller check `<= 0` and trigger Suspend in the same flow
	 * (ADR-011) without a second read.
	 *
	 * Idempotency (CLAUDE.md "Financial writes use idempotency keys"; PRD E8):
	 * if `$idempotency_key` already exists, this method MUST NOT insert a
	 * second row or change the balance again — it returns the balance that
	 * resulted from the original append. This is what makes a retried
	 * payment callback or a re-run billing cron safe (BACKLOG T-5.2, T-3.4).
	 *
	 * @param string $type            e.g. "wallet_credit", "usage_debit",
	 *                                 "manual_adjustment", "refund" (DATA-MODEL.md §5).
	 * @param Money  $amount          Positive for a credit, negative for a debit.
	 * @param string $reference_type  e.g. "payment", "usage_log". Nullable.
	 * @param int|null $reference_id
	 * @param array<string, mixed> $metadata
	 */
	public function append(
		int $customer_id,
		string $type,
		Money $amount,
		string $idempotency_key,
		?string $reference_type = null,
		?int $reference_id = null,
		?string $description = null,
		array $metadata = []
	): Money;

	/**
	 * Recent entries for one customer, newest first — the data behind the
	 * customer-facing transaction history (PRD F9) and the admin Finance →
	 * Ledger screen (ADR-016). Always scoped to a single customer; there is
	 * deliberately no "fetch by ledger id alone" method, so a caller cannot
	 * accidentally look up another customer's row (SECURITY.md §6).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function historyForCustomer( int $customer_id, int $limit = 50, int $offset = 0 ): array;

	/**
	 * Recent ledger entries across every customer, newest first — the
	 * Admin Finance "Ledger" tab (SCREEN-SPECS.md §6, ADR-016). Admin-only,
	 * unscoped like `PaymentRepository::allRecent()`.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function allRecent( int $limit = 50, int $offset = 0 ): array;
}
