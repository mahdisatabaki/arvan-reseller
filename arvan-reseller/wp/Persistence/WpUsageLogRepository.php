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

	private function table(): string {
		return Schema::table( 'usage_log' );
	}
}
