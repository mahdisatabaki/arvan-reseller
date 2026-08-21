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
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

use ArvanReseller\Domain\Money;

interface WalletRepository {

	/**
	 * Create a zero-balance wallet for a customer who does not have one yet.
	 * Idempotent: calling this for a customer who already has a wallet must
	 * not reset or otherwise touch their balance.
	 */
	public function ensureExists( int $customer_id ): void;

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
}
