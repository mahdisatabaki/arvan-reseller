<?php
/**
 * Normalized CDN provider failure.
 *
 * BACKLOG lists "normalized errors" as a T-1.3 deliverable (not a T-1.2 one),
 * and API.md §10 defines the exact category set every provider error must be
 * mapped into. This is the one exception type `ArvanCdnClient` throws for
 * every kind of failure — auth, validation, not-found, rate-limit, transient
 * 5xx, conflict, or anything unrecognized — so a caller only ever needs to
 * catch one class and read `category`/`retryable` off it.
 *
 * `retryable` is derived from `category`, not passed in separately: making it
 * a function of the category (not a second free parameter) means there is
 * exactly one place that decides "is RATE_LIMITED retryable" and a call site
 * can never disagree with it by accident.
 *
 * SECURITY.md §10/§13: the message on this exception must already be safe to
 * show — never the raw provider body, never the Authorization header, never
 * the API key. That redaction happens in `ArvanCdnClient` before this is
 * thrown, not here.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Arvan;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class CdnProviderException extends RuntimeException {

	public const AUTHENTICATION_FAILED = 'AUTHENTICATION_FAILED';
	public const FORBIDDEN = 'FORBIDDEN';
	public const INVALID_REQUEST = 'INVALID_REQUEST';
	public const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
	public const RATE_LIMITED = 'RATE_LIMITED';
	public const TEMPORARY_PROVIDER_FAILURE = 'TEMPORARY_PROVIDER_FAILURE';
	public const PROVIDER_CONFLICT = 'PROVIDER_CONFLICT';
	public const UNKNOWN_PROVIDER_ERROR = 'UNKNOWN_PROVIDER_ERROR';

	/** @var array<string, true> */
	private const KNOWN_CATEGORIES = [
		self::AUTHENTICATION_FAILED      => true,
		self::FORBIDDEN                  => true,
		self::INVALID_REQUEST            => true,
		self::RESOURCE_NOT_FOUND         => true,
		self::RATE_LIMITED               => true,
		self::TEMPORARY_PROVIDER_FAILURE => true,
		self::PROVIDER_CONFLICT          => true,
		self::UNKNOWN_PROVIDER_ERROR     => true,
	];

	/** @var array<string, true> Categories worth a bounded retry (API.md §9). */
	private const RETRYABLE_CATEGORIES = [
		self::RATE_LIMITED               => true,
		self::TEMPORARY_PROVIDER_FAILURE => true,
	];

	public readonly bool $retryable;

	private function __construct(
		public readonly string $category,
		string $safeMessage,
		?Throwable $previous = null
	) {
		if ( ! isset( self::KNOWN_CATEGORIES[ $category ] ) ) {
			throw new InvalidArgumentException( "Unknown CdnProviderException category: {$category}" );
		}

		$this->retryable = isset( self::RETRYABLE_CATEGORIES[ $category ] );

		parent::__construct( $safeMessage, 0, $previous );
	}

	public static function create( string $category, string $safeMessage, ?Throwable $previous = null ): self {
		return new self( $category, $safeMessage, $previous );
	}
}
