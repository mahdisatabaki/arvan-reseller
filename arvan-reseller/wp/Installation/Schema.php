<?php
/**
 * Database schema — CDN reseller scope (PRD §9).
 *
 * The plugin owns its tables outright (requirement E2). Nothing here reuses
 * wp_posts, wp_postmeta or wp_usermeta: financial records need their own shape,
 * their own indexes, and their own immutability guarantees.
 *
 * Money is stored as a signed BIGINT in Rial (the minor unit). Never a float.
 * Revenue model is markup-only (ADR-002) and VAT is out of scope for P0
 * (ADR-003), so no table here carries a tax column.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Installation;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public const PREFIX = 'arvan_';

	/**
	 * Every table this plugin owns, without the WordPress prefix.
	 * Order matters for teardown: children before parents.
	 */
	public const TABLES = [
		'audit_log',
		'notifications',
		'settlements',
		'api_keys',
		'usage_log',
		'services',
		'orders',
		'payments',
		'ledger',
		'wallets',
		'customers',
	];

	public static function table( string $name ): string {
		global $wpdb;

		return $wpdb->prefix . self::PREFIX . $name;
	}

	/**
	 * @return string[] One CREATE TABLE statement per table, formatted for dbDelta().
	 */
	public static function statements(): array {
		global $wpdb;

		$collate = $wpdb->get_charset_collate();

		$customers     = self::table( 'customers' );
		$wallets       = self::table( 'wallets' );
		$ledger        = self::table( 'ledger' );
		$payments      = self::table( 'payments' );
		$orders        = self::table( 'orders' );
		$services      = self::table( 'services' );
		$usage_log     = self::table( 'usage_log' );
		$api_keys      = self::table( 'api_keys' );
		$settlements   = self::table( 'settlements' );
		$notifications = self::table( 'notifications' );
		$audit_log     = self::table( 'audit_log' );

		$sql = [];

		/**
		 * E1 — a reseller customer, always backed by a WordPress user account.
		 * WordPress hosts the identity; every financial fact lives here instead.
		 */
		$sql[] = "CREATE TABLE {$customers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_user_id bigint(20) unsigned NOT NULL,
			display_name varchar(191) NOT NULL DEFAULT '',
			email varchar(191) NOT NULL DEFAULT '',
			phone varchar(32) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY wp_user_id (wp_user_id),
			KEY status (status),
			KEY email (email)
		) {$collate};";

		/**
		 * E3 — the virtual wallet. balance_rial is a cache of the ledger sum,
		 * updated inside the same transaction as every entry so the two can
		 * never disagree. It is a SIGNED integer: PRD §5.4 / ADR-007 require the
		 * real negative balance to be preserved for auditability, never clamped
		 * to zero. notify_threshold_rial is the reseller-configured Low Balance
		 * Threshold (B4); resume_threshold_rial is the balance a suspended
		 * wallet must clear before its service is resumed (§5.5, default 0).
		 */
		$sql[] = "CREATE TABLE {$wallets} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL,
			balance_rial bigint(20) NOT NULL DEFAULT 0,
			lifetime_topup_rial bigint(20) unsigned NOT NULL DEFAULT 0,
			lifetime_usage_rial bigint(20) unsigned NOT NULL DEFAULT 0,
			notify_threshold_rial bigint(20) DEFAULT NULL,
			resume_threshold_rial bigint(20) NOT NULL DEFAULT 0,
			notified_at datetime DEFAULT NULL,
			depleted_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY customer_id (customer_id),
			KEY balance_rial (balance_rial)
		) {$collate};";

		/**
		 * E1, E6, E7 — append-only ledger. Rows are never updated or deleted;
		 * a correction is a new entry. Every row carries the full base/markup
		 * breakdown so an auditor can reproduce the number without re-running
		 * the pricing engine, and balance_after_rial makes each row verifiable
		 * on its own. balance_after_rial is signed: it can legitimately go
		 * negative (ADR-007).
		 */
		$sql[] = "CREATE TABLE {$ledger} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL,
			wallet_id bigint(20) unsigned NOT NULL,
			direction varchar(10) NOT NULL,
			category varchar(30) NOT NULL,
			base_rial bigint(20) NOT NULL DEFAULT 0,
			markup_rial bigint(20) NOT NULL DEFAULT 0,
			amount_rial bigint(20) unsigned NOT NULL,
			balance_after_rial bigint(20) NOT NULL,
			markup_bps smallint(5) unsigned NOT NULL DEFAULT 0,
			reference_type varchar(30) DEFAULT NULL,
			reference_id bigint(20) unsigned DEFAULT NULL,
			idempotency_key varchar(191) NOT NULL,
			description text NULL,
			meta longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY customer_created (customer_id,created_at),
			KEY reference (reference_type,reference_id),
			KEY category_created (category,created_at)
		) {$collate};";

		/**
		 * B7, E9 — payment control. Every top-up attempt is recorded, including
		 * the ones that never succeed, because "pending / succeeded / failed per
		 * customer" is an explicit requirement.
		 */
		$sql[] = "CREATE TABLE {$payments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL,
			amount_rial bigint(20) unsigned NOT NULL,
			gateway varchar(40) NOT NULL DEFAULT 'mock',
			status varchar(20) NOT NULL DEFAULT 'pending',
			reference varchar(191) DEFAULT NULL,
			receipt_path varchar(255) DEFAULT NULL,
			ledger_id bigint(20) unsigned DEFAULT NULL,
			idempotency_key varchar(191) NOT NULL,
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			note text NULL,
			meta longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY idempotency_key (idempotency_key),
			KEY customer_status (customer_id,status),
			KEY status_created (status,created_at)
		) {$collate};";

		/**
		 * D1, PRD §14 — the Order state machine
		 * (draft → pending → provisioning → completed, or → failed) is tracked
		 * separately from the Service it produces. The order is created BEFORE
		 * any ArvanCloud API call (T-4.2), so a failed or interrupted API call
		 * never leaves an orphaned service: it leaves a `failed` order instead.
		 * markup_bps_snapshot freezes the rate that applied at order time, so a
		 * later change to the reseller's markup setting cannot rewrite history.
		 */
		$sql[] = "CREATE TABLE {$orders} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL,
			service_id bigint(20) unsigned DEFAULT NULL,
			product varchar(30) NOT NULL DEFAULT 'cdn',
			domain varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'draft',
			markup_bps_snapshot smallint(5) unsigned NOT NULL DEFAULT 0,
			requested_config longtext NULL,
			failed_reason text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_status (customer_id,status),
			KEY service_id (service_id)
		) {$collate};";

		/**
		 * D2, D3, D4 — the mapping that makes the whole billing model work:
		 * arvan_resource_id ties a CDN resource in the reseller's ArvanCloud
		 * account to exactly one customer. api_key_id records which key created
		 * it, so every later hold/unhold/delete call reuses that same
		 * credential (PRD §9 "Constraintهای حیاتی"). suspend_reason distinguishes
		 * a wallet-triggered suspension (auto-resumable on recharge, F6) from
		 * any other hold.
		 */
		$sql[] = "CREATE TABLE {$services} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			domain varchar(191) NOT NULL DEFAULT '',
			arvan_resource_id varchar(191) DEFAULT NULL,
			api_key_id bigint(20) unsigned DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'provisioning',
			suspend_reason varchar(30) DEFAULT NULL,
			provisioned_at datetime DEFAULT NULL,
			suspended_at datetime DEFAULT NULL,
			terminated_at datetime DEFAULT NULL,
			metered_through datetime DEFAULT NULL,
			provision_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
			last_error text NULL,
			meta longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY customer_status (customer_id,status),
			KEY status_metered (status,metered_through),
			KEY arvan_resource_id (arvan_resource_id),
			KEY order_id (order_id)
		) {$collate};";

		/**
		 * E4, E5 — raw CDN outbound-traffic reading as reported by ArvanCloud,
		 * one row per service per period, already converted to a base cost by
		 * the UsagePricingAdapter (PRD §10) plus the markup applied on top. The
		 * unique key on (service_id, period_start) is what makes the metering
		 * cron safe to re-run: a repeat never double-charges (T-5.2).
		 */
		$sql[] = "CREATE TABLE {$usage_log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			service_id bigint(20) unsigned NOT NULL,
			customer_id bigint(20) unsigned NOT NULL,
			period_start datetime NOT NULL,
			period_end datetime NOT NULL,
			traffic_value bigint(20) unsigned NOT NULL DEFAULT 0,
			traffic_unit varchar(20) NOT NULL DEFAULT 'byte',
			unit_price_rial bigint(20) unsigned NOT NULL DEFAULT 0,
			base_rial bigint(20) unsigned NOT NULL DEFAULT 0,
			markup_rial bigint(20) unsigned NOT NULL DEFAULT 0,
			total_rial bigint(20) unsigned NOT NULL DEFAULT 0,
			ledger_id bigint(20) unsigned DEFAULT NULL,
			settlement_id bigint(20) unsigned DEFAULT NULL,
			source varchar(20) NOT NULL DEFAULT 'computed',
			raw longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY service_period (service_id,period_start),
			KEY customer_period (customer_id,period_start),
			KEY settlement_id (settlement_id)
		) {$collate};";

		/**
		 * A4, A5 — several machine-user keys, each scoped to a purpose.
		 * Ciphertext only; the plaintext key never lands in the database.
		 */
		$sql[] = "CREATE TABLE {$api_keys} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			label varchar(191) NOT NULL,
			purpose varchar(30) NOT NULL DEFAULT 'all',
			ciphertext longtext NOT NULL,
			fingerprint varchar(64) NOT NULL,
			last_four varchar(8) NOT NULL DEFAULT '',
			is_default tinyint(1) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			services_count int(10) unsigned NOT NULL DEFAULT 0,
			last_checked_at datetime DEFAULT NULL,
			last_check_ok tinyint(1) DEFAULT NULL,
			last_check_message text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY fingerprint (fingerprint),
			KEY purpose_status (purpose,status)
		) {$collate};";

		/**
		 * E10 — periodic roll-up of what the reseller owes ArvanCloud versus the
		 * markup it keeps. No tax column: VAT is out of scope for P0 (ADR-003).
		 */
		$sql[] = "CREATE TABLE {$settlements} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			period_start datetime NOT NULL,
			period_end datetime NOT NULL,
			gross_rial bigint(20) unsigned NOT NULL DEFAULT 0,
			base_rial bigint(20) unsigned NOT NULL DEFAULT 0,
			markup_rial bigint(20) unsigned NOT NULL DEFAULT 0,
			sample_count int(10) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'draft',
			gateway varchar(40) NOT NULL DEFAULT 'mock',
			transmitted_at datetime DEFAULT NULL,
			meta longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY period (period_start,period_end),
			KEY status (status)
		) {$collate};";

		/**
		 * F1, F2 — every notification sent, so a threshold warning fires once
		 * and not once per cron tick.
		 */
		$sql[] = "CREATE TABLE {$notifications} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			customer_id bigint(20) unsigned NOT NULL,
			channel varchar(20) NOT NULL DEFAULT 'email',
			type varchar(40) NOT NULL,
			subject varchar(191) NOT NULL DEFAULT '',
			dedupe_key varchar(191) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'sent',
			error text NULL,
			meta longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY dedupe_key (dedupe_key),
			KEY customer_type (customer_id,type),
			KEY created_at (created_at)
		) {$collate};";

		/**
		 * G4-G7 — immutable audit trail for every sensitive operation, human or
		 * cron: manual wallet adjustments, lifecycle failures, key changes.
		 */
		$sql[] = "CREATE TABLE {$audit_log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			actor_type varchar(20) NOT NULL DEFAULT 'system',
			actor_id bigint(20) unsigned DEFAULT NULL,
			action varchar(60) NOT NULL,
			subject_type varchar(30) DEFAULT NULL,
			subject_id bigint(20) unsigned DEFAULT NULL,
			level varchar(20) NOT NULL DEFAULT 'info',
			message text NULL,
			meta longtext NULL,
			ip varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY subject (subject_type,subject_id),
			KEY action_created (action,created_at),
			KEY level_created (level,created_at)
		) {$collate};";

		return $sql;
	}
}
