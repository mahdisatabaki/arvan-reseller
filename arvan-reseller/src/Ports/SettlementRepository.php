<?php
/**
 * Persistence for reconciliation periods (`arvan_settlements`).
 *
 * ADR-015: Settlement can be simulated. `create()` is T-9.1's write path —
 * `SettlementService::run()` calls it once per aggregation run, after
 * summing whatever `UsageLogRepository::unsettled()` returned. Idempotent on
 * `(period_start, period_end)` (the table's own unique key, DATA-MODEL.md
 * §11) via the same "return existing on duplicate" pattern every other
 * repository in this plugin already uses.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

use ArvanReseller\Domain\Money;
use DateTimeImmutable;

interface SettlementRepository {

	/**
	 * Settlement periods, newest first — SCREEN-SPECS.md §6's "Settlements"
	 * tab. Empty until a settlement run has happened; an empty result is a
	 * legitimate "no settlements yet" state, not an error.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function allRecent( int $limit = 50 ): array;

	/**
	 * Record one settlement period, or return the existing row if this exact
	 * `(period_start, period_end)` was already settled — a repeat cron/manual
	 * run never double-counts the same window (BILLING.md §17's reconciliation
	 * invariant only holds if this is true). `$gateway` is `'mock'` for the
	 * MVP (ADR-015); `$status` is caller-supplied so a Mock run can mark
	 * itself `'transmitted'` immediately rather than needing a separate
	 * two-step draft/transmit flow with no real endpoint on the other end.
	 *
	 * @return array{id: int, created: bool}
	 */
	public function create(
		DateTimeImmutable $period_start,
		DateTimeImmutable $period_end,
		Money $base,
		Money $markup,
		Money $gross,
		int $sample_count,
		string $status = 'transmitted',
		string $gateway = 'mock'
	): array;
}
