<?php
/**
 * One measured CDN outbound-traffic interval for a specific service.
 *
 * `OutboundTrafficUsage` (src/Arvan/OutboundTrafficUsage.php) is the
 * provider-neutral reading `CdnClient` returns, but `CdnClient` only ever
 * sees a domain string — it has no notion of which service or customer that
 * domain belongs to. This DTO is that same reading enriched with the
 * `service_id`/`customer_id`/`domain` MeteringService already had on hand
 * from the `services` row, so downstream consumers (T-5.3 Pricing + Debit)
 * can attribute the usage without re-joining back to the service.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Metering;

use DateTimeImmutable;

final class UsagePeriod {

	public function __construct(
		public readonly int $serviceId,
		public readonly int $customerId,
		public readonly string $domain,
		public readonly DateTimeImmutable $periodStart,
		public readonly DateTimeImmutable $periodEnd,
		public readonly int $usageValue,
		public readonly string $usageUnit
	) {}
}
