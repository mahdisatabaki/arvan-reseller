<?php
/**
 * `LedgerRepository` implementation on `$wpdb` (`arvan_ledger` + `arvan_wallets`).
 *
 * The ledger insert and the wallet balance update happen inside one SQL
 * transaction with `SELECT ... FOR UPDATE` locking the wallet row, per
 * TECH.md §8 / BILLING.md §10. Idempotency (BILLING.md §11) is enforced by
 * the unique key on `idempotency_key`: this class checks for an existing row
 * with that key BEFORE writing, and if a concurrent request wins the race
 * anyway, the unique-key violation on insert is caught and treated the same
 * as finding the row up front — either way, no second wallet mutation
 * happens and the original resulting balance is returned.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\LedgerRepository;
use ArvanReseller\Wp\Installation\Schema;
use RuntimeException;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpLedgerRepository implements LedgerRepository {

	public function __construct( private readonly wpdb $db ) {}

	public function append(
		int $customer_id,
		string $type,
		Money $amount,
		string $idempotency_key,
		?string $reference_type = null,
		?int $reference_id = null,
		?string $description = null,
		array $metadata = []
	): Money {
		$existing = $this->findByIdempotencyKey( $idempotency_key );

		if ( null !== $existing ) {
			return Money::fromRial( (int) $existing['balance_after_rial'] );
		}

		$this->db->query( 'START TRANSACTION' );

		try {
			$wallet = $this->lockWallet( $customer_id );

			if ( null === $wallet ) {
				throw new RuntimeException(
					"No wallet found for customer {$customer_id}; WalletRepository::ensureExists() must run before the first ledger entry."
				);
			}

			$direction   = $amount->isNegative() ? 'debit' : 'credit';
			$amount_rial = $amount->absolute()->toRial();
			$balance     = Money::fromRial( (int) $wallet['balance_rial'] )->plus( $amount );
			$now         = gmdate( 'Y-m-d H:i:s' );

			$inserted = $this->db->query(
				$this->db->prepare(
					'INSERT INTO %i
						(customer_id, wallet_id, direction, category, base_rial, markup_rial,
						 amount_rial, balance_after_rial, markup_bps, reference_type, reference_id,
						 idempotency_key, description, meta, created_at)
					VALUES (%d, %d, %s, %s, %d, %d, %d, %d, %d, %s, %d, %s, %s, %s, %s)',
					$this->ledgerTable(),
					$customer_id,
					(int) $wallet['id'],
					$direction,
					$type,
					(int) ( $metadata['base_rial'] ?? 0 ),
					(int) ( $metadata['markup_rial'] ?? 0 ),
					$amount_rial,
					$balance->toRial(),
					(int) ( $metadata['markup_bps'] ?? 0 ),
					$reference_type,
					$reference_id,
					$idempotency_key,
					$description,
					[] === $metadata ? null : wp_json_encode( $metadata ),
					$now
				)
			);

			if ( false === $inserted ) {
				// Unique-key race: another request inserted this idempotency_key
				// between our lookup and our INSERT. Its transaction already
				// committed the wallet mutation, so we abandon ours and read
				// back what it wrote instead of writing a second time.
				$this->db->query( 'ROLLBACK' );

				$row = $this->findByIdempotencyKey( $idempotency_key );

				if ( null === $row ) {
					throw new RuntimeException( 'Ledger insert failed and no existing row was found for idempotency_key: ' . $idempotency_key );
				}

				return Money::fromRial( (int) $row['balance_after_rial'] );
			}

			$updated = $direction === 'credit'
				? [
					'balance_rial'        => $balance->toRial(),
					'lifetime_topup_rial' => (int) $wallet['lifetime_topup_rial'] + $amount_rial,
					'updated_at'          => $now,
				]
				: [
					'balance_rial'        => $balance->toRial(),
					'lifetime_usage_rial' => (int) $wallet['lifetime_usage_rial'] + $amount_rial,
					'updated_at'          => $now,
				];

			$this->db->update(
				$this->walletTable(),
				$updated,
				[ 'id' => (int) $wallet['id'] ],
				array_fill( 0, count( $updated ), '%s' ),
				[ '%d' ]
			);

			$this->db->query( 'COMMIT' );

			return $balance;
		} catch ( \Throwable $e ) {
			$this->db->query( 'ROLLBACK' );
			throw $e;
		}
	}

	public function historyForCustomer( int $customer_id, int $limit = 50, int $offset = 0 ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM %i WHERE customer_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
				$this->ledgerTable(),
				$customer_id,
				$limit,
				$offset
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	public function allRecent( int $limit = 50, int $offset = 0 ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d',
				$this->ledgerTable(),
				$limit,
				$offset
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findByIdempotencyKey( string $idempotency_key ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE idempotency_key = %s', $this->ledgerTable(), $idempotency_key ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	/**
	 * Locks the wallet row for the remainder of the current transaction so a
	 * concurrent append() for the same customer cannot read a stale balance.
	 *
	 * @return array<string, mixed>|null
	 */
	private function lockWallet( int $customer_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT * FROM %i WHERE customer_id = %d FOR UPDATE',
				$this->walletTable(),
				$customer_id
			),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	private function ledgerTable(): string {
		return Schema::table( 'ledger' );
	}

	private function walletTable(): string {
		return Schema::table( 'wallets' );
	}
}
