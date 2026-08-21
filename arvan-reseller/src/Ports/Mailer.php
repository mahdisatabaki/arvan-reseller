<?php
/**
 * Outbound e-mail transport.
 *
 * PRD group F1 requires an e-mail when a wallet crosses its low-balance
 * threshold. Deduplication (F2) is the caller's responsibility — it is
 * enforced with the `dedupe_key` on `arvan_notifications` (DATA-MODEL.md §12),
 * not by this port — so this interface stays a plain transport with no notion
 * of "have we already sent this."
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

interface Mailer {

	/**
	 * Send a plain e-mail. Returns whether the transport accepted it, so the
	 * caller can record the outcome in `arvan_notifications.status`
	 * (DATA-MODEL.md §12) rather than assuming success.
	 */
	public function send( string $to, string $subject, string $body ): bool;
}
