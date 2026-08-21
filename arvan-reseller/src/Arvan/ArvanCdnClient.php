<?php
/**
 * `CdnClient` implementation for the real ArvanCloud CDN API.
 *
 * Depends only on `HttpClient` (src/Ports/HttpClient.php) — never on
 * `wp_remote_request()`, never on `curl_*` directly. That keeps this class
 * loadable and unit-testable with zero WordPress bootstrap, matching every
 * other file under `src/`.
 *
 * Credential handling: the constructor takes an already-decrypted API key.
 * This class never touches `SecretStore` — resolving *which* key belongs to
 * a service and decrypting it is the caller's job (the future
 * `ProvisioningService`/`LifecycleService`), done once per operation, right
 * before constructing this client. That is what API.md §3 means by "each
 * `CdnClient` instance is constructed already bound to one resolved
 * credential."
 *
 * Confidence levels, from the T-1.1 spike (see docs/PROGRESS.md "تصمیم‌های
 * باز" and docs/API.md §14):
 *   - HIGH:   base URL, `Authorization: Apikey` header, the four endpoint
 *             path shapes, `since`/`until` as the traffic report's query
 *             parameters, the traffic report being period-bucketed.
 *   - MEDIUM: the exact JSON field names below (`id`, `domain`, `status`,
 *             `created_at`, and the traffic bucket's value field). These are
 *             the best-supported reading of the spike's evidence, not a
 *             confirmed live response. Each is isolated in its own mapping
 *             method precisely so a live-key correction touches one method,
 *             not this whole class.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Arvan;

use ArvanReseller\Ports\HttpClient;
use DateTimeImmutable;

final class ArvanCdnClient implements CdnClient {

	/** SECURITY.md §10: a fixed, allowlisted provider host — never customer-supplied. */
	private const BASE_URL = 'https://napi.arvancloud.ir/cdn/4.0';

	private const DEFAULT_TIMEOUT_SECONDS = 10.0;

	/**
	 * API.md §9: only GET-based, side-effect-free calls may be retried
	 * automatically. 1 initial attempt + 2 retries. createResource() and
	 * deleteResource() never pass allowRetry, so they always get exactly one
	 * attempt — retrying a non-idempotent POST/DELETE after an uncertain
	 * result is exactly the duplicate-resource risk API.md §9 warns about;
	 * that decision belongs to the caller (after reconciling via
	 * getResource()), not to this transport-retry loop.
	 */
	private const MAX_ATTEMPTS = 3;
	private const RETRY_BACKOFF_BASE_MICROSECONDS = 100_000;

	/**
	 * Traffic bucket field candidates, tried in order. Fails loudly
	 * (UNKNOWN_PROVIDER_ERROR) rather than silently defaulting to zero usage
	 * if none match — a wrong field name must never turn into a silent
	 * under-bill.
	 */
	private const TRAFFIC_VALUE_FIELDS = [ 'traffic', 'value', 'bytes' ];

	public function __construct(
		private readonly HttpClient $http,
		private readonly string $apiKey
	) {}

	public function createResource( string $domain ): CdnResource {
		$response = $this->send( 'POST', '/domains', [ 'domain' => $domain ] );

		return $this->mapResource( $this->unwrap( $response ), $domain );
	}

	public function getResource( string $domain ): ?CdnResource {
		try {
			$response = $this->send( 'GET', '/domains/' . rawurlencode( $domain ), null, allowRetry: true );
		} catch ( CdnProviderException $e ) {
			if ( CdnProviderException::RESOURCE_NOT_FOUND === $e->category ) {
				return null;
			}

			throw $e;
		}

		return $this->mapResource( $this->unwrap( $response ), $domain );
	}

	public function getOutboundTrafficUsage(
		string $domain,
		DateTimeImmutable $since,
		DateTimeImmutable $until
	): OutboundTrafficUsage {
		$query = http_build_query(
			[
				'since' => $since->format( DATE_ATOM ),
				'until' => $until->format( DATE_ATOM ),
			]
		);

		$response = $this->send(
			'GET',
			'/domains/' . rawurlencode( $domain ) . '/reports/traffics?' . $query,
			null,
			allowRetry: true
		);

		$buckets = $this->unwrapList( $response );

		return $this->mapTrafficUsage( $buckets, $since, $until );
	}

	public function deleteResource( string $domain ): void {
		$this->send( 'DELETE', '/domains/' . rawurlencode( $domain ) );
	}

	/**
	 * Send one request and return the decoded response body, or throw a
	 * normalized CdnProviderException.
	 *
	 * Bounded retry (API.md §9, BACKLOG T-1.3: "bounded retry only for
	 * safe/retryable requests"): only engages when `$allowRetry` is true
	 * (GET calls only, see call sites) AND the failure's category is itself
	 * marked retryable (RATE_LIMITED / TEMPORARY_PROVIDER_FAILURE) — a 401 or
	 * 404 fails on the first attempt regardless of `$allowRetry`, since
	 * retrying those can never succeed.
	 *
	 * @param array<string, mixed>|null $jsonBody
	 * @return array<string, mixed>
	 */
	private function send( string $method, string $path, ?array $jsonBody = null, bool $allowRetry = false ): array {
		$headers = [
			'Authorization' => 'Apikey ' . $this->apiKey,
			'Accept'        => 'application/json',
		];

		$body = null;

		if ( null !== $jsonBody ) {
			$headers['Content-Type'] = 'application/json';
			$body                    = (string) json_encode( $jsonBody, JSON_UNESCAPED_SLASHES );
		}

		$maxAttempts = $allowRetry ? self::MAX_ATTEMPTS : 1;

		for ( $attempt = 1; $attempt <= $maxAttempts; $attempt++ ) {
			try {
				$result = $this->http->request(
					$method,
					self::BASE_URL . $path,
					$headers,
					$body,
					self::DEFAULT_TIMEOUT_SECONDS
				);
			} catch ( \RuntimeException $e ) {
				// Transport-level failure (DNS, connection, TLS, timeout) —
				// always retryable; it never reached the provider.
				if ( $attempt < $maxAttempts ) {
					$this->backoff( $attempt );
					continue;
				}

				throw CdnProviderException::create(
					CdnProviderException::TEMPORARY_PROVIDER_FAILURE,
					'Could not reach the CDN provider.',
					$e
				);
			}

			if ( $result['status'] < 200 || $result['status'] >= 300 ) {
				$exception = CdnProviderException::create(
					$this->mapStatusToCategory( $result['status'] ),
					sprintf( 'CDN provider returned HTTP %d.', $result['status'] )
				);

				if ( $exception->retryable && $attempt < $maxAttempts ) {
					$this->backoff( $attempt );
					continue;
				}

				throw $exception;
			}

			if ( '' === trim( $result['body'] ) ) {
				return [];
			}

			$decoded = json_decode( $result['body'], true );

			if ( ! is_array( $decoded ) ) {
				throw CdnProviderException::create(
					CdnProviderException::UNKNOWN_PROVIDER_ERROR,
					'CDN provider returned a response that could not be parsed.'
				);
			}

			return $decoded;
		}

		// Unreachable: the loop above always either returns or throws on its
		// final iteration. Present only so static analysis sees every path
		// return or throw.
		throw CdnProviderException::create( // @phpstan-ignore-line
			CdnProviderException::UNKNOWN_PROVIDER_ERROR,
			'Request failed with no further detail.'
		);
	}

	private function backoff( int $attempt ): void {
		usleep( self::RETRY_BACKOFF_BASE_MICROSECONDS * $attempt );
	}

	private function mapStatusToCategory( int $status ): string {
		return match ( true ) {
			401 === $status => CdnProviderException::AUTHENTICATION_FAILED,
			403 === $status => CdnProviderException::FORBIDDEN,
			404 === $status => CdnProviderException::RESOURCE_NOT_FOUND,
			409 === $status => CdnProviderException::PROVIDER_CONFLICT,
			422 === $status, 400 === $status => CdnProviderException::INVALID_REQUEST,
			429 === $status => CdnProviderException::RATE_LIMITED,
			$status >= 500 => CdnProviderException::TEMPORARY_PROVIDER_FAILURE,
			default => CdnProviderException::UNKNOWN_PROVIDER_ERROR,
		};
	}

	/**
	 * ArvanCloud's REST responses are commonly wrapped as `{"data": {...}}`.
	 * Falls back to the root object if no `data` envelope is present, so a
	 * schema surprise degrades gracefully instead of losing the payload.
	 *
	 * @param array<string, mixed> $response
	 * @return array<string, mixed>
	 */
	private function unwrap( array $response ): array {
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return $response['data'];
		}

		return $response;
	}

	/**
	 * Same envelope convention, for endpoints whose `data` is a list.
	 *
	 * @param array<string, mixed> $response
	 * @return array<int, array<string, mixed>>
	 */
	private function unwrapList( array $response ): array {
		$data = $response['data'] ?? $response;

		return is_array( $data ) ? array_values( $data ) : [];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function mapResource( array $data, string $requestedDomain ): CdnResource {
		$createdAt = null;

		if ( isset( $data['created_at'] ) && is_string( $data['created_at'] ) && '' !== $data['created_at'] ) {
			try {
				$createdAt = new DateTimeImmutable( $data['created_at'] );
			} catch ( \Exception ) {
				$createdAt = null;
			}
		}

		return new CdnResource(
			resourceId: isset( $data['id'] ) ? (string) $data['id'] : $requestedDomain,
			domain: isset( $data['domain'] ) ? (string) $data['domain'] : $requestedDomain,
			status: isset( $data['status'] ) ? (string) $data['status'] : 'unknown',
			createdAt: $createdAt
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $buckets
	 */
	private function mapTrafficUsage(
		array $buckets,
		DateTimeImmutable $since,
		DateTimeImmutable $until
	): OutboundTrafficUsage {
		if ( [] === $buckets ) {
			return new OutboundTrafficUsage( $since, $until, 0, 'byte' );
		}

		// The report is period-bucketed (see class docblock); the most recent
		// bucket is the one closing at $until.
		$last = $buckets[ count( $buckets ) - 1 ];

		$value = null;

		foreach ( self::TRAFFIC_VALUE_FIELDS as $field ) {
			if ( array_key_exists( $field, $last ) && is_numeric( $last[ $field ] ) ) {
				$value = (int) $last[ $field ];
				break;
			}
		}

		if ( null === $value ) {
			throw CdnProviderException::create(
				CdnProviderException::UNKNOWN_PROVIDER_ERROR,
				'CDN provider traffic report did not contain a recognized traffic field.'
			);
		}

		return new OutboundTrafficUsage( $since, $until, $value, 'byte' );
	}
}
