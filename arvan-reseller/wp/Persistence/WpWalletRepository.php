<?php
/**
 * `WalletRepository` implementation on `$wpdb` (`arvan_wallets`).
 *
 * Read-only besides `ensureExists()` — see the port's own docblock for why:
 * the one write path that changes a balance is `LedgerRepository::append()`,
 * so a balance is never touched here.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\WalletRepository;
use ArvanReseller\Wp\Installation\Schema;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpWalletRepository implements WalletRepository {

	public function __construct( private readonly wpdb $db ) {}

	public function ensureExists( int $customer_id ): void {
		if ( null !== $this->find( $customer_id ) ) {
			return;
		}

		$now = gmdate( 'Y-m-d H:i:s' );

		$this->db->insert(
			$this->table(),
			[
				'customer_id' => $customer_id,
				'created_at'  => $now,
				'updated_at'  => $now,
			],
			[ '%d', '%s', '%s' ]
		);
	}

	public function currentBalance( int $customer_id ): Money {
		$wallet = $this->find( $customer_id );

		return Money::fromRial( null === $wallet ? 0 : (int) $wallet['balance_rial'] );
	}

	public function lowBalanceThreshold( int $customer_id ): Money {
		$wallet = $this->find( $customer_id );

		if ( null === $wallet || null === $wallet['notify_threshold_rial'] ) {
			return Money::zero();
		}

		return Money::fromRial( (int) $wallet['notify_threshold_rial'] );
	}

	public function resumeThreshold( int $customer_id ): Money {
		$wallet = $this->find( $customer_id );

		return Money::fromRial( null === $wallet ? 0 : (int) $wallet['resume_threshold_rial'] );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function find( int $customer_id ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare( 'SELECT * FROM %i WHERE customer_id = %d', $this->table(), $customer_id ),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	private function table(): string {
		return Schema::table( 'wallets' );
	}
}
