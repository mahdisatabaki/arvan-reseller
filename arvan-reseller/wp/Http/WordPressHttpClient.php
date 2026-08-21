<?php
/**
 * `HttpClient` implementation backed by WordPress's HTTP API.
 *
 * API.md §8 explicitly allows `wp_remote_request()` for outbound provider
 * calls; this is the one file where that happens. Everything in `src/Arvan/`
 * — including `ArvanCdnClient` — only ever sees the `HttpClient` port
 * (src/Ports/HttpClient.php), never this class or WordPress directly.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Http;

use ArvanReseller\Ports\HttpClient;
use RuntimeException;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class WordPressHttpClient implements HttpClient {

	public function request(
		string $method,
		string $url,
		array $headers = [],
		?string $body = null,
		float $timeoutSeconds = 10.0
	): array {
		$args = [
			'method'      => strtoupper( $method ),
			'headers'     => $headers,
			'timeout'     => $timeoutSeconds,
			// SECURITY.md §10 / API.md §8: TLS verification is mandatory, not
			// left to the WordPress default.
			'sslverify'   => true,
			'redirection' => 5,
		];

		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $url, $args );

		if ( $response instanceof WP_Error ) {
			// Transport-level failure only (DNS, connection, TLS, timeout).
			// get_error_message() is WordPress's own summary and does not
			// echo back the request headers/body, so this stays safe to
			// surface without a separate redaction pass.
			throw new RuntimeException( $response->get_error_message() );
		}

		return [
			'status'  => (int) wp_remote_retrieve_response_code( $response ),
			'body'    => (string) wp_remote_retrieve_body( $response ),
			'headers' => $this->normalizeHeaders( wp_remote_retrieve_headers( $response ) ),
		];
	}

	/**
	 * `wp_remote_retrieve_headers()` returns a case-insensitive dictionary
	 * object, not a plain array. Normalize it so `HttpClient`'s contract
	 * (`array<string, string>`) holds regardless of the WordPress version.
	 *
	 * @return array<string, string>
	 */
	private function normalizeHeaders( mixed $headers ): array {
		if ( is_array( $headers ) ) {
			return $headers;
		}

		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			/** @var array<string, string> */
			return $headers->getAll();
		}

		return [];
	}
}
