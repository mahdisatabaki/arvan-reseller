<?php
/**
 * A minimal, framework-agnostic HTTP transport.
 *
 * This exists specifically so `ArvanCdnClient` (src/Arvan/) can make real
 * network calls without depending on `wp_remote_request()` or raw `curl_*`
 * directly — keeping the provider adapter testable outside WordPress and
 * swappable for a fake transport in unit tests (TECH.md §14).
 *
 * Deliberately not "a WordPress HTTP wrapper": it knows nothing about
 * WordPress, ArvanCloud, or any other provider. It has exactly one
 * responsibility — send a request, return a response — so a `wp/Http/`
 * adapter can implement it with `wp_remote_request()` while a test double
 * implements it with nothing at all.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

interface HttpClient {

	/**
	 * Send one HTTP request and return its response.
	 *
	 * This method returns a plain array rather than a response object,
	 * matching the pattern already used by the read-side of the other Ports
	 * (e.g. `ServiceRepository::findOwnedByCustomer()`), so this port did not
	 * need a second new class just to describe "status, body, headers".
	 *
	 * @param array<string, string> $headers Header name => value.
	 *
	 * @return array{status: int, body: string, headers: array<string, string>}
	 *
	 * @throws \RuntimeException On a transport-level failure — DNS resolution,
	 *         connection refused, TLS handshake failure, or timeout. A normal
	 *         HTTP error response (4xx/5xx) is NOT a transport failure and
	 *         must be returned normally with its real status code; turning
	 *         that into meaning (retryable vs not, which provider error it
	 *         maps to) is the caller's job, not this port's.
	 */
	public function request(
		string $method,
		string $url,
		array $headers = [],
		?string $body = null,
		float $timeoutSeconds = 10.0
	): array;
}
