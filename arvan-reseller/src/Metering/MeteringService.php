<?php
/**
 * Turns one `services` row due for metering into a normalized usage reading.
 *
 * Usage fetching only (BACKLOG T-5.1). Deliberately does not compute price,
 * does not touch `LedgerRepository`, and does not call
 * `ServiceRepository::markMeteredThrough()` — that method's own docblock says
 * the watermark advances "after a period has been billed", paired with
 * `LedgerRepository::append()` inside the same operation (T-5.3, not built
 * yet). Advancing it here, before pricing/debit even runs, would lose a
 * usage interval forever the moment billing failed after a successful fetch.
 *
 * `measure()` takes one service row and one already-constructed `CdnClient`
 * rather than looping over `ServiceRepository::dueForMetering()` itself, for
 * the same reason `ProvisioningService::provision()` takes an injected
 * `CdnClient` (T-4.2): different services can carry different `api_key_id`s
 * (DATA-MODEL.md §8), and resolving the right key needs `SecretStore` +
 * `WordPressHttpClient` — both WordPress-bound concerns this
 * framework-agnostic class must not depend on. The per-service loop and
 * per-key client resolution belong to the WP-layer cron handler (T-5.2).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Metering;

use ArvanReseller\Arvan\CdnClient;
use ArvanReseller\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

final class MeteringService {

	public function __construct(
		private readonly Clock $clock
	) {}

	/**
	 * @param array<string, mixed> $service A `services` row (ARRAY_A) as
	 *        returned by `ServiceRepository::dueForMetering()` — at minimum
	 *        `id`, `customer_id`, `domain`, `metered_through`,
	 *        `provisioned_at`, `created_at`.
	 */
	public function measure( array $service, CdnClient $client ): UsagePeriod {
		$since = $this->periodStart( $service );
		$until = $this->clock->now();

		$usage = $client->getOutboundTrafficUsage( $service['domain'], $since, $until );

		return new UsagePeriod(
			(int) $service['id'],
			(int) $service['customer_id'],
			$service['domain'],
			$usage->periodStart,
			$usage->periodEnd,
			$usage->usageValue,
			$usage->usageUnit
		);
	}

	/**
	 * `metered_through` → `provisioned_at` → `created_at`, the first
	 * non-null one: a service never metered starts from when it went live;
	 * one never even fully provisioned falls back to its creation time as a
	 * last resort (TECH.md §9).
	 */
	private function periodStart( array $service ): DateTimeImmutable {
		$raw = $service['metered_through'] ?? $service['provisioned_at'] ?? $service['created_at'];

		return new DateTimeImmutable( $raw, new DateTimeZone( 'UTC' ) );
	}
}
