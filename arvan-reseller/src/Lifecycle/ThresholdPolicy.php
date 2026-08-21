<?php
/**
 * A resolved snapshot of one customer's lifecycle limits: the low-balance
 * threshold, the resume threshold, and the terminate grace period.
 *
 * BACKLOG.md T-6.1. The first two values are per-customer, cached on the
 * wallet row and read via `WalletRepository` (seeded from reseller defaults
 * at wallet creation, T-3.3; the port does not care whether they have since
 * diverged from those defaults). `terminate_grace_days` has no per-customer
 * override in the schema — it is reseller-wide only, from
 * `ResellerSettings::getLifecyclePolicy()`. See `ThresholdPolicyResolver` for
 * how the three are assembled.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Lifecycle;

use ArvanReseller\Domain\Money;

final class ThresholdPolicy {

	public function __construct(
		public readonly Money $lowBalanceThreshold,
		public readonly Money $resumeThreshold,
		public readonly int $terminateGraceDays
	) {}
}
