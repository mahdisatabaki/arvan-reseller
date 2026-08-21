<?php
/**
 * Suspends a service the moment its wallet goes to zero or below —
 * local-status only.
 *
 * T-1.1's API spike found no non-existent remote hold/unhold mechanism on
 * ArvanCloud (`CdnClient`'s own docblock records the search); guessing an
 * endpoint is exactly what CLAUDE.md's Work Protocol §7 forbids. Until a
 * real mechanism is confirmed, "Suspend" changes only this plugin's own
 * `arvan_services.status` — the remote CDN resource keeps serving traffic.
 * This is a deliberate, documented product decision (PROGRESS.md), not an
 * oversight: BILLING.md §14's "invoke SuspensionEngine in the same billing
 * workflow" and "do not wait for a separate cron" are both satisfied by
 * the local transition happening synchronously with the debit; only the
 * remote-call half of that section does not apply here.
 *
 * `suspendIfNeeded()` is idempotent by construction: it re-reads the
 * service's current status and does nothing unless it is still `active`,
 * so calling it again for an already-suspended service (a retried billing
 * operation, or a later period where the balance is still <= 0) is a
 * harmless no-op rather than a duplicate audit entry or a second
 * `updateStatus()` write.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Lifecycle;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\AuditLogger;
use ArvanReseller\Ports\ServiceRepository;

final class SuspensionEngine {

	public const REASON_WALLET = 'wallet';

	public function __construct(
		private readonly ServiceRepository $services,
		private readonly AuditLogger $auditLog
	) {}

	/**
	 * @return bool True if this call actually suspended the service; false
	 *              if the balance is still positive, the service was
	 *              already suspended (or in any non-`active` state), or it
	 *              does not exist.
	 */
	public function suspendIfNeeded(
		int $service_id,
		int $customer_id,
		Money $balance,
		?int $actor_wp_user_id = null
	): bool {
		if ( $balance->isPositive() ) {
			return false;
		}

		$service = $this->services->find( $service_id );

		if ( null === $service || ServiceStatus::ACTIVE !== $service['status'] ) {
			return false;
		}

		$this->services->updateStatus( $service_id, ServiceStatus::SUSPENDED, self::REASON_WALLET );

		$this->auditLog->record(
			'service.suspended',
			$actor_wp_user_id,
			$customer_id,
			'service',
			$service_id,
			'ok',
			[
				'reason'       => self::REASON_WALLET,
				'balance_rial' => $balance->toRial(),
			]
		);

		return true;
	}
}
