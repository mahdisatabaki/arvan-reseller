<?php
/**
 * Persistence for the commercial request that precedes a provisioned
 * CDN service (`arvan_orders`).
 *
 * DATA-MODEL.md §7's ordering is the reason `ProvisioningService` (T-4.2)
 * calls `create()` on this port before it ever calls
 * `ServiceRepository::createProvisioning()` or `CdnClient::createResource()`:
 * "The order is created BEFORE any ArvanCloud API call... so a failed or
 * interrupted API call never leaves an orphaned service: it leaves a
 * `failed` order instead." `markup_bps_snapshot` freezes the reseller's
 * markup rate at order time so a later settings change cannot rewrite what
 * this specific order actually charged.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

interface OrderRepository {

	/**
	 * Create a new order in `pending` status. Returns the new order id.
	 *
	 * @param array<string, mixed> $requested_config
	 */
	public function create(
		int $customer_id,
		string $product,
		string $domain,
		int $markup_bps_snapshot,
		array $requested_config = []
	): int;

	/**
	 * Link the order to the local service row `ProvisioningService` just
	 * created and move it to `provisioning` — called after
	 * `ServiceRepository::createProvisioning()` returns, before the remote
	 * `CdnClient::createResource()` call is attempted.
	 */
	public function markProvisioning( int $order_id, int $service_id ): void;

	/** The remote resource was created successfully; order → `completed`. */
	public function markCompleted( int $order_id ): void;

	/**
	 * The remote resource call failed; order → `failed`. `$reason` must
	 * already be safe to display (e.g. a `CdnProviderException` message,
	 * never a raw provider body or secret — SECURITY.md §10).
	 */
	public function markFailed( int $order_id, string $reason ): void;

	/**
	 * The IDOR-safe read: an order belongs to `$customer_id`, or this
	 * returns null.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findOwnedByCustomer( int $order_id, int $customer_id ): ?array;
}
