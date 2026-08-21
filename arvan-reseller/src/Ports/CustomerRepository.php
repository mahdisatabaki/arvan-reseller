<?php
/**
 * Persistence for the reseller customer record (`arvan_customers`).
 *
 * DATA-MODEL.md's E1 note is the reason this port exists at all: "always
 * backed by a WordPress user account" — WordPress owns identity/auth, but
 * every financial and resource fact is keyed off this table's `id`, not
 * `wp_user_id`, directly. `findByWpUserId()` is therefore the one lookup
 * every customer-facing request must perform first: SECURITY.md §6 requires
 * resolving "current WP user → customer id" server-side before any
 * ownership-scoped query runs, and this method is that resolution step.
 *
 * `create()` follows the same "return existing on duplicate" idempotency
 * pattern as ApiKeyRepository::create(), backed by the table's own UNIQUE
 * constraint on `wp_user_id` (Schema.php) as the final safety net — a
 * customer row must never be duplicated for one WordPress user.
 *
 * This port is deliberately dumb persistence only. Creating a customer
 * together with its wallet and any onboarding side effects is orchestration
 * that belongs to a service (BACKLOG T-3.3), not to this repository.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

interface CustomerRepository {

	/**
	 * Store a new customer row, or return the existing id if `$wp_user_id`
	 * already has one.
	 *
	 * @return int The customer id — new or pre-existing.
	 */
	public function create(
		int $wp_user_id,
		string $display_name,
		string $email,
		?string $phone = null
	): int;

	/**
	 * The IDOR-safe entry point: resolves the currently authenticated
	 * WordPress user to their customer row, or null if they have none yet.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findByWpUserId( int $wp_user_id ): ?array;

	/**
	 * Plain lookup by customer id, for internal/admin use (e.g. an admin
	 * customer detail screen) where the caller already holds a trusted id
	 * rather than resolving one from the current request.
	 *
	 * @return array<string, mixed>|null
	 */
	public function find( int $customer_id ): ?array;
}
