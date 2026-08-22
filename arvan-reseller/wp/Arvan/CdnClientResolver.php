<?php
/**
 * Turns a stored, encrypted `arvan_api_keys` row into a ready-to-use
 * `CdnClient`, the one place that decryption + client construction happens.
 *
 * `MeteringCronHandler` (T-5.4) had this exact logic inline (`resolveClient()`,
 * keyed by a service's stored `api_key_id`). The CDN order flow (T-7.3) needs
 * the same decrypt-then-construct step but starting from "the reseller's
 * default key for a purpose" instead, since a new order has no service row
 * yet to read an `api_key_id` from. Extracting both paths here means there is
 * exactly one place that touches `SecretStore::decrypt()` for this purpose,
 * not two copies that could drift.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Arvan;

use ArvanReseller\Arvan\ArvanCdnClient;
use ArvanReseller\Arvan\CdnClient;
use ArvanReseller\Ports\ApiKeyRepository;
use ArvanReseller\Ports\SecretStore;
use ArvanReseller\Wp\Http\WordPressHttpClient;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

final class CdnClientResolver {

	public function __construct(
		private readonly ApiKeyRepository $apiKeys,
		private readonly SecretStore $secretStore
	) {}

	/**
	 * @return array{client: CdnClient, api_key_id: int}|null Null when the key
	 *         does not exist, is not active, or fails to decrypt.
	 */
	public function resolveById( int $api_key_id ): ?array {
		if ( 0 === $api_key_id ) {
			return null;
		}

		$key = $this->apiKeys->find( $api_key_id );

		if ( null === $key || 'active' !== $key['status'] ) {
			return null;
		}

		try {
			$plaintext = $this->secretStore->decrypt( $key['ciphertext'] );
		} catch ( RuntimeException ) {
			return null;
		}

		return [
			'client'     => new ArvanCdnClient( new WordPressHttpClient(), $plaintext ),
			'api_key_id' => (int) $key['id'],
		];
	}

	/**
	 * The reseller's default active key for `$purpose` (SetupWizard writes
	 * `purpose = 'cdn'` for the key it collects — SetupWizard.php's own
	 * default). Null when no default key has been configured yet, which the
	 * caller (OrderController) must treat as "selling is not set up", never
	 * as a customer-facing validation error.
	 *
	 * @return array{client: CdnClient, api_key_id: int}|null
	 */
	public function resolveDefault( string $purpose = 'cdn' ): ?array {
		$key = $this->apiKeys->findDefault( $purpose );

		if ( null === $key ) {
			return null;
		}

		return $this->resolveById( (int) $key['id'] );
	}
}
