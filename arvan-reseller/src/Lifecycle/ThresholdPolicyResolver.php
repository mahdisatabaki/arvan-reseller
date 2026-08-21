<?php
/**
 * Assembles a customer's `ThresholdPolicy` from the two ports it needs.
 *
 * BACKLOG.md T-6.1. `lowBalanceThreshold`/`resumeThreshold` come from
 * `WalletRepository` (a port, framework-agnostic). `terminate_grace_days` is
 * reseller-wide only (`ResellerSettings::getLifecyclePolicy()`, WP layer) —
 * it is taken here as a plain int rather than the resolver importing
 * `ArvanReseller\Wp\Admin\ResellerSettings`, which would pull a WordPress
 * dependency into `src/`. The WP-layer caller is expected to pass
 * `$resellerSettings->getLifecyclePolicy()['terminate_grace_days']`.
 *
 * Later Lifecycle tasks each need a different subset of these three values
 * from two different sources — T-6.4 "Resume after Recharge" needs
 * `resumeThreshold`, T-6.5 "Terminate" needs `terminateGraceDays` — so this
 * resolver gives them one place to ask "what are this customer's limits"
 * instead of each re-deciding which value comes from where.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Lifecycle;

use ArvanReseller\Ports\WalletRepository;

final class ThresholdPolicyResolver {

	public function __construct( private readonly WalletRepository $wallets ) {}

	public function resolve( int $customer_id, int $terminateGraceDays ): ThresholdPolicy {
		return new ThresholdPolicy(
			$this->wallets->lowBalanceThreshold( $customer_id ),
			$this->wallets->resumeThreshold( $customer_id ),
			$terminateGraceDays
		);
	}
}
