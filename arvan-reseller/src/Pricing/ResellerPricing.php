<?php
/**
 * The reseller markup formula — the only pricing rule in this plugin.
 *
 * PRD §10 (Client Contract) is explicit about the boundary: a `UsagePricingAdapter`
 * converts ArvanCloud's raw CDN outbound-traffic reading into a base cost in
 * Rial; this class receives only that base cost and applies the markup. It has
 * no notion of traffic, units, or time — that keeps the same formula usable for
 * a live order preview, the hourly billing cron, and the settlement report
 * without duplicating the calculation three times.
 *
 * One place, one rule: the number a customer sees before ordering CDN and the
 * number that later debits their wallet are produced by the same code.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Pricing;

use ArvanReseller\Domain\Money;
use InvalidArgumentException;

final class ResellerPricing {

	public function __construct( private readonly MarkupRate $rate ) {}

	public function rate(): MarkupRate {
		return $this->rate;
	}

	/**
	 * Decompose an ArvanCloud base cost into base / markup / customer total.
	 */
	public function charge( Money $base_cost ): ChargeBreakdown {
		if ( $base_cost->isNegative() ) {
			throw new InvalidArgumentException( 'Base cost cannot be negative.' );
		}

		$markup = $base_cost->percentageBps( $this->rate->toBasisPoints() );
		$total  = $base_cost->plus( $markup );

		return new ChargeBreakdown( $base_cost, $markup, $total, $this->rate );
	}

	/**
	 * The customer-facing price for a given base cost, for display in the
	 * catalogue or an order preview.
	 */
	public function sellPrice( Money $base_cost ): Money {
		return $this->charge( $base_cost )->total;
	}
}
