<?php
/**
 * Provider-neutral contract for a CDN provider.
 *
 * Application code (the future ProvisioningService, MeteringService and
 * LifecycleService described in TECH.md §5) depends on this interface only —
 * never on `wp_remote_request()`, never on an ArvanCloud SDK type. Both the
 * real adapter (`ArvanCdnClient`, T-1.3) and the deterministic test double
 * (`MockCdnClient`, T-1.4) implement it identically, so the application layer
 * cannot tell which one it is talking to (ADR-013, API.md §12).
 *
 * Scope for this MVP — four methods only, each corresponding to one stage of
 * the CDN resource lifecycle this plugin actually drives
 * (SERVICE-LIFECYCLE.md §4 Provisioning and §9 Terminate):
 *
 *   createResource → provisioning
 *   getResource     → status sync / reconciliation
 *   getOutboundTrafficUsage → the one billable metric (CLAUDE.md: "Billing
 *                     metric: CDN Outbound Traffic only")
 *   deleteResource  → terminate
 *
 * Two methods BACKLOG's T-1.2 line item originally named are intentionally
 * absent, both for the same reason: the T-1.1 spike could not confirm a real
 * mechanism for them, and CLAUDE.md's Work Protocol §7 is explicit — "Do not
 * invent Arvan API endpoints... If not verified, stop that integration point
 * behind an interface/mock and record the unresolved item."
 *
 *   - `ping`: no health-check endpoint was found in either source consulted
 *     during the spike.
 *   - `holdResource`/`unholdResource`: the spike walked the full generated
 *     `DomainAPI` (12 operations) and `AccelerationAPI` (caching/image-resize
 *     settings, not a proxy toggle) and found no non-destructive suspend
 *     operation. SERVICE-LIFECYCLE.md §7's Suspend step and §8's Resume step
 *     still describe the *local* state machine correctly; they simply have no
 *     confirmed remote call to pair with yet. Adding these methods now would
 *     mean guessing their endpoint, which is exactly what is not allowed.
 *
 * When the real mechanism is confirmed (live-key verification, or a
 * different provider primitive such as a firewall deny-all rule), the
 * unresolved item is to extend this interface — not to have shipped a guess.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Arvan;

use DateTimeImmutable;

interface CdnClient {

	/**
	 * Provision a new CDN resource for a domain.
	 *
	 * SERVICE-LIFECYCLE.md §4 requires the local Order/Service row to exist
	 * with status `provisioning` *before* this is called, so a lost response
	 * never creates a remote resource without a recoverable local record
	 * (CLAUDE.md's Critical Engineering Invariants). That ordering is the
	 * caller's (ProvisioningService's) responsibility, not this method's.
	 *
	 * @throws \RuntimeException On any provider failure. Whether the caller
	 *         may safely retry, or must first reconcile via getResource()
	 *         before trying again (SERVICE-LIFECYCLE.md §4: "If remote success
	 *         is uncertain... attempt provider lookup/reconciliation first"),
	 *         is not decided by this interface — normalized, retry-aware
	 *         errors are T-1.3's concern (BACKLOG: "normalized errors" is an
	 *         `ArvanCdnClient` deliverable, not a `CdnClient` one).
	 */
	public function createResource( string $domain ): CdnResource;

	/**
	 * Read a CDN resource's current state.
	 *
	 * Used for status sync (SERVICE-LIFECYCLE.md §5, "Provider status sync")
	 * and for the reconciliation path above. Returns null rather than
	 * throwing when the resource does not exist, since "not found" is an
	 * expected, unexceptional outcome of a reconciliation check.
	 */
	public function getResource( string $domain ): ?CdnResource;

	/**
	 * The one billable signal this MVP meters: CDN Outbound Traffic for
	 * `$domain` between `$since` and `$until`.
	 *
	 * `$since`/`$until` are supplied by the caller — computed from the
	 * service's stored `metered_through` watermark and `Clock::now()`
	 * (src/Ports/Clock.php) — rather than read internally, for the same
	 * reason `ServiceRepository::dueForMetering()` takes an `$asOf`
	 * parameter: it keeps MeteringService's catch-up billing (TECH.md §9:
	 * "Metering works from `metered_through`, not one execution = one hour")
	 * testable without a live clock or a live provider.
	 *
	 * @throws \RuntimeException On any provider failure.
	 */
	public function getOutboundTrafficUsage(
		string $domain,
		DateTimeImmutable $since,
		DateTimeImmutable $until
	): OutboundTrafficUsage;

	/**
	 * Terminate (delete) a CDN resource permanently.
	 *
	 * SERVICE-LIFECYCLE.md §9: success moves the local service to
	 * `terminated`; a thrown exception is the caller's signal to move it to
	 * `terminate_failed` instead and audit the failure. "Retry is permitted
	 * only after provider state is checked" — so a retry loop belongs in the
	 * caller (LifecycleService), not in this method.
	 *
	 * @throws \RuntimeException On any provider failure.
	 */
	public function deleteResource( string $domain ): void;
}
