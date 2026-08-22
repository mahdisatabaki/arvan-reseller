<?php
/**
 * Suspends a service the moment its wallet goes to zero or below, and
 * resumes it once a recharge clears the resume threshold — both
 * local-status only.
 *
 * T-1.1's API spike found no non-existent remote hold/unhold mechanism on
 * ArvanCloud (`CdnClient`'s own docblock records the search); guessing an
 * endpoint is exactly what CLAUDE.md's Work Protocol §7 forbids. Until a
 * real mechanism is confirmed, "Suspend" and "Resume" change only this
 * plugin's own `arvan_services.status` — the remote CDN resource keeps
 * serving traffic throughout. This is a deliberate, documented product
 * decision (PROGRESS.md, made explicitly for T-6.3 and applied symmetrically
 * here for T-6.4), not an oversight: BILLING.md §14's "invoke
 * SuspensionEngine in the same billing workflow" and "do not wait for a
 * separate cron" are both satisfied by the local transition happening
 * synchronously with the debit/credit; only the remote-call half of
 * BILLING.md §14/§15 does not apply here.
 *
 * `suspendIfNeeded()` and `resumeIfEligible()` are both idempotent by
 * construction: each re-reads the service's current status and does
 * nothing unless it is in the expected starting state, so calling either
 * again for a service already in its target state (a retried billing
 * operation, or a later period where the condition still holds) is a
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

	/**
	 * @return bool True if this call actually resumed the service; false if
	 *              the balance does not exceed the resume threshold, the
	 *              service does not exist, is not currently `suspended`, or
	 *              was suspended for a reason other than `wallet`.
	 */
	public function resumeIfEligible(
		int $service_id,
		int $customer_id,
		Money $balance,
		Money $resumeThreshold,
		?int $actor_wp_user_id = null
	): bool {
		if ( ! $balance->greaterThan( $resumeThreshold ) ) {
			return false;
		}

		$service = $this->services->find( $service_id );

		if ( null === $service
			|| ServiceStatus::SUSPENDED !== $service['status']
			|| self::REASON_WALLET !== $service['suspend_reason']
		) {
			return false;
		}

		$this->services->updateStatus( $service_id, ServiceStatus::ACTIVE, null );

		$this->auditLog->record(
			'service.resumed',
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

	/**
	 * @return int[] The service ids that were actually resumed.
	 */
	public function resumeEligibleForCustomer(
		int $customer_id,
		Money $balance,
		Money $resumeThreshold,
		?int $actor_wp_user_id = null
	): array {
		$candidates = $this->services->findSuspendedByCustomer( $customer_id, self::REASON_WALLET );
		$resumed    = [];

		foreach ( $candidates as $candidate ) {
			$service_id = (int) $candidate['id'];

			if ( $this->resumeIfEligible( $service_id, $customer_id, $balance, $resumeThreshold, $actor_wp_user_id ) ) {
				$resumed[] = $service_id;
			}
		}

		return $resumed;
	}
}
