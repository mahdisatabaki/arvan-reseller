<?php
/**
 * The three numbers every CDN charge decomposes into.
 *
 * Keeping base and markup separate all the way through to the ledger is what
 * makes settlement possible later: `base` is what the reseller owes ArvanCloud
 * for the metered outbound traffic, `markup` is the reseller's own margin,
 * `total` is what the customer's wallet actually pays.
 *
 * PRD §5.1 / ADR-002 — markup only, no commission mode. VAT is out of scope
 * for P0 (ADR-003), so there is no tax field here.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Pricing;

use ArvanReseller\Domain\Money;

final class ChargeBreakdown {

	public function __construct(
		public readonly Money $base,
		public readonly Money $markup,
		public readonly Money $total,
		public readonly MarkupRate $rate
	) {}

	public static function zero( ?MarkupRate $rate = null ): self {
		return new self(
			Money::zero(),
			Money::zero(),
			Money::zero(),
			$rate ?? MarkupRate::zero()
		);
	}

	public function plus( self $other ): self {
		return new self(
			$this->base->plus( $other->base ),
			$this->markup->plus( $other->markup ),
			$this->total->plus( $other->total ),
			$this->rate
		);
	}

	/**
	 * @return array{base_rial:int, markup_rial:int, total_rial:int, markup_bps:int}
	 */
	public function toArray(): array {
		return [
			'base_rial'   => $this->base->toRial(),
			'markup_rial' => $this->markup->toRial(),
			'total_rial'  => $this->total->toRial(),
			'markup_bps'  => $this->rate->toBasisPoints(),
		];
	}
}
