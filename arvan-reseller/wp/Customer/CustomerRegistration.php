<?php
/**
 * Turns a new WordPress user into a reseller customer (BACKLOG T-3.3).
 *
 * The whole site is the storefront (CLAUDE.md Mission), so any WordPress
 * user registering here — through whatever form eventually calls
 * `wp_insert_user()`, T-7.4 or otherwise — is a customer unless they were
 * created with `manage_options` (an administrator). This hooks `user_register`
 * rather than a specific form's submit handler so it stays correct regardless
 * of which registration path fires it.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Customer;

use ArvanReseller\Ports\CustomerRepository;
use ArvanReseller\Ports\WalletRepository;
use ArvanReseller\Wp\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class CustomerRegistration {

	public function __construct(
		private readonly CustomerRepository $customers,
		private readonly WalletRepository $wallets
	) {}

	public function register(): void {
		add_action( 'user_register', [ $this, 'handleUserRegistered' ] );
	}

	/**
	 * Idempotent: `CustomerRepository::create()` returns the existing id on
	 * a repeat call for the same `$wp_user_id`, and `WalletRepository::ensureExists()`
	 * never touches an existing wallet, so this is safe to run more than once.
	 */
	public function handleUserRegistered( int $wp_user_id ): void {
		if ( user_can( $wp_user_id, 'manage_options' ) ) {
			return;
		}

		$user = get_userdata( $wp_user_id );

		if ( false === $user ) {
			return;
		}

		$user->set_role( Capabilities::CUSTOMER_ROLE );

		$customer_id = $this->customers->create(
			$wp_user_id,
			$user->display_name,
			$user->user_email
		);

		$this->wallets->ensureExists( $customer_id );
	}
}
