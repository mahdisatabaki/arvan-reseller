<?php
/**
 * Immutable record of every sensitive operation.
 *
 * SECURITY.md §14 requires an audit trail for API key changes, Access Token
 * attempts, manual wallet adjustments, provisioning, lifecycle transitions,
 * settlement runs, and critical setting changes — and is explicit that
 * "audit data must itself be escaped/redacted." This port has no way to know
 * which of its caller's values are secret, so that redaction is the caller's
 * obligation: never pass a decrypted API key or a raw Access Token into
 * `$metadata` (SECURITY.md §4, §14).
 *
 * Shaped after `arvan_audit_log` (DATA-MODEL.md §13), but this interface does
 * not assume that table — it only assumes the fields a security review needs
 * to answer "who did what, to what, with what result."
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

interface AuditLogger {

	/**
	 * @param string      $action           Short verb phrase, e.g. "api_key.replaced",
	 *                                       "wallet.manual_adjustment", "service.suspended".
	 * @param int|null    $actor_wp_user_id  The WordPress user who performed the action,
	 *                                       or null for a system/cron actor.
	 * @param int|null    $customer_id       The customer the action concerns, if any.
	 * @param string|null $entity_type       e.g. "service", "payment", "api_key".
	 * @param int|null    $entity_id         The affected row's id.
	 * @param string      $status            "ok" | "failed" | any short outcome label.
	 * @param array<string, mixed> $metadata Already-redacted context. Never a secret.
	 */
	public function record(
		string $action,
		?int $actor_wp_user_id = null,
		?int $customer_id = null,
		?string $entity_type = null,
		?int $entity_id = null,
		string $status = 'ok',
		array $metadata = []
	): void;
}
