<?php
/**
 * Converts a raw metered usage reading into a Rial base cost.
 *
 * PRD §10's `UsagePricingAdapter` and BILLING.md §6's stop condition: since
 * ArvanCloud's traffic report does not return a monetary cost directly
 * (T-1.1), `base_cost = normalized_usage × configured_unit_price`. The
 * reseller-configured price is Rial per gigabyte (decimal GB, 10^9 bytes —
 * see `ResellerSettings::getUnitPriceRialPerGb()`), not per byte, since a
 * per-byte Rial price would round to zero for any realistic value.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Metering;

use ArvanReseller\Domain\Money;
use RuntimeException;

final class UsagePricingAdapter {

	private const BYTES_PER_GB = 1_000_000_000;

	/**
	 * @throws RuntimeException If `$usage`'s unit is anything other than
	 *         "byte" — the only unit `ArvanCdnClient`/`MockCdnClient`
	 *         currently normalize to (OutboundTrafficUsage.php's docblock).
	 *         Silently mispricing an unrecognized unit would be worse than
	 *         failing loudly.
	 */
	public function priceUsage( UsagePeriod $usage, Money $unitPriceRialPerGb ): Money {
		if ( 'byte' !== $usage->usageUnit ) {
			throw new RuntimeException( "UsagePricingAdapter cannot price usage unit '{$usage->usageUnit}' — only 'byte' is supported." );
		}

		return $unitPriceRialPerGb->multipliedByFraction( $usage->usageValue, self::BYTES_PER_GB );
	}
}
