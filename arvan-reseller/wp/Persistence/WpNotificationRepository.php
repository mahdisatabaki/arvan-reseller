<?php
/**
 * `NotificationRepository` implementation on `$wpdb` (`arvan_notifications`).
 *
 * `channel` is hardcoded to `'email'` and `service_id` is never written —
 * this repository only backs the low-balance notice (BACKLOG T-6.2), which
 * is wallet-level, not service-level, and email is the only channel BACKLOG
 * currently asks for.
 *
 * `record()` upserts on `dedupe_key`: a fresh key inserts a row; an existing
 * key refreshes `status`/`error` and reports `created = false`. See
 * `NotificationRepository::record()`'s docblock for why a second write to
 * the same row is a legitimate, expected call (correcting status after a
 * mail-send attempt) rather than a duplicate-event bug.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Ports\NotificationRepository;
use ArvanReseller\Wp\Installation\Schema;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpNotificationRepository implements NotificationRepository {

	public function __construct( private readonly wpdb $db ) {}

	public function record(
		int $customer_id,
		string $type,
		string $subject,
		string $dedupe_key,
		string $status,
		?string $error = null
	): array {
		$existing = $this->findByDedupeKey( $dedupe_key );

		if ( null !== $existing ) {
			$this->db->update(
				$this->table(),
				[
					'status' => $status,
					'error'  => $error,
				],
				[ 'id' => (int) $existing['id'] ],
				[ '%s', '%s' ],
				[ '%d' ]
			);

			return [
				'id'      => (int) $existing['id'],
				'created' => false,
			];
		}

		$this->db->insert(
			$this->table(),
			[
				'customer_id' => $customer_id,
				'channel'     => 'email',
				'type'        => $type,
				'subject'     => $subject,
				'dedupe_key'  => $dedupe_key,
				'status'      => $status,
				'error'       => $error,
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return [
			'id'      => (int) $this->db->insert_id,
			'created' => true,
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findByDedupeKey( string $dedupe_key ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE dedupe_key = %s', $this->table(), $dedupe_key ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	private function table(): string {
		return Schema::table( 'notifications' );
	}
}
