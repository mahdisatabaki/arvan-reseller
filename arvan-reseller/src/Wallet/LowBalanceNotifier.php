<?php
/**
 * Fires the one-per-crossing low-balance notification BILLING.md §13
 * requires: "if previous_balance > threshold and new_balance <= threshold
 * → create one low-balance notification event", deduplicated "so recurring
 * Cron executions do not spam."
 *
 * Ordering decision — record() before send(), not after:
 *
 * `NotificationRepository::record()` is the ONLY mechanism this class has
 * for telling "first time this crossing fired" apart from "a retry of the
 * same billing operation" ($eventKey is caller-supplied and identical on a
 * retry, and the crossing math alone re-evaluates true both times — the
 * balances themselves don't change between the original attempt and a
 * retry). That means the dedupe check MUST run before `Mailer::send()`, or
 * a retry would re-send the e-mail before ever discovering it already did —
 * exactly the spam BILLING.md §13 forbids. So the call sequence is:
 *
 *   1. record($status = 'sent') — atomically claims the dedupe key.
 *      `created = false` means another call already claimed it; return
 *      `false` immediately without touching the mailer.
 *   2. Only when `created` is true (this call did the claiming): attempt
 *      `send()`.
 *   3. If `send()` failed, correct the just-claimed row with a second
 *      `record($status = 'failed', $error)` call — see `NotificationRepository`'s
 *      docblock for why this second write is safe and expected rather than
 *      a duplicate insert (the UNIQUE constraint on `dedupe_key` means it can
 *      never become a second row; it only ever refreshes the one row this
 *      same call just created).
 *
 * The alternative — attempt the send first, then make one single `record()`
 * call carrying the real outcome — reads simpler and avoids the second write
 * entirely, but it cannot pass the dedupe requirement: it has no way to
 * discover "this is a retry" before already having re-sent the e-mail, since
 * discovering that IS what `record()` does. It was rejected for that reason.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wallet;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\Mailer;
use ArvanReseller\Ports\NotificationRepository;

final class LowBalanceNotifier {

	private const TYPE = 'low_balance';

	public function __construct(
		private readonly Mailer $mailer,
		private readonly NotificationRepository $notifications
	) {}

	/**
	 * Returns true only when this call is the one that newly recorded the
	 * crossing event — regardless of whether the e-mail itself was
	 * delivered. A `false` return means either nothing crossed, or this
	 * exact crossing was already handled by an earlier call with the same
	 * `$eventKey`.
	 */
	public function notifyIfCrossed(
		int $customer_id,
		string $email,
		Money $previousBalance,
		Money $newBalance,
		Money $threshold,
		string $eventKey
	): bool {
		if ( ! $previousBalance->greaterThan( $threshold ) || ! $newBalance->lessThanOrEqual( $threshold ) ) {
			return false;
		}

		$dedupe_key = "low-balance-{$customer_id}-{$eventKey}";
		$subject    = 'اعلان کاهش موجودی کیف پول';
		$body       = sprintf(
			'موجودی کیف پول شما به %s تومان رسیده که کمتر از یا برابر با آستانه هشدار است. ' .
			'برای جلوگیری از تعلیق سرویس، لطفاً هرچه زودتر کیف پول خود را شارژ کنید.',
			number_format( $newBalance->toToman() )
		);

		$claim = $this->notifications->record( $customer_id, self::TYPE, $subject, $dedupe_key, 'sent' );

		if ( ! $claim['created'] ) {
			return false;
		}

		$sent = $this->mailer->send( $email, $subject, $body );

		if ( ! $sent ) {
			$this->notifications->record(
				$customer_id,
				self::TYPE,
				$subject,
				$dedupe_key,
				'failed',
				'Mailer::send() returned false.'
			);
		}

		return true;
	}
}
