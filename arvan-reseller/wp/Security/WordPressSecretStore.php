<?php
/**
 * `SecretStore` implementation backed by OpenSSL AES-256-GCM.
 *
 * SECURITY.md §4 in full:
 *   - encrypt, don't hash — the ArvanCloud API key must be recoverable,
 *   - authenticated encryption (AES-256-GCM),
 *   - encryption key from secure WordPress config/salts or an explicit
 *     environment/config secret,
 *   - unique nonce/IV per encryption.
 *
 * This is a `wp/` file, not a `src/` one, specifically because resolving the
 * key means reading `wp-config.php` constants — exactly the kind of
 * WordPress-configuration concern T-0.75 draws the line on keeping out of
 * the framework-agnostic layer.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Security;

use ArvanReseller\Ports\SecretStore;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

final class WordPressSecretStore implements SecretStore {

	private const CIPHER = 'aes-256-gcm';

	/** GCM's standard nonce length. Reusing a nonce with the same key breaks
	 *  the authentication guarantee, which is exactly why encrypt() generates
	 *  a fresh one every call rather than accepting one as a parameter. */
	private const NONCE_LENGTH = 12;

	/** Full-strength GCM authentication tag. */
	private const TAG_LENGTH = 16;

	/** Raw 32-byte (256-bit) key material, derived once in the constructor. */
	private readonly string $key;

	public function __construct() {
		$this->key = self::resolveKey();
	}

	public function encrypt( string $plaintext ): string {
		$nonce = random_bytes( self::NONCE_LENGTH );
		$tag   = '';

		$ciphertext = openssl_encrypt(
			$plaintext,
			self::CIPHER,
			$this->key,
			OPENSSL_RAW_DATA,
			$nonce,
			$tag,
			'',
			self::TAG_LENGTH
		);

		if ( false === $ciphertext ) {
			throw new RuntimeException( 'Failed to encrypt secret.' );
		}

		// Everything decrypt() needs travels inside the one returned string,
		// so callers never have to store a nonce/tag alongside the ciphertext
		// themselves — a single `ciphertext` column is enough (Schema.php
		// api_keys.ciphertext).
		return base64_encode( $nonce . $tag . $ciphertext );
	}

	public function decrypt( string $ciphertext ): string {
		$raw = base64_decode( $ciphertext, true );

		$minLength = self::NONCE_LENGTH + self::TAG_LENGTH;

		if ( false === $raw || strlen( $raw ) <= $minLength ) {
			throw new RuntimeException( 'Ciphertext is malformed.' );
		}

		$nonce = substr( $raw, 0, self::NONCE_LENGTH );
		$tag   = substr( $raw, self::NONCE_LENGTH, self::TAG_LENGTH );
		$data  = substr( $raw, $minLength );

		$plaintext = openssl_decrypt(
			$data,
			self::CIPHER,
			$this->key,
			OPENSSL_RAW_DATA,
			$nonce,
			$tag
		);

		if ( false === $plaintext ) {
			// Wrong key or a tampered/corrupted tag — OpenSSL does not
			// distinguish the two, and neither should the caller: both mean
			// "do not trust this ciphertext."
			throw new RuntimeException( 'Failed to decrypt secret: tampered or wrong key.' );
		}

		return $plaintext;
	}

	/**
	 * SECURITY.md §4: "encryption key comes from secure WordPress
	 * config/salts or explicit environment/config secret."
	 *
	 * Preference order:
	 *   1. `ARVAN_ENCRYPTION_KEY` — an explicit constant an operator can set
	 *      in wp-config.php, independent of and rotatable separately from
	 *      WordPress's own salts.
	 *   2. `AUTH_KEY` + `SECURE_AUTH_KEY` — the standard WordPress salts
	 *      every install already has, hashed down to a 256-bit key. This is
	 *      the zero-configuration default so the plugin works without asking
	 *      the reseller to invent and manage a separate secret.
	 *
	 * Either way the raw config value (which can be any length) is passed
	 * through SHA-256 to produce exactly the 32 raw bytes AES-256 requires.
	 */
	private static function resolveKey(): string {
		if ( defined( 'ARVAN_ENCRYPTION_KEY' ) && '' !== (string) constant( 'ARVAN_ENCRYPTION_KEY' ) ) {
			return hash( 'sha256', (string) constant( 'ARVAN_ENCRYPTION_KEY' ), true );
		}

		if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' )
			&& '' !== AUTH_KEY && '' !== SECURE_AUTH_KEY
		) {
			return hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
		}

		// No usable key material exists (e.g. a wp-config.php that was never
		// given real salts). Refusing to fall back to a hardcoded key is the
		// whole point — a predictable key is the same as no encryption.
		throw new RuntimeException(
			'No secret encryption key available: define ARVAN_ENCRYPTION_KEY, ' .
			'or ensure WordPress AUTH_KEY / SECURE_AUTH_KEY are set in wp-config.php.'
		);
	}
}
