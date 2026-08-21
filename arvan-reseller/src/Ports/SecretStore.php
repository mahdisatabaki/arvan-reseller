<?php
/**
 * Reversible encryption for ArvanCloud API credentials at rest.
 *
 * SECURITY.md §4: the Machine User key must be *encrypted*, not hashed —
 * unlike the Access Token, it has to be recovered in plaintext to make an API
 * call. Authenticated encryption (AES-256-GCM or equivalent) with a unique
 * nonce/IV per call is required, and the encryption key itself must come from
 * secure WordPress config/salts or an explicit secret, never the database.
 *
 * This port only ever sees ciphertext and plaintext strings; it has no
 * knowledge of API keys, tokens, or any other secret's meaning — that keeps
 * it reusable and keeps "what is a secret" a decision made by the caller.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

interface SecretStore {

	/**
	 * Encrypt plaintext for storage. Implementations must generate a fresh
	 * nonce/IV per call and embed whatever the matching decrypt() needs
	 * (nonce, auth tag) inside the returned string.
	 */
	public function encrypt( string $plaintext ): string;

	/**
	 * Recover the original plaintext.
	 *
	 * @throws \RuntimeException If the ciphertext is malformed or fails its
	 *                           authentication check (tampered or wrong key).
	 *                           Never returns a partially-decrypted value.
	 */
	public function decrypt( string $ciphertext ): string;
}
