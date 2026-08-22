<?php
/**
 * `SettlementRepository` implementation on `$wpdb` (`arvan_settlements`).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\SettlementRepository;
use ArvanReseller\Wp\Installation\Schema;
use DateTimeImmutable;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpSettlementRepository implements SettlementRepository {

	public function __construct( private readonly wpdb $db ) {}

	public function allRecent( int $limit = 50 ): array {
		$rows = $this->db->get_results(
			$this->db->prepare( 'SELECT * FROM %i ORDER BY period_start DESC LIMIT %d', $this->table(), $limit ),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	public function create(
		DateTimeImmutable $period_start,
		DateTimeImmutable $period_end,
		Money $base,
		Money $markup,
		Money $gross,
		int $sample_count,
		string $status = 'transmitted',
		string $gateway = 'mock'
	): array {
		$existing = $this->findByPeriod( $period_start, $period_end );

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
				'period_start'   => $period_start->format( 'Y-m-d H:i:s' ),
				'period_end'     => $period_end->format( 'Y-m-d H:i:s' ),
				'gross_rial'     => $gross->toRial(),
				'base_rial'      => $base->toRial(),
				'markup_rial'    => $markup->toRial(),
				'sample_count'   => $sample_count,
				'status'         => $status,
				'gateway'        => $gateway,
				'transmitted_at' => 'transmitted' === $status ? $now : null,
				'created_at'     => $now,
				'updated_at'     => $now,
			],
			[ '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return [
			'id'      => (int) $this->db->insert_id,
			'created' => true,
		];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findByPeriod( DateTimeImmutable $period_start, DateTimeImmutable $period_end ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT * FROM %i WHERE period_start = %s AND period_end = %s',
				$this->table(),
				$period_start->format( 'Y-m-d H:i:s' ),
				$period_end->format( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	private function table(): string {
		return Schema::table( 'settlements' );
	}
}
