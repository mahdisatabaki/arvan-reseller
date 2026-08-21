<?php
/**
 * A CDN resource, normalized to what the application actually needs.
 *
 * API.md §4 is explicit that provider responses must not be passed raw into
 * the domain: "Do not pass raw provider JSON deep into Billing." This is that
 * normalization boundary for a single CDN resource — the shape every
 * `CdnClient` implementation (real or mock) returns, regardless of what
 * ArvanCloud's actual JSON looks like.
 *
 * `status` is a plain string rather than an enum on purpose: the T-1.1 API
 * spike confirmed the field exists on ArvanCloud's `Domain` resource but
 * could not confirm its possible values (no official docs were reachable;
 * community-sourced SDKs document the field's presence, not its enum).
 * Inventing status constants here would be exactly the kind of guessed
 * response field CLAUDE.md's Work Protocol §7 forbids. Whatever raw value the
 * provider returns is passed through untouched; mapping it to this plugin's
 * own Service state machine (SERVICE-LIFECYCLE.md §3) is `ArvanCdnClient`'s
 * job once the real values are known, not this DTO's.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Arvan;

use DateTimeImmutable;

final class CdnResource {

	public function __construct(
		/** The provider's own identifier for this resource (API.md §4: "remote resource identifier"). */
		public readonly string $resourceId,
		public readonly string $domain,
		/** Raw provider status string. See class docblock — deliberately not an enum. */
		public readonly string $status,
		public readonly ?DateTimeImmutable $createdAt = null
	) {}
}
