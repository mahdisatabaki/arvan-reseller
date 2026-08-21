<?php
/**
 * Prices one measured usage period and debits the wallet for it — exactly
 * once, even under a genuinely concurrent retry (T-5.2 + T-5.3 combined:
 * they are two sides of one atomic operation, not two independent steps).
 *
 * The ledger idempotency key is deterministically built from
 * `service_id + period_start` ONLY — not `period_end` — and that is not
 * incidental. `period_start` is the stable value both a call and its
 * concurrent retry read (the service's `metered_through` watermark,
 * unchanged until one of them actually advances it); `period_end` is
 * whatever `Clock::now()` happened to be at the moment each call ran
 * `MeteringService::measure()`, which two near-simultaneous invocations
 * will NOT agree on. Keying on `period_end` too would defeat idempotency
 * on exactly the race this task exists to close. This matches
 * `arvan_usage_log`'s own unique index, `(service_id, period_start)`
 * (Schema.php) — same identity, same reasoning.
 *
 * Whichever concurrent call's `LedgerRepository::append()` wins the race
 * is the one whose computed charge stands; the loser's `append()` call
 * returns the already-settled balance instead of crediting again
 * (BILLING.md §11). The loser still advances `metered_through` to its own
 * `period_end` afterward — harmless, since the two periods are only
 * microseconds apart in a true race, and the next billing cycle can never
 * double-bill regardless of exactly where the watermark landed.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Billing;

use ArvanReseller\Metering\UsagePeriod;
use ArvanReseller\Metering\UsagePricingAdapter;
use ArvanReseller\Domain\Money;
use ArvanReseller\Pricing\ChargeBreakdown;
use ArvanReseller\Pricing\MarkupRate;
use ArvanReseller\Pricing\ResellerPricing;
use ArvanReseller\Ports\LedgerRepository;
use ArvanReseller\Ports\ServiceRepository;
use ArvanReseller\Ports\UsageLogRepository;
use DateTimeInterface;
use RuntimeException;

final class BillingService {

	private const METRIC = 'cdn_outbound_traffic';

	public function __construct(
		private readonly UsagePricingAdapter $pricingAdapter,
		private readonly LedgerRepository $ledger,
		private readonly UsageLogRepository $usageLog,
		private readonly ServiceRepository $services
	) {}

	/**
	 * @return array{ok: bool, billed: bool, charge: ?ChargeBreakdown, usage_log_id: ?int, message: ?string}
	 */
	public function bill( UsagePeriod $usage, MarkupRate $markupRate, Money $unitPriceRialPerGb ): array {
		try {
			$baseCost = $this->pricingAdapter->priceUsage( $usage, $unitPriceRialPerGb );
		} catch ( RuntimeException $e ) {
			return [
				'ok'           => false,
				'billed'       => false,
				'charge'       => null,
				'usage_log_id' => null,
				'message'      => $e->getMessage(),
			];
		}

		$charge = ( new ResellerPricing( $markupRate ) )->charge( $baseCost );

		$this->ledger->append(
			$usage->customerId,
			'usage_debit',
			$charge->total->negated(),
			$this->idempotencyKey( $usage ),
			'service',
			$usage->serviceId,
			sprintf(
				'CDN outbound traffic, %s to %s',
				$usage->periodStart->format( DateTimeInterface::ATOM ),
				$usage->periodEnd->format( DateTimeInterface::ATOM )
			),
			[
				'base_rial'   => $charge->base->toRial(),
				'markup_rial' => $charge->markup->toRial(),
				'markup_bps'  => $charge->rate->toBasisPoints(),
			]
		);

		$log = $this->usageLog->record(
			$usage->serviceId,
			$usage->customerId,
			$usage->periodStart,
			$usage->periodEnd,
			$usage->usageValue,
			$usage->usageUnit,
			$unitPriceRialPerGb,
			$charge
		);

		$this->services->markMeteredThrough( $usage->serviceId, $usage->periodEnd );

		return [
			'ok'           => true,
			'billed'       => $log['created'],
			'charge'       => $charge,
			'usage_log_id' => $log['id'],
			'message'      => null,
		];
	}

	private function idempotencyKey( UsagePeriod $usage ): string {
		return sprintf(
			'usage-%d-%s-%s',
			$usage->serviceId,
			$usage->periodStart->format( DateTimeInterface::ATOM ),
			self::METRIC
		);
	}
}
