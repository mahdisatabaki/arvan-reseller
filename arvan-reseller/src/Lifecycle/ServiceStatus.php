<?php
/**
 * A CDN service's local lifecycle status, and which transitions between
 * statuses are legal.
 *
 * The 8 states are DATA-MODEL.md §8's `arvan_services.status` values. This
 * class only tracks that local string — it never calls the CDN provider
 * itself (ProvisioningService/SuspensionEngine, not yet built, own that).
 *
 * The `*_failed` states exist because BILLING.md §14 requires a failed
 * hold/unhold/delete call to "preserve financial result, set service
 * failure state" rather than pretending the remote call succeeded or
 * silently reverting a valid financial change — so e.g. `suspend_failed`
 * means "the ledger already reflects Suspend, the remote hold has not",
 * and only a later successful retry moves on to `suspended` itself.
 * `terminated` has no outgoing transitions: BILLING.md §16, "Termination is
 * irreversible in MVP".
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Lifecycle;

use InvalidArgumentException;

final class ServiceStatus {

	public const PROVISIONING        = 'provisioning';
	public const ACTIVE              = 'active';
	public const SUSPENDED           = 'suspended';
	public const TERMINATED          = 'terminated';
	public const PROVISIONING_FAILED = 'provisioning_failed';
	public const SUSPEND_FAILED      = 'suspend_failed';
	public const RESUME_FAILED       = 'resume_failed';
	public const TERMINATE_FAILED    = 'terminate_failed';

	private const ALL = [
		self::PROVISIONING,
		self::ACTIVE,
		self::SUSPENDED,
		self::TERMINATED,
		self::PROVISIONING_FAILED,
		self::SUSPEND_FAILED,
		self::RESUME_FAILED,
		self::TERMINATE_FAILED,
	];

	/**
	 * @var array<string, string[]>
	 */
	private const TRANSITIONS = [
		self::PROVISIONING        => [ self::ACTIVE, self::PROVISIONING_FAILED ],
		self::PROVISIONING_FAILED => [ self::PROVISIONING ],
		self::ACTIVE              => [ self::SUSPENDED, self::SUSPEND_FAILED ],
		self::SUSPEND_FAILED      => [ self::SUSPENDED ],
		self::SUSPENDED           => [ self::ACTIVE, self::RESUME_FAILED, self::TERMINATED, self::TERMINATE_FAILED ],
		self::RESUME_FAILED       => [ self::ACTIVE, self::TERMINATED, self::TERMINATE_FAILED ],
		self::TERMINATE_FAILED    => [ self::TERMINATED ],
		self::TERMINATED          => [],
	];

	private function __construct( private readonly string $value ) {}

	public static function fromString( string $value ): self {
		if ( ! in_array( $value, self::ALL, true ) ) {
			throw new InvalidArgumentException( "Unknown service status: {$value}" );
		}

		return new self( $value );
	}

	public function value(): string {
		return $this->value;
	}

	public function canTransitionTo( self $next ): bool {
		return in_array( $next->value, self::TRANSITIONS[ $this->value ], true );
	}

	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return self::ALL;
	}
}
