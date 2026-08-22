<?php
/**
 * Rolls unsettled usage into a reconciliation period (BACKLOG T-9.1,
 * BILLING.md §17, ADR-015 "Settlement can be simulated").
 *
 * Unlike `MeteringCronHandler`'s daily/hourly period boundaries (which are
 * caller-computed calendar windows so re-runs stay predictable), this
 * service derives the settlement's own `period_start`/`period_end` from the
 * actual min/max of whatever usage_log rows it aggregates. That means one
 * run always settles *everything* currently outstanding — including a
 * multi-day gap after Cron sat idle — without needing separate catch-up
 * logic, and the settlement's own period honestly describes what it covers
 * rather than an arbitrary caller-supplied window that might not match.
 *
 * base_total + markup_total = customer_total (gross) is enforced by
 * construction here, not re-validated: each is a running sum of the same
 * per-row Money values `BillingService` already computed and persisted.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Settlement;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\SettlementRepository;
use ArvanReseller\Ports\UsageLogRepository;
use DateTimeImmutable;

final class SettlementService {

	public function __construct(
		private readonly UsageLogRepository $usageLogs,
		private readonly SettlementRepository $settlements
	) {}

	/**
	 * @return array{
	 *     ok: bool,
	 *     created: bool,
	 *     settlement_id: ?int,
	 *     sample_count: int,
	 *     base_rial: int,
	 *     markup_rial: int,
	 *     gross_rial: int
	 * }
	 */
	public function run(): array {
		$rows = $this->usageLogs->unsettled();

		if ( [] === $rows ) {
			return [
				'ok'            => true,
				'created'       => false,
				'settlement_id' => null,
				'sample_count'  => 0,
				'base_rial'     => 0,
				'markup_rial'   => 0,
				'gross_rial'    => 0,
			];
		}

		$base    = Money::zero();
		$markup  = Money::zero();
		$gross   = Money::zero();
		$ids     = [];
		$earliest = null;
		$latest   = null;

		foreach ( $rows as $row ) {
			$base   = $base->plus( Money::fromRial( (int) $row['base_rial'] ) );
			$markup = $markup->plus( Money::fromRial( (int) $row['markup_rial'] ) );
			$gross  = $gross->plus( Money::fromRial( (int) $row['total_rial'] ) );
			$ids[]  = (int) $row['id'];

			$periodStart = new DateTimeImmutable( (string) $row['period_start'] );
			$periodEnd   = new DateTimeImmutable( (string) $row['period_end'] );

			if ( null === $earliest || $periodStart < $earliest ) {
				$earliest = $periodStart;
			}

			if ( null === $latest || $periodEnd > $latest ) {
				$latest = $periodEnd;
			}
		}

		$result = $this->settlements->create( $earliest, $latest, $base, $markup, $gross, count( $rows ) );

		if ( $result['created'] ) {
			$this->usageLogs->markSettled( $ids, $result['id'] );
		}

		return [
			'ok'            => true,
			'created'       => $result['created'],
			'settlement_id' => $result['id'],
			'sample_count'  => count( $rows ),
			'base_rial'     => $base->toRial(),
			'markup_rial'   => $markup->toRial(),
			'gross_rial'    => $gross->toRial(),
		];
	}
}
