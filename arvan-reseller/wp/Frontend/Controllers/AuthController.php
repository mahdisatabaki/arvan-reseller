<?php
/**
 * Login/Register from the plugin-owned `/arvan/auth` screen (BACKLOG T-7.4,
 * SCREEN-SPECS.md §8).
 *
 * Two `admin-post.php` actions, nonce + CSRF-protected like every other
 * state-changing controller in this codebase (OrderController is the
 * template). Neither handler creates an `arvan_customers`/wallet row itself:
 * `CustomerRegistration` (wired in `Plugin.php::bootCustomer()`) is already
 * hooked to WordPress core's `user_register` action and fires the instant
 * `wp_insert_user()` succeeds below, so duplicating that here would risk a
 * double-wallet bug for no benefit (CLAUDE.md "smallest complete change").
 *
 * SECURITY.md §8's rate limiting is implemented the same shape
 * `AccessTokenGate` (T-2.2) established for a WordPress-backed, transient-only
 * counter: same 5-attempts/900-second numbers, kept private to this class
 * rather than factored out, for the same reason AccessTokenGate's own
 * docblock gives for staying `wp/`-only — there is exactly one real
 * implementation, nothing here is worth a new port. Login and register use
 * separate transient keys (still both hashed-IP-keyed) so a burst of invalid
 * registration attempts cannot lock a legitimate user out of signing in, and
 * vice versa.
 *
 * The login failure message is deliberately the same generic string whether
 * the account does not exist or the password was wrong (SCREEN-SPECS.md §8
 * "server-side validation" + this project's IDOR/enumeration posture) —
 * `wp_signon()`'s own `WP_Error` codes are never surfaced to the visitor.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Frontend\Controllers;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class AuthController {

	public const ACTION_LOGIN    = 'arvan_login';
	public const ACTION_REGISTER = 'arvan_register';

	/** Same numbers as AccessTokenGate (T-2.2), kept in sync deliberately. */
	private const MAX_ATTEMPTS    = 5;
	private const LOCKOUT_SECONDS = 900;

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_LOGIN, [ $this, 'handleLogin' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_LOGIN, [ $this, 'handleLogin' ] );
		add_action( 'admin_post_' . self::ACTION_REGISTER, [ $this, 'handleRegister' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION_REGISTER, [ $this, 'handleRegister' ] );
	}

	public function handleLogin(): void {
		check_admin_referer( self::ACTION_LOGIN );

		$key = $this->rateLimitKey( 'login' );

		if ( $this->isRateLimited( $key ) ) {
			$this->redirectWithError( 'rate_limited' );
		}

		$login    = isset( $_POST['login'] ) ? sanitize_text_field( wp_unslash( $_POST['login'] ) ) : '';
		$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';

		if ( '' === $login || '' === $password ) {
			$this->recordFailedAttempt( $key );
			$this->redirectWithError( 'login_failed' );
		}

		$result = wp_signon(
			[
				'user_login'    => $login,
				'user_password' => $password,
				'remember'      => true,
			],
			false
		);

		if ( $result instanceof WP_Error ) {
			$this->recordFailedAttempt( $key );
			$this->redirectWithError( 'login_failed' );
		}

		$this->resetAttempts( $key );

		wp_safe_redirect( home_url( '/arvan/account' ) );
		exit;
	}

	public function handleRegister(): void {
		check_admin_referer( self::ACTION_REGISTER );

		$key = $this->rateLimitKey( 'register' );

		if ( $this->isRateLimited( $key ) ) {
			$this->redirectWithError( 'rate_limited' );
		}

		$email       = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$password    = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$displayName = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';

		if ( '' === $email || ! is_email( $email ) ) {
			$this->recordFailedAttempt( $key );
			$this->redirectWithError( 'invalid_email' );
		}

		if ( email_exists( $email ) ) {
			$this->recordFailedAttempt( $key );
			$this->redirectWithError( 'email_taken' );
		}

		if ( strlen( $password ) < 8 ) {
			$this->recordFailedAttempt( $key );
			$this->redirectWithError( 'weak_password' );
		}

		if ( '' === $displayName ) {
			$displayName = (string) strstr( $email, '@', true );
		}

		$userLogin = self::uniqueUserLogin( $email );

		$userId = wp_insert_user(
			[
				'user_login'   => $userLogin,
				'user_email'   => $email,
				'user_pass'    => $password,
				'display_name' => $displayName,
			]
		);

		if ( $userId instanceof WP_Error ) {
			$this->recordFailedAttempt( $key );
			$this->redirectWithError( 'registration_failed' );
		}

		$this->resetAttempts( $key );

		wp_set_current_user( $userId );
		wp_set_auth_cookie( $userId );

		wp_safe_redirect( home_url( '/arvan/cdn' ) );
		exit;
	}

	/**
	 * `sanitize_user()` local-part, disambiguated with a numeric suffix on
	 * collision — mirrors the "return existing id on repeat" idempotency
	 * pattern used elsewhere in this codebase, just for username uniqueness
	 * instead of a duplicate row.
	 */
	private static function uniqueUserLogin( string $email ): string {
		$base = sanitize_user( (string) strstr( $email, '@', true ), true );

		if ( '' === $base ) {
			$base = 'customer';
		}

		if ( ! username_exists( $base ) ) {
			return $base;
		}

		$suffix = 2;

		while ( username_exists( $base . $suffix ) ) {
			++$suffix;
		}

		return $base . $suffix;
	}

	private function rateLimitKey( string $action ): string {
		return 'arvan_' . $action . '_attempts_' . wp_hash( $_SERVER['REMOTE_ADDR'] ?? '' );
	}

	private function isRateLimited( string $key ): bool {
		return ( (int) get_transient( $key ) ) >= self::MAX_ATTEMPTS;
	}

	private function recordFailedAttempt( string $key ): void {
		$attempts = ( (int) get_transient( $key ) ) + 1;

		set_transient( $key, $attempts, self::LOCKOUT_SECONDS );
	}

	private function resetAttempts( string $key ): void {
		delete_transient( $key );
	}

	private function redirectWithError( string $code ): never {
		wp_safe_redirect( add_query_arg( 'arvan_error', $code, home_url( '/arvan/auth' ) ) );
		exit;
	}
}
