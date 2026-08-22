<?php
/**
 * `CustomerRepository` implementation on `$wpdb` (`arvan_customers`).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Ports\CustomerRepository;
use ArvanReseller\Wp\Installation\Schema;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpCustomerRepository implements CustomerRepository {

	public function __construct( private readonly wpdb $db ) {}

	public function create(
		int $wp_user_id,
		string $display_name,
		string $email,
		?string $phone = null
	): int {
		$existing = $this->findByWpUserId( $wp_user_id );

		if ( null !== $existing ) {
			return (int) $existing['id'];
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$this->db->insert(
			$this->table(),
			[
				'wp_user_id'   => $wp_user_id,
				'display_name' => $display_name,
				'email'        => $email,
				'phone'        => $phone,
				'status'       => 'active',
				'created_at'   => $now,
				'updated_at'   => $now,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return (int) $this->db->insert_id;
	}

	public function findByWpUserId( int $wp_user_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE wp_user_id = %d', $this->table(), $wp_user_id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	public function find( int $customer_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $customer_id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	public function all(): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM %i ORDER BY created_at DESC', $this->table() ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	private function table(): string {
		return Schema::table( 'customers' );
	}
}
