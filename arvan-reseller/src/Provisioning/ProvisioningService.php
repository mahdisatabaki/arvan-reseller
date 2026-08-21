<?php
/**
 * Turns a customer's CDN order into a provisioned remote resource.
 *
 * The local `orders`/`services` rows are always written before
 * `CdnClient::createResource()` is ever called — `ServiceRepository`'s own
 * docblock and CLAUDE.md's Critical Engineering Invariants both require it:
 * "A failed provisioning call must not create an unowned remote resource
 * without a recoverable local record." A thrown `CdnProviderException`
 * therefore never leaves nothing behind — it leaves a `provisioning_failed`
 * service and a `failed` order, ready for a retry (T-4.4, not built yet).
 *
 * `$client` and `$api_key_id` are supplied by the caller rather than looked
 * up here: constructing a real `ArvanCdnClient` needs `SecretStore` to
 * decrypt a key and `WordPressHttpClient` to send it, both WordPress-bound
 * concerns this framework-agnostic class must not depend on (CLAUDE.md's
 * WordPress Boundary). The caller — a WP-layer controller, not built yet
 * since the CDN sales page is T-7.3 — resolves the default API key,
 * decrypts it, and builds the client before calling `provision()`.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Provisioning;

use ArvanReseller\Arvan\CdnClient;
use ArvanReseller\Arvan\CdnProviderException;
use ArvanReseller\Lifecycle\ServiceStatus;
use ArvanReseller\Ports\Clock;
use ArvanReseller\Ports\OrderRepository;
use ArvanReseller\Ports\ServiceRepository;

final class ProvisioningService {

	public function __construct(
		private readonly OrderRepository $orders,
		private readonly ServiceRepository $services,
		private readonly Clock $clock
	) {}

	/**
	 * @return array{
	 *     ok: bool,
	 *     order_id: int,
	 *     service_id: int,
	 *     status: string,
	 *     remote_resource_id: ?string,
	 *     message: ?string
	 * }
	 */
	public function provision(
		int $customer_id,
		string $domain,
		int $api_key_id,
		int $markup_bps_snapshot,
		CdnClient $client,
		string $product = 'cdn'
	): array {
		$order_id   = $this->orders->create( $customer_id, $product, $domain, $markup_bps_snapshot );
		$service_id = $this->services->createProvisioning( $customer_id, $order_id, $api_key_id, $domain );

		$this->orders->markProvisioning( $order_id, $service_id );

		try {
			$resource = $client->createResource( $domain );
		} catch ( CdnProviderException $e ) {
			$this->services->updateStatus( $service_id, ServiceStatus::PROVISIONING_FAILED );
			$this->orders->markFailed( $order_id, $e->getMessage() );

			return [
				'ok'                 => false,
				'order_id'           => $order_id,
				'service_id'         => $service_id,
				'status'             => ServiceStatus::PROVISIONING_FAILED,
				'remote_resource_id' => null,
				'message'            => $e->getMessage(),
			];
		}

		$this->services->recordProvisioned( $service_id, $resource->resourceId, $this->clock->now() );
		$this->services->updateStatus( $service_id, ServiceStatus::ACTIVE );
		$this->orders->markCompleted( $order_id );

		return [
			'ok'                 => true,
			'order_id'           => $order_id,
			'service_id'         => $service_id,
			'status'             => ServiceStatus::ACTIVE,
			'remote_resource_id' => $resource->resourceId,
			'message'            => null,
		];
	}
}
