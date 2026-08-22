<?php
/**
 * `PaymentRepository` implementation on `$wpdb` (`arvan_payments`).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\PaymentRepository;
use ArvanReseller\Wp\Installation\Schema;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpPaymentRepository implements PaymentRepository {

	public function __construct( private readonly wpdb $db ) {}

	public function createPending(
		int $customer_id,
		Money $amount,
		string $method,
		string $idempotency_key
	): array {
		$existing = $this->findByIdempotencyKey( $idempotency_key );

		if ( null !== $existing ) {
			return [
				'id'      => (int) $existing['id'],
				'created' => false,
			];
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$this->db->insert(
			$this->table(),
			[
				'customer_id'     => $customer_id,
				'amount_rial'     => $amount->toRial(),
				'gateway'         => $method,
				'status'          => 'pending',
				'idempotency_key' => $idempotency_key,
				'created_at'      => $now,
				'updated_at'      => $now,
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return [
			'id'      => (int) $this->db->insert_id,
			'created' => true,
		];
	}

	public function markSucceeded( int $payment_id ): bool {
		$payment = $this->find( $payment_id );

		if ( null === $payment || 'succeeded' === $payment['status'] ) {
			return false;
		}

		$this->db->update(
			$this->table(),
			[
				'status'     => 'succeeded',
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $payment_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		return true;
	}

	public function markFailed( int $payment_id, ?string $reason = null ): void {
		$payment = $this->find( $payment_id );

		if ( null === $payment || 'succeeded' === $payment['status'] ) {
			return;
		}

		$this->db->update(
			$this->table(),
			[
				'status'     => 'failed',
				'note'       => $reason,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $payment_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function findOwnedByCustomer( int $payment_id, int $customer_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT * FROM %i WHERE id = %d AND customer_id = %d',
				$this->table(),
				$payment_id,
				$customer_id
			),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	public function historyForCustomer( int $customer_id, int $limit = 20 ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM %i WHERE customer_id = %d ORDER BY created_at DESC LIMIT %d',
				$this->table(),
				$customer_id,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function find( int $payment_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $payment_id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findByIdempotencyKey( string $idempotency_key ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE idempotency_key = %s', $this->table(), $idempotency_key ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	private function table(): string {
		return Schema::table( 'payments' );
	}
}
