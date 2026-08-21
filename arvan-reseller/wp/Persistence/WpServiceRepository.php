<?php
/**
 * `ServiceRepository` implementation on `$wpdb` (`arvan_services`).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

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
		$data   = [
			'status'     => $status,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		];
		$format = [ '%s', '%s' ];

		if ( null !== $suspension_reason ) {
			$data['suspend_reason'] = $suspension_reason;
			$format[]               = '%s';
		}

		$this->db->update( $this->table(), $data, [ 'id' => $service_id ], $format, [ '%d' ] );
	}

	private function table(): string {
		return Schema::table( 'services' );
	}
}
