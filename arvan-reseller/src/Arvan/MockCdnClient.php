<?php
/**
 * Deterministic, in-memory `CdnClient` for local development and tests.
 *
 * ADR-013 / API.md §12: this implements exactly the same port as
 * `ArvanCdnClient` and throws the same `CdnProviderException` on failure, so
 * application code cannot tell which driver it is talking to — swapping
 * drivers is a configuration choice, never a branch in business logic.
 *
 * No constructor dependencies at all: no `HttpClient`, no API key, no
 * WordPress, no database, no filesystem. That absence is what proves this
 * class cannot reach a network, a table, or a disk — there is nothing here
 * to inject that would let it.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Arvan;

use DateTimeImmutable;

final class MockCdnClient implements CdnClient {

	/**
	 * @var array<string, array{resourceId: string, status: string, createdAt: DateTimeImmutable}>
	 */
	private array $resources = [];

	/** @var array<string, int> domain => outbound traffic in bytes */
	private array $trafficFixture = [];

	/** @var array<string, string> domain => CdnProviderException category to force */
	private array $forcedFailures = [];

	public function createResource( string $domain ): CdnResource {
		$this->throwIfForced( $domain );

		if ( isset( $this->resources[ $domain ] ) ) {
			throw CdnProviderException::create(
				CdnProviderException::PROVIDER_CONFLICT,
				"A CDN resource for '{$domain}' already exists."
			);
		}

		$this->resources[ $domain ] = [
			'resourceId' => $this->deterministicResourceId( $domain ),
			'status'     => 'active',
			'createdAt'  => new DateTimeImmutable(),
		];

		return $this->toCdnResource( $domain );
	}

	public function getResource( string $domain ): ?CdnResource {
		$this->throwIfForced( $domain );

		if ( ! isset( $this->resources[ $domain ] ) ) {
			return null;
		}

		return $this->toCdnResource( $domain );
	}

	public function getOutboundTrafficUsage(
		string $domain,
		DateTimeImmutable $since,
		DateTimeImmutable $until
	): OutboundTrafficUsage {
		$this->throwIfForced( $domain );
		$this->assertExists( $domain );

		return new OutboundTrafficUsage(
			$since,
			$until,
			$this->trafficFixture[ $domain ] ?? 0,
			'byte'
		);
	}

	public function deleteResource( string $domain ): void {
		$this->throwIfForced( $domain );
		$this->assertExists( $domain );

		unset( $this->resources[ $domain ] );
	}

	/**
	 * Test helper: configure what `getOutboundTrafficUsage()` returns for a
	 * domain until reconfigured. Unconfigured domains report zero usage.
	 */
	public function setOutboundTraffic( string $domain, int $bytes ): void {
		$this->trafficFixture[ $domain ] = $bytes;
	}

	/**
	 * Test helper: make every method throw `CdnProviderException` for this
	 * domain with the given category, checked before any other logic —
	 * including existence checks — so a forced failure always wins.
	 */
	public function forceFailure( string $domain, string $category ): void {
		$this->forcedFailures[ $domain ] = $category;
	}

	/**
	 * Test helper: remove a forced failure for a domain.
	 */
	public function clearFailure( string $domain ): void {
		unset( $this->forcedFailures[ $domain ] );
	}

	/**
	 * Test helper: register a resource as already existing, without going
	 * through createResource() first — for tests that want to start from
	 * "this was provisioned earlier" (lookup, traffic, delete scenarios).
	 */
	public function seedResource( string $domain, string $status = 'active' ): void {
		$this->resources[ $domain ] = [
			'resourceId' => $this->deterministicResourceId( $domain ),
			'status'     => $status,
			'createdAt'  => new DateTimeImmutable(),
		];
	}

	private function throwIfForced( string $domain ): void {
		if ( isset( $this->forcedFailures[ $domain ] ) ) {
			throw CdnProviderException::create(
				$this->forcedFailures[ $domain ],
				"Forced failure for '{$domain}' (test fixture)."
			);
		}
	}

	private function assertExists( string $domain ): void {
		if ( ! isset( $this->resources[ $domain ] ) ) {
			throw CdnProviderException::create(
				CdnProviderException::RESOURCE_NOT_FOUND,
				"No CDN resource exists for '{$domain}'."
			);
		}
	}

	/**
	 * Same domain always produces the same resource id (BACKLOG T-1.4:
	 * "deterministic resource IDs") — a hash of the domain, not a counter or
	 * random value, so two separate test runs never disagree.
	 */
	private function deterministicResourceId( string $domain ): string {
		return 'mock-' . substr( md5( $domain ), 0, 12 );
	}

	private function toCdnResource( string $domain ): CdnResource {
		$state = $this->resources[ $domain ];

		return new CdnResource(
			resourceId: $state['resourceId'],
			domain: $domain,
			status: $state['status'],
			createdAt: $state['createdAt']
		);
	}
}
