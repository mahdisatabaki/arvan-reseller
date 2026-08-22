<?php
/**
 * `SettlementRepository` implementation on `$wpdb` (`arvan_settlements`).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Ports\SettlementRepository;
use ArvanReseller\Wp\Installation\Schema;
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

	private function table(): string {
		return Schema::table( 'settlements' );
	}
}
