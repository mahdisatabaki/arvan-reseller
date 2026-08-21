<?php
/**
 * `AuditLogger` implementation on `$wpdb` (`arvan_audit_log`).
 *
 * The table (DATA-MODEL.md §13 / Schema.php) has a generic
 * `subject_type`/`subject_id` pair, not a dedicated `customer_id` column, so
 * `$customer_id` is written into `meta` in addition to whatever subject the
 * caller supplied — it must never be lost just because `$entity_type` also
 * happened to be given (see class-level mapping notes below).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Persistence;

use ArvanReseller\Ports\AuditLogger;
use ArvanReseller\Wp\Installation\Schema;
use wpdb;

defined( 'ABSPATH' ) || exit;

final class WpAuditLogger implements AuditLogger {

	public function __construct( private readonly wpdb $db ) {}

	public function record(
		string $action,
		?int $actor_wp_user_id = null,
		?int $customer_id = null,
		?string $entity_type = null,
		?int $entity_id = null,
		string $status = 'ok',
		array $metadata = []
	): void {
		if ( null !== $entity_type ) {
			$subject_type = $entity_type;
			$subject_id   = $entity_id;
		} elseif ( null !== $customer_id ) {
			$subject_type = 'customer';
			$subject_id   = $customer_id;
		} else {
			$subject_type = null;
			$subject_id   = null;
		}

		if ( null !== $customer_id ) {
			$metadata['customer_id'] = $customer_id;
		}

		$this->db->insert(
			$this->table(),
			[
				'actor_type'   => null !== $actor_wp_user_id ? 'user' : 'system',
				'actor_id'     => $actor_wp_user_id,
				'action'       => $action,
				'subject_type' => $subject_type,
				'subject_id'   => $subject_id,
				'level'        => 'ok' === $status ? 'info' : 'error',
				'message'      => $status,
				'meta'         => [] === $metadata ? null : wp_json_encode( $metadata ),
				'ip'           => null,
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	private function table(): string {
		return Schema::table( 'audit_log' );
	}
}
