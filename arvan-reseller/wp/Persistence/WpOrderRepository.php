<?php
/**
 * `OrderRepository` implementation on `$wpdb` (`arvan_orders`).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Ports\OrderRepository;
use ArvanReseller\Wp\Installation\Schema;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpOrderRepository implements OrderRepository {

	public function __construct( private readonly wpdb $db ) {}

	public function create(
		int $customer_id,
		string $product,
		string $domain,
		int $markup_bps_snapshot,
		array $requested_config = []
	): int {
		$now = gmdate( 'Y-m-d H:i:s' );

		$this->db->insert(
			$this->table(),
			[
				'customer_id'         => $customer_id,
				'product'             => $product,
				'domain'              => $domain,
				'status'              => 'pending',
				'markup_bps_snapshot' => $markup_bps_snapshot,
				'requested_config'    => [] === $requested_config ? null : wp_json_encode( $requested_config ),
				'created_at'          => $now,
				'updated_at'          => $now,
			],
			[ '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
		);

		return (int) $this->db->insert_id;
	}

	public function markProvisioning( int $order_id, int $service_id ): void {
		$this->db->update(
			$this->table(),
			[
				'service_id' => $service_id,
				'status'     => 'provisioning',
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $order_id ],
			[ '%d', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function markCompleted( int $order_id ): void {
		$this->db->update(
			$this->table(),
			[
				'status'     => 'completed',
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $order_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function markFailed( int $order_id, string $reason ): void {
		$this->db->update(
			$this->table(),
			[
				'status'        => 'failed',
				'failed_reason' => $reason,
				'updated_at'    => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $order_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function findOwnedByCustomer( int $order_id, int $customer_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT * FROM %i WHERE id = %d AND customer_id = %d',
				$this->table(),
				$order_id,
				$customer_id
			),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	private function table(): string {
		return Schema::table( 'orders' );
	}
}
