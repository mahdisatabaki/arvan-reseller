<?php
/**
 * CDN Outbound Traffic for one metered period, normalized.
 *
 * PRD's Client Contract (§10) draws the line this DTO sits on: the provider
 * adapter's job is "raw response → extract verified outbound traffic →
 * normalize unit/time interval → return OutboundTrafficUsage"; the pricing
 * adapter then only ever sees this normalized shape, never ArvanCloud's JSON.
 *
 * Two fields are deliberately shaped by what the T-1.1 spike actually found,
 * not by assumption:
 *
 * - No `isCumulative`/`isBucketed` flag. API.md §4 allows one "if needed" —
 *   it is not needed here, because the spike confirmed ArvanCloud's traffic
 *   report is always period-bucketed for the one endpoint this MVP uses
 *   (`GET /domains/{domain}/reports/traffics`): the reference client reads
 *   the *last* bucket of a time-indexed series, which only makes sense for
 *   bucketed data. A cumulative counter would need no "last bucket" logic.
 *   Modeling a flag that can only ever hold one value for this MVP would be
 *   speculative, not defensive.
 * - `usageValue` is `int`, not `float`. The spike's evidence (both the
 *   production `ar-prometheus-exporter` client and the generated OpenAPI
 *   models) consistently typed traffic figures as 32-bit integers.
 * - `usageUnit` stays a plain string rather than a hardcoded "byte" constant.
 *   The spike found strong circumstantial evidence for bytes (a sibling bulk
 *   endpoint's field is literally named `EgressBytes`) but could not confirm
 *   the unit on this specific endpoint. Leaving it as adapter-supplied means
 *   that if the real value turns out to be something else, only
 *   `ArvanCdnClient` changes — this contract does not.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Arvan;

use DateTimeImmutable;

final class OutboundTrafficUsage {

	public function __construct(
		public readonly DateTimeImmutable $periodStart,
		public readonly DateTimeImmutable $periodEnd,
		/** Whole-unit traffic figure as reported by the provider for this period. */
		public readonly int $usageValue,
		/** e.g. "byte" — whatever unit the adapter confirms and normalizes to. */
		public readonly string $usageUnit
	) {}
}
