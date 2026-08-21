<?php
/**
 * Customer-facing "what did I get" shape for a provisioned service
 * (SCREEN-SPECS.md §11 "Provisioning Result", §13 "Customer Service Detail"):
 * domain, status, resource id, and provisioning date, built from an
 * `arvan_services` row — not from ProvisioningService's/ResourceSyncService's
 * internal bookkeeping result arrays (those carry `order_id`/`ok`, which are
 * not customer-facing).
 *
 * `configuration` is always null. BACKLOG.md T-4.3 asks for "configuration/
 * instructions returned by API when applicable", but PROGRESS.md's open-
 * decisions table (open since T-1.1) records that ArvanCloud's actual
 * response fields were never confirmed against a real API key — only
 * `resourceId`/`domain`/`status`/`createdAt` are confirmed on `CdnResource`
 * (see that class's docblock). There is no verified shape for a
 * configuration/instructions payload anywhere in this codebase, so per
 * CLAUDE.md Work Protocol §7 ("do not invent Arvan API response fields")
 * this field exists to satisfy the delivery-data contract but can only ever
 * be null until a real credential confirms what ArvanCloud actually returns.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Provisioning;

use DateTimeImmutable;

final class DeliveryData {

	public function __construct(
		public readonly ?string $resourceId,
		public readonly string $domain,
		public readonly string $status,
		/** Always null. See class docblock. */
		public readonly ?array $configuration,
		public readonly ?DateTimeImmutable $provisionedAt
	) {}

	/**
	 * @param array<string, mixed> $service An `arvan_services` row, e.g. from
	 *                                       `ServiceRepository::find()` or
	 *                                       `findOwnedByCustomer()`.
	 */
	public static function fromServiceRow( array $service ): self {
		$provisioned_at = $service['provisioned_at'] ?? null;

		return new self(
			isset( $service['arvan_resource_id'] ) ? (string) $service['arvan_resource_id'] : null,
			(string) $service['domain'],
			(string) $service['status'],
			null,
			null === $provisioned_at ? null : new DateTimeImmutable( (string) $provisioned_at )
		);
	}
}
