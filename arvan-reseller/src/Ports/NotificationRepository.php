<?php
/**
 * Persistence for outbound notification events (`arvan_notifications`).
 *
 * BILLING.md §13 requires that a recurring Cron re-running the same billing
 * operation must not re-send a low-balance notice ("deduplicate notifications
 * so recurring Cron executions do not spam"). The mechanism that makes that
 * safe under a retry is the same "return the existing row on duplicate,
 * never insert twice" contract used by every other idempotent write in this
 * codebase — `ApiKeyRepository::create()` (fingerprint), `UsageLogRepository::record()`
 * (service_id + period_start), `CustomerRepository::create()` (wp_user_id) —
 * here backed by the table's own UNIQUE constraint on `dedupe_key`
 * (Schema.php `arvan_notifications`). A caller does not need to check for an
 * existing notification before calling `record()`; it can call this on every
 * billing pass and trust `created` to say whether this is the first time.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

interface NotificationRepository {

	/**
	 * Record one notification event, or refresh `status`/`error` on the
	 * existing row if this exact event (identified by `$dedupe_key`) was
	 * already recorded.
	 *
	 * The event itself is never duplicated — at most one row can ever exist
	 * per `dedupe_key` (Schema.php's UNIQUE constraint is the final safety
	 * net) — but `status`/`error` may legitimately need a second write for
	 * the *same* row: a mail transport's outcome (delivered vs failed) is
	 * only known after the send attempt, which cannot happen atomically with
	 * the insert. `Wallet\LowBalanceNotifier` relies on this: it calls
	 * `record()` once to atomically claim the dedupe key (gating whether it
	 * may attempt a send at all — this is what makes BILLING.md §13's "do
	 * not spam on cron retry" safe), and, only when its own claim just
	 * succeeded, may call `record()` a second time to correct `status` after
	 * attempting delivery. `created` is what tells a caller which situation
	 * it is in — it must never attempt a send when `created` is false.
	 *
	 * @return array{id: int, created: bool} `created` is true only for the
	 *         call that inserted the row. A caller must not send/act again
	 *         just because this call returned — only a `true` `created` from
	 *         *this* call authorizes that.
	 */
	public function record(
		int $customer_id,
		string $type,
		string $subject,
		string $dedupe_key,
		string $status,
		?string $error = null
	): array;
}
