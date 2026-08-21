<?php
/**
 * `ApiKeyRepository` implementation on `$wpdb` (`arvan_api_keys`).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Ports\ApiKeyRepository;
use ArvanReseller\Wp\Installation\Schema;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpApiKeyRepository implements ApiKeyRepository {

	public function __construct( private readonly wpdb $db ) {}

	public function create(
		string $label,
		string $purpose,
		string $ciphertext,
		string $fingerprint,
		string $lastFour
	): array {
		$existing = $this->findByFingerprint( $fingerprint );

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
				'label'       => $label,
				'purpose'     => $purpose,
				'ciphertext'  => $ciphertext,
				'fingerprint' => $fingerprint,
				'last_four'   => $lastFour,
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return [
			'id'      => (int) $this->db->insert_id,
			'created' => true,
		];
	}

	public function find( int $id ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE id = %d', $this->table(), $id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	public function findDefault( string $purpose ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT * FROM %i WHERE purpose = %s AND is_default = 1 AND status = %s LIMIT 1',
				$this->table(),
				$purpose,
				'active'
			),
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

	public function update( int $id, string $label, string $purpose ): void {
		$this->db->update(
			$this->table(),
			[
				'label'      => $label,
				'purpose'    => $purpose,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function setDefault( int $id ): void {
		$target = $this->find( $id );

		if ( null === $target ) {
			return;
		}

		$purpose = (string) $target['purpose'];
		$now     = gmdate( 'Y-m-d H:i:s' );

		// Clear the flag on every other key sharing this purpose first, so
		// exactly one default can ever exist for it.
		$this->db->query(
			$this->db->prepare(
				'UPDATE %i SET is_default = 0, updated_at = %s WHERE purpose = %s AND id != %d',
				$this->table(),
				$now,
				$purpose,
				$id
			)
		);

		$this->db->update(
			$this->table(),
			[
				'is_default' => 1,
				'updated_at' => $now,
			],
			[ 'id' => $id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);
	}

	public function setStatus( int $id, string $status ): void {
		$this->db->update(
			$this->table(),
			[
				'status'     => $status,
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	public function recordCheckResult( int $id, bool $ok, ?string $message ): void {
		$this->db->update(
			$this->table(),
			[
				'last_checked_at'    => gmdate( 'Y-m-d H:i:s' ),
				'last_check_ok'      => $ok ? 1 : 0,
				'last_check_message' => $message,
				'updated_at'         => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);
	}

	private function findByFingerprint( string $fingerprint ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE fingerprint = %s', $this->table(), $fingerprint ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	private function table(): string {
		return Schema::table( 'api_keys' );
	}
}
