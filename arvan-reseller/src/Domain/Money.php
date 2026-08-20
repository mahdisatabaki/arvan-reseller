<?php
/**
 * Money value object.
 *
 * Amounts are integers in Rial. Floats are never used for money anywhere in this
 * plugin: a rounding drift of a ten-thousandth of a Rial per hour per service is
 * still a ledger that does not reconcile.
 *
 * Iranian users think in Toman (1 Toman = 10 Rial), so conversion helpers live
 * here — but storage and arithmetic stay in Rial.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Domain;

use InvalidArgumentException;

final class Money {

	public const RIAL_PER_TOMAN = 10;

	private function __construct( private readonly int $rial ) {}

	public static function fromRial( int $rial ): self {
		return new self( $rial );
	}

	public static function fromToman( int $toman ): self {
		return new self( $toman * self::RIAL_PER_TOMAN );
	}

	public static function zero(): self {
		return new self( 0 );
	}

	public function toRial(): int {
		return $this->rial;
	}

	/**
	 * Rial truncated to whole Toman. Lossy by design — only use for display.
	 */
	public function toToman(): int {
		return intdiv( $this->rial, self::RIAL_PER_TOMAN );
	}

	public function plus( self $other ): self {
		return new self( $this->rial + $other->rial );
	}

	public function minus( self $other ): self {
		return new self( $this->rial - $other->rial );
	}

	public function negated(): self {
		return new self( -$this->rial );
	}

	public function absolute(): self {
		return new self( abs( $this->rial ) );
	}

	/**
	 * Scale by a rational factor without ever leaving integer arithmetic.
	 *
	 * Used for "this service ran for 1,832 of the 3,600 seconds in this hour".
	 */
	public function multipliedByFraction( int $numerator, int $denominator ): self {
		if ( 0 === $denominator ) {
			throw new InvalidArgumentException( 'Cannot scale money by a zero denominator.' );
		}

		return new self( intdiv( $this->rial * $numerator, $denominator ) );
	}

	public function multipliedBy( int $factor ): self {
		return new self( $this->rial * $factor );
	}

	/**
	 * A percentage of this amount, rounded half-up to the nearest Rial.
	 *
	 * Basis points keep the caller in integers: 20% is 2000 bps. Rounding half-up
	 * favours the reseller by at most half a Rial per calculation, which is the
	 * conventional direction and is documented so it is a choice, not an accident.
	 */
	public function percentageBps( int $basis_points ): self {
		if ( $basis_points < 0 ) {
			throw new InvalidArgumentException( 'Basis points cannot be negative.' );
		}

		$scaled = $this->rial * $basis_points;
		$sign   = $scaled < 0 ? -1 : 1;

		return new self( $sign * intdiv( abs( $scaled ) + 5000, 10000 ) );
	}

	public function isZero(): bool {
		return 0 === $this->rial;
	}

	public function isNegative(): bool {
		return $this->rial < 0;
	}

	public function isPositive(): bool {
		return $this->rial > 0;
	}

	public function greaterThan( self $other ): bool {
		return $this->rial > $other->rial;
	}

	public function greaterThanOrEqual( self $other ): bool {
		return $this->rial >= $other->rial;
	}

	public function lessThan( self $other ): bool {
		return $this->rial < $other->rial;
	}

	public function lessThanOrEqual( self $other ): bool {
		return $this->rial <= $other->rial;
	}

	public function equals( self $other ): bool {
		return $this->rial === $other->rial;
	}

	public static function min( self $a, self $b ): self {
		return $a->lessThan( $b ) ? $a : $b;
	}

	public static function max( self $a, self $b ): self {
		return $a->greaterThan( $b ) ? $a : $b;
	}

	/**
	 * @param self[] $amounts
	 */
	public static function sum( array $amounts ): self {
		$total = 0;

		foreach ( $amounts as $amount ) {
			$total += $amount->rial;
		}

		return new self( $total );
	}
}
