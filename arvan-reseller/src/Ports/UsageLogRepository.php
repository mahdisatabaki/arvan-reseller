<?php
/**
 * Persistence for one billed usage period (`arvan_usage_log`).
 *
 * DATA-MODEL.md §9's unique key on `(service_id, period_start)` is a second,
 * independent duplicate guard alongside `LedgerRepository`'s own
 * `idempotency_key` (BILLING.md §11) — `BillingService` derives its ledger
 * idempotency key deterministically from the same `(service_id, period)`
 * identity, so the ledger write is already the authoritative "billed once"
 * gate; this repository's own uniqueness is bookkeeping-level defense in
 * depth, not the primary safety mechanism.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

use ArvanReseller\Domain\Money;
use ArvanReseller\Pricing\ChargeBreakdown;
use DateTimeImmutable;

interface UsageLogRepository {

	/**
	 * Record one billed period, or return the existing row if one already
	 * exists for this `(service_id, period_start)` — same "return existing
	 * on duplicate" pattern as `ApiKeyRepository::create()`/
	 * `CustomerRepository::create()`. Never inserts a second row for the
	 * same service/period.
	 *
	 * @return array{id: int, created: bool}
	 */
	public function record(
		int $service_id,
		int $customer_id,
		DateTimeImmutable $period_start,
		DateTimeImmutable $period_end,
		int $traffic_value,
		string $traffic_unit,
		Money $unit_price,
		ChargeBreakdown $charge
	): array;
}
