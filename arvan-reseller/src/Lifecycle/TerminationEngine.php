<?php
/**
 * Deletes the remote CDN resource for a service whose wallet-triggered
 * suspension has outlasted the reseller's grace period, and marks it
 * terminated locally.
 *
 * Unlike Suspend/Resume (`SuspensionEngine`), Termination has a real,
 * confirmed remote call: `CdnClient::deleteResource()` exists and was
 * verified in T-1.1/T-1.3 — there is no "local status only" limitation
 * here. BILLING.md §16: "Termination is irreversible in MVP" — there is
 * deliberately no method on this class that reverses a terminated
 * service; `ServiceStatus::TERMINATED` has no outgoing transitions
 * (T-4.1) for the same reason.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Lifecycle;

use ArvanReseller\Arvan\CdnClient;
use ArvanReseller\Arvan\CdnProviderException;
use ArvanReseller\Ports\AuditLogger;
use ArvanReseller\Ports\ServiceRepository;

final class TerminationEngine {

	public function __construct(
		private readonly ServiceRepository $services,
		private readonly AuditLogger $auditLog
	) {}

	/**
	 * @return array{ok: bool, service_id: int, message: ?string}
	 */
	public function terminate( int $service_id, CdnClient $client, ?int $actor_wp_user_id = null ): array {
		$service = $this->services->find( $service_id );

		if ( null === $service || ServiceStatus::SUSPENDED !== $service['status'] ) {
			return [ 'ok' => false, 'service_id' => $service_id, 'message' => 'Service is not in a terminable state.' ];
		}

		$customerId = (int) $service['customer_id'];

		try {
			$client->deleteResource( $service['domain'] );
		} catch ( CdnProviderException $e ) {
			$this->services->updateStatus( $service_id, ServiceStatus::TERMINATE_FAILED );

			$this->auditLog->record(
				'service.terminate_failed',
				$actor_wp_user_id,
				$customerId,
				'service',
				$service_id,
				'failed',
				[ 'category' => $e->category ]
			);

			return [ 'ok' => false, 'service_id' => $service_id, 'message' => $e->getMessage() ];
		}

		$this->services->updateStatus( $service_id, ServiceStatus::TERMINATED );

		$this->auditLog->record(
			'service.terminated',
			$actor_wp_user_id,
			$customerId,
			'service',
			$service_id,
			'ok',
			[ 'domain' => $service['domain'] ]
		);

		return [ 'ok' => true, 'service_id' => $service_id, 'message' => null ];
	}
}
