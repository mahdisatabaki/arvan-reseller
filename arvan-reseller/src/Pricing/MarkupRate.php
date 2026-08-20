<?php
/**
 * The reseller's markup, as validated basis points.
 *
 * PRD §2 caps this at 20% and §5.1 defines the revenue model as markup-only —
 * there is no commission mode. The cap is enforced here, in the domain, rather
 * than in a form validator, so no admin screen, importer, REST route or future
 * code path can ever construct an out-of-range rate.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Pricing;

use InvalidArgumentException;

final class MarkupRate {

	/** 20% — the contractual ceiling on reseller markup (PRD §2, ADR-002). */
	public const MAX_BASIS_POINTS = 2000;

	private function __construct( private readonly int $basis_points ) {}

	public static function fromBasisPoints( int $basis_points ): self {
		if ( $basis_points < 0 ) {
			throw new InvalidArgumentException( 'Markup rate cannot be negative.' );
		}

		if ( $basis_points > self::MAX_BASIS_POINTS ) {
			throw new InvalidArgumentException(
				sprintf(
					'Markup rate of %d bps exceeds the %d bps ceiling.',
					$basis_points,
					self::MAX_BASIS_POINTS
				)
			);
		}

		return new self( $basis_points );
	}

	/**
	 * Build from a human percentage such as 12.5. Anything finer than a
	 * hundredth of a percent is meaningless here and is rounded away.
	 */
	public static function fromPercent( float $percent ): self {
		if ( ! is_finite( $percent ) ) {
			throw new InvalidArgumentException( 'Markup rate must be a finite number.' );
		}

		return self::fromBasisPoints( (int) round( $percent * 100 ) );
	}

	public static function zero(): self {
		return new self( 0 );
	}

	public function toBasisPoints(): int {
		return $this->basis_points;
	}

	public function toPercent(): float {
		return $this->basis_points / 100;
	}

	public function isZero(): bool {
		return 0 === $this->basis_points;
	}

	public function equals( self $other ): bool {
		return $this->basis_points === $other->basis_points;
	}
}
