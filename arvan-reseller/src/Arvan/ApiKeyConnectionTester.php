<?php
/**
 * Tests whether a CDN credential actually authenticates with ArvanCloud.
 *
 * T-1.2 deliberately has no `ping()` on `CdnClient` — the API spike found no
 * health-check endpoint. This reuses `getResource()` instead, against a
 * fixed probe domain that is expected not to exist. That is enough: whether
 * the provider says "found" or "not found," reaching either answer means the
 * request authenticated. Only a thrown `CdnProviderException` means the
 * request never got that far.
 *
 * The probe domain is a hardcoded constant, never user input — SECURITY.md
 * §10's "no arbitrary URL from customer input" applies here even though this
 * is a domain name, not a full URL: nothing about this call is
 * caller-controlled.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Arvan;

final class ApiKeyConnectionTester {

	private const PROBE_DOMAIN = 'arvan-reseller-connection-probe.invalid';

	/**
	 * @return array{ok: bool, message: string} `message` is always a
	 *         hand-written, static string — never `$e->getMessage()` or
	 *         anything else derived from the provider's response — so a
	 *         result from this method can never leak a secret or raw
	 *         provider body, regardless of what `CdnClient` throws.
	 */
	public function test( CdnClient $client ): array {
		try {
			// A CdnResource (found) or null (not found) are both a real
			// answer from the provider — the credential authenticated.
			$client->getResource( self::PROBE_DOMAIN );

			return [
				'ok'      => true,
				'message' => 'The API key authenticated successfully.',
			];
		} catch ( CdnProviderException $e ) {
			if ( CdnProviderException::AUTHENTICATION_FAILED === $e->category
				|| CdnProviderException::FORBIDDEN === $e->category
			) {
				return [
					'ok'      => false,
					'message' => 'ArvanCloud rejected this API key.',
				];
			}

			// Any other category (rate limit, temporary provider failure,
			// unparseable response, ...) reached the provider, so the
			// credential itself is not confirmed bad — but the test could
			// not confirm success either.
			return [
				'ok'      => false,
				'message' => 'Could not confirm the connection right now — try again shortly.',
			];
		}
	}
}
