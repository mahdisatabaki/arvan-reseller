<?php
/**
 * Drives `SettlementService` off `Scheduler::HOOK_SETTLEMENT` (already
 * scheduled daily since T-0.6; nothing consumed it until now) — same
 * "one implementation, cron and manual trigger share it" shape as
 * `MeteringCronHandler` (BACKLOG T-9.1/T-5.4).
 *
 * No lock transient here unlike `MeteringCronHandler`'s hourly job:
 * settlement runs at most daily plus the occasional manual trigger, each row
 * it aggregates is claimed via `settlement_id` the moment it is written
 * (`WpUsageLogRepository::markSettled()`), and `SettlementRepository::create()`
 * is itself idempotent on `(period_start, period_end)` — a genuinely
 * concurrent double-run is far lower risk here than for the hourly billing
 * job, and adding a lock for it would be defending against a scenario this
 * MVP does not need to.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Cron;

use ArvanReseller\Settlement\SettlementService;
use ArvanReseller\Wp\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class SettlementCronHandler {

	/**
	 * Public: a future System Status/Finance screen (T-9.2) can link a
	 * "Run Settlement Now" action at this admin-post.php action + a matching
	 * `wp_nonce_field()`, the same way the Dashboard already does for
	 * `MeteringCronHandler::MANUAL_ACTION`.
	 */
	public const MANUAL_ACTION = 'arvan_run_settlement_now';

	public function __construct( private readonly SettlementService $settlement ) {}

	public function register(): void {
		add_action( Scheduler::HOOK_SETTLEMENT, [ $this, 'run' ] );
		add_action( 'admin_post_' . self::MANUAL_ACTION, [ $this, 'handleManualTrigger' ] );
	}

	public function handleManualTrigger(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این عملیات را ندارید.', 'arvan-reseller' ) );
		}

		check_admin_referer( self::MANUAL_ACTION );

		$this->run();

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'index.php' ) );
		exit;
	}

	/**
	 * @return array{ok: bool, created: bool, settlement_id: ?int, sample_count: int, base_rial: int, markup_rial: int, gross_rial: int}
	 */
	public function run(): array {
		return $this->settlement->run();
	}
}
