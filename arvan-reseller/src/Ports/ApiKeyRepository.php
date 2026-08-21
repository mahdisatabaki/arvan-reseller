<?php
/**
 * Persistence for ArvanCloud API credentials (`arvan_api_keys`).
 *
 * Same shape as the other repositories from T-0.8 (WalletRepository,
 * ServiceRepository, ...): a dumb persistence port with no knowledge of
 * encryption. Every write method here takes ciphertext, a fingerprint, and a
 * last-four fragment — never a plaintext key. Encrypting a key and deciding
 * which purpose/label it gets is the caller's job (SECURITY.md §4); this
 * port only ever stores what it is handed.
 *
 * `fingerprint` exists purely for duplicate detection — SHA-256 of the
 * plaintext key, not reversible to it, not a secret in its own right (an
 * attacker who already has the fingerprint gains nothing usable against
 * ArvanCloud). It is what makes `create()` idempotent: adding the same key
 * twice returns the existing row instead of creating a second one, backed by
 * the table's own UNIQUE constraint on `fingerprint` (Schema.php) as the
 * final safety net.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

interface ApiKeyRepository {

	/**
	 * Store a new key, or return the existing row if this exact key
	 * (identified by `$fingerprint`) was already added.
	 *
	 * @return array{id: int, created: bool} `created` is false when this
	 *         fingerprint already had a row — the caller must not treat that
	 *         as a fresh key (e.g. must not re-run onboarding for it).
	 */
	public function create(
		string $label,
		string $purpose,
		string $ciphertext,
		string $fingerprint,
		string $lastFour
	): array;

	/**
	 * @return array<string, mixed>|null Includes `ciphertext` — this read is
	 *         for internal use (e.g. decrypting to test a connection), not
	 *         for direct display. A UI renders `last_four`, never this.
	 */
	public function find( int $id ): ?array;

	/**
	 * The active default credential for a purpose (API.md §11: "one default
	 * credential for CDN can be selected"), or null if none is set.
	 *
	 * @return array<string, mixed>|null
	 */
	public function findDefault( string $purpose ): ?array;

	/**
	 * All keys, for the admin management screen.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array;

	public function update( int $id, string $label, string $purpose ): void;

	/**
	 * Make this key the default for its own purpose, clearing the default
	 * flag on every other key that shares that purpose. Exactly one default
	 * per purpose can exist at a time.
	 */
	public function setDefault( int $id ): void;

	/**
	 * @param string $status "active" or "disabled".
	 */
	public function setStatus( int $id, string $status ): void;

	/**
	 * Persist the outcome of a connection test (src/Arvan/ApiKeyConnectionTester.php).
	 * `$message` must already be safe to display — see that class for why.
	 */
	public function recordCheckResult( int $id, bool $ok, ?string $message ): void;
}
