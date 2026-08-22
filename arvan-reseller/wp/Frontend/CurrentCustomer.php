<?php
/**
 * Resolves the current WordPress visitor to their `arvan_customers` row, the
 * one step SECURITY.md §6 requires before any customer-facing screen or
 * action runs an ownership-scoped query: "resolving 'current WP user →
 * customer id' server-side before any ownership-scoped query runs."
 *
 * Every plugin-owned template and controller under `wp/Frontend/` resolves
 * the current customer through this one class rather than each calling
 * `is_user_logged_in()`/`CustomerRepository::findByWpUserId()` itself, so
 * there is exactly one place that logic can be gotten wrong.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Frontend;

use ArvanReseller\Ports\CustomerRepository;

defined( 'ABSPATH' ) || exit;

final class CurrentCustomer {

	public function __construct( private readonly CustomerRepository $customers ) {}

	/**
	 * The logged-in visitor's customer row, or null when logged out or when
	 * the logged-in user has no customer record (e.g. the reseller's own
	 * admin account — CustomerRegistration skips `manage_options` users).
	 *
	 * @return array<string, mixed>|null
	 */
	public function resolve(): ?array {
		if ( ! is_user_logged_in() ) {
			return null;
		}

		return $this->customers->findByWpUserId( get_current_user_id() );
	}
}
