<?php
/**
 * `UsageLogRepository` implementation on `$wpdb` (`arvan_usage_log`).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Domain\Money;
use ArvanReseller\Pricing\ChargeBreakdown;
use ArvanReseller\Ports\UsageLogRepository;
use ArvanReseller\Wp\Installation\Schema;
use DateTimeImmutable;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpUsageLogRepository implements UsageLogRepository {

	public function __construct( private readonly wpdb $db ) {}

	public function record(
		int $service_id,
		int $customer_id,
		DateTimeImmutable $period_start,
		DateTimeImmutable $period_end,
		int $traffic_value,
		string $traffic_unit,
		Money $unit_price,
		ChargeBreakdown $charge
	): array {
		$existing = $this->findByServicePeriod( $service_id, $period_start );

		if ( null !== $existing ) {
			return [
				'id'      => (int) $existing['id'],
				'created' => false,
			];
		}

		$this->db->insert(
			$this->table(),
			[
				'service_id'      => $service_id,
				'customer_id'     => $customer_id,
				'period_start'    => $period_start->format( 'Y-m-d H:i:s' ),
				'period_end'      => $period_end->format( 'Y-m-d H:i:s' ),
				'traffic_value'   => $traffic_value,
				'traffic_unit'    => $traffic_unit,
				'unit_price_rial' => $unit_price->toRial(),
				'base_rial'       => $charge->base->toRial(),
				'markup_rial'     => $charge->markup->toRial(),
				'total_rial'      => $charge->total->toRial(),
				'source'          => 'computed',
				'created_at'      => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s' ]
		);

		return [
			'id'      => (int) $this->db->insert_id,
			'created' => true,
		];
	}

	public function historyForCustomer( int $customer_id, ?int $service_id = null, int $limit = 20 ): array {
		if ( null !== $service_id ) {
			$rows = $this->db->get_results(
				$this->db->prepare(
					'SELECT * FROM %i WHERE customer_id = %d AND service_id = %d ORDER BY period_start DESC LIMIT %d',
					$this->table(),
					$customer_id,
					$service_id,
					$limit
				),
				ARRAY_A
			);
		} else {
			$rows = $this->db->get_results(
				$this->db->prepare(
					'SELECT * FROM %i WHERE customer_id = %d ORDER BY period_start DESC LIMIT %d',
					$this->table(),
					$customer_id,
					$limit
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function findByServicePeriod( int $service_id, DateTimeImmutable $period_start ): ?array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT * FROM %i WHERE service_id = %d AND period_start = %s',
				$this->table(),
				$service_id,
				$period_start->format( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		return null === $row ? null : $row;
	}

	public function totalsSince( DateTimeImmutable $since ): array {
		$row = $this->db->get_row(
			$this->db->prepare(
				'SELECT
					COALESCE(SUM(traffic_value),0) AS traffic_value,
					COALESCE(SUM(base_rial),0) AS base_rial,
					COALESCE(SUM(markup_rial),0) AS markup_rial,
					COALESCE(SUM(total_rial),0) AS total_rial
				FROM %i WHERE period_start >= %s',
				$this->table(),
				$since->format( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		return [
			'traffic_value' => (int) ( $row['traffic_value'] ?? 0 ),
			'base_rial'     => (int) ( $row['base_rial'] ?? 0 ),
			'markup_rial'   => (int) ( $row['markup_rial'] ?? 0 ),
			'total_rial'    => (int) ( $row['total_rial'] ?? 0 ),
		];
	}

	public function unsettled( int $limit = 1000 ): array {
		$rows = $this->db->get_results(
			$this->db->prepare(
				'SELECT * FROM %i WHERE settlement_id IS NULL ORDER BY period_start ASC LIMIT %d',
				$this->table(),
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	public function markSettled( array $usage_log_ids, int $settlement_id ): void {
		if ( [] === $usage_log_ids ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $usage_log_ids ), '%d' ) );

		$this->db->query(
			$this->db->prepare(
				"UPDATE %i SET settlement_id = %d WHERE id IN ({$placeholders})",
				$this->table(),
				$settlement_id,
				...$usage_log_ids
			)
		);
	}

	private function table(): string {
		return Schema::table( 'usage_log' );
	}
}
