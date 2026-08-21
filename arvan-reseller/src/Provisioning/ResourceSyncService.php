<?php
/**
 * Retries a failed provisioning attempt, reconciling against the provider
 * first rather than blindly re-creating.
 *
 * `CdnClient::createResource()`'s own docblock leaves this exact question
 * open: "Whether the caller may safely retry, or must first reconcile via
 * getResource() before trying again... is not decided by this interface."
 * This class is that decision: it always calls `getResource()` before ever
 * calling `createResource()` again. If the provider already has the
 * resource (the original `createResource()` call likely succeeded remotely
 * but the response was lost, e.g. a timeout), this adopts that resource's
 * id locally instead of attempting a second, possibly conflicting, create —
 * and audits the mismatch, since "local says failed, remote says it
 * exists" is exactly the kind of disagreement CLAUDE.md's audit
 * requirements exist to catch.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Provisioning;

use ArvanReseller\Arvan\CdnClient;
use ArvanReseller\Arvan\CdnProviderException;
use ArvanReseller\Lifecycle\ServiceStatus;
use ArvanReseller\Ports\AuditLogger;
use ArvanReseller\Ports\Clock;
use ArvanReseller\Ports\OrderRepository;
use ArvanReseller\Ports\ServiceRepository;
use RuntimeException;

final class ResourceSyncService {

	public function __construct(
		private readonly ServiceRepository $services,
		private readonly OrderRepository $orders,
		private readonly AuditLogger $auditLog,
		private readonly Clock $clock
	) {}

	/**
	 * @return array{ok: bool, service_id: int, status: string, remote_resource_id: ?string, reconciled: bool, message: ?string}
	 */
	public function retry( int $service_id, CdnClient $client, ?int $actor_wp_user_id = null ): array {
		$service = $this->services->find( $service_id );

		if ( null === $service ) {
			throw new RuntimeException( "Service {$service_id} does not exist." );
		}

		if ( ServiceStatus::PROVISIONING_FAILED !== $service['status'] ) {
			throw new RuntimeException( "Service {$service_id} is not in provisioning_failed status; nothing to retry." );
		}

		$domain   = $service['domain'];
		$order_id = (int) $service['order_id'];

		try {
			$existing = $client->getResource( $domain );
		} catch ( CdnProviderException $e ) {
			// getResource() only returns null for a confirmed "not found" —
			// any other provider failure (rate limit, transient 5xx, ...)
			// still throws, and it means we genuinely don't know whether the
			// resource exists remotely. Retrying createResource() blind in
			// that state risks a duplicate/conflicting resource, so this
			// stops here and leaves local state untouched for a later retry.
			$this->auditLog->record(
				'service.reconcile_check_failed',
				$actor_wp_user_id,
				(int) $service['customer_id'],
				'service',
				$service_id,
				'failed',
				[ 'category' => $e->category ]
			);

			return [
				'ok'                 => false,
				'service_id'         => $service_id,
				'status'             => $service['status'],
				'remote_resource_id' => null,
				'reconciled'         => false,
				'message'            => $e->getMessage(),
			];
		}

		if ( null !== $existing ) {
			$this->auditLog->record(
				'service.reconcile_mismatch',
				$actor_wp_user_id,
				(int) $service['customer_id'],
				'service',
				$service_id,
				'ok',
				[ 'local_status' => $service['status'], 'remote_resource_id' => $existing->resourceId, 'remote_status' => $existing->status ]
			);

			return $this->applyProvisioned( $service_id, $order_id, $existing->resourceId, true );
		}

		try {
			$resource = $client->createResource( $domain );
		} catch ( CdnProviderException $e ) {
			$this->orders->markFailed( $order_id, $e->getMessage() );

			$this->auditLog->record(
				'service.provisioning_retry_failed',
				$actor_wp_user_id,
				(int) $service['customer_id'],
				'service',
				$service_id,
				'failed',
				[ 'category' => $e->category ]
			);

			return [
				'ok'                 => false,
				'service_id'         => $service_id,
				'status'             => ServiceStatus::PROVISIONING_FAILED,
				'remote_resource_id' => null,
				'reconciled'         => false,
				'message'            => $e->getMessage(),
			];
		}

		$this->auditLog->record(
			'service.provisioning_retried',
			$actor_wp_user_id,
			(int) $service['customer_id'],
			'service',
			$service_id,
			'ok',
			[ 'remote_resource_id' => $resource->resourceId ]
		);

		return $this->applyProvisioned( $service_id, $order_id, $resource->resourceId, false );
	}

	/**
	 * @return array{ok: bool, service_id: int, status: string, remote_resource_id: ?string, reconciled: bool, message: ?string}
	 */
	private function applyProvisioned( int $service_id, int $order_id, string $remote_resource_id, bool $reconciled ): array {
		$this->services->recordProvisioned( $service_id, $remote_resource_id, $this->clock->now() );
		$this->services->updateStatus( $service_id, ServiceStatus::ACTIVE );
		$this->orders->markCompleted( $order_id );

		return [
			'ok'                 => true,
			'service_id'         => $service_id,
			'status'             => ServiceStatus::ACTIVE,
			'remote_resource_id' => $remote_resource_id,
			'reconciled'         => $reconciled,
			'message'            => null,
		];
	}
}
