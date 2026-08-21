<?php
/**
 * Demo Access Token verification.
 *
 * SECURITY.md §3: verify a submitted token against a bundled hash allowlist
 * with `password_verify()`; never store the raw token; never log an
 * attempted token; rate-limit repeated failures; successful verification
 * unlocks reseller setup/sales configuration.
 *
 * Unrelated to `SecretStore`/`WordPressSecretStore` on purpose (BACKLOG
 * T-2.2 rule: "do not mix this with API Key encryption"). That pair does
 * reversible encryption for a secret that must be recovered in plaintext to
 * make API calls; this class does one-way verification for a value that is
 * never recovered, only ever compared. Different problem, different class,
 * nothing shared.
 *
 * This lives in `wp/`, not `src/`, because its state — the rate-limit
 * counter and the activation flag — is inherently WordPress-backed
 * (transient / option). There is nothing here worth abstracting behind a
 * new port: unlike `CdnClient`, this has exactly one real implementation.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Security;

defined( 'ABSPATH' ) || exit;

final class AccessTokenGate {

	/** Boolean flag: has any token ever verified successfully. */
	private const OPTION_ACTIVATED = 'arvan_reseller_access_token_verified';

	/** Failed-attempt counter, auto-expiring — the rate-limit window. */
	private const TRANSIENT_ATTEMPTS = 'arvan_reseller_token_attempts';

	private const MAX_ATTEMPTS = 5;

	private const LOCKOUT_SECONDS = 900; // 15 minutes.

	/** @var string[]|null Lazily loaded, cached for the life of the request. */
	private static ?array $hashesCache = null;

	/**
	 * Verify a submitted token against the bundled hash allowlist.
	 *
	 * The submitted token is never written to an option, transient, log, or
	 * exception message anywhere in this method — on failure, only a
	 * count increments; the value itself is discarded when this call returns.
	 */
	public function verify( string $token ): bool {
		if ( $this->isRateLimited() ) {
			return false;
		}

		foreach ( self::hashes() as $hash ) {
			if ( password_verify( $token, $hash ) ) {
				$this->resetAttempts();
				update_option( self::OPTION_ACTIVATED, '1' );

				return true;
			}
		}

		$this->recordFailedAttempt();

		return false;
	}

	/**
	 * SECURITY.md §3: "successful verification unlocks reseller
	 * setup/sales configuration." Everything that should be gated behind a
	 * verified token (Setup Wizard, Markup settings, the CDN sales page)
	 * checks this instead of asking for the token again.
	 */
	public function isActivated(): bool {
		return '1' === get_option( self::OPTION_ACTIVATED, '' );
	}

	public function isRateLimited(): bool {
		return ( (int) get_transient( self::TRANSIENT_ATTEMPTS ) ) >= self::MAX_ATTEMPTS;
	}

	private function recordFailedAttempt(): void {
		$attempts = ( (int) get_transient( self::TRANSIENT_ATTEMPTS ) ) + 1;

		set_transient( self::TRANSIENT_ATTEMPTS, $attempts, self::LOCKOUT_SECONDS );
	}

	private function resetAttempts(): void {
		delete_transient( self::TRANSIENT_ATTEMPTS );
	}

	/**
	 * @return string[]
	 */
	private static function hashes(): array {
		if ( null === self::$hashesCache ) {
			/** @var string[] $hashes */
			$hashes             = require __DIR__ . '/../../data/access-token-hashes.php';
			self::$hashesCache = $hashes;
		}

		return self::$hashesCache;
	}
}
