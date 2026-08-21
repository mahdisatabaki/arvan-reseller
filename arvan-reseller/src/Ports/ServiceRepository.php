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
}
