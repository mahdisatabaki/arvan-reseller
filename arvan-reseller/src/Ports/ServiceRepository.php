<?php
/**
 * Ownership-scoped persistence for a customer's CDN service.
 *
 * `findOwnedByCustomer()` follows the IDOR-safe pattern SECURITY.md §6 spells
 * out verbatim: "requested service_id + current customer_id → owned service
 * query", never "requested service_id → service" alone. Every screen and
 * every lifecycle action must go through this method, not a bare find-by-id.
 *
 * `createProvisioning()` exists to satisfy CLAUDE.md's invariant that "a
 * failed provisioning call must not create an unowned remote resource without
 * a recoverable local record": the local row is written, in `provisioning`
 * status, *before* ProvisioningService ever calls the ArvanCloud API
 * (BACKLOG T-4.2), so a crash mid-call still leaves something to retry
 * against instead of an orphaned resource on ArvanCloud's side.
 *
 * DATA-MODEL.md §8's rule — "lifecycle calls always use this row's
 * `api_key_id`" — is why every read here returns the full row rather than a
 * bare status string: the caller needs `api_key_id` and `remote_resource_id`
 * together to hold/unhold/delete the right resource with the right credential.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

use DateTimeImmutable;

interface ServiceRepository {

	/**
	 * Create the local service row before any remote ArvanCloud API call is
	 * made. Returns the new service id.
	 */
	public function createProvisioning(
		int $customer_id,
		int $order_id,
		int $api_key_id,
		string $domain
	): int;

	/**
	 * The IDOR-safe read: a service belongs to `$customer_id`, or this
	 * returns null. Never returns another customer's row.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findOwnedByCustomer( int $service_id, int $customer_id ): ?array;

	/**
	 * Unscoped read by id, for admin/system contexts only (e.g. the Admin
	 * Services "retry" action, SCREEN-SPECS.md §5, or a reconciliation job
	 * — neither is a per-customer request with a customer id to check
	 * against). Never call this from customer-facing code; use
	 * `findOwnedByCustomer()` there instead (SECURITY.md §6).
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( int $service_id ): ?array;

	/**
	 * Active services whose next metering period starts at or before
	 * `$asOf` — i.e. work outstanding for MeteringService (TECH.md §5:
	 * "determine unprocessed usage interval"). `$asOf` is caller-supplied
	 * (from Clock::now()) rather than read internally, so this stays testable
	 * without a live clock.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function dueForMetering( DateTimeImmutable $asOf ): array;

	/**
	 * Advance the metering watermark after a period has been billed. Paired
	 * with LedgerRepository::append() inside the same billing operation.
	 */
	public function markMeteredThrough( int $service_id, DateTimeImmutable $through ): void;

	/**
	 * Records the provider's resource id once `CdnClient::createResource()`
	 * succeeds (T-4.2). Separate from `updateStatus()` because storing the
	 * resource's identity is a one-time fact tied to successful creation,
	 * not a state transition by itself — `ProvisioningService` calls both
	 * together on success, but a future reconciliation path (T-4.4) may
	 * need to call `updateStatus()` alone.
	 */
	public function recordProvisioned( int $service_id, string $remote_resource_id, DateTimeImmutable $at ): void;

	/**
	 * Persist a lifecycle state transition. This port does not validate that
	 * the transition is legal — that is LifecycleService's job (TECH.md §5);
	 * this method only records the outcome the caller already decided on.
	 *
	 * Implementations stamp `suspended_at`/`terminated_at` automatically when
	 * `$status` is `suspended`/`terminated` — that timestamp is what
	 * `dueForTermination()` measures the grace period against, so it must
	 * always be set the moment a service actually becomes suspended, not
	 * left to a caller that might forget.
	 *
	 * @param string      $status            One of the Service states in
	 *                                        DATA-MODEL.md §8, e.g. "active",
	 *                                        "suspended", "terminated",
	 *                                        "suspend_failed".
	 * @param string|null $suspension_reason  Set when $status is "suspended",
	 *                                        so a later recharge (ADR-012)
	 *                                        can tell a wallet-triggered
	 *                                        suspension apart from any other
	 *                                        hold before attempting auto-resume.
	 */
	public function updateStatus(
		int $service_id,
		string $status,
		?string $suspension_reason = null
	): void;

	/**
	 * A customer's services currently suspended for the given reason (e.g.
	 * `wallet`) — the set a successful recharge must re-check for eligible
	 * resume (BILLING.md §15). Scoped to one customer by construction (the
	 * caller already knows whose wallet was just credited), so this has no
	 * separate IDOR concern the way a bare `find()` would.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function findSuspendedByCustomer( int $customer_id, string $reason ): array;

	/**
	 * Every service belonging to one customer, newest first — the Customer
	 * Account Dashboard's "Services" section (SCREEN-SPECS.md §12). Scoped to
	 * one customer by construction, the same "no separate IDOR concern"
	 * reasoning as `findSuspendedByCustomer()`.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function allForCustomer( int $customer_id ): array;

	/**
	 * Wallet-suspended services whose grace period has elapsed —
	 * `suspended_at <= $suspendedBefore` (BILLING.md §16:
	 * `terminate_after = suspended_at + grace_period`). `$suspendedBefore`
	 * is caller-computed (`Clock::now()` minus the reseller's configured
	 * grace period), matching `dueForMetering()`'s own `$asOf` pattern, so
	 * this stays testable without a live clock.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function dueForTermination( DateTimeImmutable $suspendedBefore ): array;

	/**
	 * Every service across every customer, newest first — the Admin
	 * Services list (SCREEN-SPECS.md §5). Admin-only, unscoped like
	 * `find()`; never call this from customer-facing code.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function allForAdmin(): array;

	/**
	 * Records one provisioning attempt outcome: increments the attempt
	 * counter and stores (or clears, on success) the last error message.
	 * Separate from `updateStatus()`/`recordProvisioned()` because a
	 * retry (Admin Services "retry" action, SCREEN-SPECS.md §5) needs to
	 * track attempt history independently of the state transition itself.
	 */
	public function recordProvisionAttempt( int $service_id, ?string $error ): void;
}
