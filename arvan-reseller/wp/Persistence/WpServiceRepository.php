<?php
/**
 * `ServiceRepository` implementation on `$wpdb` (`arvan_services`).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Lifecycle\ServiceStatus;
use ArvanReseller\Ports\ServiceRepository;
use ArvanReseller\Wp\Installation\Schema;
use DateTimeImmutable;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpServiceRepository implements ServiceRepository {

	public function __construct( private readonly wpdb $db ) {}

	public function createProvisioning(
		int $customer_id,
		int $order_id,
		int $api_key_id,
		string $domain
	): int {
		$now = gmdate( 'Y-m-d H:i:s' );

		$this->db->insert(
			$this->table(),
			[
				'customer_id' => $customer_id,
				'order_id'    => $order_id,
				'api_key_id'  => $api_key_id,
				'domain'      => $domain,
				'status'      => 'provisioning',
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
		);

		return (int) $this->db->insert_id;
	}

	public function findOwnedByCustomer( int $service_id, int $customer_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT * FROM %i WHERE id = %d AND customer_id = %d',
				$this->table(),
				$service_id,
				$customer_id
			),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	public function find( int $service_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $service_id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	public function dueForMetering( DateTimeImmutable $asOf ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM %i WHERE status = 'active' AND ( metered_through IS NULL OR metered_through <= %s )",
				$this->table(),
				$asOf->format( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	public function markMeteredThrough( int $service_id, DateTimeImmutable $through ): void {
		$this->db->update(
			$this->table(),
			[
				'metered_through' => $through->format( 'Y-m-d H:i:s' ),
				'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $service_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function recordProvisioned( int $service_id, string $remote_resource_id, DateTimeImmutable $at ): void {
		$this->db->update(
			$this->table(),
			[
				'arvan_resource_id' => $remote_resource_id,
				'provisioned_at'    => $at->format( 'Y-m-d H:i:s' ),
				'updated_at'        => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $service_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function updateStatus(
		int $service_id,
		string $status,
		?string $suspension_reason = null
	): void {
		$now = gmdate( 'Y-m-d H:i:s' );

		$data   = [
			'status'     => $status,
			'updated_at' => $now,
		];
		$format = [ '%s', '%s' ];

		if ( ServiceStatus::ACTIVE === $status ) {
			// A service becoming active again (Resume) must not keep the
			// stale reason from its last suspension — a future check that
			// reads suspend_reason without also checking status would
			// otherwise get a wrong answer for an already-active service.
			$data['suspend_reason'] = null;
			$format[]               = '%s';
		} elseif ( null !== $suspension_reason ) {
			$data['suspend_reason'] = $suspension_reason;
			$format[]               = '%s';
		}

		if ( 'suspended' === $status ) {
			$data['suspended_at'] = $now;
			$format[]             = '%s';
		} elseif ( 'terminated' === $status ) {
			$data['terminated_at'] = $now;
			$format[]              = '%s';
		}

		$this->db->update( $this->table(), $data, [ 'id' => $service_id ], $format, [ '%d' ] );
	}

	public function findSuspendedByCustomer( int $customer_id, string $reason ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM %i WHERE customer_id = %d AND status = 'suspended' AND suspend_reason = %s",
				$this->table(),
				$customer_id,
				$reason
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	public function dueForTermination( DateTimeImmutable $suspendedBefore ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM %i WHERE status = 'suspended' AND suspend_reason = 'wallet' AND suspended_at IS NOT NULL AND suspended_at <= %s",
				$this->table(),
				$suspendedBefore->format( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	private function table(): string {
		return Schema::table( 'services' );
	}
}
