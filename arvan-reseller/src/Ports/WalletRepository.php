<?php
/**
 * Read access to a customer's cached wallet balance and thresholds.
 *
 * DATA-MODEL.md §4 states the invariant this port is built around:
 * `wallet.balance_rial == latest ledger.balance_after_rial` — the wallet is a
 * cache, not an independent source of truth. That is why this interface has
 * no `debit()`/`credit()`/`setBalance()` method: TECH.md §8 requires the
 * ledger insert and the wallet balance update to happen atomically, in one
 * transaction, so that single write path belongs to LedgerRepository::append()
 * instead. Giving WalletRepository its own write method would create two
 * independent ways to change a balance and the two would eventually disagree.
 *
 * The one write this port does own — `ensureExists()` — creates the
 * zero-balance row a new customer needs (PRD §6, "Register/Login" produces a
 * wallet with nothing in it yet); it never touches an existing balance.
 *
 * `ensureExists()` takes the reseller's current lifecycle-policy thresholds
 * (T-2.4's `ResellerSettings::getLifecyclePolicy()`) rather than defaulting
 * them internally: without this, a wallet created via the DB column defaults
 * (`notify_threshold_rial IS NULL`, `resume_threshold_rial = 0`) would
 * silently ignore whatever the reseller actually configured in the Setup
 * Wizard — every new customer would get the low-balance warning and
 * resume-after-recharge behavior of a reseller who set both to zero,
 * regardless of the real setting.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

use ArvanReseller\Domain\Money;

interface WalletRepository {

	/**
	 * Create a zero-balance wallet for a customer who does not have one yet,
	 * seeded with the reseller's current low-balance/resume thresholds.
	 * Idempotent: calling this for a customer who already has a wallet must
	 * not reset or otherwise touch their balance OR their thresholds — a
	 * customer whose wallet already exists keeps whatever thresholds they
	 * have, even if the reseller's defaults changed since.
	 */
	public function ensureExists( int $customer_id, Money $notify_threshold, Money $resume_threshold ): void;

	/**
	 * The cached current balance. May be negative (DATA-MODEL.md §4: "Wallet
	 * may be negative"; ADR-010).
	 */
	public function currentBalance( int $customer_id ): Money;

	/**
	 * The reseller-configured Low Balance Threshold for this customer
	 * (`low_balance_threshold_rial`, PRD B4).
	 */
	public function lowBalanceThreshold( int $customer_id ): Money;

	/**
	 * The balance a suspended wallet must clear before its service becomes
	 * resumable (`resume_threshold_rial`, PRD §5.5 / ADR-012).
	 */
	public function resumeThreshold( int $customer_id ): Money;

	/**
	 * Every wallet's current balance, keyed by customer_id — the Admin
	 * Dashboard's "total virtual balances" figure (SCREEN-SPECS.md §2) and
	 * the Admin Customers list's per-row balance column (§3). One query
	 * for the whole reseller rather than N `currentBalance()` calls, so an
	 * admin list with many customers stays free of an obvious N+1
	 * (TECH.md §13).
	 *
	 * @return array<int, Money>
	 */
	public function allBalances(): array;

	/**
	 * Count of wallets currently in the low-balance warning zone — balance
	 * positive but at or below the reseller's configured
	 * `notify_threshold_rial` — the Admin Dashboard's "low-balance warnings"
	 * figure (SCREEN-SPECS.md §2). Excludes `balance <= 0`: that zone is
	 * already covered by the Dashboard's separate "suspended services"
	 * count (CLAUDE.md: `balance <= 0` triggers Suspend), so counting it
	 * here too would double up the same condition under two labels.
	 */
	public function countLowBalance(): int;
}
